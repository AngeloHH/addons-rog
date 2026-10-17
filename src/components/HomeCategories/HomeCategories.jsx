import { Link } from 'react-router-dom';
import { homeCategoryGroups } from '../../config/homeCategories.js';
import { exploreFilterPath } from '../../router/paths.js';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './HomeCategories.module.scss';

export default function HomeCategories({ taxonomy }) {
  const { language, t } = useI18n();

  return (
    <section className={styles.section} id="categories">
      <div className={styles.inner}>
        <div className={styles.head}>
          <div className={styles.kicker}>{t('homeCategories.kicker')}</div>
          <h2 className={styles.title}>{t('homeCategories.title')}</h2>
        </div>

        <div className={styles.groups}>
          {homeCategoryGroups.map((group) => (
            <div key={group.id}>
              <div className={styles.groupHead}>
                <h3 className={styles.groupTitle}>{t(group.titleKey)}</h3>
                <span className={styles.groupRule} />
                <span className={styles.groupCount}>
                  {t('homeCategories.categories', { count: group.items.length })}
                </span>
              </div>

              <div className={styles.grid}>
                {group.items.map((item) => (
                  <Link
                    key={item.id}
                    className={styles.item}
                    to={exploreFilterPath(language, group.filterKey, item.id)}
                  >
                    <img
                      alt=""
                      className={styles.itemIcon}
                      src={`/assets/categorias/${item.icon}.png`}
                    />
                    <span className={styles.itemText}>
                      <span className={styles.itemName}>{t(item.nameKey)}</span>
                      <span className={styles.itemCount}>
                        {t('homeCategories.addons', {
                          count:
                            taxonomy?.groups
                              ?.find((entry) => entry.id === group.filterKey)
                              ?.items.find((entry) => entry.id === item.id)?.addonCount ?? 0,
                        })}
                      </span>
                    </span>
                  </Link>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
