import { useI18n } from '../../../i18n/I18nProvider.jsx';
import PostForm from '../../../components/PostForm/PostForm.jsx';

export default function ActionForm({ action, close, onSubmit }) {
  const { t } = useI18n();
  if (!action) return null;
  return (
    <section className="api-action-form" aria-label={action.title}>
      <PostForm
        key={action.key || action.title}
        title={action.title}
        description={action.description}
        fields={action.fields || []}
        initialValues={action.values || {}}
        submitLabel={t("ui.confirm")}
        onSubmit={onSubmit}
      />
      <button type="button" onClick={close}>
        {t("ui.cancel")}
      </button>
    </section>
  );
}
