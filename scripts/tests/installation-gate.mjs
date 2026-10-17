/**
 * Addons ROG - Installation routing tests
 *
 * - Check installer redirects and recovery with simulated API responses.
 */

import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { createServer } from 'vite';
import { fileURLToPath } from 'node:url';
import net from 'node:net';

// =============================================================================
// TEMPORARY FRONTEND AND BROWSER
// =============================================================================

const root = fileURLToPath(new URL('../../', import.meta.url));
const socket = net.createServer();
await new Promise((resolve) => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise((resolve) => socket.close(resolve));
let vite, browser;
try {
  vite = await createServer({
    root, cacheDir: 'node_modules/.vite-installation-tests',
    server: { host: '127.0.0.1', port, strictPort: true },
  });
  await vite.listen();
  browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.setDefaultTimeout(10000);
  page.setDefaultNavigationTimeout(30000);
  await page.route((url) => url.origin !== `http://127.0.0.1:${port}`, (route) => route.abort());
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  let installed = false,
    failed = false,
    malformed = false;
  let requests = [];
  await page.route(
    (url) => url.pathname.startsWith('/api/'),
    (route) => {
      const pathname = new URL(route.request().url()).pathname;
      requests.push(pathname);
      const status = pathname === '/api/setup/status';
      const session = pathname === '/api/auth/session';
      return route.fulfill({
        status: status && failed ? 503 : status || session ? 200 : 503,
        contentType: 'application/json',
        body: JSON.stringify(
          status
            ? failed
              ? { ok: false, error: { code: 'UNAVAILABLE' } }
              : malformed
                ? { ok: true }
                : { ok: true, installed, available: false, pending: false }
            : session
              ? { ok: true, authenticated: false, user: null, csrfToken: 'preview' }
              : { ok: false, error: { code: 'UNAVAILABLE' } },
        ),
      });
    },
  );
  async function visit(route) {
    requests = [];
    await page.goto(`http://127.0.0.1:${port}${route}`);
    await page.waitForLoadState('networkidle');
  }
  for (const [route, target] of [
    ['/', '/en/setup'],
    ['/es/upload', '/es/setup'],
    ['/en/explore', '/en/setup'],
    ['/fr/profile/TestAuthor', '/fr/setup'],
  ]) {
    await visit(route);
    await page.waitForURL(`**${target}`);
    assert.equal(new URL(page.url()).pathname, target);
    assert.ok(requests.length > 0);
    assert.deepEqual([...new Set(requests)], ['/api/setup/status']);
  }
  console.log('PASS Uninstalled routes redirect in their language before session or data requests');
  await visit('/es/setup/');
  assert.equal(new URL(page.url()).pathname, '/es/setup/');
  assert.deepEqual([...new Set(requests)], ['/api/setup/status']);
  console.log('PASS Direct installer remains accessible without a redirect loop');
  failed = true;
  await visit('/en/login');
  assert.equal(new URL(page.url()).pathname, '/en/login');
  await page.getByRole('button', { name: 'Retry', exact: true }).waitFor();
  assert.deepEqual([...new Set(requests)], ['/api/setup/status']);
  installed = true;
  failed = false;
  await page.getByRole('button', { name: 'Retry', exact: true }).click();
  await page.locator('#login').waitFor();
  assert.ok(requests.includes('/api/auth/session'));
  console.log('PASS Connection errors can retry and installed sites load normally');
  malformed = true;
  await visit('/en/login');
  assert.equal(new URL(page.url()).pathname, '/en/login');
  await page.getByRole('button', { name: 'Retry', exact: true }).waitFor();
  assert.deepEqual([...new Set(requests)], ['/api/setup/status']);
  console.log('PASS Invalid status responses never imply an uninstalled site');
  malformed = false;
  installed = false;
  await visit('/en/setup');
  installed = true;
  requests = [];
  await Promise.all([
    page.waitForResponse(response => new URL(response.url()).pathname === '/api/auth/session'),
    page.locator('footer a[href="/en/contact"]').click(),
  ]);
  await page.waitForLoadState('networkidle');
  assert.equal(new URL(page.url()).pathname, '/en/contact');
  assert.ok(requests.includes('/api/setup/status'));
  assert.ok(requests.includes('/api/auth/session'));
  console.log('PASS Leaving the installer rechecks status after installation');
  assert.deepEqual(errors, []);
} finally {
  await browser?.close();
  await vite?.close();
}
