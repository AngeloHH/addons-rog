import { useSession } from '../../context/SessionContext.jsx';
import { useAction } from '../../hooks/useApi.js';
import { ApiState } from '../ApiState/ApiState.jsx';
import { Link } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './AuthBar.module.scss';

export default function AuthBar({ variant = 'accountLinks', backHref, backLabel }) {
  const { language, t } = useI18n();
  const session = useSession();
  const action = useAction();

  const resolvedBackHref = backHref ?? `/${language}`;

  return (
    <div className={styles.authBar}>
      <div className={styles.inner}>
        <span className={styles.community}>{t('auth.community')}</span>

        {variant === 'back' ? (
          <Link className={styles.backLink} to={resolvedBackHref}>
            <span className={styles.backIcon} aria-hidden="true">
              <Icon name="arrowLeft" size={15} />
            </span>
            {backLabel ?? t('auth.backToHome')}
          </Link>
        ) : (
          <nav className={styles.accountLinks} aria-label="Account">
            {session.user ? (
              <>
                <Link to={`/${language}/profile`}>{session.user.name}</Link>
                {session.staff && (
                  <Link to={`/${language}/admin-pannel`}>
                    {t("ui.administration")}
                  </Link>
                )}
                <button
                  type="button"
                  disabled={action.busy}
                  onClick={() => action.run(session.logout)}
                >
                  {t("admin.signOut")}
                </button>
                <ApiState error={action.error} />
              </>
            ) : (
              !session.loading && (
                <>
                  <Link className={styles.signIn} to={`/${language}/login`}>
                    {t('auth.signIn')}
                  </Link>
                  <span className={styles.separator}>|</span>
                  <Link className={styles.createAccount} to={`/${language}/register`}>
                    {t('auth.createAccount')}
                  </Link>
                </>
              )
            )}
          </nav>
        )}
      </div>
    </div>
  );
}
