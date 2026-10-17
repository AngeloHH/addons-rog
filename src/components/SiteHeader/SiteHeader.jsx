import { useSession } from '../../context/SessionContext.jsx';
import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './SiteHeader.module.scss';

export default function SiteHeader() {
  const { user } = useSession();
  const [menuOpen, setMenuOpen] = useState(false);
  const location = useLocation();
  const { language, t } = useI18n();
  const currentPath = location.pathname;
  const homePath = `/${language}`;

  const navItems = [
    { label: t('nav.home'), to: homePath, active: currentPath === homePath },
    {
      label: t('nav.explore'),
      to: `/${language}/explore`,
      active: currentPath === `/${language}/explore`,
    },
    {
      label: t('nav.install'),
      to: `/${language}/install`,
      active: currentPath === `/${language}/install`,
    },
    { label: t('nav.categories'), to: `/${language}#categories` },
    { label: t('nav.latest'), to: `/${language}#latest` },
  ];

  return (
    <>
      <header className={styles.header}>
        <div className={styles.inner}>
          <Link className={styles.brand} to={`/${language}`}>
            <img src="/assets/logo-rog.png" alt="RoG" />
            <div className={styles.brandText}>
              <span>{t('nav.brand')}</span>
              <span>{t('nav.tagline')}</span>
            </div>
          </Link>

          <nav className={styles.desktopNav} aria-label={t('nav.primary')}>
            {navItems.map((item) => (
              <Link
                className={item.active ? styles.activeLink : undefined}
                key={item.label}
                to={item.to}
                reloadDocument={item.to.includes('#')}
              >
                {item.label}
              </Link>
            ))}
            <Link className={styles.uploadButton} to={`/${language}/upload`}>
              <Icon name="upload" size={15} />
              {t('nav.upload')}
            </Link>
          </nav>

          <button
            className={styles.menuButton}
            type="button"
            onClick={() => setMenuOpen(true)}
            aria-label={t('nav.openMenu')}
          >
            <Icon name="menu" size={22} />
          </button>
        </div>
      </header>

      {menuOpen && (
        <div className={styles.mobileMenu}>
          <div className={styles.mobileTop}>
            <img src="/assets/logo-rog.png" alt="RoG" />
            <button
              type="button"
              onClick={() => setMenuOpen(false)}
              aria-label={t('nav.closeMenu')}
            >
              <Icon name="close" size={22} />
            </button>
          </div>

          <nav className={styles.mobileNav} aria-label={t('nav.primary')}>
            {navItems.map((item) => (
              <Link
                className={item.active ? styles.mobileActiveLink : undefined}
                key={item.label}
                to={item.to}
                reloadDocument={item.to.includes('#')}
                onClick={() => setMenuOpen(false)}
              >
                {item.label}
              </Link>
            ))}
            <div className={styles.mobileActions}>
              <Link
                className={styles.mobileUpload}
                to={`/${language}/upload`}
                onClick={() => setMenuOpen(false)}
              >
                {t('nav.upload')}
              </Link>
              <div className={styles.mobileAuth}>
                {user ? (
                  <Link to={`/${language}/profile`} onClick={() => setMenuOpen(false)}>
                    {user.name}
                  </Link>
                ) : (
                  <>
                    <Link to={`/${language}/login`} onClick={() => setMenuOpen(false)}>
                      {t('auth.signIn')}
                    </Link>
                    <Link to={`/${language}/register`} onClick={() => setMenuOpen(false)}>
                      {t('auth.createAccount')}
                    </Link>
                  </>
                )}
              </div>
            </div>
          </nav>
        </div>
      )}
    </>
  );
}
