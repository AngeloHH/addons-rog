/**
 * Addons ROG - Stat Tiles
 *
 * Display statistic tiles with optional filtering controls.
 *
 * items     [{ id, label, value, delta, tone, sub, color }]
 * tone      'up', 'down', or 'flat' by default; affects the delta color.
 * size      'sm' by default, 'md', or 'lg'; controls spacing and type size.
 * columns   { sm, md, lg } using the shared column utilities.
 * onSelect  Makes tiles filter buttons; value identifies the selected tile.
 *
 * Ordinary tiles color the value; filter tiles color the label and diamond.
 */

import { columnClasses } from '../../lib/columns.js';
import styles from './StatTiles.module.scss';

export default function StatTiles({ columns, items = [], onSelect, size = 'sm', value }) {
  const className = [styles.tiles, ...columnClasses(styles, columns)].filter(Boolean).join(' ');
  const Box = onSelect ? 'button' : 'div';

  return (
    <div className={className} data-filter={onSelect ? 'true' : undefined} data-size={size}>
      {items.map((item) => (
        <Box
          className={styles.tile}
          data-active={onSelect && item.id === value ? 'true' : undefined}
          key={item.id ?? item.label}
          style={item.color ? { '--tile-color': item.color } : undefined}
          type={onSelect ? 'button' : undefined}
          onClick={onSelect ? () => onSelect(item.id === value ? undefined : item.id) : undefined}
        >
          <span className={styles.label}>
            {onSelect && item.color && <span className={styles.dot} />}
            {item.label}
          </span>

          <span className={styles.row}>
            <span className={styles.value}>{item.value}</span>
            {item.delta && (
              <span className={styles.delta} data-tone={item.tone ?? 'flat'}>
                {item.delta}
              </span>
            )}
          </span>

          {item.sub && <span className={styles.sub}>{item.sub}</span>}
        </Box>
      ))}
    </div>
  );
}
