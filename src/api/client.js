let csrfToken = null;
let sessionRequest = null;
const listeners = new Set();

export class ApiError extends Error {
  constructor(status, error = {}) {
    super(error.message || error.code || 'Request failed');
    this.status = status;
    this.code = error.code || 'API_UNAVAILABLE';
    this.fields = error.fields || {};
    this.details = error.details || {};
    this.retryAfterSeconds = error.retryAfterSeconds;
  }
}

export function subscribeSession(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export async function request(path, { method = 'GET', body, signal } = {}) {
  const writing = !['GET', 'HEAD'].includes(method);
  if (writing && !csrfToken) await refreshSession();
  const headers = {};
  if (writing) headers['X-CSRF-Token'] = csrfToken;
  const multipart = body instanceof FormData;
  if (body !== undefined && !multipart) headers['Content-Type'] = 'application/json';
  let response;
  try {
    response = await fetch(`/api${path}`, {
      method,
      credentials: 'same-origin',
      cache: 'no-store',
      headers,
      signal,
      ...(body !== undefined ? { body: multipart ? body : JSON.stringify(body) } : {}),
    });
  } catch (error) {
    if (error.name === 'AbortError') throw error;
    throw new ApiError(0);
  }
  let result;
  try {
    result = await response.json();
  } catch {
    throw new ApiError(response.status, { code: 'API_RESPONSE_INVALID' });
  }
  if (!response.ok || result.ok !== true) {
    if (response.status === 401 || response.status === 403) csrfToken = null;
    if (response.status === 401 && result.error?.code === 'UNAUTHENTICATED')
      listeners.forEach((listener) => listener({ authenticated: false, user: null }));
    throw new ApiError(response.status, result.error);
  }
  if (result.csrfToken) csrfToken = result.csrfToken;
  if (typeof result.authenticated === 'boolean') listeners.forEach((listener) => listener(result));
  if (path === '/auth/logout') {
    csrfToken = null;
    listeners.forEach((listener) => listener({ authenticated: false, user: null }));
  }
  return result;
}

export function refreshSession() {
  if (!sessionRequest)
    sessionRequest = request('/auth/session').finally(() => {
      sessionRequest = null;
    });
  return sessionRequest;
}

export const api = {
  get: (path, options) => request(path, options),
  post: (path, body = {}) => request(path, { method: 'POST', body }),
  put: (path, body) => request(path, { method: 'PUT', body }),
  patch: (path, body) => request(path, { method: 'PATCH', body }),
};

export function queryPath(path, values = {}) {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(values)) {
    if (value !== undefined && value !== null && value !== '') query.set(key, String(value));
  }
  return `${path}${query.size ? `?${query}` : ''}`;
}
