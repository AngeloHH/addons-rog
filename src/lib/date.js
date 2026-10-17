/**
 * Keep date-only values on their calendar day and preserve API timestamp timezones.
 */
export function formatDate(value, language = 'en') {
  if (!value) return '—';
  const date = /^\d{4}-\d{2}-\d{2}$/.test(value)
    ? new Date(...value.split('-').map((part, index) => Number(part) - (index === 1 ? 1 : 0)))
    : new Date(value);
  if (!Number.isFinite(date.getTime())) return '—';
  return new Intl.DateTimeFormat(language, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(date);
}
