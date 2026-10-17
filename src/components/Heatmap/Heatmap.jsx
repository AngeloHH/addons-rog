/**
 * Addons ROG - Heatmap
 *
 * Display a grid of cells colored by intensity.
 *
 * cells    [{ id, level, label }]; level ranges from 0 to 3 and label is the tooltip.
 * columns  Number of cells per row; defaults to 7.
 * title    Heading above the grid.
 * note     Supporting text below the grid.
 */

import styles from './Heatmap.module.scss';

export default function Heatmap({ cells = [], columns = 7, note, title }) {
  return (
    <div className={styles.panel}>
      {title && <span className={styles.title}>{title}</span>}

      <div className={styles.grid} style={{ '--heatmap-columns': columns }}>
        {cells.map((cell, index) => (
          <span
            className={styles.cell}
            data-level={cell.level}
            key={cell.id ?? index}
            title={cell.label}
          />
        ))}
      </div>

      {note && <span className={styles.note}>{note}</span>}
    </div>
  );
}
