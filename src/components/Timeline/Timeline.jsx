/**
 * Addons ROG - Timeline
 *
 * Display daily activity groups along a vertical timeline.
 * Each entry combines actor, role, action, target, details, and metadata.
 *
 * groups  [{ id, label, count, items }]
 * item    { id, time, initial, color, background, solid, who, whoTo, role,
 *           roleColor, verb, target, targetTo, detail, meta, kind }
 * solid   Fills the diamond for additions; removals use an outline.
 * color   Tints the diamond, detail border, and trailing badge.
 */

import { Link } from 'react-router-dom';
import styles from './Timeline.module.scss';

function Event({ item }) {
  const Who = item.whoTo ? Link : 'span';
  const Target = item.targetTo ? Link : 'span';

  return (
    <div className={styles.event} style={{ '--event-color': item.color }}>
      <span className={styles.when}>
        <span className={styles.time}>{item.time}</span>
        <span className={styles.dot} data-solid={Boolean(item.solid)} />
      </span>

      <span
        className={styles.avatar}
        style={{
          ...(item.avatarColor && { '--avatar-color': item.avatarColor }),
          ...(item.background && { '--avatar-background': item.background }),
        }}
      >
        {item.initial}
      </span>

      <span className={styles.body}>
        <span className={styles.line}>
          <Who className={styles.who} {...(item.whoTo ? { to: item.whoTo } : {})}>
            {item.who}
          </Who>

          {item.role && (
            <span className={styles.role} style={{ '--role-color': item.roleColor }}>
              {item.role}
            </span>
          )}

          <span>{item.verb}</span>

          <Target className={styles.target} {...(item.targetTo ? { to: item.targetTo } : {})}>
            {item.target}
          </Target>
        </span>

        {item.detail && <span className={styles.detail}>{item.detail}</span>}
        {item.meta && <span className={styles.meta}>{item.meta}</span>}
      </span>

      <span className={styles.kind}>{item.kind}</span>
    </div>
  );
}

export default function Timeline({ groups = [] }) {
  return (
    <>
      {groups.map((group) => (
        <div key={group.id}>
          <div className={styles.day}>
            <span className={styles.dayLabel}>{group.label}</span>
            <span className={styles.dayRule} />
            <span className={styles.dayCount}>{group.count}</span>
          </div>

          <div className={styles.track}>
            {group.items.map((item) => (
              <Event item={item} key={item.id} />
            ))}
          </div>
        </div>
      ))}
    </>
  );
}
