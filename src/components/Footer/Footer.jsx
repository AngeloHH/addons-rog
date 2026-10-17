import { useState } from 'react';
import { siteMeta } from '../../config/siteMeta.js';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './Footer.module.scss';
import {Link} from "react-router-dom";

export default function Footer() {
  const [languageOpen, setLanguageOpen] = useState(false);
  const { activeLanguage, languages, setLanguage, t, language } = useI18n();
  const { authors, startYear, endYear } = siteMeta.copyright;

  function chooseLanguage(code) {
    setLanguage(code);
    setLanguageOpen(false);
  }

  return (
    <footer className={styles.footer}>
      <div className={styles.inner}>
        <div className={styles.credits}>
          <img className={styles.logo} src="/assets/logo-rog.png" alt="RoG" />
          <span className={styles.dot}>·</span>
          <span>
            {t('footer.copyright')}{' '}
            {authors.map((author, index) => (
              <span key={author.name}>
                {index > 0 && ' & '}
                <a href={author.url}>{author.name}</a>
              </span>
            ))}{' '}
            {t('footer.years', { startYear, endYear })}
          </span>
        </div>

        <div className={styles.links}>
          <Link to={`/${language}/contact`}>{t('footer.contact')}</Link>
          <Link to={`/${language}/rules`}>{t('footer.uploadRules')}</Link>
          <Link to={`/${language}/about`}>{t('footer.about')}</Link>
          <div className={styles.language}>
            <button
              className={styles.languageButton}
              type="button"
              onClick={() => setLanguageOpen((open) => !open)}
              aria-expanded={languageOpen}
              aria-haspopup="listbox"
            >
              <span>
                {t('footer.language')}: {activeLanguage.name}
              </span>
              <span className={styles.chevron} aria-hidden="true">
                <Icon name={languageOpen ? 'chevronDown' : 'chevronUp'} size={14} />
              </span>
            </button>

            {languageOpen && (
              <div className={styles.languageMenu} role="listbox" aria-label={t('footer.language')}>
                {languages.map((item) => (
                  <button
                    className={item.code === language ? styles.activeLanguage : undefined}
                    key={item.code}
                    type="button"
                    onClick={() => chooseLanguage(item.code)}
                    role="option"
                    aria-selected={item.code === language}
                  >
                    <span>{item.name}</span>
                    <span>{item.code.toUpperCase()}</span>
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </footer>
  );
}
