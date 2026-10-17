import { api, queryPath } from '../../api/client.js';
import { date, number, presentAddon } from '../../api/presenters.js';
import { useApi, useAction } from '../../hooks/useApi.js';
import { ApiState } from '../../components/ApiState/ApiState.jsx';
import Pagination from '../../components/Pagination/Pagination.jsx';
import { addonPath } from '../../router/paths.js';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import AchievementGrid from '../../components/AchievementGrid/AchievementGrid.jsx';
import AddonCard from '../../components/AddonCard/AddonCard.jsx';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import Heatmap from '../../components/Heatmap/Heatmap.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import ListSection from '../../components/ListSection/ListSection.jsx';
import MarkedLinks from '../../components/MarkedLinks/MarkedLinks.jsx';
import ProfileHero from '../../components/ProfileHero/ProfileHero.jsx';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import {
  profileAchievements as achievementDefinitions,
  profileTitles as titleDefinitions,
} from '../../config/profile.js';
import { findExpansion } from '../../config/expansions.js';
import { explorePath } from '../../router/paths.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './ProfilePage.module.scss';

const loadoutSlots = 8;
const talentPoints = 6;

/**
 * Choose a slot color from its rating, matching item-quality colors.
 */
function qualityColor(rating) {
  if (rating >= 4.8) return '#e8a33c';
  if (rating >= 4.5) return '#b98ad9';
  if (rating >= 4.2) return '#7fb7d9';

  return '#8fc98a';
}

export default function ProfilePage() {
  const { language, t } = useI18n();
  const achievementLabels = achievementDefinitions.map((item) => ({ ...item, ...t(item.copyKey) }));
  const titleLabels = titleDefinitions.map((item) => ({ ...item, ...t(item.copyKey) }));
  const [tab, setTab] = useState('addons');
  const [page, setPage] = useState(1);
  const { username = 'me' } = useParams();

  const action = useAction();
  const resource = useApi(`/profiles/${encodeURIComponent(username)}`);
  const list = useApi(
    tab !== 'achievements'
      ? queryPath(`/users/${encodeURIComponent(username)}/${tab}`, { page })
      : null,
  );
  if (!resource.data)
    return (
      <div className="site-frame">
        <AuthBar />
        <SiteHeader />
        <main className="app-shell">
          <ApiState {...resource} retry={resource.reload} />
        </main>
        <Footer />
      </div>
    );
  const data = resource.data;
  const user = data.user;
  const profileUser = {
    ...user,
    initial: user.name[0],
    title: titleLabels.find((item) => item.id === user.titleId)?.label || '',
    classLabel: user.role,
    joined: date(user.joinedAt, language),
    presence: user.online ? t("ui.online") : t("ui.offline"),
    presenceColor: user.online ? '#8fc98a' : '#8f7b54',
    badge: user.badge?.achievementId,
    spec: user.spec
      ? { label: user.spec.name, image: `/assets/categorias/${user.spec.role}.png` }
      : null,
    skill: user.skill.value,
    skillMax: user.skill.max || 1,
    rep: user.reputation.value,
    repMax: user.reputation.max || 1,
    repLabel: user.reputation.standing,
    repHint: '',
  };
  const profileAchievements = data.achievements.map((item) => ({
    ...achievementLabels.find((label) => label.id === item.id),
    ...item,
    title: achievementLabels.find((label) => label.id === item.id)?.title || item.id,
    done: !!item.completedAt,
    date: date(item.completedAt, language),
    progress: item.progress ? `${item.progress.current} / ${item.progress.max}` : '',
  }));
  const profileTitles = data.titles.map((item) => ({
    ...titleLabels.find((label) => label.id === item.id),
    ...item,
    earned: !!item.earnedAt,
  }));
  const profileStats = [
    ['addons', 'Addons'],
    ['downloads', t("addon.stats.downloads")],
    ['averageRating', t("ui.rating")],
    ['followers', t("ui.followers")],
    ['versions', t("addon.tabs.versions")],
    ['replies', t("ui.replies")],
  ].map(([id, label]) => ({
    id,
    label,
    value: data.stats[id] == null ? '—' : number(data.stats[id], language),
  }));
  const profileLoadout = data.loadout.map((item, index) => ({
    ...presentAddon(item, language),
    slot: ['Head', 'Chest', 'Shoulders', 'Hands', 'Trinket', 'Legs', 'Feet', 'Main hand'][index],
  }));
  const profileBag =
    tab === 'addons' ? (list.data?.items || []).map((item) => presentAddon(item, language)) : [];
  const profileTalentClients = Object.keys(data.addonsByExpansion);
  const profileReviews =
    tab === 'reviews'
      ? (list.data?.items || []).map((item) => ({
          ...item,
          addonName: item.addon.name,
          when: date(item.createdAt, language),
        }))
      : [];
  const profileJournal =
    tab === 'journal'
      ? (list.data?.items || []).map((item) => ({
          ...item,
          when: date(item.createdAt, language),
          verb: item.type,
          target: item.target.label,
          to: item.target.addonSlug
            ? `${explorePath(language)}?q=${encodeURIComponent(item.target.addonSlug)}`
            : `/${language}/requests`,
          tail: item.detail,
        }))
      : [];
  const profileWhisper = data.lastWhisper
    ? {
        ...data.lastWhisper,
        author: data.lastWhisper.author.name,
        when: date(data.lastWhisper.createdAt, language),
      }
    : null;
  const profileHeat = data.heat.counts.map((count) =>
    count === 0 ? 0 : count < 3 ? 1 : count < 6 ? 2 : 3,
  );

  // Resolve navigation targets in the view; configuration supplies only target IDs.
  const targets = {
    addon: explorePath(language),
    requests: `/${language}#requests`,
    profile: `/${language}/profile`,
  };

  const unlocked = profileAchievements.filter((item) => item.done);
  const points = unlocked.reduce((sum, item) => sum + item.points, 0);

  // Add one talent point per published addon for each client, up to six points.
  const talents = profileTalentClients.map((id) => {
    const expansion = findExpansion(id);
    const count = data.addonsByExpansion[id];

    return {
      id,
      abbr: expansion?.abbr ?? id,
      color: expansion?.colorAccent,
      count,
      points: Array.from({ length: talentPoints }, (_, index) => index < count),
    };
  });

  /**
   * Always render eight equipment slots, leaving unused positions empty.
   */
  function loadout() {
    const filled = profileLoadout.slice(0, loadoutSlots).map(slot);
    const empty = Array.from({ length: Math.max(0, loadoutSlots - filled.length) }, (_, index) => ({
      id: `empty-${index}`,
    }));

    return [...filled, ...empty];
  }

  function slot(item) {
    return {
      id: item.id,
      name: item.name,
      detail: `${item.slot} \u00b7 ${item.downloads} dl`,
      image: item.image,
      rating: item.rating == null ? '—' : Number(item.rating).toFixed(1),
      color: qualityColor(item.rating),
      to: addonPath(language, item),
    };
  }

  /**
   * Keep each journal sentence and its inline link together in the list's custom content slot.
   */
  function entry(item) {
    return {
      id: item.id,
      mark: item.when,
      tone: { dot: item.dot },
      content: (
        <>
          <span className={styles.entry}>
            {item.verb}{' '}
            <Link className={styles.entryTarget} to={item.to}>
              {item.target}
            </Link>
            {item.tail}
          </span>
          {item.note && <span className={styles.entryNote}>{item.note}</span>}
        </>
      ),
    };
  }

  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className="app-shell">
        <ProfileHero
          actions={
            <>
              <button
                className={styles.primaryAction}
                type="button"
                disabled={data.viewer?.isSelf || action.busy}
                aria-pressed={!!data.viewer?.following}
                onClick={() =>
                  action.run(async () => {
                    await api.put(
                      `/users/${encodeURIComponent(user.username || user.name)}/follow`,
                      { following: !data.viewer?.following },
                    );
                    resource.reload();
                  })
                }
              >
                <Icon name="heart" size={15} />
                {data.viewer?.following ? t("ui.unfollow") : t('profile.follow')}
              </button>
              {data.viewer?.isSelf && (
                <Link className={styles.action} to={`/${language}/upload?view=submissions`}>
                  {t("ui.mySubmissions")}
                </Link>
              )}
            </>
          }
          activeTab={tab}
          avatar={{
            badge: profileUser.badge,
            initial: profileUser.initial,
            color: profileUser.color,
            spec: profileUser.spec ? { ...profileUser.spec, kicker: t('profile.mainSpec') } : null,
            bars: [
              {
                id: 'skill',
                icon: 'pencil',
                label: t('profile.crafting'),
                value: `${profileUser.skill} / ${profileUser.skillMax}`,
                percent: Math.round((profileUser.skill / profileUser.skillMax) * 100),
                hint: profileUser.skillHint,
              },
              {
                id: 'rep',
                label: t('profile.reputation'),
                value: profileUser.repLabel,
                percent: Math.round((profileUser.rep / profileUser.repMax) * 100),
                hint: `${profileUser.rep.toLocaleString()} \u00b7 ${profileUser.repHint}`,
                color: profileUser.repColor,
              },
            ],
          }}
          background="/assets/slide3.png"
          kicker={{
            label: t('profile.characterSheet'),
            status: {
              label: profileUser.presence,
              color: profileUser.presenceColor,
            },
          }}
          meta={[
            {
              id: 'title',
              label: profileUser.title,
              icon: 'crown',
              tone: '#f5d98a',
            },
            {
              id: 'level',
              label: t('profile.level', {
                level: profileUser.level,
                role: profileUser.classLabel,
              }),
            },
            {
              id: 'realm',
              icon: 'hearth',
              label: t('profile.hearth', { realm: profileUser.realm }),
            },
            {
              id: 'joined',
              label: t('profile.joined', { date: profileUser.joined }),
            },
          ]}
          name={profileUser.name}
          slots={{ left: loadout().slice(0, 4), right: loadout().slice(4) }}
          stats={profileStats}
          tabs={[
            {
              id: 'addons',
              label: t('profile.tabAddons'),
              count: data.counts.addons,
            },
            {
              id: 'achievements',
              label: t('profile.tabAchievements'),
              count: `${unlocked.length}/${profileAchievements.length}`,
            },
            {
              id: 'journal',
              label: t('profile.tabJournal'),
              count: data.counts.journal,
            },
            {
              id: 'reviews',
              label: t('profile.tabReviews'),
              count: data.counts.reviews,
            },
          ]}
          onTab={(tab) => {
            setTab(tab);
            setPage(1);
          }}
        />

        <ApiState error={action.error} loading={action.busy} />
        <ApiState {...list} retry={list.reload} empty={list.data?.items.length === 0} />
        <section className={styles.content}>
          {tab === 'addons' && (
            <div className={styles.block}>
              <div className={styles.head}>
                <span className={styles.kicker}>{t('profile.bagKicker')}</span>
                <h2 className={styles.title}>{t('profile.bagTitle')}</h2>
              </div>

              <div className={styles.bagLayout}>
                <div className={styles.bag}>
                  {profileBag.map((addon) => (
                    <AddonCard addon={addon} compact key={addon.id} />
                  ))}
                </div>

                <aside className={styles.aside}>
                  <div className={styles.panel}>
                    <span className={styles.panelKicker}>{t('profile.talents')}</span>

                    <div className={styles.talents}>
                      {talents.map((talent) => (
                        <div
                          className={styles.talent}
                          key={talent.id}
                          style={{ '--talent-color': talent.color }}
                        >
                          <span className={styles.talentAbbr}>{talent.abbr}</span>

                          <span className={styles.talentPoints}>
                            {talent.points.map((filled, index) => (
                              <span
                                className={styles.talentPoint}
                                data-filled={filled ? 'true' : 'false'}
                                key={index}
                              />
                            ))}
                          </span>

                          <span className={styles.talentCount}>{talent.count}</span>
                        </div>
                      ))}
                    </div>

                    <span className={styles.panelNote}>{t('profile.talentsNote')}</span>
                  </div>

                  <MarkedLinks
                    boxed={false}
                    items={profileTitles.map((item) => ({
                      id: item.id,
                      label: item.label,
                      trailing: item.requirement,
                      icon: 'check',
                      muted: !item.earned,
                      tone: { accent: item.earned ? '#8fc98a' : '#3a2f1e' },
                    }))}
                    title={t('profile.titles')}
                  />

                  {profileWhisper && (
                    <div className={styles.whisper}>
                      <span className={styles.panelKicker}>{t('profile.lastWhisper')}</span>
                      <p className={styles.whisperText}>{`\u201c${profileWhisper.text}\u201d`}</p>
                      <span className={styles.whisperAuthor}>
                        {`\u2014 ${profileWhisper.author} \u00b7 ${profileWhisper.when}`}
                      </span>
                    </div>
                  )}
                </aside>
              </div>
            </div>
          )}

          {tab === 'achievements' && (
            <div className={styles.block}>
              <div className={styles.head}>
                <span className={styles.kicker}>{t('profile.achievementsKicker')}</span>
                <h2 className={styles.title}>{t('profile.achievementsTitle')}</h2>
                <span className={styles.note}>
                  {t('profile.achievementsCount', {
                    points,
                    done: unlocked.length,
                    total: profileAchievements.length,
                  })}
                </span>
              </div>

              <AchievementGrid
                columns={{ sm: 1, md: 2, lg: 3 }}
                items={profileAchievements.map((item) => ({
                  id: item.id,
                  title: item.title,
                  text: item.done
                    ? `${item.text} · ${item.date}`
                    : `${item.text} · ${item.progress}`,
                  value: item.points,
                  unit: 'PTS',
                  active: item.done,
                }))}
              />
            </div>
          )}

          {tab === 'journal' && (
            <div className={styles.block}>
              <div className={styles.head}>
                <span className={styles.kicker}>{t('profile.journalKicker')}</span>
                <h2 className={styles.title}>{t('profile.journalTitle')}</h2>
              </div>

              <div className={styles.journalLayout}>
                <ListSection items={profileJournal.map(entry)} markStyle="plain" />

                <aside className={styles.aside}>
                  <Heatmap
                    cells={profileHeat.map((level, index) => ({
                      id: index,
                      level,
                      label: t('profile.heatActions', { count: data.heat.counts[index] }),
                    }))}
                    note={t('profile.playTimeNote')}
                    title={t('profile.playTime')}
                  />

                  <div className={styles.panel}>
                    <span className={styles.panelKicker}>{t('profile.rested')}</span>
                    <span className={styles.restedText}>
                      {date(data.stats.lastVersionAt, language)}
                    </span>
                    <Link className={styles.restedAction} to={`/${language}/upload`}>
                      {t('profile.shipVersion')}
                    </Link>
                  </div>
                </aside>
              </div>
            </div>
          )}

          {tab === 'reviews' && (
            <div className={styles.block}>
              <div className={styles.head}>
                <span className={styles.kicker}>{t('profile.reviewsKicker')}</span>
                <h2 className={styles.title}>{t('profile.reviewsTitle')}</h2>
              </div>

              <div className={styles.reviews}>
                {profileReviews.map((review) => (
                  <div className={styles.review} key={review.id}>
                    <div className={styles.reviewHead}>
                      <Link className={styles.reviewAddon} to={addonPath(language, review.addon)}>
                        {review.addonName}
                      </Link>
                      <span className={styles.reviewStars}>
                        {Array.from({ length: review.rating }, (_, index) => (
                          <Icon fill="currentColor" key={index} name="star" size={13} />
                        ))}
                      </span>
                    </div>

                    <p className={styles.reviewText}>{review.text}</p>

                    <span className={styles.reviewMeta}>
                      {t('profile.reviewMeta', {
                        when: review.when,
                        count: review.helpful,
                      })}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </section>
        <Pagination page={list.data?.page} onChange={setPage} disabled={list.loading} />
      </main>
      <Footer />
    </div>
  );
}
