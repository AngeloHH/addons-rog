import { useMemo } from 'react';
import { siteLanguages, timezones } from '../setupConfig.js';
import { Action, Actions, Field, Note } from '../SetupControls.jsx';
import styles from '../SetupPage.module.scss';

function mebibytes(bytes) {
  return `${Math.round(bytes / 1048576)} MiB`;
}

export default function SiteStep({ t, wizard }) {
  const { back, busy, checks, fieldsLocked, next, setField, values } = wizard;
  const { mail, site } = values;
  const zones = useMemo(() => timezones(), []);
  const limits = checks?.limits;
  const smtp = mail.transport === 'smtp';

  return (
    <>
      <div className={styles.grid}>
        <Field label={t('site.name')}>
          <input className={styles.input} disabled={fieldsLocked} maxLength={120} value={site.name} onChange={setField('site', 'name')} />
        </Field>
        <Field hint={t('site.urlHint')} label={t('site.url')}>
          <input
            className={styles.input}
            disabled={fieldsLocked}
            spellCheck={false}
            type="url"
            value={site.url}
            onChange={setField('site', 'url')}
          />
        </Field>
        <Field label={t('site.language')}>
          <select className={styles.input} disabled={fieldsLocked} value={site.language} onChange={setField('site', 'language')}>
            {siteLanguages.map((item) => (
              <option key={item.code} value={item.code}>
                {item.name}
              </option>
            ))}
          </select>
        </Field>
        <Field hint={t('site.timezoneHint')} label={t('site.timezone')}>
          <input
            className={styles.input}
            disabled={fieldsLocked}
            list="setup-timezones"
            spellCheck={false}
            value={site.timezone}
            onChange={setField('site', 'timezone')}
          />
          <datalist id="setup-timezones">
            {zones.map((zone) => (
              <option key={zone} value={zone} />
            ))}
          </datalist>
        </Field>
        <Field hint={t('site.storageHint')} label={t('site.storage')}>
          <input
            className={styles.input}
            disabled={fieldsLocked}
            spellCheck={false}
            value={site.storagePath}
            onChange={setField('site', 'storagePath')}
          />
        </Field>
        <Field hint={t('site.limitsHint')} label={t('site.limits')}>
          <input
            className={styles.input}
            readOnly
            value={limits ? t('site.limitsValue', { zip: mebibytes(limits.addonZipMaxBytes), image: mebibytes(limits.imageMaxBytes) }) : '—'}
          />
        </Field>
      </div>

      <h3 className={styles.subheading}>{t('site.mail')}</h3>

      <div className={styles.grid}>
        <Field label={t('site.transport')}>
          <select className={styles.input} disabled={fieldsLocked} value={mail.transport} onChange={setField('mail', 'transport')}>
            <option value="spool">{t('site.transports.spool')}</option>
            <option value="smtp">{t('site.transports.smtp')}</option>
          </select>
        </Field>
        <Field hint={t('site.fromHint')} label={t('site.from')}>
          <input className={styles.input} disabled={fieldsLocked} type="email" value={mail.from} onChange={setField('mail', 'from')} />
        </Field>
        {smtp && (
          <>
            <Field label={t('site.smtpHost')}>
              <input className={styles.input} disabled={fieldsLocked} spellCheck={false} value={mail.host} onChange={setField('mail', 'host')} />
            </Field>
            <Field hint={t('site.smtpPortHint')} label={t('site.smtpPort')}>
              <input
                className={styles.input}
                disabled={fieldsLocked}
                inputMode="numeric"
                max="65535"
                min="1"
                type="number"
                value={mail.port}
                onChange={setField('mail', 'port')}
              />
            </Field>
            <Field hint={t('site.smtpUserHint')} label={t('site.smtpUser')}>
              <input
                autoComplete="off"
                className={styles.input}
                disabled={fieldsLocked}
                maxLength={254}
                value={mail.username}
                onChange={setField('mail', 'username')}
              />
            </Field>
            <Field label={t('site.smtpPassword')}>
              <input
                autoComplete="new-password"
                className={styles.input}
                disabled={fieldsLocked}
                maxLength={1024}
                type="password"
                value={mail.password}
                onChange={setField('mail', 'password')}
              />
            </Field>
          </>
        )}
      </div>

      <Note>{t('site.mailNote')}</Note>

      <Actions>
        <Action disabled={busy} icon="back" secondary onClick={back}>
          {t('back')}
        </Action>
        <Action disabled={busy} icon="next" onClick={next}>
          {t('site.next')}
        </Action>
      </Actions>
    </>
  );
}
