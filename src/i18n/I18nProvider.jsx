import { createContext, useCallback, useContext, useEffect, useMemo } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { defaultLanguage, languages } from './languages.js';
import { translate } from './translate.js';

const I18nContext = createContext(null);
const languageCodes = languages.map((language) => language.code);

export function I18nProvider({ children }) {
  const { lang } = useParams();
  const location = useLocation();
  const navigate = useNavigate();
  const language = languageCodes.includes(lang) ? lang : defaultLanguage;

  useEffect(() => {
    window.localStorage.setItem('rog-language', language);
    document.documentElement.lang = language;
  }, [language]);

  const changeLanguage = useCallback((nextLanguage) => {
    if (!languageCodes.includes(nextLanguage)) return;

    const parts = location.pathname.split('/').filter(Boolean);

    if (languageCodes.includes(parts[0])) {
      parts[0] = nextLanguage;
    } else {
      parts.unshift(nextLanguage);
    }

    navigate(`/${parts.join('/')}${location.search}${location.hash}`);
  }, [location.hash, location.pathname, location.search, navigate]);

  const value = useMemo(() => {
    const activeLanguage = languages.find((item) => item.code === language) ?? languages[0];

    return {
      language,
      languages,
      activeLanguage,
      setLanguage: changeLanguage,
      t: (key, params) => translate(language, key, params),
    };
  }, [changeLanguage, language]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
  const context = useContext(I18nContext);

  if (!context) {
    throw new Error('useI18n must be used inside I18nProvider');
  }

  return context;
}
