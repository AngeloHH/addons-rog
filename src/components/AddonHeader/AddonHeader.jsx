import { Link } from 'react-router-dom';
import AddonTabs from '../AddonTabs/AddonTabs.jsx';
import Breadcrumbs from '../Breadcrumbs/Breadcrumbs.jsx';
import Icon from '../Icon/Icon.jsx';
import { expansionAccent, findExpansion } from '../../config/expansions.js';
import { formatDate } from '../../lib/date.js';
import { exploreFilterPath } from '../../router/paths.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './AddonHeader.module.scss';

const stars = [1, 2, 3, 4, 5];

export default function AddonHeader({ addon, tabs, onFavorite, onReport, busy }) {
  const { language, t } = useI18n();
  const filledStars = Math.round(addon.rating);
  const patch = findExpansion(addon.expansion)?.patch;
  const releaseDate = formatDate(addon.releasedAt, language);

  return (
    <section className={styles.header}>
      <div className={styles.glow} />
      <div className={styles.inner}>
        <Breadcrumbs
          overrides={{
            // Expansion and category breadcrumbs filter /explore through query parameters.
            2: {
              to: exploreFilterPath(language, 'expansion', addon.expansionId),
              label: findExpansion(addon.expansion)?.name,
            },
            3: {
              to: exploreFilterPath(language, 'category', addon.categoryId),
              label: addon.category,
            },
            4: { label: addon.name },
          }}
        />

        <div className={styles.main}>
          <div className={styles.identity}>
            <div className={styles.frame}>
              <img alt="" className={styles.icon} src={addon.image} />
            </div>

            <div className={styles.titleBlock}>
              <div className={styles.kicker}>
                <span className={styles.category}>{addon.category}</span>
                <span
                  className={styles.expansionBadge}
                  style={{ '--expansion-accent': expansionAccent(addon.expansion) }}
                >
                  {addon.expansion}
                </span>
                {patch && <span className={styles.patch}>{t('addon.compatible', { patch })}</span>}
              </div>

              <h1 className={styles.title}>{addon.name}</h1>
              <p className={styles.short}>{addon.description}</p>

              <div className={styles.stats}>
                <span className={styles.rating}>
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
                  <span className={styles.ratingValue}>{addon.rating}</span>{' '}
                  <span className={styles.votes}>{t('addon.votes', { count: addon.votes })}</span>
                </span>
                <span className={styles.downloads}>
                  <Icon name="download" size={14} />
                  {t('addon.downloadsCount', { count: addon.downloads })}
                </span>
                <span>
                  {t('addon.by')}{' '}
                  <Link
                    className={styles.author}
                    to={`/${language}/profile/${encodeURIComponent(addon.authorUsername || addon.author)}`}
                  >
                    {addon.author}
                  </Link>
                </span>
              </div>
            </div>
          </div>

          <div className={styles.actions}>
            <a className={styles.downloadButton} href={addon.downloadUrl}>
              <span className={styles.downloadLabel}>
                <Icon name="download" size={18} />
                {t('addon.downloadVersion', { version: addon.version })}
              </span>
              <span className={styles.downloadMeta}>
                {addon.fileType} · {addon.fileSize} · {releaseDate}
              </span>
            </a>

            <div className={styles.secondaryActions}>
              <button
                className={styles.secondaryButton}
                type="button"
                onClick={onFavorite}
                disabled={busy}
                aria-pressed={addon.viewer?.isFavorite}
              >
                <Icon name="heart" size={15} />
                {t('addon.favourite')}
              </button>
              <button
                className={styles.secondaryButton}
                type="button"
                onClick={onReport}
                disabled={busy}
              >
                <Icon name="flag" size={15} />
                {t('addon.report')}
              </button>
            </div>
          </div>
        </div>

        {tabs && <AddonTabs tabs={tabs} />}
      </div>
    </section>
  );
}
