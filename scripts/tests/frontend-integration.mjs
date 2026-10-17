/**
 * Addons ROG - Frontend integration tests
 *
 * - Exercise browser workflows against a temporary API and SQLite database.
 * - Keep installed configuration, files and queued mail outside the test environment.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import net from 'node:net';
import crypto from 'node:crypto';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { createServer } from 'vite';
import { inspectPhp } from '../php-runtime.mjs';

// =============================================================================
// SHARED PHP RUNTIME AND ISOLATED API SOURCE
// =============================================================================

const root = fileURLToPath(new URL('../../', import.meta.url));
const runtime = inspectPhp();
if (!runtime || runtime.configured.versionId < 80200 || runtime.missing.length)
  throw new Error('PHP 8.2+ and its required extensions are needed. Run npm run setup:check.');
if (!runtime.configured.extensions.includes('pdo_sqlite'))
  throw new Error('Frontend tests require the PHP pdo_sqlite extension.');

const work = await fs.mkdtemp(path.join(os.tmpdir(), 'rog-frontend-'));
const source = path.join(root, 'api'), apiRoot = path.join(work, 'api');
const directories = new Set(['bin', 'database', 'public', 'src', 'templates']);
const files = new Set([
  '', 'bootstrap.php', 'index.php', 'routes.php', 'config', 'config/config.php',
  'tests', 'tests/fixtures', 'tests/fixtures/frontend.php',
]);
await fs.cp(source, apiRoot, {
  recursive: true,
  filter(file) {
    const entry = path.relative(source, file).split(path.sep).join('/');
    if (/\.(?:sqlite3?|db)(?:$|[.-])/i.test(entry)) return false;
    return files.has(entry) || directories.has(entry.split('/')[0]);
  },
});

// =============================================================================
// DISPOSABLE DATABASE, STORAGE AND LOCAL SERVERS
// =============================================================================

async function port() {
  const s = net.createServer();
  await new Promise((r) => s.listen(0, '127.0.0.1', r));
  const p = s.address().port;
  await new Promise((r) => s.close(r));
  return p;
}
const httpPort = await port(),
  vitePort = await port(),
  origin = `http://127.0.0.1:${vitePort}`;
const env = {
  ...Object.fromEntries(Object.entries(process.env).filter(([name]) => !name.startsWith('ROG_'))),
  ROG_DSN: `sqlite:${path.join(work, 'database.sqlite')}`,
  ROG_DB_USER: 'rog_test',
  ROG_DB_PASSWORD: '',
  ROG_SECRET: crypto.randomBytes(32).toString('hex'),
  ROG_STORAGE: path.join(work, 'storage'),
  ROG_ORIGIN: origin,
  ROG_SECURE_COOKIE: '0',
  ROG_CREATE_DATABASE: '1',
  ROG_TEST_MODE: 'disposable',
  ROG_MAIL_TRANSPORT: 'spool',
};
await fs.mkdir(env.ROG_STORAGE);
const php = runtime.path, flags = [...runtime.args, '-d', 'display_errors=0'];
function run(args) {
  return new Promise((resolve, reject) => {
    let output = '';
    const child = spawn(php, [...flags, ...args], { cwd: apiRoot, env, windowsHide: true });
    child.stdout.on('data', (b) => (output += b));
    child.stderr.on('data', (b) => (output += b));
    child.on('error', reject);
    child.on('exit', (code) => (code === 0 ? resolve(output) : reject(new Error(output))));
  });
}
let server, vite, browser, page;
let serverLog = '';
const errors = [];
const failedResponses = [];
const checks = [];
async function check(name, callback) {
  await callback();
  checks.push(name);
  console.log(`PASS ${name}`);
}
async function goto(route) {
  await page.goto(origin + route);
  await page.waitForLoadState('networkidle');
  assert.equal(errors.length, 0, errors.join('\n'));
}
async function login(name) {
  await goto('/en/login');
  await page.locator('[name=identifier]').fill(name);
  await page.locator('[name=password]').fill('Frontend test password 123');
  await page.locator('#login button[type=submit]').click();
  await page.waitForURL(origin + '/en');
  await page.waitForLoadState('networkidle');
  assert.equal(await page.getByRole('button', { name: 'Sign out', exact: true }).count(), 1);
}
async function logout() {
  await page.getByRole('button', { name: 'Sign out', exact: true }).click();
  await page.getByRole('link', { name: 'Sign in', exact: true }).first().waitFor();
}
async function prepare(url, body) {
  const response = await page.request.get(origin + '/api/auth/session');
  const session = await response.json();
  const r = await page.request.fetch(origin + '/api' + url, {
    method: 'POST',
    headers: { Origin: origin, 'X-CSRF-Token': session.csrfToken },
    ...(body ? { data: body } : {}),
  });
  const data = await r.json();
  assert.equal(data.ok, true, JSON.stringify(data));
  return data;
}
try {
  console.log(await run(['bin/schema.php', 'create']));
  console.log(await run(['tests/fixtures/frontend.php']));
  server = spawn(
    php,
    [...flags, '-S', `127.0.0.1:${httpPort}`, '-t', 'public', 'public/router.php'],
    { cwd: apiRoot, env, windowsHide: true },
  );
  server.stdout.on('data', (b) => (serverLog += b));
  server.stderr.on('data', (b) => (serverLog += b));
  vite = await createServer({
    root,
    cacheDir: path.join(work, 'node_modules/.vite'),
    server: {
      host: '127.0.0.1',
      port: vitePort,
      strictPort: true,
      proxy: {
        '/api': {
          target: `http://127.0.0.1:${httpPort}`,
          changeOrigin: true,
          rewrite: (value) => value.replace(/^\/api/, ''),
        },
      },
    },
  });
  await vite.listen();
  browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  await context.route((url) => url.origin !== origin, (route) => route.abort());
  page = await context.newPage();
  page.setDefaultTimeout(12000);
  page.setDefaultNavigationTimeout(30000);
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('response', (response) => {
    if (response.url().includes('/api/') && response.status() >= 400 && response.status() !== 503)
      failedResponses.push(`${response.status()} ${new URL(response.url()).pathname}`);
  });
  await check('Guest pages render empty database without sample addons', async () => {
    for (const route of [
      '/en',
      '/en/explore',
      '/en/about',
      '/en/contact',
      '/en/requests',
      '/es',
      '/en/profile/TestAuthor',
      '/en/missing-page',
    ])
      await goto(route);
    assert.equal(await page.getByText('Deadly Boss Mods', { exact: true }).count(), 0);
  });
  await check('Guest cannot enter upload or administration', async () => {
    for (const route of ['/en/upload', '/en/admin-pannel/queue']) {
      await goto(route);
      assert.match(await page.locator('body').innerText(), /Sign in to continue/);
    }
  });
  await check('Login updates shared session', () => login('TestAuthor'));
  await check('Requests are saved and appear in the public list', async () => {
    await goto('/en/requests');
    const form = page.locator('form');
    await form.locator('input').fill('Browser request');
    await form.locator('select').selectOption('wotlk');
    await form.locator('textarea').fill('Please add this useful addon for my client.');
    await form.locator('button[type=submit]').click();
    await page.getByText('Browser request', { exact: true }).waitFor();
  });
  let submissionId;
  await check('ZIP is uploaded and draft survives reopening', async () => {
    await goto('/en/upload');
    await page
      .locator('input[type=file][accept=".zip"]')
      .setInputFiles(path.join(env.ROG_STORAGE, 'test-addon.zip'));
    await page.getByText('BrowserAddon', { exact: true }).waitFor();
    const fields = page.locator('fieldset input[type=text]');
    await fields.nth(0).fill('Browser Addon');
    await fields.nth(1).fill('1.0');
    const categories = page.getByRole('combobox', { name: 'Categories', exact: true });
    await categories.fill('pve');
    await page.getByRole('option', { name: 'PvE / Raids', exact: true }).click();
    assert.equal(await categories.inputValue(), '');
    await categories.fill('map');
    await categories.press('ArrowDown');
    await categories.press('Enter');
    await categories.fill('mage');
    await page.getByRole('option', { name: 'Mage', exact: true }).click();
    await categories.fill('alchemy');
    await page.getByRole('option', { name: 'Alchemy', exact: true }).click();
    await categories.fill('pve');
    assert.equal(await page.getByRole('option', { name: 'PvE / Raids', exact: true }).count(), 0);
    await page.getByText('No matching options.', { exact: true }).waitFor();
    await categories.press('Escape');
    assert.equal(await categories.getAttribute('aria-expanded'), 'false');
    await page.getByRole('button', { name: 'Remove Map / World', exact: true }).click();
    await categories.fill('map');
    await page.getByRole('option', { name: 'Map / World', exact: true }).click();
    await categories.press('Escape');
    await page.getByRole('textbox', { name: /^Short description/ }).fill('A real addon created through the browser.');
    await page.locator('fieldset textarea').fill('Browser integration description.');
    await page
      .locator('fieldset input[type=file]')
      .nth(1)
      .setInputFiles(path.join(env.ROG_STORAGE, 'icon.png'));
    await page.locator('fieldset img[src*="/api/files/"]').first().waitFor();
    for (let index = 0; index < 4; index += 1) {
      await page
        .locator('fieldset input[type=file]')
        .nth(2)
        .setInputFiles(path.join(env.ROG_STORAGE, 'screenshot.png'));
      await page.waitForFunction(
        (count) => document.querySelectorAll('fieldset img[src*="/api/files/"]').length === count,
        index + 2,
      );
    }
    assert.equal(await page.locator('fieldset input[type=file]').nth(2).isDisabled(), true);
    await page.getByRole('button', { name: 'Save draft', exact: true }).click();
    await page.waitForURL(/submissionId=/);
    submissionId = new URL(page.url()).searchParams.get('submissionId');
    await page.reload();
    await page.waitForLoadState('networkidle');
    assert.equal(
      await page.locator('fieldset input[type=text]').nth(0).inputValue(),
      'Browser Addon',
    );
    assert.equal(await page.locator('fieldset img[src*="/api/files/"]').count(), 5);
    for (const label of ['PvE / Raids', 'Map / World', 'Mage', 'Alchemy'])
      assert.equal(await page.getByRole('button', { name: `Remove ${label}`, exact: true }).count(), 1);
  });
  await check('Draft submits to moderation', async () => {
    const checks = page.locator('fieldset input[type=checkbox]');
    await checks.nth((await checks.count()) - 2).check({ force: true });
    await checks.last().check({ force: true });
    await page.locator('button').filter({ hasText: 'Submit for review' }).click();
    await page.getByText(`#${submissionId} · pending`, { exact: true }).waitFor();
  });
  await logout();
  await login('TestFounder');
  await check('All admin sections render real responses', async () => {
    for (const section of [
      'dashboard',
      'queue',
      'reports',
      'requests',
      'accounts',
      'activity',
      'stats',
    ])
      await goto(`/en/admin-pannel/${section}`);
  });
  await check('Moderator inspects and approves a submission', async () => {
    await goto('/en/admin-pannel/queue');
    await page.getByRole('button', { name: 'Inspect', exact: true }).click();
    await page.getByRole('link', { name: 'Download ZIP', exact: true }).waitFor();
    await page.getByRole('button', { name: 'Approve', exact: true }).click();
    await page.locator('.api-action-form button[type=submit]').click();
    await page.getByText('Nothing to show yet.', { exact: true }).waitFor();
  });
  await check('Published addon has working page, download and canonical redirect', async () => {
    await goto('/en/explore');
    await page.getByRole('link', { name: 'Browser Addon', exact: true }).click();
    await page.waitForLoadState('networkidle');
    assert.match(page.url(), /wotlk\/pve\/browser-addon/);
    const link = page.locator('a[href*="/api/downloads/"]').first();
    const [download] = await Promise.all([page.waitForEvent('download'), link.click()]);
    assert.match(download.suggestedFilename(), /browser-addon/);
    await goto('/en/explore/wotlk/map/browser-addon');
    assert.match(page.url(), /wotlk\/pve/);
  });
  await check(
    'Home anchors scroll on direct entry, repeated clicks and cross-page navigation',
    async () => {
      const atAnchor = (id) =>
        page.waitForFunction((id) => {
          const target = document.getElementById(id);
          return target && Math.abs(target.getBoundingClientRect().top - 84) < 3;
        }, id);
      await goto('/en#latest');
      await atAnchor('latest');
      await page.evaluate(() => window.scrollTo(0, 0));
      await page.locator('header a[href="/en#latest"]').click();
      await atAnchor('latest');
      await goto('/en/about');
      await page.locator('header a[href="/en#categories"]').click();
      await atAnchor('categories');
      await page.waitForLoadState('networkidle');
      await atAnchor('categories');
      await page.setViewportSize({ width: 390, height: 844 });
      await goto('/en/about');
      await page.locator('header button').click();
      await page.locator('a[href="/en#latest"]:visible').click();
      await atAnchor('latest');
      assert.equal(await page.getByRole('button', { name: 'Close menu', exact: true }).count(), 0);
      await page.setViewportSize({ width: 1440, height: 1000 });
      await goto('/en/explore/wotlk/pve/browser-addon');
    },
  );
  await check('Favorite, rating, report and follow controls work', async () => {
    await page.getByRole('button', { name: /favourite/i }).click();
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: /favourite/i, pressed: true }).waitFor();
    const form = page.locator('form');
    await form.locator('textarea').fill('It works correctly with this client.');
    await form.getByRole('button', { name: /5/ }).click();
    await form.locator('button[type=submit]').click();
    await page.getByText('It works correctly with this client.', { exact: true }).waitFor();
    await page.getByRole('button', { name: 'Report', exact: true }).click();
    await page
      .getByLabel('Reason', { exact: true })
      .fill('Please check the author credits for this addon.');
    await page.getByRole('button', { name: 'Send report', exact: true }).click();
    await goto('/en/profile/TestAuthor');
    await page.getByRole('button', { name: 'Follow', exact: true }).click();
    await page.getByRole('button', { name: 'Unfollow', exact: true }).waitFor();
  });
  await check('Profile tabs load without rendering errors', async () => {
    for (const name of ['Achievements', 'Journal', 'Reviews', 'Addons']) {
      await page.getByRole('button', { name: new RegExp(`^${name}`) }).click();
      await page.waitForLoadState('networkidle');
    }
  });
  await check('Report resolution updates the available actions', async () => {
    await goto('/en/admin-pannel/reports');
    await page.getByRole('button', { name: 'Dismiss', exact: true }).click();
    await page.locator('.api-action-form textarea').fill('Checked in browser integration test.');
    await page.locator('.api-action-form button[type=submit]').click();
    await page.getByRole('button', { name: 'Reopen', exact: true }).waitFor();
  });
  await check('Contact recipient selection and attachment show ticket confirmation', async () => {
    await goto('/en/contact');
    assert.equal(await page.getByRole('button', { name: /TestFounder/ }).count(), 1);
    await page.getByRole('button', { name: /Moderators/ }).click();
    await page.getByRole('button', { name: 'Something else', exact: true }).click();
    const form = page.locator('form');
    await form.locator('input[type=text]').nth(0).fill('Test Founder');
    await form.locator('input[type=text]').nth(1).fill('Browser contact test');
    await form
      .locator('textarea')
      .fill('This is a complete message from the browser integration test.');
    await form.locator('input[type=file]').setInputFiles(path.join(env.ROG_STORAGE, 'screenshot.png'));
    await page.getByText('screenshot.png', { exact: true }).waitFor();
    await form.locator('button[type=submit]').click();
    await page.getByRole('status').filter({ hasText: /^#\d+ ·/ }).waitFor();
  });
  await check('Catalog filtering and retry show actual results', async () => {
    await goto('/en/explore?q=no-such-addon');
    assert.match(await page.locator('body').innerText(), /Nothing to show yet/);
    await page.route('**/api/addons?*', (route) =>
      route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({ ok: false, error: { code: 'UNAVAILABLE' } }),
      }),
    );
    await goto('/en/explore');
    await page.getByRole('button', { name: 'Retry', exact: true }).waitFor();
    await page.unroute('**/api/addons?*');
    await page.getByRole('button', { name: 'Retry', exact: true }).click();
    await page.getByRole('link', { name: 'Browser Addon', exact: true }).waitFor();
  });
  await check('Pack expansion selection downloads the server archive', async () => {
    await run(['tests/fixtures/frontend.php', 'pack']);
    await goto('/en');
    await page.getByRole('heading', { name: 'Browser pack', exact: true }).waitFor();
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('a[href*="/api/packs/tank/download"]').click(),
    ]);
    assert.match(download.suggestedFilename(), /rog-tank-kit-wotlk/);
  });
  await check('Request voting, following and moderation update the page', async () => {
    await goto('/en/requests');
    await page.getByRole('button', { name: 'Upvote', exact: true }).click();
    await page.getByRole('button', { name: 'Upvote', exact: true, pressed: true }).waitFor();
    await page.getByRole('button', { name: /^Follow ·/ }).click();
    await page.getByRole('button', { name: /^Unfollow ·/ }).waitFor();
    await goto('/en/admin-pannel/requests');
    await page.getByRole('button', { name: 'Ping authors', exact: true }).click();
    await page.locator('.api-action-form button[type=submit]').click();
    await page.locator('.api-action-form').waitFor({ state: 'detached' });
    await page.getByRole('button', { name: 'Link upload', exact: true }).click();
    await page.locator('.api-action-form input').first().fill('browser-addon');
    await page.locator('.api-action-form button[type=submit]').click();
    await page.getByRole('link', { name: 'Browser request', exact: true }).waitFor();
    await page.getByText('fulfilled', { exact: true }).last().waitFor();
    await prepare('/requests', {
      addonName: 'Close this request', expansionId: 'wotlk', text: 'A request that is no longer needed.',
    });
    await goto('/en/admin-pannel/requests');
    await page.locator('input[type=search]').fill('Close this request');
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: 'Close', exact: true }).click();
    await page.locator('.api-action-form button[type=submit]').click();
    await page.getByText('Nothing to show yet.', { exact: true }).waitFor();
  });
  await check('Account suspension, reinstatement and role selection update the controls', async () => {
    await goto('/en/admin-pannel/accounts');
    await page.getByRole('button').filter({ hasText: 'TestPlayer' }).click();
    await page.getByRole('button', { name: 'Suspend', exact: true }).click();
    await page.locator('.api-action-form textarea').fill('Temporary integration suspension.');
    await page.locator('.api-action-form button[type=submit]').click();
    await page.getByRole('button', { name: 'Reinstate', exact: true }).waitFor();
    await page.getByRole('button', { name: 'Reinstate', exact: true }).click();
    await page.locator('.api-action-form button[type=submit]').click();
    await page.getByRole('button', { name: 'Suspend', exact: true }).waitFor();
    await page.getByLabel('Role', { exact: true }).selectOption('Moderator');
    await page.locator('.api-action-form button[type=submit]').click();
    await page.getByLabel('Role', { exact: true }).locator('option:checked')
      .filter({ hasText: /^Moderator$/ }).waitFor({ state: 'attached' });
  });
  await check('Staff message and invitation forms close after submission', async () => {
    await page.getByRole('button', { name: 'Message', exact: true }).click();
    await page.locator('.api-action-form input').fill('Browser message');
    await page.locator('.api-action-form textarea').fill('A test message to the selected account.');
    await page.locator('.api-action-form button[type=submit]').click();
    await page.locator('.api-action-form').waitFor({ state: 'detached' });
    await page.getByRole('button', { name: 'Invite moderator', exact: true }).click();
    await page.locator('.api-action-form input').fill('invite-browser@example.test');
    await page.locator('.api-action-form button[type=submit]').click();
    await page.locator('.api-action-form').waitFor({ state: 'detached' });
  });
  await check('CSV exports are downloadable', async () => {
    await goto('/en/admin-pannel/activity');
    const [csv] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: 'Export CSV', exact: true }).click(),
    ]);
    assert.match(csv.suggestedFilename(), /\.csv$/);
    await goto('/en/admin-pannel/stats');
    const [stats] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Export period', exact: true }).click(),
    ]);
    assert.match(stats.suggestedFilename(), /downloads-7d\.csv/);
  });
  await check('Own submissions navigate to the next page', async () => {
    await goto('/en');
    await logout();
    await login('TestAuthor');
    for (let index = 0; index < 11; index++)
      await prepare('/submissions', { name: `Draft ${index}` });
    await goto('/en/upload?view=submissions');
    await page
      .locator('.api-pagination')
      .getByRole('button', { name: 'Next', exact: true })
      .click();
    await page.waitForLoadState('networkidle');
    assert.match(await page.locator('.api-pagination').innerText(), /2 \/ 2/);
  });
  await check('Registration, code resend and verification create a session', async () => {
    await goto('/en');
    await logout();
    await goto('/en/register');
    await page.locator('[name=username]').fill('RegisteredPlayer');
    await page.locator('[name=email]').fill('registered@example.test');
    await page.locator('[name=password]').fill('Frontend test password 123');
    await page.locator('label').filter({ hasText: 'I accept' }).locator('span').first().click();
    const [pending] = await Promise.all([
      page.waitForResponse((r) => r.url().endsWith('/auth/register')),
      page.locator('#signup button[type=submit]').click(),
    ]);
    const registration = await pending.json();
    assert.equal(registration.ok, true, JSON.stringify(registration));
    await page.waitForURL(/\/verify$/);
    await run(['tests/fixtures/frontend.php', 'resend-ready', registration.verificationId]);
    const [resend] = await Promise.all([
      page.waitForResponse((r) => r.url().endsWith('/auth/verify/resend')),
      page.getByRole('button', { name: 'resend the code', exact: true }).click(),
    ]);
    const renewed = await resend.json();
    assert.equal(renewed.ok, true, JSON.stringify(renewed));
    await page.reload();
    await page.waitForLoadState('networkidle');
    const code = (await run(['tests/fixtures/frontend.php', 'code', renewed.verificationId])).trim();
    for (let index = 0; index < 6; index++)
      await page.locator('#verify input[type=text]').nth(index).fill(code[index]);
    await page.locator('#verify button[type=submit]').click();
    await page.waitForURL(/\/done$/);
    await goto('/en');
    await page.getByRole('navigation', { name: 'Account', exact: true })
      .getByRole('link', { name: 'RegisteredPlayer', exact: true }).waitFor();
  });
  await check('Mobile navigation and screens render', async () => {
    await page.setViewportSize({ width: 390, height: 844 });
    for (const route of [
      '/en',
      '/en/explore',
      '/en/explore/wotlk/pve/browser-addon',
      '/en/profile/TestAuthor',
      '/en/contact',
      '/en/upload',
      '/en/requests',
      '/en/missing-page',
      '/en/register',
    ]) {
      await goto(route);
      const widths = await page.evaluate(() => ({
        page: document.documentElement.scrollWidth,
        window: innerWidth,
      }));
      assert.ok(widths.page <= widths.window + 2, `${route}: horizontal overflow ${widths.page}`);
    }
    await page.screenshot({ path: path.join(work, 'mobile-contact.png'), fullPage: true });
  });
  await check('Existing account accepts a moderator invitation', async () => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await goto('/en');
    await logout();
    await login('TestFounder');
    const invitation = await prepare('/admin/invitations', {
      email: 'registered@example.test',
      role: 'moderator',
    });
    const token = (
      await run(['tests/fixtures/frontend.php', 'invitation', String(invitation.invitationId)])
    ).trim();
    await logout();
    await goto(`/register?invitationToken=${token}`);
    await page.getByRole('link', { name: 'Sign in', exact: true }).first().click();
    await page.locator('[name=identifier]').fill('RegisteredPlayer');
    await page.locator('[name=password]').fill('Frontend test password 123');
    await page.locator('#login button[type=submit]').click();
    await page.getByRole('button', { name: 'Accept invitation', exact: true }).click();
    await page.waitForURL(/admin-pannel/);
    await page.locator('aside').getByText(/^moderator$/i).waitFor();
    await page.setViewportSize({ width: 390, height: 844 });
    await goto('/en');
    const widths = await page.evaluate(() => ({
      page: document.documentElement.scrollWidth,
      window: innerWidth,
    }));
    assert.ok(widths.page <= widths.window + 2, 'Staff navigation overflows on mobile');
  });
  assert.equal(errors.length, 0, errors.join('\n'));
  assert.deepEqual(failedResponses, []);
  await fs.writeFile(
    path.join(work, 'frontend-test-results.json'),
    JSON.stringify(
      {
        passed: checks.length,
        checks,
        scope:
          'Headless Chromium against disposable SQLite and real PHP API; no production data or external email.',
      },
      null,
      2,
    ),
  );
} catch (error) {
  if (page)
    await page.screenshot({ path: path.join(work, 'failure.png'), fullPage: true }).catch(() => {});
  console.error('Page:', page?.url());
  console.error('Browser errors:', errors);
  if (page)
    console.error(
      (
        await page
          .locator('body')
          .innerText()
          .catch(() => '')
      ).slice(-3500),
    );
  throw error;
} finally {
  await browser?.close();
  await vite?.close();
  if (server && server.exitCode === null)
    await new Promise((resolve) => {
      server.once('exit', resolve);
      server.kill();
    });
  await fs.writeFile(path.join(work, 'server.log'), serverLog);
  console.log('Disposable browser test directory:', work);
}
