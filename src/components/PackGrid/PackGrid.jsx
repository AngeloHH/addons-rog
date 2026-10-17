/**
 * Addons ROG - Pack Grid
 *
 * Display addon packs with expansion choices and download actions.
 *
 * items    [{ id, image, title, meta, description, tags, options, action }]
 * options  [{ id, label }]
 * action   { label, to }
 * columns  { sm: 1, md: 2, lg: 3 } columns by screen size.
 */

import { useState } from 'react';
import Pill from '../Pill/Pill.jsx';
import { columnClasses } from '../../lib/columns.js';
import styles from './PackGrid.module.scss';

export default function PackGrid({ columns, items, selectLabel }) {
  const [selected, setSelected] = useState({});
  const className = [styles.grid, ...columnClasses(styles, columns)].filter(Boolean).join(' ');

  return (
    <div className={className}>
      {items.map((item, index) => {
        const option =
          item.options?.find((option) => option.id === selected[item.id]) || item.options?.[0];
        return (
          <article className={styles.card} key={item.id ?? index}>
            <div className={styles.header}>
              <img alt="" className={styles.icon} src={item.image} />
              <div>
                <h3 className={styles.title}>{item.title}</h3>
                <div className={styles.meta}>{option?.meta || item.meta}</div>
              </div>
            </div>

            <p className={styles.description}>{item.description}</p>

            {item.tags && (
              <div className={styles.tags}>
                {item.tags.map((tag) => (
                  <Pill key={tag}>{tag}</Pill>
                ))}
              </div>
            )}

            <div className={styles.footer}>
              {item.options && (
                <select
                  aria-label={selectLabel}
                  className={styles.select}
                  value={option?.id || ''}
                  onChange={(event) =>
                    setSelected((previous) => ({ ...previous, [item.id]: event.target.value }))
                  }
                >
                  {item.options.map((option) => (
                    <option key={option.id} value={option.id}>
                      {option.label}
                    </option>
                  ))}
                </select>
              )}

              {item.action && option?.href && (
                <a className={styles.action} href={option.href}>
                  {item.action.label}
                </a>
              )}
            </div>
          </article>
        );
      })}
    </div>
  );
}
