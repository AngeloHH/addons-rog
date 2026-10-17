import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { exploreFilterGroups, ratingFilterOptions } from '../../config/exploreFilters.js';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './ExploreFilters.module.scss';

const filterParamKeys = new Set([
  'author',
  'category',
  'classes',
  'expansion',
  'professions',
  'rating',
  'recent',
  'role',
]);

function readSet(searchParams, key) {
  return new Set((searchParams.get(key) ?? '').split(',').filter(Boolean));
}

function writeSet(nextParams, key, selectedValues) {
  if (selectedValues.size) {
    nextParams.set(key, Array.from(selectedValues).join(','));
  } else {
    nextParams.delete(key);
  }
}

export default function ExploreFilters({ taxonomy, facets }) {
  const { t } = useI18n();
  const [searchParams, setSearchParams] = useSearchParams();
  const [openGroups, setOpenGroups] = useState(() =>
    Object.fromEntries(exploreFilterGroups.map((group) => [group.id, group.defaultOpen])),
  );
  const [author, setAuthor] = useState(searchParams.get('author') ?? '');

  useEffect(() => {
    setAuthor(searchParams.get('author') ?? '');
  }, [searchParams]);

  function updateParams(updater) {
    setSearchParams((current) => {
      const nextParams = new URLSearchParams(current);
      updater(nextParams);
      nextParams.delete('page');
      return nextParams;
    });
  }

  function clearAll() {
    setAuthor('');
    updateParams((nextParams) => {
      nextParams.delete('q');

      for (const key of filterParamKeys) {
        nextParams.delete(key);
      }
    });
  }

  function toggleRecent() {
    updateParams((nextParams) => {
      if (nextParams.get('recent') === '1') nextParams.delete('recent');
      else nextParams.set('recent', '1');
    });
  }

  function toggleOption(groupId, optionId) {
    updateParams((nextParams) => {
      const selectedValues = readSet(nextParams, groupId);

      if (selectedValues.has(optionId)) selectedValues.delete(optionId);
      else selectedValues.add(optionId);

      writeSet(nextParams, groupId, selectedValues);
    });
  }

  function setRating(rating) {
    updateParams((nextParams) => {
      if (rating) nextParams.set('rating', String(rating));
      else nextParams.delete('rating');
    });
  }

  function setAuthorFilter(value) {
    setAuthor(value);
    updateParams((nextParams) => {
      if (value.trim()) nextParams.set('author', value);
      else nextParams.delete('author');
    });
  }

  const recent = searchParams.get('recent') === '1';
  const selectedRating = Number(searchParams.get('rating') ?? 0);

  return (
    <aside className={styles.filters}>
      <div className={styles.heading}>
        <span className={styles.headingLabel}>
          <Icon name="sliders" size={14} />
          {t('explore.filters.title')}
        </span>
        <button className={styles.clearButton} type="button" onClick={clearAll}>
          {t('explore.filters.clearAll')}
        </button>
      </div>

      <label className={styles.recent}>
        <span className={styles.recentText}>
          <span className={styles.recentTitle}>{t('explore.filters.recentlyUpdated')}</span>
          <span className={styles.recentHint}>{t('explore.filters.last30Days')}</span>
        </span>
        <button
          aria-label={t('explore.filters.updatedOnly')}
          className={styles.switch}
          data-active={recent}
          type="button"
          onClick={toggleRecent}
        >
          <span></span>
        </button>
      </label>

      {exploreFilterGroups.map((group) => {
        const selectedValues = readSet(searchParams, group.id);
        const isOpen = openGroups[group.id];

        return (
          <div className={styles.group} key={group.id}>
            <button
              className={styles.groupButton}
              type="button"
              onClick={() => setOpenGroups((current) => ({ ...current, [group.id]: !isOpen }))}
            >
              <span>{t(group.titleKey)}</span>
              <span className={styles.groupMeta}>
                <span>
                  {selectedValues.size
                    ? t('explore.filters.activeBadge', { count: selectedValues.size })
                    : ''}
                </span>
                <span className={styles.groupChevron}>
                  <Icon name={isOpen ? 'chevronUp' : 'chevronDown'} size={14} />
                </span>
              </span>
            </button>

            {isOpen && (
              <div className={styles.options}>
                {(group.id === 'expansion'
                  ? taxonomy?.expansions || []
                  : taxonomy?.groups?.find((item) => item.id === group.id)?.items || []
                ).map((item) => {
                  const option = {
                    id: item.id,
                    label: item.name,
                    count: facets?.[group.id]?.[item.id] ?? item.addonCount,
                  };
                  const selected = selectedValues.has(option.id);

                  return (
                    <button
                      type="button"
                      aria-pressed={selected}
                      className={styles.option}
                      key={option.id}
                      onClick={() => toggleOption(group.id, option.id)}
                    >
                      <span className={styles.checkbox} data-active={selected}></span>
                      <span className={styles.optionLabel}>{option.label}</span>
                      <span className={styles.optionCount}>{option.count}</span>
                    </button>
                  );
                })}
              </div>
            )}
          </div>
        );
      })}

      <div className={styles.rating}>
        <span className={styles.sectionTitle}>{t('explore.filters.minimumRating')}</span>
        <div className={styles.ratingOptions}>
          {ratingFilterOptions.map((rating) => {
            const active = selectedRating === rating;

            return (
              <button
                className={styles.ratingButton}
                data-active={active}
                key={rating}
                type="button"
                onClick={() => setRating(rating)}
              >
                {rating > 0 && <Icon fill="currentColor" name="star" size={12} />}
                {rating ? `${rating}+` : t('explore.filters.allRatings')}
              </button>
            );
          })}
        </div>
      </div>

      <div className={styles.author}>
        <span className={styles.sectionTitle}>{t('explore.filters.author')}</span>
        <input
          type="text"
          value={author}
          placeholder={t('explore.filters.authorPlaceholder')}
          onChange={(event) => setAuthorFilter(event.target.value)}
        />
      </div>
    </aside>
  );
}
