import Pill from '../Pill/Pill.jsx';
import styles from './TagList.module.scss';

/**
 * Display labeled tags with optional navigation targets.
 *
 * items  [{ label, to }]
 */
export default function TagList({ items, title }) {
  return (
    <div className={styles.panel}>
      <span className={styles.title}>{title}</span>

      <div className={styles.tags}>
        {items.map((item) => (
          <Pill key={item.label} to={item.to}>
            {item.label}
          </Pill>
        ))}
      </div>
    </div>
  );
}
