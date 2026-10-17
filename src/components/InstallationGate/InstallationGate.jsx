import { translate } from '../../i18n/translate.js';
import { useEffect, useState } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { defaultLanguage, languages } from '../../i18n/languages.js';
import { fetchStatus } from '../../pages/SetupPage/setupApi.js';
import styles from './InstallationGate.module.scss';

/**
 * Allow the installer to open without an authenticated session or database connection.
 */
export default function InstallationGate({ children }) {
  const { pathname } = useLocation();
  const parts = pathname.split('/').filter(Boolean);
  const knownLanguage = languages.some((item) => item.code === parts[0]);
  const language = knownLanguage ? parts[0] : defaultLanguage;
  if (knownLanguage && parts.length === 2 && parts[1] === 'setup') return children;
  return <InstallationCheck language={language}>{children}</InstallationCheck>;
}

function InstallationCheck({ children, language }) {
  const [attempt, setAttempt] = useState(0);
  const [state, setState] = useState({ error: false, installed: null });
  useEffect(() => {
    let cancelled = false;
    fetchStatus().then(
      (status) => {
        if (!cancelled) setState({ error: false, installed: status.installed });
      },
      () => {
        if (!cancelled) setState({ error: true, installed: null });
      },
    );
    return () => {
      cancelled = true;
    };
  }, [attempt]);

  if (state.installed === false) return <Navigate replace to={`/${language}/setup`} />;
  if (state.installed === true) return children;
  const t = (key) => translate(language, key);
  return (
    <main className={styles.status}>
      {state.error ? (
        <div className="api-state" role="alert">
          <p>
            {t('installation.checkFailed')}
          </p>
          <button
            type="button"
            onClick={() => {
              setState({ error: false, installed: null });
              setAttempt((value) => value + 1);
            }}
          >
            {t('ui.retry')}
          </button>
        </div>
      ) : (
        <p role="status">{t('installation.checking')}</p>
      )}
    </main>
  );
}
