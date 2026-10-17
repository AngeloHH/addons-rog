/**
 * Addons ROG - Pager
 *
 * Build a page selector while leaving data pagination to the caller.
 *
 * page / pages  Current page and total pages, starting at 1.
 * summary       Prepared summary text on the left.
 * max           Maximum visible page numbers before inserting an ellipsis.
 */

import Icon from '../Icon/Icon.jsx';
import styles from './Pager.module.scss';

function pageList(page, pages, max) {
  if (pages <= max) return Array.from({ length: pages }, (_, index) => index + 1);

  const around = Math.floor((max - 2) / 2);
  const start = Math.min(Math.max(page - around, 2), pages - max + 3);
  const middle = Array.from({ length: max - 3 }, (_, index) => start + index);

  return [1, ...(start > 2 ? ['gap'] : []), ...middle, 'gap', pages];
}

export default function Pager({ max = 7, page = 1, pages = 1, previousLabel, nextLabel, summary, onPage }) {
  if (pages <= 1) return summary ? <div className={styles.pager}>{summary}</div> : null;

  return (
    <div className={styles.pager}>
      {summary && <span className={styles.summary}>{summary}</span>}

      <div className={styles.pages}>
        <button
          aria-label={previousLabel}
          className={styles.arrow}
          disabled={page <= 1}
          type="button"
          onClick={() => onPage?.(page - 1)}
        >
          <Icon name="chevronRight" size={14} />
        </button>

        {pageList(page, pages, max).map((entry, index) =>
          entry === 'gap' ? (
            <span className={styles.gap} key={`gap-${index}`}>
              …
            </span>
          ) : (
            <button
              aria-current={entry === page ? 'page' : undefined}
              className={styles.page}
              data-selected={entry === page ? 'true' : 'false'}
              key={entry}
              type="button"
              onClick={() => onPage?.(entry)}
            >
              {entry}
            </button>
          ),
        )}

        <button
          aria-label={nextLabel}
          className={styles.arrow}
          data-next="true"
          disabled={page >= pages}
          type="button"
          onClick={() => onPage?.(page + 1)}
        >
          <Icon name="chevronRight" size={14} />
        </button>
      </div>
    </div>
  );
}
