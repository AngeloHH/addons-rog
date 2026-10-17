import { useCallback, useEffect, useRef, useState } from 'react';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import { fetchRequirements, fetchStatus, install, SetupError, testDatabase } from './setupApi.js';
import { databaseEngines, initialValues, setupSteps, tokenLength } from './setupConfig.js';
import { validateStep } from './setupValidation.js';

function now() {
  return new Date().toLocaleTimeString();
}

function databasePayload({ db }) {
  if (db.driver === 'sqlite') return { driver: db.driver, path: db.path.trim(), createIfMissing: db.createIfMissing };
  return {
    driver: db.driver,
    host: db.host.trim(),
    port: Number(db.port),
    name: db.name.trim(),
    username: db.username.trim(),
    password: db.password,
    ...(db.driver === 'pgsql' ? { sslMode: db.sslMode } : { sslCa: db.sslCa.trim() }),
    createIfMissing: db.createIfMissing,
  };
}

/**
 * Build the POST /api/setup/install payload.
 * Omit empty optional fields because the API rejects unknown or invalid inputs.
 */
export function installPayload(values) {
  const { site, mail, admin } = values;

  return {
    database: databasePayload(values),
    site: {
      name: site.name.trim(),
      url: site.url.trim().replace(/\/+$/, ''),
      language: site.language,
      timezone: site.timezone.trim(),
      ...(site.storagePath.trim() ? { storagePath: site.storagePath.trim() } : {}),
    },
    admin: {
      username: admin.username.trim(),
      email: admin.email.trim(),
      password: admin.password,
      passwordConfirmation: admin.passwordConfirmation,
    },
    mail: {
      transport: mail.transport,
      ...(mail.from.trim() ? { from: mail.from.trim() } : {}),
      ...(mail.transport === 'smtp'
        ? { host: mail.host.trim(), port: Number(mail.port), username: mail.username, password: mail.password }
        : {}),
    },
  };
}

function toError(error) {
  if (error instanceof SetupError) return { code: error.code, fields: Object.keys(error.fields ?? {}) };
  return { code: 'UNKNOWN' };
}

export function useSetupWizard() {
  const { language } = useI18n();
  const [step, setStep] = useState(setupSteps[0]);
  const [token, setTokenValue] = useState('');
  const [status, setStatus] = useState(null);
  const [checks, setChecks] = useState(null);
  const [dbTest, setDbTest] = useState(null);
  const [values, setValues] = useState(() => initialValues(language));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [run, setRun] = useState({ started: false, finished: false, result: null, log: [] });

  // Read current form values inside asynchronous checks rather than values captured when they started.
  const latest = useRef(values);
  latest.current = values;
  const busyRef = useRef(false);

  const fail = useCallback((code, extra = {}) => setError({ code, ...extra }), []);

  const refreshStatus = useCallback(async () => {
    const next = await fetchStatus();
    setStatus(next);
    return next;
  }, []);

  useEffect(() => {
    refreshStatus().catch((reason) => {
      setStatus({ unreachable: true });
      setError(toError(reason));
    });
  }, [refreshStatus]);

  async function guarded(task) {
    if (busyRef.current) return;
    busyRef.current = true;
    setBusy(true);

    try {
      await task();
    } finally {
      busyRef.current = false;
      setBusy(false);
    }
  }

  const pending = Boolean(status?.pending);
  const installed = Boolean(status?.installed);
  const fieldsLocked = busy || installed || pending;
  const stepIndex = setupSteps.indexOf(step);

  function setField(group, key) {
    return (event) => {
      const value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;

      setValues((previous) => ({ ...previous, [group]: { ...previous[group], [key]: value, ...(group === 'db' && key === 'driver' ? { port: databaseEngines.find((engine) => engine.id === value).port } : {}) } }));
      setError(null);

      // Invalidate the connection result when its database inputs change.
      if (group === 'db') setDbTest(null);
    };
  }

  function setToken(event) {
    setTokenValue(event.target.value);
    setChecks(null);
    setDbTest(null);
    setError(null);
  }

  function recheck() {
    return guarded(async () => {
      setError(null);
      setChecks(null);

      try {
        const current = await refreshStatus();
        if (current.installed) throw new SetupError('SETUP_LOCKED');
        if (!current.available) throw new SetupError('SETUP_DISABLED');

        const key = token.trim();
        if (key.length < tokenLength.min || key.length > tokenLength.max) {
          setStep('requirements');
          fail('TOKEN_LENGTH');
          return;
        }

        const result = await fetchRequirements(key);
        setChecks(result);
        const available = result.database?.drivers?.filter((driver) => driver.available) ?? [];
        if (available.length && !available.some((driver) => driver.id === latest.current.db.driver)) {
          const engine = databaseEngines.find((item) => item.id === available[0].id);
          setValues((previous) => ({ ...previous, db: { ...previous.db, driver: engine.id, port: engine.port } }));
        }
        setStep(current.pending && result.ready ? 'install' : 'requirements');
        setRun({ started: false, finished: false, result: null, log: [] });
      } catch (reason) {
        setStep('requirements');
        setError(toError(reason));
      }
    });
  }

  function runDatabaseTest() {
    return guarded(async () => {
      const invalid = validateStep('database', latest.current);
      if (invalid) {
        setDbTest(null);
        fail(invalid);
        return;
      }

      const database = databasePayload(latest.current);
      setError(null);
      setDbTest(null);

      try {
        const result = await testDatabase(token.trim(), database);
        if (JSON.stringify(database) === JSON.stringify(databasePayload(latest.current))) setDbTest(result);
      } catch (reason) {
        setError(toError(reason));
      }
    });
  }

  function next() {
    if (busy || installed) return;

    let invalid = validateStep(step, values);
    if (step === 'requirements' && !checks?.ready) invalid = 'CHECKS_REQUIRED';
    if (step === 'database' && !invalid && !dbTest?.ready) invalid = 'DB_TEST_REQUIRED';

    if (invalid) {
      fail(invalid);
      return;
    }

    setError(null);
    setStep(pending ? 'install' : setupSteps[Math.min(stepIndex + 1, setupSteps.length - 1)]);
  }

  function back() {
    if (busy) return;

    setError(null);
    setRun((previous) => ({ ...previous, started: false }));
    setStep(pending ? 'requirements' : setupSteps[Math.max(stepIndex - 1, 0)]);
  }

  function startInstall() {
    if (run.finished || installed) return undefined;

    return guarded(async () => {
      if (!checks?.ready) {
        setStep('requirements');
        fail('CHECKS_REQUIRED');
        return;
      }

      if (!pending) {
        for (const id of ['database', 'site', 'founder']) {
          const invalid = validateStep(id, values);
          if (invalid) {
            setStep(id);
            fail(invalid);
            return;
          }
        }

        if (!dbTest?.ready) {
          setStep('database');
          fail('DB_TEST_REQUIRED');
          return;
        }
      }

      setError(null);
      setRun({ started: true, finished: false, result: null, log: [{ time: now(), key: pending ? 'install.resuming' : 'install.waiting' }] });

      try {
        // Recheck status in case another tab completed installation or left a pending journal.
        const current = await refreshStatus();
        if (current.installed) throw new SetupError('SETUP_LOCKED');
        if (!current.available) throw new SetupError('SETUP_DISABLED');
        if (current.pending !== pending) {
          setRun({ started: false, finished: false, result: null, log: [] });
          setChecks(null);
          setStep('requirements');
          fail(current.pending ? 'PENDING_FOUND' : 'STATUS_CHANGED');
          return;
        }

        const result = await install(token.trim(), pending ? {} : installPayload(values));
        const time = now();

        setRun({
          started: true,
          finished: true,
          result,
          log: result.steps.map((id) => ({ time, key: `install.log.${id}`, done: true })),
        });
        setStatus({ installed: true, available: false, pending: false });
        setTokenValue('');

        // Clear secret form values after installation.
        setValues((previous) => ({
          ...previous,
          db: { ...previous.db, password: '' },
          mail: { ...previous.mail, password: '' },
          admin: { ...previous.admin, password: '', passwordConfirmation: '' },
        }));
      } catch (reason) {
        setError(toError(reason));
        setRun((previous) => ({ ...previous, log: [...previous.log, { time: now(), key: 'install.unconfirmed', failed: true }] }));

        try {
          await refreshStatus();
        } catch {
          setStatus(null);
        }
      }
    });
  }

  return {
    step,
    stepIndex,
    token,
    status,
    checks,
    dbTest,
    values,
    busy,
    error,
    run,
    pending,
    installed,
    fieldsLocked,
    setField,
    setToken,
    recheck,
    runDatabaseTest,
    next,
    back,
    startInstall,
  };
}
