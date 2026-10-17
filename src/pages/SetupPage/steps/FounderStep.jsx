import { founderPasswordLength } from '../setupConfig.js';
import { passwordStrength } from '../setupValidation.js';
import { Action, Actions, Field, Note } from '../SetupControls.jsx';
import styles from '../SetupPage.module.scss';

const strengthTones = ['muted', 'danger', 'warning', 'ok', 'ok'];

export default function FounderStep({ t, wizard }) {
  const { back, busy, fieldsLocked, next, setField, values } = wizard;
  const { admin } = values;
  const strength = passwordStrength(admin.password);
  const confirmed = admin.passwordConfirmation.length > 0;
  const matches = admin.password === admin.passwordConfirmation;

  return (
    <>
      <div className={styles.grid}>
        <Field hint={t('founder.usernameHint')} label={t('founder.username')}>
          <input
            autoComplete="off"
            className={styles.input}
            disabled={fieldsLocked}
            maxLength={24}
            spellCheck={false}
            value={admin.username}
            onChange={setField('admin', 'username')}
          />
        </Field>
        <Field label={t('founder.email')}>
          <input className={styles.input} disabled={fieldsLocked} type="email" value={admin.email} onChange={setField('admin', 'email')} />
        </Field>
        <Field hint={t('founder.passwordHint')} label={t('founder.password')}>
          <input
            autoComplete="new-password"
            className={styles.input}
            disabled={fieldsLocked}
            maxLength={founderPasswordLength.max}
            type="password"
            value={admin.password}
            onChange={setField('admin', 'password')}
          />
          <span className={styles.strength} aria-hidden="true">
            {[1, 2, 3, 4].map((bar) => (
              <span className={styles.strengthBar} data-filled={bar <= strength} data-tone={strengthTones[strength]} key={bar} />
            ))}
          </span>
          <span className={styles.fieldStatus} data-tone={strengthTones[strength]}>
            {t('founder.strength')[strength]}
          </span>
        </Field>
        <Field label={t('founder.confirmation')}>
          <input
            autoComplete="new-password"
            className={styles.input}
            data-invalid={confirmed && !matches}
            disabled={fieldsLocked}
            maxLength={founderPasswordLength.max}
            type="password"
            value={admin.passwordConfirmation}
            onChange={setField('admin', 'passwordConfirmation')}
          />
          {confirmed && (
            <span className={styles.fieldStatus} data-tone={matches ? 'ok' : 'danger'}>
              {matches ? t('founder.match') : t('founder.mismatch')}
            </span>
          )}
        </Field>
      </div>

      <Note>
        {t('founder.roleBefore')} <strong className={styles.role}>{t('founder.role')}</strong> {t('founder.roleAfter')}
      </Note>

      <Actions>
        <Action disabled={busy} icon="back" secondary onClick={back}>
          {t('back')}
        </Action>
        <Action disabled={busy} icon="next" onClick={next}>
          {t('founder.next')}
        </Action>
      </Actions>
    </>
  );
}
