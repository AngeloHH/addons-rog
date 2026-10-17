import { Fragment } from 'react';
import styles from './DetailList.module.scss';

/**
 * Display term/value pairs; each value can be text or a React node.
 *
 * items  [{ term, value }]
 */
export default function DetailList({ items, title }) {
  return (
    <div className={styles.panel}>
      <span className={styles.title}>{title}</span>

      <dl className={styles.list}>
        {items.map((item) => (
          <Fragment key={item.term}>
            <dt className={styles.term}>{item.term}</dt>
            <dd className={styles.value}>{item.value}</dd>
          </Fragment>
        ))}
      </dl>
    </div>
  );
}
