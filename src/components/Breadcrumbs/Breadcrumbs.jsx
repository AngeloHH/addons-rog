import { Fragment } from 'react';
import { Link, useLocation } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './Breadcrumbs.module.scss';

/**
 * Translate known navigation segments.
 * Keep unknown segments unchanged until the view supplies a label.
 */
function segmentLabel(segment, t) {
  const key = `nav.${segment}`;
  const translated = t(key);
  return translated === key ? segment : translated;
}

export default function Breadcrumbs({ overrides = {} }) {
  const { pathname } = useLocation();
  const { language, t } = useI18n();

  const segments = pathname.split('/').filter(Boolean).slice(1);
  const trail = [{ label: t('nav.home'), to: `/${language}` }];

  let path = `/${language}`;

  for (const segment of segments) {
    path += `/${segment}`;
    trail.push({ label: segmentLabel(segment, t), to: path });
  }

  const items = trail.map((item, index) => ({ ...item, ...overrides[index] }));
  const last = items.length - 1;
  items[last] = { ...items[last], to: undefined };

  return (
    <nav aria-label={t('breadcrumbs.label')} className={styles.breadcrumbs}>
      {items.map((item, index) => (
        <Fragment key={item.to ?? item.label}>
          {index > 0 && (
            <span className={styles.separator}>
              <Icon name="chevronRight" size={13} />
            </span>
          )}
          {item.to ? (
            <Link className={styles.link} to={item.to}>
              {item.label}
            </Link>
          ) : (
            <span className={styles.current} aria-current="page">
              {item.label}
            </span>
          )}
        </Fragment>
      ))}
    </nav>
  );
}
