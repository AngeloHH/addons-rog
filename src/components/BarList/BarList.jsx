/**
 * Addons ROG - Bar List
 *
 * Display labeled percentage bars with a shared accent.
 *
 * items   [{ id, label, value }]
 * value   Sets the bar width and the value shown on the right.
 * accent  Sets the fill color; gold is the default.
 */

import styles from './BarList.module.scss';

export default function BarList({ accent, items }) {
  return (
    <div className={styles.bars} style={accent ? { '--bar-accent': accent } : undefined}>
      {items.map((item) => (
        <div className={styles.bar} key={item.id ?? item.label}>
          <span className={styles.label}>{item.label}</span>

          <span className={styles.track}>
            <span className={styles.fill} style={{ width: `${item.value}%` }} />
          </span>

          <span className={styles.value}>{item.value}</span>
        </div>
      ))}
    </div>
  );
}
