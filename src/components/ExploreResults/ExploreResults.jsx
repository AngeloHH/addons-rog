import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { presentAddon } from '../../api/presenters.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import { ApiState } from '../ApiState/ApiState.jsx';
import Pagination from '../Pagination/Pagination.jsx';
import { exploreFilterGroups } from '../../config/exploreFilters.js';
import AddonCard from '../AddonCard/AddonCard.jsx';
import AddonRow from '../AddonRow/AddonRow.jsx';
import Icon from '../Icon/Icon.jsx';
import styles from './ExploreResults.module.scss';

const pageSize = 6;
const defaultSort = 'downloads';
const sortOptions = new Set(['downloads', 'rating', 'recent', 'updated', 'az']);

function countActiveFilters(searchParams) {
  let count = 0;

  for (const group of exploreFilterGroups) {
    count += (searchParams.get(group.id) ?? '').split(',').filter(Boolean).length;
  }

  if (searchParams.get('recent') === '1') count += 1;
  if (searchParams.get('rating')) count += 1;
  if (searchParams.get('author')) count += 1;

  return count;
}

export default function ExploreResults({ filtersOpen, onToggleFilters, resource }) {
  const [searchParams, setSearchParams] = useSearchParams();
  const [view, setView] = useState('grid');
  const { language } = useI18n();
  const query = searchParams.get('q') ?? '';
  const sortParam = searchParams.get('sort');
  const sort = sortOptions.has(sortParam) ? sortParam : defaultSort;
  const activeFilterCount = useMemo(() => countActiveFilters(searchParams), [searchParams]);
  const filtersLabel = filtersOpen
    ? 'Hide filters'
    : `Filters${activeFilterCount ? ` · ${activeFilterCount}` : ''}`;
  const shownAddons = (resource.data?.items || []).map((item) => presentAddon(item, language));
  const totalFound = resource.data?.page.total ?? '—';

  function setSortParam(nextSort) {
    setSearchParams((current) => {
      const nextParams = new URLSearchParams(current);
      nextParams.delete('page');

      if (nextSort === defaultSort) {
        nextParams.delete('sort');
      } else {
        nextParams.set('sort', nextSort);
      }

      return nextParams;
    });
  }

  return (
    <div className={styles.results}>
      <div className={styles.toolbar}>
        <div className={styles.countBlock}>
          <span className={styles.foundLabel}>
            <span className={styles.total}>{totalFound}</span>
            <span>{query ? `match "${query}"` : 'addons found'}</span>
          </span>
        </div>

        <div className={styles.tools}>
          <label className={styles.sort}>
            <span>
              <Icon name="sort" size={14} />
              Sort
            </span>
            <select value={sort} onChange={(event) => setSortParam(event.target.value)}>
              <option value="downloads">Most downloaded</option>
              <option value="rating">Top rated</option>
              <option value="recent">Newest</option>
              <option value="updated">Recently updated</option>
              <option value="az">A-Z</option>
            </select>
          </label>

          <button
            className={styles.filtersButton}
            data-m-show
            type="button"
            onClick={onToggleFilters}
          >
            <Icon name="filter" size={13} />
            {filtersLabel}
          </button>

          <div className={styles.viewButtons} data-m-hide>
            <button
              aria-label="Cards"
              data-active={view === 'grid'}
              type="button"
              onClick={() => setView('grid')}
            >
              <Icon name="grid" size={16} />
            </button>
            <button
              aria-label="List"
              data-active={view === 'list'}
              type="button"
              onClick={() => setView('list')}
            >
              <Icon name="list" size={16} />
            </button>
          </div>
        </div>
      </div>

      <ApiState {...resource} retry={resource.reload} empty={resource.data?.items.length === 0} />
      {view === 'list' ? (
        <div className={styles.rowsList}>
          {/* AddonRow shows a download button only when downloadUrl is available. */}
          {shownAddons.map((addon) => (
            <AddonRow addon={addon} key={addon.name} />
          ))}
        </div>
      ) : (
        <div className={styles.cardsGrid}>
          {shownAddons.map((addon) => (
            <AddonCard addon={addon} key={addon.name} />
          ))}
        </div>
      )}

      <Pagination
        page={resource.data?.page}
        disabled={resource.loading}
        onChange={(page) =>
          setSearchParams((current) => {
            const next = new URLSearchParams(current);
            next.set('page', String(page));
            return next;
          })
        }
      />
    </div>
  );
}
