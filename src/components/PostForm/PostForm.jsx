/**
 * Addons ROG - Post Form
 *
 * Render ordered fields from a supplied form definition.
 *
 * fields       A field or an array of fields sharing one row.
 * field        { name, kind, label, placeholder, rows, source, note }
 * kind         text by default, or email, select, textarea.
 * label        Optional; absent labels leave only the placeholder.
 * source       'expansions' loads select choices from the server.
 * note         (value) => ({ text, tone }); tone is muted or ok.
 * footer       Content to the left of the submit button.
 * submitReady  False disables submission; the caller decides the requirements.
 * onChange     Receives all form values whenever a field changes.
 */

import { useEffect, useState } from 'react';
import { fetchExpansions } from '../../api/catalogApi.js';
import { useAction } from '../../hooks/useApi.js';
import { ApiState } from '../ApiState/ApiState.jsx';
import styles from './PostForm.module.scss';

function Field({ field, value, onChange }) {
  const note = field.note?.(value ?? '');

  const control =
    field.kind === 'textarea' ? (
      <textarea
        className={styles.textarea}
        placeholder={field.placeholder}
        rows={field.rows ?? 9}
        value={value ?? ''}
        onChange={(event) => onChange(field.name, event.target.value)}
      />
    ) : field.kind === 'select' ? (
      <select
        className={styles.field}
        value={value ?? ''}
        onChange={(event) => onChange(field.name, event.target.value)}
      >
        <option value="">{field.placeholder}</option>
        {(field.options ?? []).map((option) => (
          <option key={option.id} value={option.id}>
            {option.name}
          </option>
        ))}
      </select>
    ) : (
      <input
        className={styles.field}
        placeholder={field.placeholder}
        type={field.kind === 'email' ? 'email' : 'text'}
        value={value ?? ''}
        onChange={(event) => onChange(field.name, event.target.value)}
      />
    );

  if (!field.label) return control;

  return (
    <label className={styles.label}>
      <span className={styles.labelRow}>
        <span>{field.label}</span>
        {note && (
          <span className={styles.note} data-tone={note.tone}>
            {note.text}
          </span>
        )}
      </span>
      {control}
    </label>
  );
}

export default function PostForm({
  description,
  fields = [],
  footer,
  framed = true,
  largeTitle = false,
  submitLabel,
  submitReady = true,
  title,
  onChange,
  onSubmit,
  initialValues = {},
}) {
  const [values, setValues] = useState(initialValues);
  const [expansions, setExpansions] = useState([]);
  const action = useAction();
  const [loadError, setLoadError] = useState(null);

  const rows = fields.map((entry) => (Array.isArray(entry) ? entry : [entry]));
  const needsExpansions = rows.some((row) => row.some((field) => field.source === 'expansions'));

  // Load expansion choices independently so the remaining fields stay usable if loading fails.
  useEffect(() => {
    if (!needsExpansions) return;

    let cancelled = false;

    fetchExpansions()
      .then((list) => {
        if (!cancelled) setExpansions(list);
      })
      .catch((error) => {
        if (!cancelled) setLoadError(error);
      });

    return () => {
      cancelled = true;
    };
  }, [needsExpansions]);

  function change(name, value) {
    const next = { ...values, [name]: value };
    setValues(next);
    onChange?.(next);
    action.clear();
  }

  return (
    <form
      className={styles.form}
      data-framed={framed}
      onSubmit={(event) => {
        event.preventDefault();
        if (submitReady && onSubmit) action.run(() => onSubmit(values));
      }}
    >
      {title && (
        <h3 className={styles.title} data-large={largeTitle}>
          {title}
        </h3>
      )}
      {description && <p className={styles.text}>{description}</p>}

      {rows.map((row, index) => {
        const painted = row.map((field) => (
          <Field
            field={field.source === 'expansions' ? { ...field, options: expansions } : field}
            key={field.name}
            value={values[field.name]}
            onChange={change}
          />
        ));

        return row.length > 1 ? (
          <div className={styles.row} key={row.map((field) => field.name).join('-')}>
            {painted}
          </div>
        ) : (
          painted
        );
      })}

      <ApiState error={action.error || loadError} loading={action.busy} done={action.done} />
      <div className={styles.footer} data-alone={!footer}>
        {footer}
        <button
          className={styles.submit}
          data-ready={submitReady}
          disabled={!submitReady || action.busy || !onSubmit}
          type="submit"
        >
          {submitLabel}
        </button>
      </div>
    </form>
  );
}
