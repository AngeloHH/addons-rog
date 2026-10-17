import { Link } from 'react-router-dom';
import styles from './Pill.module.scss';

/**
 * Render a navigation link when to is supplied; otherwise render a static label.
 */
export default function Pill({ children, to }) {
  if (to) {
    return (
      <Link className={styles.pill} to={to}>
        {children}
      </Link>
    );
  }

  return <span className={styles.pill}>{children}</span>;
}
