import { useSearchParams } from 'react-router-dom';
import { useApi } from '../../hooks/useApi.js';
import { useState } from 'react';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import ExploreFilters from '../../components/ExploreFilters/ExploreFilters.jsx';
import ExploreResults from '../../components/ExploreResults/ExploreResults.jsx';
import ExploreSearch from '../../components/ExploreSearch/ExploreSearch.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import styles from './ExplorePage.module.scss';

export default function ExplorePage() {
  const [params] = useSearchParams();
  const query = new URLSearchParams(params);
  query.set('pageSize', '12');
  const resource = useApi(`/addons?${query}`);
  const taxonomy = useApi('/taxonomy');
  const [filtersOpen, setFiltersOpen] = useState(false);

  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className="app-shell">
        <ExploreSearch />
        <section className={styles.resultsShell} data-screen-label="Results">
          <div
            className={styles.filtersColumn}
            data-m-collapsible
            data-m-sticky-off
            data-open={filtersOpen ? 'true' : 'false'}
          >
            <ExploreFilters taxonomy={taxonomy.data} facets={resource.data?.facets} />
          </div>
          <ExploreResults
            resource={resource}
            filtersOpen={filtersOpen}
            onToggleFilters={() => setFiltersOpen((open) => !open)}
          />
        </section>
      </main>
      <Footer />
    </div>
  );
}
