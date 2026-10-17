/**
 * Addons ROG - Achievement Grid
 *
 * Display achievement cards with active or muted visual states.
 *
 * items    [{ id, title, text, value, unit, active }]
 * active   Controls the diamond and gold frame; its meaning belongs to the caller.
 * columns  { sm: 1, md: 2, lg: 4 } columns by screen size.
 */

import { columnClasses } from '../../lib/columns.js';
import styles from './AchievementGrid.module.scss';

export default function AchievementGrid({ columns, items }) {
  const className = [styles.grid, ...columnClasses(styles, columns)].filter(Boolean).join(' ');

  return (
    <div className={className}>
      {items.map((item) => (
        <div className={styles.item} data-active={item.active ? 'true' : 'false'} key={item.id}>
          <span className={styles.mark}>
            <span className={styles.diamond} />
          </span>

          <span className={styles.body}>
            <span className={styles.title}>{item.title}</span>
            {item.text && <span className={styles.text}>{item.text}</span>}
          </span>

          {item.value != null && (
            <span className={styles.value}>
              <span className={styles.number}>{item.value}</span>
              {item.unit && <span className={styles.unit}>{item.unit}</span>}
            </span>
          )}
        </div>
      ))}
    </div>
  );
}
