/**
 * Addons ROG - Panel
 *
 * Display the shared panel background, border, and inset shadow.
 *
 * title   Optional heading with a separator.
 * note    Small text beside the title.
 * aside   Trailing heading content, such as a link or button.
 * padded  False removes inner spacing for lists and tables.
 */

import styles from './Panel.module.scss';

export default function Panel({ aside, children, note, padded = true, title }) {
  return (
    <section className={styles.panel}>
      {(title || aside) && (
        <header className={styles.header}>
          <span className={styles.heading}>
            {title && <span className={styles.title}>{title}</span>}
            {note && <span className={styles.note}>{note}</span>}
          </span>

          {aside}
        </header>
      )}

      <div className={styles.body} data-padded={padded}>
        {children}
      </div>
    </section>
  );
}
