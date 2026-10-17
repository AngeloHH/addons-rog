import { useState } from 'react';
import { ApiState } from '../ApiState/ApiState.jsx';
import { registerAccount } from '../../api/authApi.js';
import { useApi } from '../../hooks/useApi.js';
import { useSearchParams } from 'react-router-dom';
import { useI18n } from '../../i18n/I18nProvider.jsx';

export default function RegisterForm({ onRegistered, onTabChange, styles }) {
  const [passwordVisible, setPasswordVisible] = useState(false);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [registerPassword, setRegisterPassword] = useState('');
  const [status, setStatus] = useState('idle');
  const [error, setError] = useState(null);
  const { t, language } = useI18n();

  const taxonomy = useApi('/taxonomy');
  const expansions = taxonomy.data?.expansions || [];
  const [params] = useSearchParams();
  const passwordType = passwordVisible ? 'text' : 'password';
  const passwordLabel = passwordVisible ? t('login.hidePassword') : t('login.showPassword');
  const strengthScore = Math.min(4, Math.floor(registerPassword.length / 3));
  const strengthBars = Array.from({ length: 4 }, (_, index) => index < strengthScore);
  const strengthLabel =
    registerPassword.length >= 8 ? t('register.passwordGood') : t('register.passwordWeak');
  const isSubmitting = status === 'submitting';

  async function handleSubmit(event) {
    event.preventDefault();
    setStatus('submitting');
    setError(null);

    const formData = new FormData(event.currentTarget);

    try {
      const result = await registerAccount({
        username: formData.get('username'),
        email: formData.get('email'),
        password: formData.get('password'),
        expansionId: formData.get('expansion') || null,
        termsAccepted,
        ...(params.get('invitationToken')
          ? { invitationToken: params.get('invitationToken') }
          : {}),
      });

      setStatus('success');
      onRegistered?.(result);
    } catch (failure) {
      setError(failure);
      setStatus('error');
    }
  }

  return (
    <form className={styles.form} id="signup" onSubmit={handleSubmit}>
      <label className={styles.field}>
        {t('register.usernameLabel')}
        <span className={styles.hint}>{t('register.usernameHint')}</span>
        <input name="username" type="text" placeholder={t('register.usernamePlaceholder')} />
      </label>

      <label className={styles.field}>
        {t('register.emailLabel')}
        <input name="email" type="email" placeholder={t('register.emailPlaceholder')} />
      </label>

      <label className={styles.field}>
        {t('login.passwordLabel')}
        <span className={styles.hint}>{t('register.passwordHint')}</span>
        <span className={styles.passwordField}>
          <input
            name="password"
            type={passwordType}
            value={registerPassword}
            onChange={(event) => setRegisterPassword(event.target.value)}
            placeholder="••••••••"
          />
          <button type="button" onClick={() => setPasswordVisible((visible) => !visible)}>
            {passwordLabel}
          </button>
        </span>
        <span className={styles.strengthBars}>
          {strengthBars.map((isFilled, index) => (
            <span
              className={isFilled ? styles.strengthBarFilled : styles.strengthBar}
              key={index}
            ></span>
          ))}
        </span>
        <span className={styles.strengthLabel}>{strengthLabel}</span>
      </label>

      <label className={styles.field}>
        {t('register.expansionLabel')}
        <span className={styles.hint}>{t('register.expansionHint')}</span>
        <select name="expansion" defaultValue="">
          <option value="">{t('register.expansionPlaceholder')}</option>
          {expansions.map((expansion) => (
            <option key={expansion.id} value={expansion.id}>
              {expansion.name}
            </option>
          ))}
        </select>
      </label>

      <label className={styles.terms} onClick={() => setTermsAccepted((value) => !value)}>
        <span className={termsAccepted ? styles.checkboxChecked : styles.checkbox}></span>
        <span>
          {t('register.acceptPrefix')}{' '}
          <a href={`/${language}/rules`} onClick={(event) => event.stopPropagation()}>
            {t('register.communityRules')}
          </a>{' '}
          {t('register.acceptMiddle')}{' '}
          <a href={`/${language}/rules#privacy`} onClick={(event) => event.stopPropagation()}>
            {t('register.privacyPolicy')}
          </a>
          .
        </span>
      </label>

      <button className={styles.submit} type="submit" disabled={isSubmitting}>
        {isSubmitting ? t('auth.submitting') : t('auth.createAccount')}
      </button>

      <ApiState error={error} />
      <p className={styles.switchText}>
        {t('register.alreadyHaveAccount')}{' '}
        <a
          href="#login"
          onClick={(event) => {
            event.preventDefault();
            onTabChange?.('login');
          }}
        >
          {t('auth.signIn')}
        </a>
      </p>
    </form>
  );
}
