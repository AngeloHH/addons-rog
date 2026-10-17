import { databaseEngines, siteLanguages } from '../setupConfig.js';
import { Action, Actions, Note } from '../SetupControls.jsx';
import styles from '../SetupPage.module.scss';

function reviewGroups(t, { admin, db, mail, site }) {
  const yesNo = (value) => (value ? t('install.yes') : t('install.no'));
  const language = siteLanguages.find((item) => item.code === site.language)?.name ?? site.language;

  return [
    {
      id: 'database',
      rows: [
        ['engine', databaseEngines.find((engine) => engine.id === db.driver)?.name],
        ...(db.driver === 'sqlite' ? [['database', db.path.trim()]] : [
        ['host', `${db.host.trim()}:${db.port}`],
        ['database', db.name.trim()],
        ['account', db.username.trim()],
        ['ssl', db.driver === 'pgsql' ? t(`database.ssl.${db.sslMode}`) : db.sslCa.trim() || t('install.no')],
        ]),
        ['create', yesNo(db.createIfMissing)],
      ],
    },
    {
      id: 'site',
      rows: [
        ['name', site.name.trim()],
        ['url', site.url.trim()],
        ['language', `${language} · ${site.timezone.trim()}`],
        ['storage', site.storagePath.trim() || t('install.defaultStorage')],
        ['mail', t(`site.transports.${mail.transport}`)],
      ],
    },
    {
      id: 'founder',
      rows: [
        ['username', admin.username.trim()],
        ['email', admin.email.trim()],
        ['role', t('founder.role')],
      ],
    },
  ];
}

function InstallLog({ log, t }) {
  if (!log.length) return null;

  return (
    <ol className={styles.log}>
      {log.map((line, index) => (
        <li className={styles.logLine} data-state={line.failed ? 'failed' : line.done ? 'done' : 'waiting'} key={`${line.key}-${index}`}>
          <span className={styles.logTime}>{line.time}</span>
          <span>{t(line.key)}</span>
        </li>
      ))}
    </ol>
  );
}

function Done({ language, result, t }) {
  const siteUrl = (result.siteUrl || window.location.origin).replace(/\/+$/, '');

  return (
    <div className={styles.done}>
      <span className={styles.doneIcon} aria-hidden="true">
        ✓
      </span>
      <h2 className={styles.doneTitle}>{t('done.title')}</h2>
      <p className={styles.doneText}>
        {t('done.textBefore')} <strong>{t('done.textLocked')}</strong>
        {t('done.textAfter')}
      </p>
      <Actions>
        <a className={styles.secondaryAction} href={`${siteUrl}/${language}`}>
          {t('done.viewSite')}
        </a>
        <a className={styles.action} href={`${siteUrl}/${language}/login`}>
          {t('done.signIn')}
        </a>
      </Actions>
    </div>
  );
}

export default function InstallStep({ t, wizard }) {
  const { back, busy, checks, pending, recheck, run, startInstall, values } = wizard;

  if (run.finished) {
    return (
      <>
        <InstallLog log={run.log} t={t} />
        <Done language={values.site.language} result={run.result} t={t} />
      </>
    );
  }

  let installLabel = t('install.install');
  if (busy && run.started) installLabel = t('install.installing');
  else if (pending) installLabel = t('install.resume');

  // Resume an unconfirmed attempt by asking the server for its current installation state.
  const unconfirmed = run.started && !busy;

  return (
    <>
      {pending ? (
        <Note tone="warning">{t('install.pending')}</Note>
      ) : (
        <>
          <div className={styles.review}>
            {reviewGroups(t, values).map((group) => (
              <section className={styles.reviewGroup} key={group.id}>
                <h3 className={styles.subheading}>{t(`install.groups.${group.id}`)}</h3>
                <dl className={styles.reviewRows}>
                  {group.rows.map(([key, value]) => (
                    <div className={styles.reviewRow} key={key}>
                      <dt>{t(`install.rows.${key}`)}</dt>
                      <dd>{value}</dd>
                    </div>
                  ))}
                </dl>
              </section>
            ))}
          </div>


        </>
      )}

      {busy && run.started && (
        <Note role="status" tone="warning">
          {t('install.inProgress')}
        </Note>
      )}
      <InstallLog log={run.log} t={t} />

      <Actions>
        <Action disabled={busy} icon="back" secondary onClick={back}>
          {t('back')}
        </Action>
        {unconfirmed ? (
          <Action disabled={busy} secondary onClick={recheck}>
            {t('install.checkStatus')}
          </Action>
        ) : (
          <Action disabled={busy || !checks?.ready} onClick={startInstall}>
            {installLabel}
          </Action>
        )}
      </Actions>
    </>
  );
}
