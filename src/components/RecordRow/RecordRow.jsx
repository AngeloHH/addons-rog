/**
 * Addons ROG - Record Row
 *
 * Render moderation records with metadata and optional action menus.
 *
 * time     Timestamp in a narrow leading column.
 * accent   Leading stripe color when no image is supplied.
 * score    Leading score box with an upward arrow.
 * initial  { label, color, background } in place of the score.
 * badge    { label, color } at the end of the row.
 * tags     [{ label, color, plain }]; uncolored tags stay muted and unframed.
 *          plain keeps colored tags unframed.
 * text     Optional main text.
 * meta     Custom metadata, including inline links.
 * flag     { text, tone } for scanner warnings.
 * note     Uploader-provided note.
 * actions  [{ id, label, icon, variant, tone, to, onClick, items }]
 *          variant 'gold' highlights; tone controls text color; items creates a menu.
 * compact  Reduces image size and hides text on icon-bearing action buttons.
 */

import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import styles from './RecordRow.module.scss';

function Button({ action, iconOnly }) {
  const Tag = action.to ? Link : action.href ? 'a' : 'button';

  return (
    <Tag
      aria-label={iconOnly ? action.label : undefined}
      className={styles.action}
      data-icon-only={iconOnly ? 'true' : 'false'}
      data-tone={action.tone ?? 'plain'}
      data-variant={action.variant ?? 'plain'}
      {...(action.to
        ? { to: action.to }
        : action.href
          ? { href: action.href }
          : { type: 'button', onClick: action.onClick, disabled: action.disabled })}
    >
      {action.icon && <Icon name={action.icon} size={14} />}
      {!iconOnly && action.label}
    </Tag>
  );
}

/**
 * Display actions in a menu that closes on an outside click or Escape.
 */
function Menu({ action }) {
  const [open, setOpen] = useState(false);
  const box = useRef(null);

  useEffect(() => {
    if (!open) return undefined;

    function away(event) {
      if (!box.current?.contains(event.target)) setOpen(false);
    }

    function escape(event) {
      if (event.key === 'Escape') setOpen(false);
    }

    document.addEventListener('mousedown', away);
    document.addEventListener('keydown', escape);

    return () => {
      document.removeEventListener('mousedown', away);
      document.removeEventListener('keydown', escape);
    };
  }, [open]);

  return (
    <span className={styles.menu} ref={box}>
      <button
        aria-expanded={open}
        aria-haspopup="menu"
        className={styles.action}
        data-tone={action.tone ?? 'plain'}
        data-variant={action.variant ?? 'plain'}
        type="button"
        onClick={() => setOpen((current) => !current)}
      >
        {action.label}
        <Icon name={open ? 'chevronUp' : 'chevronDown'} size={13} />
      </button>

      {open && (
        <span className={styles.menuList} role="menu">
          {action.items.map((item) => (
            <button
              className={styles.menuItem}
              data-tone={item.tone ?? 'plain'}
              key={item.id ?? item.label}
              role="menuitem"
              type="button"
              onClick={() => {
                setOpen(false);
                item.onClick?.();
              }}
            >
              {item.label}
            </button>
          ))}
        </span>
      )}
    </span>
  );
}

export default function RecordRow({
  accent,
  actions = [],
  badge,
  initial,
  score,
  time,
  compact = false,
  flag,
  image,
  meta,
  note,
  tags = [],
  text,
  title,
  titleTo,
}) {
  return (
    <div
      className={styles.row}
      data-compact={compact}
      style={accent ? { '--row-accent': accent } : undefined}
    >
      {time && <span className={styles.time}>{time}</span>}
      {image && <img alt="" className={styles.image} src={image} />}
      {!image && accent && <span className={styles.accent} />}
      {initial && (
        <span
          className={styles.initial}
          style={{
            ...(initial.color && { '--initial-color': initial.color }),
            ...(initial.background && { '--initial-background': initial.background }),
          }}
        >
          {initial.label}
        </span>
      )}
      {score != null && (
        <span className={styles.score}>
          <Icon name="chevronUp" size={12} />
          {score}
        </span>
      )}

      <div className={styles.body}>
        <span className={styles.head}>
          {title &&
            (titleTo ? (
              <Link className={styles.title} to={titleTo}>
                {title}
              </Link>
            ) : (
              <span className={styles.title}>{title}</span>
            ))}

          {tags.map((tag) => (
            <span
              className={styles.tag}
              data-plain={tag.plain ? 'true' : 'false'}
              key={tag.label}
              style={tag.color ? { '--tag-color': tag.color } : undefined}
            >
              {tag.label}
            </span>
          ))}
        </span>

        {text && <span className={styles.text}>{text}</span>}
        {meta && <span className={styles.meta}>{meta}</span>}

        {flag && (
          <span className={styles.flag} data-tone={flag.tone ?? 'danger'}>
            <Icon name="flag" size={12} />
            {flag.text}
          </span>
        )}

        {note && <span className={styles.note}>{`“${note}”`}</span>}
      </div>

      {badge && (
        <span
          className={styles.badge}
          style={badge.color ? { '--badge-color': badge.color } : undefined}
        >
          {badge.label}
        </span>
      )}

      {actions.length > 0 && (
        <span className={styles.actions}>
          {actions.map((action) =>
            action.items ? (
              <Menu action={action} key={action.id ?? action.label} />
            ) : (
              <Button
                action={action}
                iconOnly={compact && Boolean(action.icon) && action.variant !== 'gold'}
                key={action.id ?? action.label}
              />
            ),
          )}
        </span>
      )}
    </div>
  );
}
