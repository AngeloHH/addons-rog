import { columnClasses } from '../../lib/columns.js';
import styles from './GridSection.module.scss';

export default function GridSection({ columns, images = false, items, title }) {
  const gridClassName = [styles.grid, images && styles.gridImages, ...columnClasses(styles, columns)]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={styles.section} data-images={images}>
      <h3 className={styles.title}>{title}</h3>

      <ul className={gridClassName}>
        {items.map((item) => (
          <li className={styles.item} key={item}>
            {images ? (
              // Keep the frame visible while the image URL loads or when the image is missing.
              <img alt="" className={styles.image} src={item} />
            ) : (
              <>
                <span className={styles.diamond} />
                <span>{item}</span>
              </>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
