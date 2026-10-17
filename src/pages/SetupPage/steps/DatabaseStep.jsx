import { databaseEngines, sslModes } from '../setupConfig.js';
import { Action, Actions, Field, Note } from '../SetupControls.jsx';
import styles from '../SetupPage.module.scss';

function testNote(t, dbTest) {
  if (!dbTest) return { text: t('database.untested'), tone: 'muted' };
  if (!dbTest.ready) return { text: dbTest.reason ? t(`errors.${dbTest.reason}`) : t('database.notReady'), tone: 'danger' };

  const key = dbTest.databaseExists ? 'database.readyExisting' : 'database.readyCreate';
  return { text: t(key, { version: dbTest.serverVersion }), tone: 'ok' };
}

export default function DatabaseStep({ t, wizard }) {
  const { back, busy, checks, dbTest, fieldsLocked, next, runDatabaseTest, setField, values } = wizard;
  const { db } = values;
  const note = testNote(t, dbTest);

  return (
    <>
      <div className={styles.grid}>
        <Field hint={t('database.engineHint')} label={t('database.engine')}>
          <select className={styles.input} disabled={fieldsLocked} value={db.driver} onChange={setField('db', 'driver')}>
            {databaseEngines.map((engine) => <option key={engine.id} value={engine.id} disabled={checks?.database?.drivers?.find((item) => item.id === engine.id)?.available === false}>{engine.name}</option>)}
          </select>
        </Field>
        {db.driver === 'sqlite' ? <Field label={t('database.path')} hint={t('database.pathHint')} wide>
          <input className={styles.input} disabled={fieldsLocked} spellCheck={false} value={db.path} onChange={setField('db', 'path')} />
        </Field> : <>
        {db.driver === 'pgsql' ?
        <Field hint={t('database.sslHint')} label={t('database.sslMode')}>
          <select className={styles.input} disabled={fieldsLocked} value={db.sslMode} onChange={setField('db', 'sslMode')}>
            {sslModes.map((mode) => (
              <option key={mode} value={mode}>
                {t(`database.ssl.${mode}`)}
              </option>
            ))}
          </select>
        </Field> : <Field label={t('database.sslCa')} hint={t('database.sslCaHint')}>
          <input className={styles.input} disabled={fieldsLocked} spellCheck={false} value={db.sslCa} onChange={setField('db', 'sslCa')} />
        </Field>}
        <Field label={t('database.host')}>
          <input className={styles.input} disabled={fieldsLocked} spellCheck={false} value={db.host} onChange={setField('db', 'host')} />
        </Field>
        <Field hint={t('database.portHint')} label={t('database.port')}>
          <input
            className={styles.input}
            disabled={fieldsLocked}
            inputMode="numeric"
            max="65535"
            min="1"
            type="number"
            value={db.port}
            onChange={setField('db', 'port')}
          />
        </Field>
        <Field hint={t('database.nameHint')} label={t('database.name')}>
          <input className={styles.input} disabled={fieldsLocked} maxLength={63} spellCheck={false} value={db.name} onChange={setField('db', 'name')} />
        </Field>
        <Field label={t('database.username')}>
          <input
            autoComplete="off"
            className={styles.input}
            disabled={fieldsLocked}
            maxLength={63}
            spellCheck={false}
            value={db.username}
            onChange={setField('db', 'username')}
          />
        </Field>
        <Field hint={t('database.passwordHint')} label={t('database.password')} wide>
          <input
            autoComplete="new-password"
            className={styles.input}
            disabled={fieldsLocked}
            maxLength={1024}
            type="password"
            value={db.password}
            onChange={setField('db', 'password')}
          />
        </Field>
        </>}
      </div>

      <div className={styles.toggle}>
        <span className={styles.toggleText}>
          <span className={styles.fieldLabel}>{t('database.create')}</span>
          <span className={styles.fieldHint}>{t('database.createHint')}</span>
        </span>
        <label className={styles.checkbox}>
          <input checked={db.createIfMissing} disabled={fieldsLocked} type="checkbox" onChange={setField('db', 'createIfMissing')} />
          {t('database.createLabel')}
        </label>
      </div>

      <Note role="status" tone={note.tone}>
        {note.text}
      </Note>

      <Actions>
        <Action disabled={busy} icon="back" secondary onClick={back}>
          {t('back')}
        </Action>
        <Action disabled={fieldsLocked} secondary onClick={runDatabaseTest}>
          {busy ? t('database.testing') : t('database.test')}
        </Action>
        <Action disabled={busy || !dbTest?.ready} icon="next" onClick={next}>
          {t('database.next')}
        </Action>
      </Actions>
    </>
  );
}
