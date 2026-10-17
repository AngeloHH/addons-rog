import { useMemo } from 'react';
import styles from './Motes.module.scss';

/**
 * Generate repeatable particle positions with a seeded sine function.
 * Avoid Math.random() so rendering preserves the same particle layout.
 */
function pseudoRandom(index) {
  const seed = Math.sin(index * 12.9898 + 78.233) * 43758.5453;
  return seed - Math.floor(seed);
}

function buildMotes(count) {
  return Array.from({ length: count }, (_, index) => ({
    left: `${(pseudoRandom(index) * 100).toFixed(1)}%`,
    top: `${(10 + pseudoRandom(index + 40) * 80).toFixed(1)}%`,
    size: `${4 + Math.round(pseudoRandom(index + 80) * 5)}px`,
    opacity: (0.25 + pseudoRandom(index + 120) * 0.5).toFixed(2),
    background: index % 3 === 0 ? '#e2bd63' : '#3a2a10',
    duration: `${(6 + pseudoRandom(index + 160) * 8).toFixed(1)}s`,
    delay: `${(-pseudoRandom(index + 200) * 8).toFixed(1)}s`,
  }));
}

/**
 * Render decorative particles without focus, pointer interaction, or screen-reader output.
 */
export default function Motes({ count = 14 }) {
  const motes = useMemo(() => buildMotes(count), [count]);

  return (
    <div aria-hidden="true" className={styles.motes}>
      {motes.map((mote, index) => (
        <span
          className={styles.mote}
          key={index}
          style={{
            '--left': mote.left,
            '--top': mote.top,
            '--size': mote.size,
            '--opacity': mote.opacity,
            '--background': mote.background,
            '--duration': mote.duration,
            '--delay': mote.delay,
          }}
        />
      ))}
    </div>
  );
}
