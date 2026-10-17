/**
 * Addons ROG - Addon Card
 *
 * Render addon cards with an optional compact layout.
 *
 * compact      Frames the icon, moves the expansion badge into the metadata row,
 *              and hides the description and download button.
 * description  Omit the paragraph when absent.
 * updated      Omit the date when absent.
 */

import { Link } from 'react-router-dom';
import { expansionAccent } from '../../config/expansions.js';
import Icon from '../Icon/Icon.jsx';
import { addonPath } from '../../router/paths.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './AddonCard.module.scss';

const stars = [1, 2, 3, 4, 5];

export default function AddonCard({ addon, compact = false }) {
  const { language, t } = useI18n();
  const filledStars = Math.round(addon.rating);

  const badge = (
    <span
      className={styles.expansionBadge}
      style={{ '--expansion-accent': expansionAccent(addon.expansion) }}
    >
      {addon.expansion}
    </span>
  );

  const rating = addon.rating != null && (
    <span className={styles.rating}>
      {compact ? (
        <Icon fill="currentColor" name="star" size={12} />
      ) : (
        <span className={styles.stars}>
          {stars.map((star) => (
            <Icon
              fill={star <= filledStars ? 'currentColor' : 'none'}
              key={star}
              name="star"
              size={13}
            />
          ))}
        </span>
      )}
      <span>{addon.rating}</span>
    </span>
  );

  const downloads = addon.downloads && (
    <span className={styles.downloads}>
      <Icon name="download" size={compact ? 12 : 13} />
      {addon.downloads}
    </span>
  );

  return (
    <article className={styles.card} data-compact={compact}>
      {addon.pinned && (
        <span className={styles.pin}>
          <Icon name="flag" size={13} />
        </span>
      )}

      <div className={styles.header}>
        <div className={styles.identity}>
          <span className={styles.iconFrame}>
            <img alt="" className={styles.icon} src={addon.image} />
          </span>

          <div className={styles.titleBlock}>
            <Link className={styles.name} to={addonPath(language, addon)}>
              {addon.name}
            </Link>
            <div className={styles.meta}>
              {addon.category} ·{' '}
              {addon.previousVersion ? (
                <>
                  <span className={styles.previousVersion}>v{addon.previousVersion}</span>
                  <Icon className={styles.versionArrow} name="arrowRight" size={11} />
                  <span className={styles.newVersion}>v{addon.version}</span>
                </>
              ) : (
                <>v{addon.version}</>
              )}
            </div>
          </div>
        </div>

        {!compact && badge}
      </div>

      {addon.description && <p className={styles.description}>{addon.description}</p>}

      <div className={styles.footer}>
        <div className={styles.stats}>
          {compact && badge}
          {compact ? (
            <>
              {downloads}
              {rating}
            </>
          ) : (
            <>
              {rating}
              {downloads}
              {addon.author && (
                <span>
                  {t('addon.by')}{' '}
                  <Link
                    className={styles.author}
                    to={`/${language}/profile/${encodeURIComponent(addon.authorUsername || addon.author)}`}
                  >
                    {addon.author}
                  </Link>
                </span>
              )}
            </>
          )}
          {addon.updated && <span className={styles.updated}>{addon.updated}</span>}
        </div>

        {!compact && addon.downloadUrl && (
          <a className={styles.downloadButton} href={addon.downloadUrl}>
            <Icon name="download" size={14} />
            {t('addon.download')}
          </a>
        )}
      </div>
    </article>
  );
}
