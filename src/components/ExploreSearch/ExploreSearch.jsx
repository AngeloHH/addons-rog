import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { popularAddonSearches } from '../../config/homeHero.js';
import Icon from '../Icon/Icon.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './ExploreSearch.module.scss';

export default function ExploreSearch() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [query, setQuery] = useState(searchParams.get('q') ?? '');
  const { t } = useI18n();
  const trimmedQuery = query.trim();
  const hasQuery = Boolean(searchParams.get('q'));

  useEffect(() => {
    setQuery(searchParams.get('q') ?? '');
  }, [searchParams]);

  function submitSearch(event) {
    event.preventDefault();

    setSearchParams((current) => {
      const nextParams = new URLSearchParams(current);
      nextParams.delete('page');

      if (trimmedQuery) {
        nextParams.set('q', trimmedQuery);
      } else {
        nextParams.delete('q');
      }

      return nextParams;
    });
  }

  function applyPopular(label) {
    setSearchParams((current) => {
      const nextParams = new URLSearchParams(current);
      nextParams.delete('page');
      nextParams.set('q', label);
      return nextParams;
    });
  }

  return (
    <section className={styles.searchSection} data-screen-label="Search">
      <div className={styles.inner}>
        <div className={styles.kicker}>
          <span></span>
          <span>{t('explore.kicker')}</span>
          <span></span>
        </div>

        <h1>
          {hasQuery
            ? t('explore.resultsHeadline', { query: searchParams.get('q') })
            : t('explore.emptyHeadline')}
        </h1>

        <form className={styles.searchBox} onSubmit={submitSearch}>
          <input
            type="text"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder={t('explore.searchPlaceholder')}
          />
          <button type="submit">
            <Icon name="search" size={16} />
            {t('explore.search')}
          </button>
        </form>

        <div className={styles.popular}>
          <span>{t('explore.popular')}</span>
          {popularAddonSearches.map((label) => (
            <button key={label} type="button" onClick={() => applyPopular(label)}>
              {label}
            </button>
          ))}
        </div>
      </div>
    </section>
  );
}
