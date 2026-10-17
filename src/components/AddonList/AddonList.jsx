import { Link } from 'react-router-dom';
import AddonCard from '../AddonCard/AddonCard.jsx';
import Icon from '../Icon/Icon.jsx';
import styles from './AddonList.module.scss';

export default function AddonList({ addons, compact = false, kicker, linkLabel, linkTo }) {
  return (
    <div>
      <div className={styles.header}>
        <div>
          <div className={styles.kicker}>{kicker}</div>
        </div>
        <Link className={styles.link} to={linkTo}>
          {linkLabel}
          <Icon className={styles.linkArrow} name="arrowRight" size={14} />
        </Link>
      </div>

      <div className={styles.grid}>
        {addons.map((addon) => (
          <AddonCard addon={addon} compact={compact} key={addon.name} />
        ))}
      </div>
    </div>
  );
}
