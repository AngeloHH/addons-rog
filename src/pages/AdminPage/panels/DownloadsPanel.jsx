import Icon from '../../../components/Icon/Icon.jsx';
import LineChart from '../../../components/LineChart/LineChart.jsx';
import Panel from '../../../components/Panel/Panel.jsx';
import { useApi } from '../../../hooks/useApi.js';
import { ApiState } from '../../../components/ApiState/ApiState.jsx';
import { useI18n } from '../../../i18n/I18nProvider.jsx';
import styles from './DownloadsPanel.module.scss';

/**
 * Display the period chart followed by the most downloaded addons.
 */
export default function DownloadsPanel({ rangeLabel, range = '7d' }) {
  const { t, language } = useI18n();

  const resource = useApi(`/admin/stats?range=${range}`);
  const raw = resource.data?.downloads;
  const adminDownloads = {
    current: raw?.current || [],
    previous: raw?.previous || [],
    labels: (raw?.current || []).map((_, index) => {
      const day = new Date(`${raw.from}T00:00:00Z`);
      day.setUTCDate(day.getUTCDate() + index);
      return day.toLocaleDateString(language, { month: 'short', day: 'numeric', timeZone: 'UTC' });
    }),
  };
  const adminTopAddons = (resource.data?.topAddons || []).map((item) => ({
    ...item,
    id: item.slug,
    count: item.downloads,
  }));
  return (
    <Panel>
      <ApiState {...resource} retry={resource.reload} />
      <LineChart
        format={(value) => value}
        max={adminDownloads.max}
        note={t('admin.daily', { range: rangeLabel })}
        series={[
          {
            id: 'previous',
            label: t('admin.previousPeriod'),
            values: adminDownloads.previous,
            tone: 'muted',
            dashed: true,
          },
          {
            id: 'current',
            label: t('admin.downloads'),
            values: adminDownloads.current,
            area: true,
            dots: true,
          },
        ]}
        tip={(value) => t('admin.downloadsTip', { count: value })}
        title={t('admin.downloads')}
        xLabels={adminDownloads.labels}
      />

      <div className={styles.top}>
        {adminTopAddons.map((item, index) => (
          <div className={styles.topItem} key={item.id}>
            <span className={styles.topRank}>{String(index + 1).padStart(2, '0')}</span>
            <a
              className={styles.topName}
              href={`/${language}/explore?q=${encodeURIComponent(item.slug)}`}
            >
              {item.name}
            </a>
            <span className={styles.topCount}>
              <Icon name="download" size={12} />
              {item.count}
            </span>
          </div>
        ))}
      </div>
    </Panel>
  );
}
