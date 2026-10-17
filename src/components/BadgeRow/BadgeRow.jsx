/**
 * Addons ROG - Badge Row
 *
 * Render shared row elements from plain values.
 *
 * badge  { label, color }
 * link   { label, to }
 *
 * The component owns the stars, badges, links, and buttons so views share their styles.
 */

import { Link } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import styles from './BadgeRow.module.scss';

const stars = [1, 2, 3, 4, 5];

export default function BadgeRow({
  as: Tag = 'div',
  badge,
  boxed = false,
  lead,
  likes,
  link,
  mark,
  rating,
  subtitle,
  text,
  title,
  titleTo,
  tone,
}) {
  const toneStyle = tone && {
    ...(tone.accent && { '--tone-accent': tone.accent }),
    ...(tone.background && { '--tone-background': tone.background }),
  };

  return (
    <Tag className={styles.row} data-boxed={boxed} data-likes={Boolean(likes)} style={toneStyle}>
      <span className={styles.mark}>{mark}</span>

      <div className={styles.body}>
        <div className={styles.head}>
          {titleTo ? (
            <Link className={styles.title} to={titleTo}>
              {title}
            </Link>
          ) : (
            <span className={styles.title}>{title}</span>
          )}

          {subtitle && (
            <>
              <span className={styles.separator}>·</span>
              <span>{subtitle}</span>
            </>
          )}

          {rating > 0 && (
            <span className={styles.stars}>
              {stars.map((star) => (
                <Icon
                  fill={star <= Math.round(rating) ? 'currentColor' : 'none'}
                  key={star}
                  name="star"
                  size={13}
                />
              ))}
            </span>
          )}

          {badge && (
            <span
              className={styles.badge}
              style={badge.color ? { '--badge-color': badge.color } : undefined}
            >
              {badge.label}
            </span>
          )}
        </div>

        <p className={styles.text}>
          {lead && <strong className={styles.lead}>{lead}</strong>}
          {lead && text ? ' — ' : ''}
          {text}
        </p>

        {link && (
          <div className={styles.footer}>
            <Link className={styles.link} to={link.to}>
              {link.label}
              <Icon className={styles.linkArrow} name="arrowRight" size={14} />
            </Link>
          </div>
        )}
      </div>

      {likes && (
        <button
          aria-label={likes.label}
          className={styles.likes}
          type="button"
          onClick={likes.onClick}
          disabled={likes.disabled}
          aria-pressed={likes.active}
        >
          <Icon name="chevronUp" size={14} />
          <span className={styles.likesCount}>{likes.count}</span>
        </button>
      )}
    </Tag>
  );
}
