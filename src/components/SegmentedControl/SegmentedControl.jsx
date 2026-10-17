/**
 * Addons ROG - Segmented Control
 *
 * Render adjacent buttons with one controlled selection.
 *
 * options  [{ value, label }]
 * value    Currently selected option.
 * size     'sm' (34), 'md' (36, default), or 'lg' (38).
 */

import styles from './SegmentedControl.module.scss';

export default function SegmentedControl({ label, options = [], size = 'md', value, onChange }) {
  return (
    <span aria-label={label} className={styles.group} data-size={size} role="group">
      {options.map((option) => (
        <button
          aria-pressed={option.value === value}
          className={styles.option}
          data-selected={option.value === value ? 'true' : 'false'}
          key={option.value}
          type="button"
          onClick={() => onChange?.(option.value)}
        >
          {option.label}
        </button>
      ))}
    </span>
  );
}
