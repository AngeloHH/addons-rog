/**
 * Addons ROG - Clean API package
 *
 * - Copy runtime code and base configuration into the production build.
 * - Keep local installation state and stored data in the source installation.
 */

import { cpSync, existsSync, rmSync } from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const root = resolve(scriptDirectory, '..');
const source = join(root, 'api');
const dist = join(root, 'dist');
const target = join(dist, 'api');

// =============================================================================
// PACKAGE CONTENTS
// =============================================================================

const directories = new Set(['bin', 'database', 'public', 'src', 'templates']);
const files = new Set([
  '', '.htaccess', 'bootstrap.php', 'index.php', 'routes.php',
  'config', 'config/config.php', 'config/config.example.php',
  'storage', 'storage/.gitkeep',
]);

function include(file) {
  const entry = relative(source, file).split(sep).join('/');
  if (/\.(?:sqlite3?|db)(?:$|[.-])/i.test(entry)) return false;
  return files.has(entry) || directories.has(entry.split('/')[0]);
}

// =============================================================================
// CLEAN DESTINATION AND COPY RUNTIME FILES
// =============================================================================

if (!existsSync(dist)) {
  throw new Error('dist folder was not found. Run vite build first.');
}

if (existsSync(target)) {
  rmSync(target, { recursive: true, force: true });
}

cpSync(source, target, { recursive: true, filter: include });
console.log('Copied clean PHP API to dist/api');
