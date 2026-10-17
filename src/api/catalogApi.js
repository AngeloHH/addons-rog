import { api } from './client.js';

export async function fetchExpansions() {
  const taxonomy = await api.get('/taxonomy');
  return taxonomy.expansions;
}
