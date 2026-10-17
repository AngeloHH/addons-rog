import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import styles from './AddonTabs.module.scss';

// Leave room for the fixed header when scrolling to a section.
const scrollOffset = 96;

export default function AddonTabs({ tabs }) {
  const { hash } = useLocation();
  const navigate = useNavigate();
  const { t } = useI18n();

  const entries = Object.entries(tabs);
  const anchors = entries.filter(([, url]) => url.startsWith('#'));
  const activeHash = hash || anchors[0]?.[1];

  /**
   * Resolve an i18n key, falling back to the supplied label.
   */
  function label(key) {
    const translated = t(key);
    return translated === key ? key : translated;
  }

  function goToAnchor(event, url) {
    event.preventDefault();

    const element = document.getElementById(url.slice(1));

    if (element) {
      window.scrollTo({
        top: element.getBoundingClientRect().top + window.scrollY - scrollOffset,
        behavior: 'smooth',
      });
    }

    navigate({ hash: url }, { replace: true });
  }

  return (
    <div className={styles.tabs}>
      {entries.map(([key, url]) =>
        url.startsWith('#') ? (
          <a
            className={styles.tab}
            data-active={url === activeHash}
            href={url}
            key={key}
            onClick={(event) => goToAnchor(event, url)}
          >
            {label(key)}
          </a>
        ) : (
          <Link className={styles.tab} key={key} to={url}>
            {label(key)}
          </Link>
        ),
      )}
    </div>
  );
}
