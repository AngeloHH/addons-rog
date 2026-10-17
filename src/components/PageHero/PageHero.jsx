/**
 * Addons ROG - Page Hero
 *
 * Display the shared page glow, kicker, heading, and introduction.
 * Render the supplied aside without a wrapper so each view controls its placement.
 */

import styles from './PageHero.module.scss';

export default function PageHero({ aside, kicker, text, title }) {
  return (
    <section className={styles.hero}>
      <div className={styles.glow} />

      <div className={styles.inner}>
        <div className={styles.content}>
          {kicker && (
            <div className={styles.kicker}>
              <span className={styles.kickerLine} />
              <span>{kicker}</span>
            </div>
          )}

          <h1 className={styles.title}>{title}</h1>
          {text && <p className={styles.text}>{text}</p>}
        </div>

        {aside}
      </div>
    </section>
  );
}
