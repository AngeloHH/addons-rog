import { useI18n } from '../../i18n/I18nProvider.jsx';

export default function Pagination({ page, onChange, disabled }) {
  const { t } = useI18n();
  if (!page || page.totalPages < 2) return null;
  return (
    <nav className="api-pagination" aria-label={t("ui.pages")}>
      <button type="button" disabled={disabled || page.number <= 1} onClick={() => onChange(page.number - 1)}>
        {t("homeHero.previous")}
      </button>
      <span>
        {page.number} / {page.totalPages}
      </span>
      <button
        type="button"
        disabled={disabled || page.number >= page.totalPages}
        onClick={() => onChange(page.number + 1)}
      >
        {t("homeHero.next")}
      </button>
    </nav>
  );
}
