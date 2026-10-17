import { useApi } from '../../hooks/useApi.js';
import { number, profilePath } from '../../api/presenters.js';
import { ApiState } from '../../components/ApiState/ApiState.jsx';
import { Link } from 'react-router-dom';
import AchievementGrid from '../../components/AchievementGrid/AchievementGrid.jsx';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import FounderCard from '../../components/FounderCard/FounderCard.jsx';
import ListSection from '../../components/ListSection/ListSection.jsx';
import PageHero from '../../components/PageHero/PageHero.jsx';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import {
  aboutAchievements as achievementDefinitions,
  aboutFounders,
  aboutTimeline,
} from '../../config/about.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './AboutPage.module.scss';

/**
 * Map timeline data to the shared list: year as mark, label as badge, and colors as tone.
 */
function event(item) {
  return {
    id: item.id,
    mark: item.year,
    title: item.title,
    badge: { label: item.tag, color: item.accent },
    description: item.text,
    tone: { accent: item.accent, dot: item.dot },
  };
}

/**
 * Append unfinished progress to the text passed to the achievement component.
 */
function achievement(item) {
  return {
    id: item.id,
    title: item.title,
    text: item.progress ? `${item.text} · ${item.progress}` : item.text,
    value: item.points,
    unit: 'PTS',
    active: item.done,
  };
}

export default function AboutPage() {
  const { language, t } = useI18n();
  const achievementLabels = achievementDefinitions.map((item) => ({ ...item, ...t(item.copyKey) }));
  const founders = aboutFounders.map((item) => {
    const copy = t(item.copyKey);
    return { ...item, ...copy, bars: item.bars.map((bar) => ({ ...bar, label: copy.bars[bar.id] })) };
  });
  const resource = useApi('/about');
  const stats = useApi('/site/stats');
  const aboutStats = ['addons', 'downloads', 'players'].map((id) => ({
    id,
    label: id,
    value: stats.data ? number(stats.data[id], language) : '—',
  }));
  const support = resource.data?.support;
  const aboutSupport = {
    ...support,
    people: support?.contributors ?? 0,
    percent: support?.goal ? Math.round((support.raised / support.goal) * 100) : 0,
  };
  const aboutHallOfFame = (resource.data?.hallOfFame || []).map((item) => ({
    ...item,
    ...item.user,
    colorAccent: item.user.color,
  }));
  const aboutAchievements = (resource.data?.achievements || []).map((item) => ({
    ...achievementLabels.find((label) => label.id === item.id),
    ...item,
    title: achievementLabels.find((label) => label.id === item.id)?.title || item.id,
    done: !!item.completedAt,
    progress: item.progress ? `${item.progress.current} / ${item.progress.max}` : '',
  }));
  const unlocked = aboutAchievements.filter((item) => item.done);
  const done = unlocked.length;
  const total = aboutAchievements.length;
  const points = unlocked.reduce((sum, item) => sum + item.points, 0);

  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className="app-shell">
        <PageHero
          aside={
            <div className={styles.stats}>
              {aboutStats.map((stat) => (
                <div className={styles.stat} key={stat.id}>
                  <span className={styles.statValue}>{stat.value}</span>
                  <span className={styles.statLabel}>{stat.label}</span>
                </div>
              ))}
            </div>
          }
          kicker={t('about.kicker')}
          text={t('about.intro')}
          title={t('about.title')}
        />

        <ApiState {...resource} retry={resource.reload} />
        <ApiState {...stats} retry={stats.reload} />
        <section className={styles.founders}>
          <div className={styles.head}>
            <span className={styles.kicker}>{t('about.foundersKicker')}</span>
            <h2 className={styles.title}>{t('about.foundersTitle')}</h2>
          </div>

          <div className={styles.foundersGrid}>
            {founders.map((founder) => (
              <FounderCard
                accent={founder.colorAccent}
                background={founder.colorDark}
                bars={founder.bars}
                initial={founder.initial}
                key={founder.id}
                name={founder.name}
                role={founder.role}
                tags={founder.tags}
                text={founder.biography}
                title={founder.title}
              />
            ))}
          </div>
        </section>

        <section className={styles.timeline}>
          <div className={styles.timelineMain}>
            <div className={styles.head}>
              <span className={styles.kicker}>{t('about.timelineKicker')}</span>
              <h2 className={styles.title}>{t('about.timelineTitle')}</h2>
            </div>

            <ListSection items={aboutTimeline.map((item) => event({ ...item, ...t(item.copyKey) }))} />
          </div>

          <aside className={styles.aside}>
            <div className={styles.panel}>
              <span className={styles.panelKicker}>{t('about.adsKicker')}</span>
              <p className={styles.panelText}>{t('about.adsText')}</p>

              <div className={styles.funding}>
                <div className={styles.fundingHead}>
                  <span>{t('about.serverMonth')}</span>
                  <span className={styles.fundingValue}>
                    {t('about.covered', { percent: aboutSupport.percent })}
                  </span>
                </div>

                <div className={styles.bar}>
                  <div className={styles.barFill} style={{ width: `${aboutSupport.percent}%` }} />
                </div>

                <span className={styles.fundingDetail}>
                  {t('about.fundingDetail', {
                    raised: aboutSupport.raised ?? 0,
                    goal: aboutSupport.goal ?? 0,
                    people: aboutSupport.people,
                  })}
                </span>
              </div>

              <Link className={styles.panelAction} to={`/${language}/contact`}>
                {t('about.coffee')}
              </Link>
            </div>

            <div className={styles.panel}>
              <span className={styles.panelKicker}>{t('about.hallKicker')}</span>
              <span className={styles.panelNote}>{t('about.hallNote')}</span>

              <div className={styles.ranking}>
                {aboutHallOfFame.map((person, index) => (
                  <div className={styles.person} key={person.id}>
                    <span className={styles.rank}>{String(index + 1).padStart(2, '0')}</span>

                    <span
                      className={styles.initial}
                      style={{
                        '--person-dark': person.colorDark,
                        '--person-accent': person.colorAccent,
                      }}
                    >
                      {person.name[0]}
                    </span>

                    <span className={styles.personName}>
                      <Link
                        className={styles.personLink}
                        to={profilePath(language, person.username || person.name)}
                      >
                        {person.name}
                      </Link>
                      <span className={styles.personSince}>
                        {t('about.since', { year: person.since })}
                      </span>
                    </span>

                    <span className={styles.personCount}>{person.uploads}</span>
                  </div>
                ))}
              </div>
            </div>
          </aside>
        </section>
        <section className={styles.achievements}>
          <div className={styles.achievementsHead}>
            <div className={styles.head}>
              <span className={styles.kicker}>{t('about.achievementsKicker')}</span>
              <h2 className={styles.title}>{t('about.achievementsTitle')}</h2>
            </div>

            <span className={styles.achievementsCount}>
              {t('about.achievementsCount', { done, total, points })}
            </span>
          </div>

          <AchievementGrid
            columns={{ sm: 1, md: 2, lg: 4 }}
            items={aboutAchievements.map(achievement)}
          />
        </section>
      </main>
      <Footer />
    </div>
  );
}
