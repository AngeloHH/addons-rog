import { Link } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './AuthSuccess.module.scss';

export default function AuthSuccess() {
  const { language, t } = useI18n();

  return (
    <div className={styles.success}>
      <span className={styles.icon}>
        <Icon name="check" size={28} />
      </span>
      <h2>{t('done.title')}</h2>
      <p>{t('done.message')}</p>
      <Link to={`/${language}`}>{t('done.goHome')}</Link>
    </div>
  );
}
