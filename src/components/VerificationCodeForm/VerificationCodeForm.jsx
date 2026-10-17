import { Link } from 'react-router-dom';
import { ApiState } from '../ApiState/ApiState.jsx';
import { useAction } from '../../hooks/useApi.js';
import { useMemo, useState } from 'react';
import { verifyAccount, resendVerification } from '../../api/authApi.js';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './VerificationCodeForm.module.scss';

export default function VerificationCodeForm({
  maskedEmail = 'your email',
  verificationId,
  onVerified,
  onResent,
}) {

  const [changingEmail, setChangingEmail] = useState(false);
  const [email, setEmail] = useState('');
  const [shownEmail, setShownEmail] = useState(maskedEmail);
  const [currentId, setCurrentId] = useState(verificationId);
  const [code, setCode] = useState(['', '', '', '', '', '']);
  const [status, setStatus] = useState('idle');
  const [error, setError] = useState(null);
  const resend = useAction();
  const { language, t } = useI18n();
  const joinedCode = useMemo(() => code.join(''), [code]);
  const isSubmitting = status === 'submitting';

  function updateCode(index, value) {
    const next = [...code];
    next[index] = value.slice(-1).toUpperCase();
    setCode(next);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setStatus('submitting');
    setError(null);

    try {
      const result = await verifyAccount({ verificationId: currentId, code: joinedCode });
      setStatus('success');
      onVerified?.(result);
    } catch (error) {
      setError(error);
      setStatus('error');
    }
  }

  return (
    <form className={styles.form} id="verify" onSubmit={handleSubmit}>
      <span className={styles.icon}>
        <Icon name="mail" size={24} />
      </span>

      <div className={styles.copy}>
        <h2>{t('verify.title')}</h2>
        <p>
          {t('verify.messagePrefix')} <strong>{shownEmail}</strong>. {t('verify.messageSuffix')}
        </p>
      </div>

      <div className={styles.codeBoxes}>
        {code.map((value, index) => (
          <input
            aria-label={t('verify.codeCharacter')}
            key={index}
            maxLength="1"
            type="text"
            value={value}
            onChange={(event) => updateCode(index, event.target.value)}
          />
        ))}
      </div>

      <button
        className={styles.submit}
        type="submit"
        disabled={isSubmitting || resend.busy || !currentId || joinedCode.length !== 6}
      >
        {isSubmitting ? t('auth.submitting') : t('verify.confirm')}
      </button>

      <ApiState error={error || resend.error} loading={resend.busy} done={resend.done} />
      {changingEmail && (
        <label>
          {t("ui.resendEmailLabel")}
          <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
        </label>
      )}
      {!currentId && (
        <Link to={`/${language}/login`}>
          {t("ui.signInForNewCode")}
        </Link>
      )}
      <p className={styles.help}>
        {t('verify.help')}{' '}
        <button
          type="button"
          disabled={isSubmitting || resend.busy || !currentId}
          onClick={() =>
            resend.run(async () => {
              const result = await resendVerification({
                verificationId: currentId,
                ...(changingEmail ? { email } : {}),
              });
              onResent?.(result);
              setCurrentId(result.verificationId);
              setShownEmail(result.maskedEmail);
              setChangingEmail(false);
              setCode(['', '', '', '', '', '']);
            })
          }
        >
          {t('verify.resend')}
        </button>
        <br />
        <button type="button" onClick={() => setChangingEmail((value) => !value)}>
          {t('verify.changeEmail')}
        </button>
      </p>
    </form>
  );
}
