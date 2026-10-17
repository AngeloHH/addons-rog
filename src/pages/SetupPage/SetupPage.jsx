import { Link } from 'react-router-dom';
import Footer from '../../components/Footer/Footer.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import { setupSteps } from './setupConfig.js';
import { Note } from './SetupControls.jsx';
import { useSetupText } from './setupText.js';
import { useSetupWizard } from './useSetupWizard.js';
import DatabaseStep from './steps/DatabaseStep.jsx';
import FounderStep from './steps/FounderStep.jsx';
import InstallStep from './steps/InstallStep.jsx';
import RequirementsStep from './steps/RequirementsStep.jsx';
import SiteStep from './steps/SiteStep.jsx';
import styles from './SetupPage.module.scss';

const stepComponents = {
  requirements: RequirementsStep,
  database: DatabaseStep,
  site: SiteStep,
  founder: FounderStep,
  install: InstallStep,
};

function errorText(t, error) {
  if (error.code === 'VALIDATION_FAILED') return t('errors.VALIDATION_FAILED', { fields: error.fields.join(', ') });

  const text = t(`errors.${error.code}`);
  return text === `errors.${error.code}` ? t('errors.UNKNOWN', { code: error.code }) : text;
}

export default function SetupPage() {
  const { language } = useI18n();
  const t = useSetupText();
  const wizard = useSetupWizard();
  const { checks, error, installed, run, step, stepIndex } = wizard;
  const StepContent = stepComponents[step];
  const php = checks?.checks.find((check) => check.id === 'php')?.value ?? t('notChecked');
  const finished = run.finished;

  return (
    <div className="site-frame">
      <main className={styles.page}>
        <div className={styles.topBar}>
          <span>{t('topBar', { php })}</span>
          <span className={styles.lockNotice}>{t('autoLock')}</span>
        </div>

        <header className={styles.hero}>
          <img alt="RoG" className={styles.logo} src="/assets/logo-rog.png" />
          <span className={styles.kicker}>{t('kicker')}</span>
          <h1 className={styles.title}>{finished ? t('done.title') : t(`steps.${step}.title`)}</h1>
          {!finished && <p className={styles.intro}>{t(`steps.${step}.text`)}</p>}
        </header>

        <ol className={styles.rail} aria-label={t('progress')}>
          {setupSteps.map((id, index) => {
            const done = finished || index < stepIndex;
            const state = done ? 'done' : index === stepIndex ? 'active' : 'next';

            return (
              <li aria-current={state === 'active' ? 'step' : undefined} className={styles.railStep} data-state={state} key={id}>
                <span className={styles.railNumber}>{done ? '✓' : index + 1}</span>
                <span className={styles.railLabel}>{t(`steps.${id}.label`)}</span>
              </li>
            );
          })}
        </ol>

        <section aria-label={t(`steps.${step}.label`)} className={styles.card}>
          {error && (
            <Note role="alert" tone="danger">
              {errorText(t, error)}
            </Note>
          )}

          {installed && !finished && (
            <Note role="status" tone="ok">
              {t('lockedNotice')}{' '}
              <Link className={styles.noteLink} to={`/${language}/login`}>
                {t('lockedLink')}
              </Link>
              .
            </Note>
          )}

          <StepContent t={t} wizard={wizard} />
        </section>
      </main>
      <Footer />
    </div>
  );
}
