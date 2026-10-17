import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import AddonRow from '../../components/AddonRow/AddonRow.jsx';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import Motes from '../../components/Motes/Motes.jsx';
import Pill from '../../components/Pill/Pill.jsx';
import { useApi } from '../../hooks/useApi.js';
import { queryPath } from '../../api/client.js';
import { presentAddon } from '../../api/presenters.js';
import { ApiState } from '../../components/ApiState/ApiState.jsx';
import { explorePath } from '../../router/paths.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './NotFoundPage.module.scss';

export default function NotFoundPage() {
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const { language, t } = useI18n();
  const [query, setQuery] = useState('');
  const term = pathname.split('/').filter(Boolean).at(-1)?.replace(/-/g, ' ') || '';
  const matches = useApi(queryPath('/addons', { q: term, pageSize: 3, sort: 'downloads' }));
  const popular = useApi(
    matches.data && matches.data.items.length < 3 ? '/addons?sort=downloads&pageSize=3' : null,
  );
  const suggestions = [
    ...new Map(
      [...(matches.data?.items || []), ...(popular.data?.items || [])].map((item) => [
        item.id,
        item,
      ]),
    ).values(),
  ]
    .slice(0, 3)
    .map((item) => presentAddon(item, language));

  function search(event) {
    event.preventDefault();

    const trimmed = query.trim();
    navigate(
      trimmed ? `${explorePath(language)}?q=${encodeURIComponent(trimmed)}` : explorePath(language),
    );
  }

  return (
    <div className="site-frame">
      <AuthBar variant="back" />
      <main className={`app-shell ${styles.main}`}>
        <section className={styles.notFound}>
          <img alt="" className={styles.background} src="/assets/slide2.png" />
          <div className={styles.radial} />
          <div className={styles.fade} />
          <Motes />

          <div className={styles.inner}>
            <div className={styles.column}>
              <div className={styles.kicker}>
                <span className={styles.kickerLine} />
                <span>{t('notFound.kicker')}</span>
              </div>

              <div className={styles.headline}>
                <span className={styles.code}>404</span>
                <span className={styles.headlineText}>
                  <span className={styles.title}>{t('notFound.title')}</span>
                </span>
              </div>

              <p className={styles.text}>
                {t('notFound.textBefore')} <span className={styles.path}>{pathname}</span>{' '}
                {t('notFound.textAfter')}
              </p>

              <form className={styles.form} onSubmit={search}>
                <span className={styles.searchIcon}>
                  <Icon name="search" size={18} />
                </span>
                <input
                  className={styles.input}
                  placeholder={t('notFound.searchPlaceholder')}
                  type="text"
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                />
                <button className={styles.searchButton} type="submit">
                  <Icon name="search" size={16} />
                  {t('notFound.search')}
                </button>
              </form>

              <div className={styles.actions}>
                <Link className={styles.action} to={`/${language}`}>
                  <Icon name="arrowLeft" size={15} />
                  {t('notFound.backHome')}
                </Link>
                <Link className={styles.action} to={`/${language}/requests`}>
                  <Icon name="mail" size={15} />
                  {t('notFound.requestAddon')}
                </Link>
                <Link className={styles.action} to={`/${language}/contact`}>
                  <Icon name="flag" size={15} />
                  {t('notFound.reportLink')}
                </Link>
              </div>
            </div>

            <aside className={styles.panel}>
              <div className={styles.panelHeader}>
                <span className={styles.panelTitle}>{t('notFound.didYouMean')}</span>
                <span className={styles.panelNote}>{t('notFound.closestMatches')}</span>
              </div>

              <div className={styles.suggestions}>
                <ApiState
                  loading={matches.loading || popular.loading}
                  error={matches.error || popular.error}
                  retry={() => {
                    matches.reload();
                    popular.reload();
                  }}
                  empty={!matches.loading && !popular.loading && suggestions.length === 0}
                />
                {/* Omit downloadUrl so suggestion rows leave out the download button. */}
                {suggestions.map((addon) => (
                  <AddonRow
                    addon={{
                      slug: addon.slug,
                      name: addon.name,
                      image: addon.image,
                      category: addon.category,
                      categoryId: addon.categoryId,
                      expansion: addon.expansion,
                      expansionId: addon.expansionId,
                      downloads: addon.downloads,
                    }}
                    key={addon.slug}
                  />
                ))}
              </div>

              <div className={styles.panelFooter}>
                <span>{t('notFound.portal')}</span>
                <span className={styles.portals}>
                  <Pill to={explorePath(language)}>{t('notFound.portalExplore')}</Pill>
                  <Pill to={`/${language}#packs`}>{t('notFound.portalPacks')}</Pill>
                  <Pill to={`/${language}/docs/install`}>{t('notFound.portalInstall')}</Pill>
                </span>
              </div>
            </aside>
          </div>
        </section>
      </main>
      <Footer />
    </div>
  );
}
