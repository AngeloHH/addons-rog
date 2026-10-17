import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { ApiState } from '../ApiState/ApiState.jsx';
import { loginAccount } from '../../api/authApi.js';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';

export default function LoginForm({ onTabChange, styles }) {
  const [passwordVisible, setPasswordVisible] = useState(false);
  const [remember, setRemember] = useState(false);
  const [status, setStatus] = useState('idle');
  const { t, language } = useI18n();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [error, setError] = useState(null);

  const passwordType = passwordVisible ? 'text' : 'password';
  const passwordLabel = passwordVisible ? t('login.hidePassword') : t('login.showPassword');
  const isSubmitting = status === 'submitting';

  async function handleSubmit(event) {
    event.preventDefault();
    setStatus('submitting');
    setError(null);

    const formData = new FormData(event.currentTarget);

    try {
      await loginAccount({
        identifier: formData.get('identifier'),
        password: formData.get('password'),
        remember,
      });
      setStatus('success');
      navigate(params.get('invitationToken') ? `/${language}/register?${params}` : `/${language}`);
    } catch (failure) {
      if (failure.code === 'EMAIL_NOT_VERIFIED' && failure.details.verificationId)
        navigate(`/${language}/verify`, { state: failure.details });
      setError(failure);
      setStatus('error');
    }
  }

  return (
    <form className={styles.form} id="login" onSubmit={handleSubmit}>
      <label className={styles.field}>
        {t('login.identifierLabel')}
        <input name="identifier" type="text" placeholder={t('login.identifierPlaceholder')} />
      </label>

      <label className={styles.field}>
        <span className={styles.labelRow}>
          <span>{t('login.passwordLabel')}</span>
          <Link to={`/${language}/contact`}>{t('login.forgotPassword')}</Link>
        </span>
        <span className={styles.passwordField}>
          <input name="password" type={passwordType} placeholder="••••••••" />
          <button type="button" onClick={() => setPasswordVisible((visible) => !visible)}>
            {passwordLabel}
          </button>
        </span>
      </label>

      <label className={styles.remember} onClick={() => setRemember((value) => !value)}>
        <span className={remember ? styles.checkboxChecked : styles.checkbox}></span>
        <span>{t('login.remember')}</span>
      </label>

      <button className={styles.submit} type="submit" disabled={isSubmitting}>
        <Icon name="login" size={16} />
        {isSubmitting ? t('auth.submitting') : t('auth.signIn')}
      </button>

      <ApiState error={error} />
      <p className={styles.switchText}>
        {t('login.noAccount')}{' '}
        <a
          href="#signup"
          onClick={(event) => {
            event.preventDefault();
            onTabChange?.('register');
          }}
        >
          {t('login.createInMinute')}
        </a>
      </p>
    </form>
  );
}
