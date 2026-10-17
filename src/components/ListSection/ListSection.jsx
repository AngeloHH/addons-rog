/**
 * Addons ROG - List Section
 *
 * Render reusable list rows and optional featured cards.
 *
 * mark         Leading column; moves into the body on small screens.
 * title        Main heading.
 * badge        Optional framed label.
 * description  Optional supporting text.
 * content      Custom JSX replacing description; the caller owns its inner styles.
 * meta         Small text below the heading.
 * aside        Trailing text before the action.
 *
 * Rows without a badge or body use the larger heading style.
 * action     Enables the trailing action button.
 * markStyle  'display' enlarges the mark; 'plain' uses small text; 'dot' shows a diamond.
 * card       Overrides the two text values reused by the featured card.
 * to         Makes the whole row a link and its trailing button decorative.
 */

import { Link } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import styles from './ListSection.module.scss';

function toneStyle(tone) {
  if (!tone) return undefined;

  return {
    ...(tone.accent && { '--tone-accent': tone.accent }),
    ...(tone.background && { '--tone-background': tone.background }),
    ...(tone.card && { '--tone-card': tone.card }),
    ...(tone.dot && { '--tone-dot': tone.dot }),
    ...(tone.hover && { '--tone-hover': tone.hover }),
  };
}

function Clickable({ children, className, item, ...rest }) {
  if (item.to) {
    return (
      <Link className={className} to={item.to} {...rest}>
        {children}
      </Link>
    );
  }

  return (
    <a className={className} href={item.href || item.action?.href} {...rest}>
      {children}
    </a>
  );
}

export default function ListSection({ items, markStyle = 'display', primary = 0 }) {
  const cards = items.slice(0, primary);

  return (
    <div className={styles.list}>
      {cards.length > 0 && (
        <div className={styles.cards}>
          {cards.map((item, index) => (
            <Clickable
              className={styles.card}
              item={item}
              key={item.id ?? index}
              style={toneStyle(item.tone)}
            >
              <span className={styles.cardMark}>{item.mark}</span>
              {(item.card?.kicker ?? item.meta) && (
                <span className={styles.cardKicker}>{item.card?.kicker ?? item.meta}</span>
              )}
              <span className={styles.cardTitle}>{item.title}</span>
              {(item.card?.footer ?? item.aside) && (
                <span className={styles.cardFooter}>{item.card?.footer ?? item.aside}</span>
              )}
            </Clickable>
          ))}
        </div>
      )}

      <div className={styles.rows} data-mark={markStyle}>
        <div className={styles.rowsLine} />

        {items.map((item, index) => {
          const Row = item.to ? Link : 'div';
          const Action = item.to ? 'span' : 'a';

          return (
            <Row
              className={styles.row}
              data-content={item.content ? 'true' : 'false'}
              data-headline={!item.description && !item.content && !item.badge}
              data-highlight={item.highlight === true}
              data-in-cards={index < primary}
              key={item.id ?? index}
              style={toneStyle(item.tone)}
              {...(item.to ? { to: item.to } : {})}
            >
              <span className={styles.mark}>
                <span className={styles.markText}>{item.mark}</span>
                <span className={styles.dot} />
              </span>

              <span className={styles.body}>
                <span className={styles.titleRow}>
                  <span className={styles.markInline}>{item.mark}</span>
                  <span className={styles.itemTitle}>{item.title}</span>
                  {item.badge && (
                    <span
                      className={styles.badge}
                      style={item.badge.color ? { '--badge-color': item.badge.color } : undefined}
                    >
                      {item.badge.label}
                    </span>
                  )}
                </span>
                {item.content ? (
                  <span className={styles.content}>{item.content}</span>
                ) : (
                  item.description && <span className={styles.description}>{item.description}</span>
                )}
                {item.meta && <span className={styles.meta}>{item.meta}</span>}
              </span>

              {item.aside && <span className={styles.aside}>{item.aside}</span>}

              {item.action && (
                <Action
                  aria-label={item.action.label}
                  className={styles.action}
                  {...(item.to ? { 'aria-hidden': 'true' } : { href: item.action.href })}
                >
                  <Icon
                    name={item.action.icon === 'access' ? 'chevronRight' : 'download'}
                    size={15}
                  />
                </Action>
              )}
            </Row>
          );
        })}
      </div>
    </div>
  );
}
