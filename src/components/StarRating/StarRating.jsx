import { useState } from 'react';
import Icon from '../Icon/Icon.jsx';
import styles from './StarRating.module.scss';

/**
 * Store the selected rating and notify onChange.
 * Use starLabel(n) for each star's accessible label.
 */
export default function StarRating({ count = 5, label, starLabel, onChange, initialValue = 0 }) {
  const [value, setValue] = useState(initialValue);
  const stars = Array.from({ length: count }, (_, index) => index + 1);

  function pick(star) {
    setValue(star);
    onChange?.(star);
  }

  return (
    <span className={styles.rating}>
      {label}{' '}
      <span className={styles.stars}>
        {stars.map((star) => (
          <button
            aria-label={starLabel?.(star)}
            aria-pressed={star === value}
            className={styles.star}
            key={star}
            type="button"
            onClick={() => pick(star)}
          >
            <Icon fill={star <= value ? 'currentColor' : 'none'} name="star" size={14} />
          </button>
        ))}
      </span>
    </span>
  );
}
