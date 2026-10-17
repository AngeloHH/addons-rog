/**
 * Addons ROG - Deployment package tests
 *
 * - Verify runtime files are included without private state or local databases.
 * - Check repeated packaging removes stale files without changing the source.
 */

import assert from 'node:assert/strict';
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, dirname, join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

// =============================================================================
// ISOLATED PACKAGE FIXTURES
// =============================================================================

const script = fileURLToPath(new URL('../copy-api.mjs', import.meta.url));
const included = [
  '.htaccess', 'bootstrap.php', 'index.php', 'routes.php',
  'bin/setup-token.php', 'bin/cleanup.php', 'database/models.php', 'database/objects.sql',
  'public/index.php', 'public/.htaccess', 'src/App.php', 'src/Services/InstallService.php',
  'templates/emails/Blank.html', 'config/config.php', 'config/config.example.php', 'storage/.gitkeep',
];
const excluded = [
  'config/config.local.php', 'config/install.lock', 'config/.install.guard',
  'config/setup.token.json', 'config/setup.pending.json', 'config/setup.pending.php',
  'config/.setup-incomplete', 'config/backup/config.local.php', 'storage/mail/message.eml',
  'storage/uploads/addon.zip', 'storage/database.sqlite3', 'database.sqlite3', 'private.db',
  'src/local.sqlite', 'public/local.db-wal', 'database/local.sqlite3-shm',
  'database/local.db-journal', 'database/local.sqlite.bak', '.env', '.gitignore',
  'tests/run.php', 'tests/.runtime/database.sqlite3', 'notes/development.md',
];

function write(root, entry, contents = entry) {
  const file = join(root, entry);
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, contents);
}

function fixture(t) {
  const root = mkdtempSync(join(tmpdir(), 'rog-build-'));
  t.after(() => {
    assert.equal(dirname(root), tmpdir());
    assert.match(basename(root), /^rog-build-/);
    rmSync(root, { recursive: true, force: true });
  });
  mkdirSync(join(root, 'scripts'));
  copyFileSync(script, join(root, 'scripts/copy-api.mjs'));
  for (const entry of [...included, ...excluded]) write(join(root, 'api'), entry);
  write(join(root, 'dist'), 'index.html', 'Built frontend');
  return root;
}

function build(root) {
  return spawnSync(process.execPath, ['scripts/copy-api.mjs'], {
    cwd: root, encoding: 'utf8', windowsHide: true,
  });
}

// =============================================================================
// PACKAGE CONTENTS AND REPEATED BUILDS
// =============================================================================

test('deployment includes runtime files and excludes local state, databases and tests', (t) => {
  const root = fixture(t);
  const result = build(root);
  assert.equal(result.status, 0, result.stderr);
  for (const entry of included) {
    assert.equal(readFileSync(join(root, 'dist/api', entry), 'utf8'), entry);
  }
  for (const entry of excluded) assert.equal(existsSync(join(root, 'dist/api', entry)), false, entry);
  for (const entry of [...included, ...excluded]) {
    assert.equal(readFileSync(join(root, 'api', entry), 'utf8'), entry);
  }
  assert.equal(readFileSync(join(root, 'dist/index.html'), 'utf8'), 'Built frontend');
});

test('repeated packaging removes private files left by an older build', (t) => {
  const root = fixture(t);
  for (const entry of excluded) write(join(root, 'dist/api'), entry);
  write(join(root, 'dist/api'), 'src/RemovedService.php');
  for (let attempt = 0; attempt < 2; attempt++) {
    const result = build(root);
    assert.equal(result.status, 0, result.stderr);
    for (const entry of excluded) assert.equal(existsSync(join(root, 'dist/api', entry)), false, entry);
    assert.equal(existsSync(join(root, 'dist/api/src/RemovedService.php')), false);
    assert.equal(readFileSync(join(root, 'api/config/config.local.php'), 'utf8'), 'config/config.local.php');
  }
});

test('packaging requires a frontend build and leaves the installation untouched', (t) => {
  const root = fixture(t);
  const dist = join(root, 'dist');
  assert.equal(dirname(dist), root);
  rmSync(dist, { recursive: true });
  const result = build(root);
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /dist folder was not found/);
  assert.equal(existsSync(dist), false);
  for (const entry of [...included, ...excluded]) {
    assert.equal(readFileSync(join(root, 'api', entry), 'utf8'), entry);
  }
});
