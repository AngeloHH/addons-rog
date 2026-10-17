/**
 * Addons ROG - Feed
 *
 * Display incoming activity with its image, prepared text, and timestamp.
 *
 * items  [{ id, image, text, when, fresh }]
 * fresh  Highlights newly added entries.
 */

import styles from './Feed.module.scss';

export default function Feed({ items = [] }) {
  return (
    <div className={styles.feed}>
      {items.map((item) => (
        <div className={styles.item} data-fresh={item.fresh ? 'true' : 'false'} key={item.id}>
          {item.image && <img alt="" className={styles.image} src={item.image} />}
          <span className={styles.text}>{item.text}</span>
          <span className={styles.when}>{item.when}</span>
        </div>
      ))}
    </div>
  );
}
