import assert from 'node:assert/strict';
import test from 'node:test';
import { loginAccount, registerAccount, verifyAccount } from '../../src/api/authApi.js';
import { fetchExpansions } from '../../src/api/catalogApi.js';
let serial = 0;
const fresh = () => import(`../../src/api/client.js?test=${++serial}`);
const response = (body, status = 200) => ({ ok: status < 400, status, json: async () => body });

test('canonical auth routes reuse CSRF and accept rotated tokens', async (t) => {
  const calls = [];
  t.mock.method(globalThis, 'fetch', async (url, options = {}) => {
    calls.push({ url, options });
    if (url === '/api/auth/session') return response({ ok: true, csrfToken: 'guest' });
    assert.equal(options.credentials, 'same-origin');
    assert.equal(options.headers['X-CSRF-Token'], url.endsWith('/login') ? 'signed-in' : 'guest');
    return response({
      ok: true,
      verificationId: 'pending-id',
      ...(url.endsWith('/verify')
        ? { authenticated: true, user: { id: 1 }, csrfToken: 'signed-in' }
        : {}),
    });
  });
  const result = await registerAccount({ username: 'Player', expansionId: 'wotlk' });
  await verifyAccount({ verificationId: result.verificationId, code: 'ABCDEF' });
  await loginAccount({ identifier: 'Player', password: 'example' });
  assert.deepEqual(
    calls.map((call) => call.url),
    ['/api/auth/session', '/api/auth/register', '/api/auth/verify', '/api/auth/login'],
  );
  assert.deepEqual(JSON.parse(calls[2].options.body), {
    verificationId: 'pending-id',
    code: 'ABCDEF',
  });
});

test('failed session never sends mutation credentials', async (t) => {
  const { api } = await fresh();
  const calls = [];
  t.mock.method(globalThis, 'fetch', async (url) => {
    calls.push(url);
    return response({ ok: false, error: { message: 'Unavailable' } }, 503);
  });
  await assert.rejects(api.post('/auth/login', { password: 'private' }), /Unavailable/);
  assert.deepEqual(calls, ['/api/auth/session']);
});

test('concurrent mutations share session discovery and multipart stays intact', async (t) => {
  const { api } = await fresh();
  const calls = [];
  const form = new FormData();
  form.append('kind', 'icon');
  t.mock.method(globalThis, 'fetch', async (url, options) => {
    calls.push({ url, options });
    return response(url.endsWith('/session') ? { ok: true, csrfToken: 'token' } : { ok: true });
  });
  await Promise.all([api.post('/media', form), api.put('/requests/1/vote', { voted: true })]);
  assert.equal(calls.filter((call) => call.url.endsWith('/session')).length, 1);
  const upload = calls.find((call) => call.url.endsWith('/media'));
  assert.equal(upload.options.body, form);
  assert.equal(upload.options.headers['Content-Type'], undefined);
});

test('session expiration notifies listeners and mutations are never replayed', async (t) => {
  const { api, subscribeSession } = await fresh();
  const updates = [];
  subscribeSession((value) => updates.push(value));
  let writes = 0;
  t.mock.method(globalThis, 'fetch', async (url) =>
    url.endsWith('/session')
      ? response({ ok: true, csrfToken: 'token' })
      : (++writes, response({ ok: false, error: { code: 'UNAUTHENTICATED' } }, 401)),
  );
  await assert.rejects(api.post('/submissions'), (error) => error.status === 401);
  assert.equal(writes, 1);
  assert.equal(updates.at(-1).authenticated, false);
});

test('structured validation errors and invalid responses are preserved', async (t) => {
  const { api } = await fresh();
  t.mock.method(globalThis, 'fetch', async () =>
    response(
      { ok: false, error: { code: 'VALIDATION_FAILED', fields: { summary: ['REQUIRED'] } } },
      422,
    ),
  );
  await assert.rejects(
    api.get('/addons'),
    (error) => error.code === 'VALIDATION_FAILED' && error.fields.summary[0] === 'REQUIRED',
  );
  t.mock.method(globalThis, 'fetch', async () => ({
    ok: true,
    status: 200,
    json: async () => {
      throw new Error('HTML');
    },
  }));
  await assert.rejects(api.get('/addons'), (error) => error.code === 'API_RESPONSE_INVALID');
});

test('taxonomy supplies the expansion options', async (t) => {
  t.mock.method(globalThis, 'fetch', async (url) => {
    assert.equal(url, '/api/taxonomy');
    return response({ ok: true, expansions: [{ id: 'wotlk' }] });
  });
  assert.deepEqual(await fetchExpansions(), [{ id: 'wotlk' }]);
});

test('API timestamps and date-only strings render safely', async () => {
  const { formatDate } = await import('../../src/lib/date.js');
  assert.notEqual(formatDate('2026-10-12T14:30:00Z', 'en'), '—');
  assert.match(formatDate('2026-10-12', 'en'), /12/);
  assert.equal(formatDate(null), '—');
  assert.equal(formatDate('invalid'), '—');
});
