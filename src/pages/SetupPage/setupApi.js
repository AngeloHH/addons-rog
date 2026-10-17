// Call /api/setup using the temporary X-Setup-Token rather than session CSRF.
// The server also validates the browser's Origin for POST requests.
export class SetupError extends Error {
  constructor(code, fields = {}, details = {}) {
    super(code);
    this.code = code;
    this.fields = fields;
    this.details = details;
  }
}

async function request(path, { token, body } = {}) {
  const headers = {};

  if (token !== undefined) headers['X-Setup-Token'] = token;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  let response;

  try {
    response = await fetch(`/api/setup/${path}`, {
      method: body === undefined ? 'GET' : 'POST',
      headers,
      cache: 'no-store',
      credentials: 'same-origin',
      redirect: 'error',
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
  } catch {
    throw new SetupError('API_UNAVAILABLE');
  }

  let payload;

  try {
    payload = await response.json();
  } catch {
    throw new SetupError('API_RESPONSE_INVALID');
  }

  if (!response.ok || payload?.error) {
    const error = payload?.error;
    throw new SetupError(error?.code ?? 'API_RESPONSE_INVALID', error?.fields, error?.details);
  }

  if (payload?.ok !== true) throw new SetupError('API_RESPONSE_INVALID');

  return payload;
}

function expect(condition) {
  if (!condition) throw new SetupError('API_RESPONSE_INVALID');
}

export async function fetchStatus() {
  const status = await request('status');
  expect(['installed', 'available', 'pending'].every((key) => typeof status[key] === 'boolean'));
  return status;
}

export async function fetchRequirements(token) {
  const result = await request('requirements', { token });
  expect(Array.isArray(result.checks) && typeof result.ready === 'boolean');
  return result;
}

export async function testDatabase(token, database) {
  const result = await request('database', { token, body: { database } });
  expect(result.connected === true && typeof result.ready === 'boolean');
  return result;
}

export async function install(token, payload) {
  const result = await request('install', { token, body: payload });
  expect(result.installed === true && result.locked === true && Array.isArray(result.steps));
  return result;
}
