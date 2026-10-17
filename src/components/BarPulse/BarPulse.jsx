/**
 * Addons ROG - Bar Pulse
 *
 * Display adjacent sample bars and highlight values above the threshold.
 *
 * values     Numbers from 0 to 100, used as bar heights.
 * threshold  Minimum value for the gold highlight.
 * height     Height of the sample strip.
 */

import styles from './BarPulse.module.scss';

export default function BarPulse({ height = 44, threshold = 70, tip, values = [] }) {
  return (
    <div className={styles.pulse} style={{ height: `${height}px` }}>
      {values.map((value, index) => (
        <span
          className={styles.bar}
          data-high={value > threshold ? 'true' : 'false'}
          key={index}
          style={{ height: `${value}%` }}
          title={tip?.(value, index)}
        />
      ))}
    </div>
  );
}
