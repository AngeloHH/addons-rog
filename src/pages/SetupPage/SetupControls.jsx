import Icon from '../../components/Icon/Icon.jsx';
import styles from './SetupPage.module.scss';

export function Field({ children, hint, label, wide = false }) {
  return (
    <label className={styles.field} data-wide={wide}>
      <span className={styles.fieldLabel}>{label}</span>
      {children}
      {hint && <span className={styles.fieldHint}>{hint}</span>}
    </label>
  );
}

export function Action({ children, icon, secondary = false, ...props }) {
  return (
    <button className={secondary ? styles.secondaryAction : styles.action} type="button" {...props}>
      {icon === 'back' && <Icon name="arrowLeft" size={15} />}
      {children}
      {icon === 'next' && <Icon name="arrowRight" size={15} />}
    </button>
  );
}

/**
 * Display a status note using a muted, ok, warning, or danger tone.
 */
export function Note({ children, role, tone = 'muted' }) {
  return (
    <p className={styles.note} data-tone={tone} role={role}>
      {children}
    </p>
  );
}

export function Actions({ children }) {
  return <div className={styles.actions}>{children}</div>;
}
