import { useApi } from '../../../hooks/useApi.js';
import { date, bytes, number } from '../../../api/presenters.js';
import { ApiState } from '../../../components/ApiState/ApiState.jsx';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import BarPulse from '../../../components/BarPulse/BarPulse.jsx';
import ChordDiagram from '../../../components/ChordDiagram/ChordDiagram.jsx';
import Feed from '../../../components/Feed/Feed.jsx';
import Icon from '../../../components/Icon/Icon.jsx';
import Panel from '../../../components/Panel/Panel.jsx';
import RadialHeatmap from '../../../components/RadialHeatmap/RadialHeatmap.jsx';
import { expansionAccent, findExpansion } from '../../../config/expansions.js';
import { useI18n } from '../../../i18n/I18nProvider.jsx';
import { explorePath } from '../../../router/paths.js';
import shared from '../admin.module.scss';
import styles from './StatsSection.module.scss';

/**
 * Resolve each client's expansion color for the diagram.
 */
function colorOf(id) {
  return expansionAccent(id);
}

/**
 * Format a weekday name using a Monday-based index.
 */
function dayName(language, index) {
  return new Intl.DateTimeFormat(language, { weekday: 'long' }).format(
    new Date(2024, 0, 1 + index),
  );
}

/**
 * Format the current weekday for comparisons with the same weekday in other periods.
 */
function weekday(language) {
  return new Intl.DateTimeFormat(language, { weekday: 'long' }).format(new Date());
}

/**
 * Highlight asterisk-delimited text so each language can place emphasis within one message.
 */
function emphasise(text) {
  return text
    .split('*')
    .map((part, index) =>
      index % 2 ? (
        <strong key={part + index}>{part}</strong>
      ) : (
        <span key={part + index}>{part}</span>
      ),
    );
}

/**
 * Display the activity pulse, live downloads, hourly distribution, client migration, records, and story.
 */
export default function StatsSection({ rangeLabel, range = '7d' }) {
  const { language, t } = useI18n();
  const [running, setRunning] = useState(true);
  const resource = useApi(
    `/admin/stats?range=${range}&tz=${encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone)}`,
  );
  const live = useApi('/admin/live');
  useEffect(() => {
    if (!running) return;
    const timer = setInterval(live.reload, 10000);
    return () => clearInterval(timer);
  }, [running, live.reload]);
  const raw = resource.data;
  const current = live.data;
  const adminNow = {
    online: current?.online ?? '—',
    perMinute: current?.perMinute ?? '—',
    today: current?.today ?? '—',
    pace: current?.sameTimeLastWeek
      ? `${Math.round((current.today / current.sameTimeLastWeek - 1) * 100)}%`
      : '—',
  };
  const adminPulse = current?.pulse || [];
  const adminFeed = (current?.feed || []).map((item) => ({
    id: item.id,
    who: item.user,
    where: item.city,
    addon: item.addon.name,
    slug: item.addon.slug,
    expansion: item.expansionId,
    image: item.addon.iconUrl,
    when: date(item.createdAt, language),
  }));
  const rings = raw?.primeTime || Array.from({ length: 7 }, () => Array(24).fill(0));
  const total = rings.flat().reduce((sum, value) => sum + value, 0);
  const max = Math.max(...rings.flat(), 1);
  const hourly = Array.from({ length: 24 }, (_, hour) =>
    rings.reduce((sum, day) => sum + day[hour], 0),
  );
  const peakIndex = rings.flat().indexOf(Math.max(...rings.flat()));
  const peak = { ring: Math.floor(peakIndex / 24), slice: peakIndex % 24 };
  const week =
    rings
      .slice(0, 5)
      .flat()
      .reduce((sum, value) => sum + value, 0) / 5;
  const weekend =
    rings
      .slice(5)
      .flat()
      .reduce((sum, value) => sum + value, 0) / 2;
  const adminPrimeTime = {
    rings: rings.map((day) => day.map((value) => value / max)),
    days: rings.map((_, index) => dayName(language, index)),
    hours: ['00', '03', '06', '09', '12', '15', '18', '21'],
    peak,
    peakHour: total ? `${String(peak.slice).padStart(2, '0')}:00` : '—',
    peakDay: total ? dayName(language, peak.ring) : '',
    share: total
      ? `${Math.round((hourly.slice(18, 24).reduce((a, b) => a + b, 0) / total) * 100)}%`
      : '—',
    quiet: total ? `${String(hourly.indexOf(Math.min(...hourly))).padStart(2, '0')}:00` : '—',
    lift: week ? `${Math.round((weekend / week - 1) * 100)}%` : '—',
  };
  const flows = raw?.migration || [];
  const ids = [...new Set(flows.flatMap((flow) => [flow.from, flow.to]))];
  const adminMigration = {
    flows,
    nodes: ids.map((id) => ({
      id,
      label: findExpansion(id)?.abbr || id,
      color: expansionAccent(id),
    })),
  };
  const top = [...flows].sort((a, b) => b.value - a.value).slice(0, 3);
  const adminRecords = (raw?.records || []).map((item) => ({
    ...item,
    slug: item.holder.addonSlug,
    holder: item.holder.label,
    gold: item.active,
    value:
      item.unit === 'bytes'
        ? bytes(item.value)
        : item.unit === 'minuteOfDay'
          ? `${String(Math.floor(item.value / 60)).padStart(2, '0')}:${String(item.value % 60).padStart(2, '0')}`
          : `${number(item.value, language)} ${item.unit}`,
  }));
  const chapters = raw
    ? [
        {
          numeral: 'I',
          text: t('admin.chapterOne', {
            count: number(raw.periodTotal, language),
            share: adminPrimeTime.share,
          }),
        },
        ...(total
          ? [
              {
                numeral: 'II',
                text: t('admin.chapterTwo', {
                  day: dayName(language, peak.ring),
                  lift: adminPrimeTime.lift,
                  quiet: adminPrimeTime.quiet,
                }),
              },
            ]
          : []),
      ]
    : [];
  function exportPeriod() {
    if (!raw) return;
    const rows = ['date,downloads,previous'];
    raw.downloads.current.forEach((value, index) => {
      const day = new Date(`${raw.downloads.from}T00:00:00Z`);
      day.setUTCDate(day.getUTCDate() + index);
      rows.push(`${day.toISOString().slice(0, 10)},${value},${raw.downloads.previous[index] ?? 0}`);
    });
    const url = URL.createObjectURL(
      new Blob([rows.join('\r\n')], { type: 'text/csv;charset=utf-8' }),
    );
    const link = document.createElement('a');
    link.href = url;
    link.download = `downloads-${range}.csv`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  // Derive feed timestamps from list position so the newest entry appears first.
  const feed = adminFeed.map((item, index, list) => {
    const entry = item;

    return {
      id: entry.id,
      image: entry.image,
      when: item.when,
      fresh: index === 0,
      text: (
        <>
          <strong>{entry.who}</strong>{' '}
          {entry.where && <em>{t('admin.fromPlace', { place: entry.where })}</em>}{' '}
          {t('admin.grabbed')}{' '}
          <Link to={`/${language}/explore?q=${encodeURIComponent(entry.slug)}`}>{entry.addon}</Link>{' '}
          <mark style={{ '--tag-color': expansionAccent(entry.expansion) }}>{entry.expansion}</mark>
        </>
      ),
    };
  });

  return (
    <>
      <ApiState {...resource} retry={resource.reload} />
      <ApiState error={live.error} retry={live.reload} />
      <div className={`${shared.columns} ${shared.stretch}`}>
        <div className={styles.pulsePanel}>
          <div className={styles.pulseGlow} />

          <div className={styles.pulseHead}>
            <span className={styles.heading}>
              <span className={styles.kicker}>{t('admin.rightNow')}</span>
              <span className={styles.pulseTitle}>{t('admin.realmAwake')}</span>
            </span>

            <span className={styles.pulseBadge}>
              <span className={styles.liveDot} />
              {t('admin.onlineNow', { count: adminNow.online, rate: adminNow.perMinute })}
            </span>
          </div>

          <div className={styles.pulseCount}>
            <span className={styles.pulseNumber}>{adminNow.today}</span>
            <span className={styles.pulseText}>
              <span>{t('admin.downloadsToday')}</span>
              <span className={styles.pace}>
                {t('admin.pace', { value: adminNow.pace, day: weekday(language) })}
              </span>
            </span>
          </div>

          <BarPulse tip={(value) => t('admin.perMinute', { count: value })} values={adminPulse} />

          <span className={styles.pulseNote}>{t('admin.pulseNote')}</span>
        </div>

        <Panel
          aside={
            <button
              className={`${shared.framed} ${styles.feedToggle}`}
              onClick={() => setRunning((value) => !value)}
              type="button"
            >
              <Icon name={running ? 'pause' : 'play'} size={12} />
              {t(running ? 'admin.pause' : 'admin.resume')}
            </button>
          }
          note={t('admin.feedNote')}
          padded={false}
          title={t('admin.liveFeed')}
        >
          <Feed items={feed} />
        </Panel>
      </div>

      <div className={`${shared.columns} ${shared.stretch}`}>
        <Panel>
          <div className={styles.chartHead}>
            <span className={styles.heading}>
              <span className={styles.kicker}>{t('admin.primeTime')}</span>
              <span className={styles.chartTitle}>{t('admin.whenDownloads')}</span>
            </span>
            <span className={styles.chartNote}>
              {t('admin.ringsNote')}
              <br />
              {t('admin.ringsHours', { range: rangeLabel })}
            </span>
          </div>

          <div className={styles.chartBody}>
            <RadialHeatmap
              center={{ value: adminPrimeTime.peakHour, label: `${adminPrimeTime.peakDay} PEAK` }}
              labels={adminPrimeTime.hours}
              peak={adminPrimeTime.peak}
              rings={adminPrimeTime.rings}
              tip={(value, ring, slice) =>
                `${adminPrimeTime.days[ring]} ${String(slice).padStart(2, '0')}:00`
              }
            />

            <div className={styles.chartFacts}>
              <span className={styles.fact}>
                <span className={styles.factValue}>{adminPrimeTime.share}</span>
                <span className={styles.factLabel}>{t('admin.primeShare')}</span>
              </span>
              <span className={styles.fact}>
                <span className={styles.factValue}>{adminPrimeTime.quiet}</span>
                <span className={styles.factLabel}>{t('admin.quietHour')}</span>
              </span>
              <span className={styles.fact}>
                <span className={styles.factValue}>{adminPrimeTime.lift}</span>
                <span className={styles.factLabel}>{t('admin.weekendLift')}</span>
              </span>
              <span className={styles.scale}>
                <span className={styles.scaleBar} />
                {t('admin.lowHigh')}
              </span>
            </div>
          </div>
        </Panel>

        <Panel>
          <div className={styles.chartHead}>
            <span className={styles.heading}>
              <span className={styles.kicker}>{t('admin.migration')}</span>
              <span className={styles.chartTitle}>{t('admin.whereTravel')}</span>
            </span>
            <span className={styles.chartNote}>
              {t('admin.migrationNote')}
              <br />
              {t('admin.migrationThen', { range: rangeLabel })}
            </span>
          </div>

          <div className={styles.chartBody}>
            <ChordDiagram
              flows={adminMigration.flows}
              nodes={adminMigration.nodes}
              tip={(flow) => `${flow.from} → ${flow.to}: ${flow.value}`}
            />

            <div className={styles.chartFacts}>
              {top.map((flow) => (
                <span className={styles.fact} key={`${flow.from}-${flow.to}`}>
                  <span className={styles.flow}>
                    <span style={{ color: colorOf(flow.from) }}>{flow.from}</span>
                    <span className={styles.arrow}>→</span>
                    <span style={{ color: colorOf(flow.to) }}>{flow.to}</span>
                  </span>
                  <span className={styles.factLabel}>
                    {t('admin.players', { count: flow.value })}
                    {flow.note ? ` · ${t(`admin.flowNotes.${flow.note}`)}` : ''}
                  </span>
                </span>
              ))}
            </div>
          </div>
        </Panel>
      </div>

      <div className={shared.columns}>
        <Panel>
          <span className={styles.heading}>
            <span className={styles.kicker}>{t('admin.hallOfRecords')}</span>
            <span className={styles.chartTitle}>{t('admin.feats')}</span>
          </span>

          <div className={styles.recordGrid}>
            {adminRecords.map((item) => (
              <div className={styles.record} key={item.id}>
                <span className={styles.recordLabel}>
                  <span className={styles.recordDot} data-gold={Boolean(item.gold)} />
                  {t(`admin.records.${item.id}.label`)}
                </span>

                <Link
                  className={styles.recordHolder}
                  to={`${explorePath(language)}?q=${encodeURIComponent(item.slug || item.holder)}`}
                >
                  {item.holder}
                </Link>

                <span className={styles.recordValue}>
                  {item.value ?? t('admin.recordDays', { count: item.days })}
                </span>
                <span className={styles.recordNote}>{t(`admin.records.${item.id}.note`)}</span>
              </div>
            ))}
          </div>
        </Panel>

        <div className={styles.chroniclePanel}>
          <span className={styles.heading}>
            <span className={styles.kicker}>{t('admin.chronicle')}</span>
            <span className={styles.chartTitle}>
              {t('admin.numbersSay', { range: rangeLabel })}
            </span>
          </span>

          <div className={styles.story}>
            {chapters.map((chapter) => (
              <p className={styles.chapter} key={chapter.numeral}>
                <span className={styles.numeral}>{chapter.numeral}</span>
                <span>{emphasise(chapter.text)}</span>
              </p>
            ))}
          </div>

          <div className={styles.chronicleFoot}>
            <span>{t('admin.chronicleNote')}</span>

            <button
              className={`${shared.framed} ${styles.export}`}
              type="button"
              disabled={!raw}
              onClick={exportPeriod}
            >
              <Icon name="download" size={12} />
              {t('admin.exportPeriod')}
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
