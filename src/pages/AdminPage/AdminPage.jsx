import { useSession } from '../../context/SessionContext.jsx';
import { useApi, useAction } from '../../hooks/useApi.js';
import { ApiState, RequireAccount } from '../../components/ApiState/ApiState.jsx';
import { useState } from 'react';
import { Link, Navigate, useParams } from 'react-router-dom';
import EmptyState from '../../components/EmptyState/EmptyState.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import Panel from '../../components/Panel/Panel.jsx';
import SegmentedControl from '../../components/SegmentedControl/SegmentedControl.jsx';
import StatTiles from '../../components/StatTiles/StatTiles.jsx';
import { adminKpis, adminNav, adminRanges } from '../../config/admin.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import NotFoundPage from '../NotFoundPage/NotFoundPage.jsx';
import AccountsPanel from './panels/AccountsPanel.jsx';
import ActivityPanel from './panels/ActivityPanel.jsx';
import DownloadsPanel from './panels/DownloadsPanel.jsx';
import QueuePanel from './panels/QueuePanel.jsx';
import ReportsPanel from './panels/ReportsPanel.jsx';
import RequestsPanel from './panels/RequestsPanel.jsx';
import StatsSection from './panels/StatsSection.jsx';
import shared from './admin.module.scss';
import styles from './AdminPage.module.scss';

/**
 * Format the header's long date in the selected language.
 */
function today(language) {
  return new Intl.DateTimeFormat(language, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date());
}

/**
 * Render the administration sidebar, header, and section selected by the URL.
 * Keep sections in panels/ so the dashboard can reuse their summary views.
 */
export default function AdminPage() {
  return (
    <RequireAccount staff>
      <AdminContent />
    </RequireAccount>
  );
}

function AdminContent() {
  const { language, t } = useI18n();
  const { section } = useParams();
  const [range, setRange] = useState('7d');

  // Load sidebar counts from the API.
  const session = useSession();
  const action = useAction();
  const admin = { ...session.user, initial: session.user.name[0] };
  const counter = useApi('/admin/counts');
  const dashboard = useApi(`/admin/dashboard?range=${range}`);
  const counts = counter.data || {};
  function reload() {
    counter.reload();
    dashboard.reload();
  }

  const groups = [
    { id: 'manage', label: t('admin.manage') },
    { id: 'people', label: t('admin.people') },
  ];

  const navigation = adminNav.map((item) => ({ ...item, label: t(item.labelKey) }));
  const ranges = adminRanges.map((item) => ({ ...item, label: t(item.labelKey) }));
  const current = navigation.find((item) => item.id === section);
  const rangeLabel = ranges.find((item) => item.value === range)?.label ?? range;

  // Map header statistic IDs to the corresponding dashboard response fields.
  const keys = {
    downloads: 'downloads',
    uploads: 'uploads',
    queue: 'pendingReview',
    reports: 'openReports',
    users: 'activePlayers',
  };
  const kpis = adminKpis.map((item) => ({
    ...item,
    label: t(item.labelKey),
    value: dashboard.data?.kpis[keys[item.id]]?.value ?? '—',
    delta: undefined,
    sub: undefined,
  }));

  // Choose the content below the header from the current section.
  const content = {
    dashboard: (
      <>
        <StatTiles columns={{ sm: 1, md: 3, lg: 5 }} items={kpis} size="lg" />

        <div className={shared.columns}>
          <div className={shared.column}>
            <QueuePanel range={range} onChanged={reload} summary />
            <DownloadsPanel range={range} rangeLabel={rangeLabel} />
          </div>

          <div className={shared.column}>
            <ReportsPanel range={range} onChanged={reload} summary />
            <RequestsPanel range={range} onChanged={reload} summary />
            <ActivityPanel range={range} summary />
          </div>
        </div>
      </>
    ),
    queue: <QueuePanel range={range} onChanged={reload} />,
    reports: <ReportsPanel range={range} onChanged={reload} />,
    requests: <RequestsPanel range={range} onChanged={reload} />,
    activity: <ActivityPanel range={range} />,
    accounts: <AccountsPanel range={range} onChanged={reload} />,
    stats: <StatsSection range={range} rangeLabel={rangeLabel} />,
  };

  // Show the missing-page view for unknown administration sections.
  if (!current) return <NotFoundPage />;

  return (
    <div className={styles.shell}>
      <aside className={styles.sidebar}>
        <Link className={styles.brand} to={`/${language}`}>
          <img alt="RoG" className={styles.logo} src="/assets/logo-rog.png" />
          <span className={styles.brandText}>
            <span className={styles.brandName}>{t('admin.title')}</span>
            <span className={styles.brandSite}>RoG Addons</span>
          </span>
        </Link>

        <nav className={styles.nav}>
          {groups.map((group) => (
            <div className={styles.group} key={group.id}>
              <span className={styles.groupLabel}>{group.label}</span>

              {navigation
                .filter((item) => item.group === group.id)
                .map((item) => (
                  <Link
                    className={styles.navItem}
                    data-active={item.id === section ? 'true' : 'false'}
                    key={item.id}
                    to={`/${language}/admin-pannel/${item.id}`}
                  >
                    <Icon name={item.icon} size={17} />
                    <span className={styles.navLabel}>{item.label}</span>

                    {item.badge && <span className={styles.badge}>{counts[item.badge]}</span>}
                    {item.count && <span className={styles.count}>{counts[item.count]}</span>}
                    {item.live && (
                      <span className={styles.live}>
                        <span className={styles.liveDot} />
                        {t('admin.live')}
                      </span>
                    )}
                  </Link>
                ))}
            </div>
          ))}
        </nav>

        <div className={styles.footer}>
          <div className={styles.account}>
            <span className={styles.accountInitial}>{admin.initial}</span>
            <span className={styles.accountText}>
              <span className={styles.accountName}>{admin.name}</span>
              <span className={styles.accountRole}>
                <Icon name="shield" size={11} />
                {admin.role}
              </span>
            </span>
          </div>

          <div className={styles.footerActions}>
            <Link className={`${shared.framed} ${styles.exit}`} to={`/${language}`}>
              <Icon name="external" size={13} />
              {t('admin.viewSite')}
            </Link>

            <button
              aria-label={t('admin.signOut')}
              className={`${shared.framed} ${styles.signOut}`}
              disabled={action.busy}
              onClick={() => action.run(session.logout)}
            >
              <Icon name="signOut" size={15} />
            </button>
          </div>
        </div>
      </aside>

      <main className={styles.main}>
        <header className={styles.topbar}>
          <div className={styles.topbarText}>
            <span className={styles.date}>{today(language)}</span>
            <h1 className={styles.pageTitle}>{current.label}</h1>
          </div>

          <div className={styles.tools}>
            <SegmentedControl
              label={t('admin.range')}
              options={ranges}
              size="lg"
              value={range}
              onChange={setRange}
            />
          </div>
        </header>

        <section className={styles.section}>
          <ApiState error={action.error || counter.error || dashboard.error} retry={reload} />
          {content[section] ?? (
            <Panel padded={false}>
              <EmptyState text={t('admin.soonText')} title={t('admin.soon')} />
            </Panel>
          )}
        </section>
      </main>
    </div>
  );
}

/**
 * Open the first administration section when the URL contains no section.
 */
export function AdminIndex() {
  const { language } = useI18n();

  return <Navigate replace to={`/${language}/admin-pannel/${adminNav[0].id}`} />;
}
