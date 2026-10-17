import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../api/client.js';
import { useSession } from '../context/SessionContext.jsx';

export function useApi(path) {
  const { user } = useSession();
  const key = `${user?.id || 'guest'}:${path}`;
  const [revision, setRevision] = useState(0);
  const [state, setState] = useState({ key: null, data: null, error: null, loading: true });
  const reload = useCallback(() => setRevision((value) => value + 1), []);
  useEffect(() => {
    if (!path) return;
    const controller = new AbortController();
    setState((previous) => ({
      key,
      data: previous.key === key ? previous.data : null,
      loading: true,
      error: null,
    }));
    api.get(path, { signal: controller.signal }).then(
      (data) => {
        if (!controller.signal.aborted) setState({ key, data, error: null, loading: false });
      },
      (error) => {
        if (!controller.signal.aborted)
          setState((previous) => ({ ...previous, key, error, loading: false }));
      },
    );
    return () => controller.abort();
  }, [path, key, revision]);
  return {
    ...(path && state.key === key
      ? { data: state.data, error: state.error, loading: state.loading }
      : { data: null, error: null, loading: Boolean(path) }),
    reload,
  };
}

export function useAction() {
  const active = useRef(false);
  const [state, setState] = useState({ busy: false, error: null, done: false });
  const run = async (action) => {
    if (active.current) return;
    active.current = true;
    setState({ busy: true, error: null, done: false });
    try {
      const result = await action();
      setState({ busy: false, error: null, done: true });
      return result;
    } catch (error) {
      setState({ busy: false, error, done: false });
      return undefined;
    } finally {
      active.current = false;
    }
  };
  return { ...state, run, clear: () => setState({ busy: false, error: null, done: false }) };
}
