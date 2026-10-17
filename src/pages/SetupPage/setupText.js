import { useCallback } from 'react';
import { useI18n } from '../../i18n/I18nProvider.jsx';

export function useSetupText() {
  const { t } = useI18n();
  return useCallback((key, params) => {
    const value = t('setup.' + key, params);
    return value === 'setup.' + key ? key : value;
  }, [t]);
}
