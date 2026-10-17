/**
 * Addons ROG - Founder Card
 *
 * Display a person with an initial and role beside their profile details.
 *
 * accent      Personal accent color.
 * background  Personal background color.
 *
 * Render the name, title, biography, bars, and tags only when supplied.
 */

import BarList from '../BarList/BarList.jsx';
import Pill from '../Pill/Pill.jsx';
import styles from './FounderCard.module.scss';

export default function FounderCard({
  accent,
  background,
  bars = [],
  initial,
  name,
  role,
  tags = [],
  text,
  title,
}) {
  return (
    <article
      className={styles.card}
      style={{
        ...(accent && { '--person-accent': accent }),
        ...(background && { '--person-background': background }),
      }}
    >
      <div className={styles.side}>
        <span className={styles.initial}>{initial}</span>
        {role && <span className={styles.role}>{role}</span>}
      </div>

      <div className={styles.body}>
        <div className={styles.heading}>
          <span className={styles.name}>{name}</span>
          {title && <span className={styles.title}>{title}</span>}
        </div>

        {text && <p className={styles.text}>{text}</p>}

        {bars.length > 0 && (
          <div className={styles.bars}>
            <BarList accent={accent} items={bars} />
          </div>
        )}

        {tags.length > 0 && (
          <div className={styles.tags}>
            {tags.map((tag) => (
              <Pill key={tag}>{tag}</Pill>
            ))}
          </div>
        )}
      </div>
    </article>
  );
}
