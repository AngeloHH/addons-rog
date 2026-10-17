import { queryPath } from '../../../api/client.js';
import { date, profilePath } from '../../../api/presenters.js';
import { useApi } from '../../../hooks/useApi.js';
import { ApiState } from '../../../components/ApiState/ApiState.jsx';
import { useEffect, useState } from 'react';
import EmptyState from '../../../components/EmptyState/EmptyState.jsx';
import Icon from '../../../components/Icon/Icon.jsx';
import ListSection from '../../../components/ListSection/ListSection.jsx';
import Panel from '../../../components/Panel/Panel.jsx';
import StatTiles from '../../../components/StatTiles/StatTiles.jsx';
import Timeline from '../../../components/Timeline/Timeline.jsx';
import { adminActivityGroups } from '../../../config/admin.js';
import { useI18n } from '../../../i18n/I18nProvider.jsx';
import shared from '../admin.module.scss';
import SectionLink from './SectionLink.jsx';
import Toolbar from './Toolbar.jsx';
import styles from './ActivityPanel.module.scss';

// Roles treated as staff when presenting activity actors.
const staff = ['Founder', 'Moderator', 'System'];

// Fill diamonds for additions and outline them for removals.
const solid = adminActivityGroups
  .filter((group) => group.id === 'uploads' || group.id === 'signUps')
  .flatMap((group) => group.kinds);

/**
 * Highlight text enclosed in asterisks so each language controls emphasis placement.
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
 * Render recent activity as a compact list or a full timeline grouped by day.
 * Show actor totals and each activity group's share beside the full timeline.
 */
export default function ActivityPanel({ summary, range = '7d' }) {
  const { t, language } = useI18n();
  const [query, setQuery] = useState('');
  const [group, setGroup] = useState();
  const [who, setWho] = useState('all');

  const [before, setBefore] = useState('');
  const [history, setHistory] = useState([]);
  useEffect(() => {
    setBefore('');
    setHistory([]);
  }, [range]);
  const resource = useApi(
    queryPath('/admin/activity', { q: query, group, who, range, before, limit: summary ? 5 : 10 }),
  );
  const adminActivity = (resource.data?.items || []).map((item) => ({
    ...item,
    who: item.actor.name,
    username: item.actor.username || item.actor.name,
    role: item.actor.role,
    initial: item.actor.name[0],
    colorAccent: item.actor.color,
    color: item.actor.color,
    verb: item.kind,
    target: item.target.label,
    when: date(item.createdAt, language),
    day: date(item.createdAt, language),
    time: new Date(item.createdAt).toLocaleTimeString(language),
    meta: '',
  }));
  const busiest = () =>
    (resource.data?.topActors || []).map((item) => ({
      who: item.actor.name,
      username: item.actor.username || item.actor.name,
      colorAccent: item.actor.color,
      initial: item.actor.name[0],
      count: item.count,
      kinds: Object.entries(item.kindCounts).map(([kind, count]) => `${count} ${kind}`),
    }));
  const mix = () => {
    const total = Object.values(resource.data?.groupCounts || {}).reduce((a, b) => a + b, 0);
    return adminActivityGroups.map((group) => ({
      ...group,
      count: resource.data?.groupCounts[group.id] || 0,
      share: total ? Math.round(((resource.data?.groupCounts[group.id] || 0) / total) * 100) : 0,
    }));
  };
  function change(setter, value) {
    setter(value);
    setBefore('');
    setHistory([]);
  }

  if (summary) {
    return (
      <Panel
        aside={<SectionLink label={t('admin.allActivity')} section="activity" />}
        padded={false}
        title={t('admin.activity')}
      >
        <ApiState {...resource} retry={resource.reload} />
        <div className={styles.feed}>
          <ListSection
            markStyle="dot"
            items={adminActivity.slice(0, 5).map((item) => ({
              id: item.id,
              tone: { dot: item.color },
              aside: item.when,
              content: (
                <span className={styles.event}>
                  <a href={profilePath(language, item.username)}>{item.who}</a> {item.verb}{' '}
                  <strong>{item.target}</strong>
                </span>
              ),
            }))}
          />
        </div>
      </Panel>
    );
  }

  const cards = adminActivityGroups.map((item) => ({
    id: item.id,
    color: item.color,
    label: t(`admin.groups.${item.id}.label`),
    sub: t(`admin.groups.${item.id}.sub`),
    value: resource.data?.groupCounts[item.id] ?? 0,
  }));

  const shown = adminActivity;

  const groups = [...new Set(shown.map((item) => item.day))].map((day) => {
    const items = shown.filter((item) => item.day === day);

    return {
      id: day,
      label: day,
      count: t('admin.events', { count: items.length }),
      items: items.map((item) => ({
        id: item.id,
        time: item.time,
        initial: item.initial,
        avatarColor: item.colorAccent,
        background: item.colorDark,
        color: item.color,
        solid: solid.includes(item.kind),
        who: item.who,
        whoTo: profilePath(language, item.username),
        role: item.role,
        roleColor: item.colorAccent,
        verb: item.verb,
        target: item.target,
        detail: item.detail,
        meta: item.meta,
        kind: item.kind,
      })),
    };
  });

  return (
    <>
      <StatTiles
        columns={{ sm: 2, md: 3, lg: 6 }}
        items={cards}
        size="md"
        value={group}
        onSelect={(value) => change(setGroup, value)}
      />

      <div className={styles.columns}>
        <Panel padded={false}>
          <Toolbar
            placeholder={t('admin.searchActivity')}
            query={query}
            onQuery={(value) => change(setQuery, value)}
            action={
              <a
                className={`${shared.framed} ${styles.export}`}
                href={`/api${queryPath('/admin/activity/export', { q: query, group, who, range })}`}
              >
                <Icon name="download" size={14} />
                {t('admin.exportCsv')}
              </a>
            }
            filters={[
              {
                id: 'who',
                label: t('admin.who'),
                value: who,
                onChange: (value) => change(setWho, value),
                options: [
                  { value: 'all', label: t('admin.everyone') },
                  { value: 'staff', label: t('admin.staff') },
                  { value: 'users', label: t('admin.users') },
                ],
              },
            ]}
          />

          <ApiState {...resource} retry={resource.reload} />
          {shown.length ? (
            <Timeline groups={groups} />
          ) : (
            <EmptyState text={t('admin.noMatchText')} title={t('admin.noMatch')} />
          )}
          <nav className="api-pagination">
            <button
              disabled={!history.length || resource.loading}
              onClick={() => {
                setBefore(history.at(-1));
                setHistory((values) => values.slice(0, -1));
              }}
            >
              {t("homeHero.previous")}
            </button>
            <button
              disabled={!resource.data?.nextCursor || resource.loading}
              onClick={() => {
                setHistory((values) => [...values, before]);
                setBefore(resource.data.nextCursor);
              }}
            >
              {t("homeHero.next")}
            </button>
          </nav>
        </Panel>

        <div className={shared.column}>
          <Panel>
            <span className={styles.kicker}>{t('admin.mostActive')}</span>

            <div className={styles.people}>
              {busiest().map((person) => (
                <div className={styles.person} key={person.who}>
                  <span
                    className={styles.avatar}
                    style={{
                      '--avatar-color': person.colorAccent,
                      '--avatar-background': person.colorDark,
                    }}
                  >
                    {person.initial}
                  </span>

                  <span className={styles.personText}>
                    <a className={styles.personName} href={profilePath(language, person.username)}>
                      {person.who}
                    </a>
                    <span className={styles.personKinds}>{person.kinds.join(' · ')}</span>
                  </span>

                  <span className={styles.personCount}>{person.count}</span>
                </div>
              ))}
            </div>
          </Panel>

          <Panel>
            <span className={styles.kicker}>{t('admin.eventMix')}</span>

            <div className={styles.mixBar}>
              {mix()
                .filter((item) => item.count)
                .map((item) => (
                  <span key={item.id} style={{ background: item.color, flex: item.count }} />
                ))}
            </div>

            <div className={styles.mixList}>
              {mix().map((item) => (
                <span className={styles.mixItem} key={item.id}>
                  <span className={styles.mixDot} style={{ background: item.color }} />
                  {t(`admin.groups.${item.id}.label`)}
                  <b className={styles.mixShare}>{`${item.share}%`}</b>
                </span>
              ))}
            </div>
          </Panel>

          <Panel>
            <span className={styles.kicker}>{t('admin.retention')}</span>

            <p className={styles.retention}>{emphasise(t('admin.retentionText'))}</p>

            <a className={styles.policy} href={`/${language}/rules`}>
              <Icon name="external" size={13} />
              {t('admin.moderationPolicy')}
            </a>
          </Panel>
        </div>
      </div>
    </>
  );
}
