import styles from './ExplorePaginator.module.scss';

export default function ExplorePaginator({ remaining = 0, onLoadMore }) {
  if (remaining <= 0) {
    return null;
  }

  return (
    <div className={styles.paginator}>
      <button type="button" onClick={onLoadMore}>
        Load more ({remaining} left)
      </button>
    </div>
  );
}
