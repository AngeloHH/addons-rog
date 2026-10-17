import { api } from './client.js';

export const registerAccount = (data) => api.post('/auth/register', data);
export const verifyAccount = (data) => api.post('/auth/verify', data);
export const loginAccount = (data) => api.post('/auth/login', data);
export const resendVerification = (data) => api.post('/auth/verify/resend', data);
