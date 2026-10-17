/**
 * Addons ROG - Callout Panel
 *
 * Display an illustration beside explanatory text and action links.
 *
 * orientation  'left' places the image and text on the left; 'right' mirrors the layout.
 * links        Up to two { label, to } actions; the first is gold and the second is dark.
 */

import { Link } from 'react-router-dom';
import styles from './CalloutPanel.module.scss';

export default function CalloutPanel({
  image,
  links = [],
  orientation = 'left',
  subtitle,
  text,
  title,
}) {
  return (
    <div className={styles.panel} data-orientation={orientation}>
      {image && <img alt="" className={styles.image} src={image} />}

      <div className={styles.content}>
        {subtitle && <div className={styles.subtitle}>{subtitle}</div>}
        <h2 className={styles.title}>{title}</h2>
        {text && <p className={styles.text}>{text}</p>}

        {links.length > 0 && (
          <div className={styles.links}>
            {links.map((link, index) => (
              <Link
                className={index === 0 ? styles.primary : styles.secondary}
                key={link.to ?? link.label}
                to={link.to}
              >
                {link.label}
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
