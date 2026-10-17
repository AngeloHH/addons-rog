import { Link } from 'react-router-dom';
import Icon from '../../../components/Icon/Icon.jsx';
import { useI18n } from '../../../i18n/I18nProvider.jsx';
import styles from '../admin.module.scss';

/**
 * Link a dashboard summary to its full administration section.
 *
 * section  Section ID as used in the URL.
 * label    Visible link text.
 */
export default function SectionLink({ label, section }) {
  const { language } = useI18n();

  return (
    <Link className={styles.panelLink} to={`/${language}/admin-pannel/${section}`}>
      {label}
      <Icon name="arrowRight" size={13} />
    </Link>
  );
}
