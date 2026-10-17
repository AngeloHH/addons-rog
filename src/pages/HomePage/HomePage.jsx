import { useEffect } from 'react';
import { useApi } from '../../hooks/useApi.js';
import { presentAddon, presentExpansion, bytes } from '../../api/presenters.js';
import { ApiState } from '../../components/ApiState/ApiState.jsx';
import Requests from '../../components/Requests/Requests.jsx';
import { Link } from 'react-router-dom';
import AddonList from '../../components/AddonList/AddonList.jsx';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import CalloutPanel from '../../components/CalloutPanel/CalloutPanel.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import HomeCategories from '../../components/HomeCategories/HomeCategories.jsx';
import HomeHero from '../../components/HomeHero/HomeHero.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import ListSection from '../../components/ListSection/ListSection.jsx';
import PackGrid from '../../components/PackGrid/PackGrid.jsx';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import { findExpansion } from '../../config/expansions.js';

import { exploreFilterPath } from '../../router/paths.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './HomePage.module.scss';

export default function HomePage() {
  const { language, t } = useI18n();
  const home = useApi('/home');
  const taxonomy = useApi('/taxonomy');

  // Resolve an entry hash after the home sections finish loading.
  useEffect(() => {
    if (!home.loading && !taxonomy.loading && window.location.hash) {
      window.location.replace(window.location.hash);
    }
  }, [home.loading, taxonomy.loading]);
  const expansions = (taxonomy.data?.expansions || []).map(presentExpansion);
  const latestAddons = (home.data?.latest || []).map((item) => presentAddon(item, language));
  const updatedAddons = (home.data?.updated || []).map((item) => presentAddon(item, language));

  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className={`app-shell ${styles.content}`}>
        <HomeHero expansions={expansions} />
        <ApiState {...home} retry={home.reload} />
        <ApiState {...taxonomy} retry={taxonomy.reload} />
        <section className={styles.expansionsSection} id="expansions">
          <div className={styles.sectionHeader}>
            <div>
              <div className={styles.sectionKicker}>{t('homeExpansions.kicker')}</div>
              <h2 className={styles.sectionTitle}>{t('homeExpansions.title')}</h2>
            </div>
            <p className={styles.sectionIntro}>{t('homeExpansions.intro')}</p>
          </div>

          <ListSection
            items={expansions.map((expansion) => ({
              id: expansion.id,
              to: exploreFilterPath(language, 'expansion', expansion.id),
              mark: expansion.abbr,
              title: expansion.name,
              meta: t('homeExpansions.client', { patch: expansion.patch }),
              aside: t('homeExpansions.addons', { count: expansion.count }),
              action: { icon: 'access', label: expansion.name },
              tone: {
                accent: expansion.colorAccent,
                dot: expansion.colorDark,
                hover: expansion.colorDark,
                card: `linear-gradient(160deg, ${expansion.colorDark} 0%, #0f0c08 70%)`,
              },
            }))}
            primary={4}
          />
        </section>
        <HomeCategories taxonomy={taxonomy.data} />

        <section className={styles.latestSection} id="latest">
          <AddonList
            addons={latestAddons}
            kicker={t('homeLatest.kicker')}
            linkLabel={t('homeLatest.viewAll')}
            linkTo={`/${language}/explore`}
          />

          <div className={styles.updatedList}>
            <AddonList
              addons={updatedAddons}
              kicker={t('homeLatest.updatesKicker')}
              linkLabel={t('homeLatest.viewUpdates')}
              linkTo={`/${language}/explore?sort=updated`}
            />
          </div>
        </section>

        <section className={styles.packsSection} id="packs">
          <div className={styles.sectionHeader}>
            <div>
              <div className={styles.sectionKicker}>{t('packs.kicker')}</div>
              <h2 className={styles.sectionTitle}>{t('packs.title')}</h2>
            </div>
            <p className={styles.sectionIntro}>{t('packs.intro')}</p>
          </div>

          <PackGrid
            columns={{ sm: 1, md: 2, lg: 3 }}
            selectLabel={t('packs.chooseExpansion')}
            items={(home.data?.packs || []).map((pack) => ({
              id: pack.id,
              image: pack.imageUrl,
              title: pack.name,
              meta: t('packs.meta', {
                count: pack.addonCount,
                size: bytes(pack.availability?.[0]?.size),
              }),
              description: pack.description,
              options: (pack.availability || []).map((item) => ({
                id: item.expansionId,
                label: findExpansion(item.expansionId)?.name || item.expansionId,
                href: item.downloadUrl,
                meta: t('packs.meta', { count: item.addons.length, size: bytes(item.size) }),
              })),
              action: { label: t('packs.download') },
            }))}
          />
        </section>

        <section className={styles.ctaSection}>
          <CalloutPanel
            image="/assets/troll.png"
            subtitle={t('uploadCta.subtitle')}
            title={t('uploadCta.title')}
            text={t('uploadCta.text')}
            links={[
              { label: t('uploadCta.upload'), to: `/${language}/upload` },
              { label: t('uploadCta.request'), to: `/${language}#requests` },
            ]}
          />
        </section>

        <section className={styles.requestsSection} id="requests">
          <div className={styles.sectionHeader}>
            <div>
              <div className={styles.sectionKicker}>{t('requests.kicker')}</div>
              <h2 className={styles.sectionTitle}>{t('requests.title')}</h2>
            </div>
            <Link className={styles.requestsLink} to={`/${language}/requests`}>
              {t('requests.viewAll')}
              <Icon className={styles.requestsLinkArrow} name="arrowRight" size={14} />
            </Link>
          </div>

          <Requests compact />
        </section>
      </main>
      <Footer />
    </div>
  );
}
