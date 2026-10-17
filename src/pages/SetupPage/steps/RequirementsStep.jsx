import { requirementIds, tokenLength } from '../setupConfig.js';
import { Action, Actions, Field, Note } from '../SetupControls.jsx';
import styles from '../SetupPage.module.scss';

function statusKey(status) {
  if (!status) return 'loading';
  if (status.unreachable) return 'unreachable';
  if (status.installed) return 'installed';
  if (status.pending) return 'pending';
  return status.available ? 'available' : 'disabled';
}

function mark(check) {
  if (!check) return { symbol: '—', tone: 'muted' };
  if (check.passed) return { symbol: '✓', tone: 'ok' };
  return check.required ? { symbol: '×', tone: 'danger' } : { symbol: '!', tone: 'warning' };
}

export default function RequirementsStep({ t, wizard }) {
  const { busy, checks, installed, next, recheck, setToken, status, token } = wizard;
  const warnings = checks?.checks.filter((check) => !check.required && !check.passed).length ?? 0;

  let summary = { key: 'none', tone: 'muted' };
  if (checks) {
    if (!checks.ready) summary = { key: 'failed', tone: 'danger' };
    else summary = warnings ? { key: 'warnings', tone: 'warning' } : { key: 'ready', tone: 'ok' };
  }

  return (
    <>
      <Field hint={t('requirements.tokenHint')} label={t('requirements.token')} wide>
        <input
          autoComplete="off"
          className={styles.input}
          disabled={busy || installed}
          maxLength={tokenLength.max}
          spellCheck={false}
          type="password"
          value={token}
          onChange={setToken}
          onKeyDown={(event) => {
            if (event.key === 'Enter') recheck();
          }}
        />
      </Field>

      <Note role="status">{t(`requirements.status.${statusKey(status)}`)}</Note>

      <ul className={styles.checks}>
        {requirementIds.map((id) => {
          const check = checks?.checks.find((item) => item.id === id);
          const [label, hint] = t(`requirements.items.${id}`);
          const { symbol, tone } = mark(check);
          let value = t('requirements.pendingCheck');
          if (check) value = check.value ?? t(check.passed ? 'requirements.passed' : 'requirements.failed');

          return (
            <li className={styles.check} key={id}>
              <span className={styles.checkMark} data-tone={tone}>
                {symbol}
              </span>
              <span className={styles.checkText}>
                <span className={styles.checkLabel}>{label}</span>
                <span className={styles.checkHint}>{hint}</span>
              </span>
              <span className={styles.checkValue}>{value}</span>
            </li>
          );
        })}
      </ul>

      <Note tone={summary.tone}>{t(`requirements.summary.${summary.key}`, { count: warnings })}</Note>

      <Actions>
        <Action disabled={busy || installed} secondary onClick={recheck}>
          {busy ? t('requirements.checking') : t('requirements.check')}
        </Action>
        <Action disabled={busy || installed || !checks?.ready} icon="next" onClick={next}>
          {t('requirements.next')}
        </Action>
      </Actions>
    </>
  );
}
