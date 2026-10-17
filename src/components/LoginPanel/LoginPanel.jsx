import LoginForm from './LoginForm.jsx';
import RegisterForm from './RegisterForm.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './LoginPanel.module.scss';

export default function LoginPanel({ activeTab = 'login', onRegistered, onTabChange }) {
  const { t } = useI18n();

  function selectTab(tab) {
    onTabChange?.(tab);
  }

  return (
    <div className={styles.panel}>
      <div className={styles.tabs}>
        <a
          className={activeTab === 'login' ? styles.activeTab : undefined}
          href="#login"
          onClick={(event) => {
            event.preventDefault();
            selectTab('login');
          }}
        >
          {t('auth.signIn')}
        </a>
        <a
          className={activeTab === 'register' ? styles.activeTab : undefined}
          href="#signup"
          onClick={(event) => {
            event.preventDefault();
            selectTab('register');
          }}
        >
          {t('auth.createAccount')}
        </a>
      </div>

      {activeTab === 'register' ? (
        <RegisterForm onRegistered={onRegistered} onTabChange={selectTab} styles={styles} />
      ) : (
        <LoginForm onTabChange={selectTab} styles={styles} />
      )}
    </div>
  );
}
