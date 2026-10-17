import styles from './EmptyState.module.scss';

/**
 * Display the supplied title and text when a list is empty.
 */
export default function EmptyState({ text, title }) {
  return (
    <div className={styles.empty}>
      <span className={styles.title}>{title}</span>
      {text && <span className={styles.text}>{text}</span>}
    </div>
  );
}
