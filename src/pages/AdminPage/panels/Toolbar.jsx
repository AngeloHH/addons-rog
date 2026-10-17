import Icon from '../../../components/Icon/Icon.jsx';
import SegmentedControl from '../../../components/SegmentedControl/SegmentedControl.jsx';
import { useI18n } from '../../../i18n/I18nProvider.jsx';
import styles from './Toolbar.module.scss';

/**
 * Display search and filter controls above a panel's rows.
 * Let the toolbar expand or stack to fit its available width.
 *
 * filters  [{ id, label, options, value, onChange }] button groups.
 * sort     { label, options, value, onChange } for the trailing select.
 * query    Current search text.
 * action   Trailing content, such as an export button.
 */
export default function Toolbar({ action, filters = [], onQuery, placeholder, query = '', sort }) {
  const { t } = useI18n();

  return (
    <div className={styles.toolbar}>
      <label className={styles.search}>
        <Icon name="search" size={14} />
        <input
          className={styles.input}
          placeholder={placeholder}
          type="search"
          value={query}
          onChange={(event) => onQuery?.(event.target.value)}
        />
      </label>

      {filters.map((filter) => (
        <SegmentedControl
          key={filter.id}
          label={filter.label}
          options={filter.options}
          value={filter.value}
          onChange={filter.onChange}
        />
      ))}

      {sort && (
        <label className={styles.sort}>
          {t('admin.sort')}
          <select
            className={styles.select}
            value={sort.value}
            onChange={(event) => sort.onChange(event.target.value)}
          >
            {sort.options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
      )}

      {action}
    </div>
  );
}
