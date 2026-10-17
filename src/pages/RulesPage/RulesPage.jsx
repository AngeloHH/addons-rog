import { useState } from 'react';
import { Link } from 'react-router-dom';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Checker from '../../components/Checker/Checker.jsx';
import Icon from '../../components/Icon/Icon.jsx';
import ListSection from '../../components/ListSection/ListSection.jsx';
import PageHero from '../../components/PageHero/PageHero.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import SiteHeader from '../../components/SiteHeader/SiteHeader.jsx';
import { rulesArticles as articleDefinitions } from '../../config/rulesArticles.js';
import { rulesChecks as checkDefinitions } from '../../config/rulesChecks.js';
import { rulesNotices as noticeDefinitions, rulesThreat as threatDefinitions } from '../../config/rulesConsequences.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './RulesPage.module.scss';

/**
 * Build the rules verdict from the current answers.
 * Missing answers keep the checklist pending; critical failures block uploading.
 * Other failures ask the uploader to make corrections.
 */
function buildVerdict(answers, checks, language, t) {
  const pending = checks.filter((check) => !(check.id in answers));
  const failed = checks.filter((check) => check.id in answers && answers[check.id] !== check.expected);

  if (pending.length > 0) {
    return {
      tone: 'neutral',
      title: t(pending.length === 1 ? 'rules.verdictPendingOne' : 'rules.verdictPendingMany', {
        count: pending.length,
      }),
      text: t('rules.verdictPendingText'),
    };
  }

  if (failed.some((check) => check.critical)) {
    return { tone: 'danger', title: t('rules.verdictBlocked'), text: failed[0].failure };
  }

  if (failed.length > 0) {
    return {
      tone: 'warning',
      title: t(failed.length === 1 ? 'rules.verdictFixOne' : 'rules.verdictFixMany', {
        count: failed.length,
      }),
      text: failed.map((check) => check.failure).join(' '),
    };
  }

  return {
    tone: 'success',
    title: t('rules.verdictOk'),
    text: t('rules.verdictOkText'),
    action: { label: t('rules.uploadAddon'), to: `/${language}/upload` },
  };
}

/**
 * Group each rule's text and examples in ListSection's custom content slot.
 */
function article(rule, index) {
  return {
    id: rule.id,
    mark: `Art. ${index + 1}`,
    title: rule.title,
    badge: { label: rule.severity, color: rule.severityColor },
    tone: { dot: index < 2 ? '#e2bd63' : '#3a2a10' },
    content: (
      <>
        <p className={styles.articleText}>{rule.text}</p>

        <div className={styles.examples}>
          <span className={styles.example} data-tone="positive">
            <Icon name="check" size={14} />
            <span>{rule.allowed}</span>
          </span>
          <span className={styles.example} data-tone="negative">
            <Icon name="close" size={14} />
            <span>{rule.forbidden}</span>
          </span>
        </div>
      </>
    ),
  };
}

export default function RulesPage() {
  const { language, t } = useI18n();
  const [answers, setAnswers] = useState({});
  const rulesArticles = articleDefinitions.map((item) => ({
    ...item, ...t(item.copyKey), severity: t(item.severityKey),
  }));
  const rulesChecks = checkDefinitions.map((item) => ({ ...item, ...t(item.copyKey) }));
  const rulesThreat = threatDefinitions.map((item) => ({ ...item, ...t(item.copyKey), level: t(item.levelKey) }));
  const rulesNotices = noticeDefinitions.map((item) => ({ ...item, ...t(item.copyKey) }));

  function answer(id, value) {
    setAnswers((current) => ({ ...current, [id]: value }));
  }

  return (
    <div className="site-frame">
      <AuthBar />
      <SiteHeader />
      <main className="app-shell">
        <PageHero
          aside={
            <div className={styles.heroActions}>
              <Link className={styles.primaryAction} to={`/${language}/rules#checker`}>
                {t('rules.checker')}
              </Link>
              <Link className={styles.action} to={`/${language}/upload`}>
                {t('rules.goToUpload')}
              </Link>
            </div>
          }
          kicker={t('rules.kicker')}
          text={t('rules.intro')}
          title={t('rules.title')}
        />

        <section className={styles.rules}>
          <div className={styles.articles}>
            <div className={styles.articlesHead}>
              <h2 className={styles.articlesTitle}>{t('rules.articlesTitle')}</h2>
              <span className={styles.articlesNote}>{t('rules.articlesNote')}</span>
            </div>

            <ListSection items={rulesArticles.map(article)} />
          </div>

          <aside className={styles.aside} id="checker">
            <Checker
              answers={answers}
              kicker={t('rules.checkerKicker')}
              note={t('rules.checkerNote')}
              options={[
                { value: true, label: t('rules.yes'), tone: 'accept' },
                { value: false, label: t('rules.no'), tone: 'reject' },
              ]}
              questions={rulesChecks.map((check) => ({ id: check.id, text: check.question }))}
              title={t('rules.checker')}
              verdict={buildVerdict(answers, rulesChecks, language, t)}
              onAnswer={answer}
            />
          </aside>
        </section>

        <section className={styles.consequences}>
          <div className={styles.consequencesHead}>
            <span className={styles.consequencesKicker}>{t('rules.threatKicker')}</span>
            <h2 className={styles.articlesTitle}>{t('rules.threatTitle')}</h2>
            <p className={styles.consequencesText}>{t('rules.threatText')}</p>
          </div>

          <div className={styles.threat}>
            {rulesThreat.map((level) => (
              <div className={styles.level} key={level.id} style={{ '--level-color': level.color }}>
                <div className={styles.bar}>
                  <div className={styles.barFill} style={{ width: `${level.percent}%` }} />
                </div>

                <span className={styles.levelHead}>
                  <span className={styles.levelName}>{level.level}</span>
                  <span className={styles.levelPercent}>{level.percent}%</span>
                </span>

                <span className={styles.levelTitle}>{level.title}</span>
                <span className={styles.levelText}>{level.text}</span>
                <span className={styles.levelDrop}>
                  <span className={styles.levelDropLabel}>{t('rules.howToDrop')} · </span>
                  {level.howToDrop}
                </span>
              </div>
            ))}
          </div>

          <div className={styles.notices}>
            {rulesNotices.map((notice) => (
              <div className={styles.notice} key={notice.id}>
                <span className={styles.noticeKicker}>{notice.kicker}</span>
                <p className={styles.noticeText}>
                  {notice.before}
                  {notice.link && (
                    <>
                      <Link className={styles.noticeLink} to={`/${language}`}>
                        {notice.link}
                      </Link>
                      {notice.after}
                    </>
                  )}
                </p>
              </div>
            ))}
          </div>
        </section>
      </main>
      <Footer />
    </div>
  );
}



