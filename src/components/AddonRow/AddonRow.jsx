/**
 * Addons ROG - Addon Row
 *
 * Render only the cells supported by the addon data.
 * Missing values leave no empty columns; downloadUrl enables the download button.
 */

import { Link } from 'react-router-dom';
import { expansionAccent } from '../../config/expansions.js';
import Icon from '../Icon/Icon.jsx';
import { addonPath } from '../../router/paths.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './AddonRow.module.scss';

export default function AddonRow({ addon }) {
  const { language, t } = useI18n();

  return (
    <article className={styles.row}>
      {addon.image && <img alt="" className={styles.icon} src={addon.image} />}

      <div className={styles.titleBlock}>
        <Link className={styles.name} to={addonPath(language, addon)}>
          {addon.name}
        </Link>
        {(addon.category || addon.author) && (
          <span className={styles.meta}>
            {addon.category}
            {addon.category && addon.author ? ` · ${t('addon.by')} ` : ''}
            {addon.author}
          </span>
        )}
      </div>

      {addon.version && (
        <span className={styles.version}>
          v{addon.version}
          {addon.updatedLabel ? ` · ${addon.updatedLabel}` : ''}
        </span>
      )}

      {addon.expansion && (
        <span
          className={styles.expansionBadge}
          style={{ '--expansion-accent': expansionAccent(addon.expansion) }}
        >
          {addon.expansion}
        </span>
      )}

      {(addon.rating || addon.downloads) && (
        <span className={styles.stats}>
          {addon.rating && (
            <>
              <Icon fill="currentColor" name="star" size={13} />
              {addon.rating}
            </>
          )}
          {addon.rating && addon.downloads && <span className={styles.statsSeparator}>·</span>}
          {addon.downloads && (
            <span className={styles.downloads}>
              <Icon name="download" size={12} />
              {addon.downloads}
            </span>
          )}
        </span>
      )}

      {addon.downloadUrl && (
        <a className={styles.downloadButton} href={addon.downloadUrl}>
          <Icon name="download" size={14} />
          {t('addon.download')}
        </a>
      )}
    </article>
  );
}
