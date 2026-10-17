import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { api, refreshSession, subscribeSession } from '../api/client.js';

const SessionContext = createContext(null);

export function SessionProvider({ children }) {
  const { pathname } = useLocation();
  const setup = pathname.replace(/\/+$/, '').endsWith('/setup');
  const [state, setState] = useState({ user: null, loading: true, error: null });
  const refresh = useCallback(async () => {
    try {
      const result = await refreshSession();
      setState({ user: result.user, loading: false, error: null });
      return result;
    } catch (error) {
      setState({ user: null, loading: false, error });
      throw error;
    }
  }, []);
  useEffect(
    () =>
      subscribeSession((result) => setState({ user: result.user, loading: false, error: null })),
    [],
  );
  useEffect(() => {
    if (!setup) refresh().catch(() => {});
  }, [setup, refresh]);
  const logout = () => api.post('/auth/logout');
  const value = {
    ...state,
    refresh,
    logout,
    staff: ['moderator', 'founder'].includes(state.user?.role),
    founder: state.user?.role === 'founder',
  };
  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession() {
  return useContext(SessionContext);
}
