/**
 * Addons ROG - Marked Links
 *
 * Render marked text rows or links with optional framing.
 *
 * items  [{ id, label, trailing, to, icon, muted, tone: { accent, background } }]
 * icon   Replaces the diamond marker.
 * muted  Dims the row.
 * to     Enables navigation; absent values leave the item as text.
 * boxed  False removes row frames and aligns the marker with the first text line.
 * link   { label, to } action below the panel.
 */

import { Link } from 'react-router-dom';
import Icon from '../Icon/Icon.jsx';
import styles from './MarkedLinks.module.scss';

export default function MarkedLinks({ boxed = true, items, link, title }) {
  return (
    <div className={styles.panel}>
      <span className={styles.title}>{title}</span>

      <div className={styles.links} data-boxed={boxed}>
        {items.map((item) => {
          const Item = item.to ? Link : 'span';

          return (
            <Item
              className={styles.link}
              data-link={item.to ? 'true' : 'false'}
              data-muted={item.muted ? 'true' : 'false'}
              key={item.id ?? item.label}
              {...(item.to ? { to: item.to } : {})}
            >
              <span className={styles.name}>
                {item.icon ? (
                  <span
                    className={styles.mark}
                    style={item.tone?.accent ? { '--tone-accent': item.tone.accent } : undefined}
                  >
                    <Icon name={item.icon} size={13} />
                  </span>
                ) : (
                  <span
                    className={styles.diamond}
                    style={{
                      ...(item.tone?.accent && { '--tone-accent': item.tone.accent }),
                      ...(item.tone?.background && { '--tone-background': item.tone.background }),
                    }}
                  />
                )}
                {item.label}
              </span>
              {item.trailing && <span className={styles.trailing}>{item.trailing}</span>}
            </Item>
          );
        })}
      </div>

      {link && (
        <Link className={styles.footerLink} to={link.to}>
          {link.label}
          <Icon name="chevronRight" size={14} />
        </Link>
      )}
    </div>
  );
}
