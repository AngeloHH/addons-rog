import { useApi } from '../../hooks/useApi.js';
import { number } from '../../api/presenters.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './AccountIntro.module.scss';

export default function AccountIntro() {
  const { t, language } = useI18n();
  const resource = useApi('/site/stats');
  const accountStats = [
    {
      value: resource.data ? number(resource.data.players, language) : '—',
      labelKey: 'account.registeredPlayers',
    },
    {
      value: resource.data ? number(resource.data.addons, language) : '—',
      labelKey: 'account.uploadedAddons',
    },
  ];
  const benefits = t('account.benefits');

  return (
    <div className={styles.accountIntro}>
      <div className={styles.kicker}>
        <span className={styles.kickerLine}></span>
        <span>{t('account.kicker')}</span>
      </div>

      <h1 className={styles.title}>{t('account.title')}</h1>

      <div className={styles.desktopOnly}>
        <ul className={styles.benefits}>
          {benefits.map((benefit) => (
            <li key={benefit}>
              <span className={styles.bullet}></span>
              <span>{benefit}</span>
            </li>
          ))}
        </ul>

        <div className={styles.stats}>
          {accountStats.map((stat) => (
            <span className={styles.stat} key={stat.labelKey}>
              <span className={styles.statValue}>{stat.value}</span>
              <span>{t(stat.labelKey)}</span>
            </span>
          ))}
        </div>
      </div>
    </div>
  );
}
