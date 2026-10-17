import { Link } from 'react-router-dom';
import { useI18n } from '../../i18n/I18nProvider.jsx';
import { useSession } from '../../context/SessionContext.jsx';
export function ApiState({ loading, error, empty, retry, done }) {
  const { language } = useI18n();
  const { t } = useI18n();
  if (error) {
    let message = t("ui.requestFailed");
    if (error.status === 401)
      message = t("ui.signInToContinue");
    else if (error.status === 403)
      message = t("ui.forbidden");
    else if (error.status === 404)
      message = t("ui.notFound");
    else if (error.status === 409)
      message = t("ui.stateConflict");
    else if (error.status === 422)
      message = t("ui.validationFailed");
    else if (error.status === 429)
      message = t("ui.rateLimited");
    const messages = {
      INVALID_CREDENTIALS: "ui.invalidCredentials",
      INVALID_CODE: "ui.invalidCode",
      ACCOUNT_SUSPENDED: "ui.accountSuspended",
      ACCOUNT_BANNED: "ui.accountBanned",
      NOT_ADDON_OWNER: "ui.notAddonOwner",
      NAME_TAKEN: "ui.nameTaken",
      VERSION_EXISTS: "ui.versionExists",
      ALREADY_REPORTED: "ui.alreadyReported",
      REVISION_MISMATCH: "ui.revisionMismatch",
      FILE_TOO_LARGE: "ui.fileTooLarge",
      UNSUPPORTED_MEDIA_TYPE: "ui.unsupportedMediaType",
    };
    if (messages[error.code]) message = t(messages[error.code]);
    if (error.status === 410)
      message = t("ui.expired");
    const fields = Object.keys(error.fields || {});
    return (
      <div className="api-state" role="alert">
        <p>
          {message}
          {fields.length ? ` (${fields.join(', ')})` : ''}
        </p>
        {error.status === 401 && error.code !== 'INVALID_CREDENTIALS' && (
          <Link to={`/${language}/login`}>{t("auth.signIn")}</Link>
        )}
        {retry && (
          <button type="button" onClick={retry}>
            {t("ui.retry")}
          </button>
        )}
      </div>
    );
  }
  if (loading)
    return (
      <p className="api-state" data-state="loading" role="status">
        {t("ui.loading")}
      </p>
    );
  if (done)
    return (
      <p className="api-state" role="status">
        {t("ui.savedSuccessfully")}
      </p>
    );
  if (empty)
    return (
      <p className="api-state" data-state="empty">
        {t("ui.nothingToShowYet")}
      </p>
    );
  return null;
}
export function RequireAccount({ children, staff = false, founder = false }) {
  const session = useSession();
  if (session.loading || session.error)
    return (
      <ApiState
        loading={session.loading}
        error={session.error}
        retry={() => session.refresh().catch(() => {})}
      />
    );
  if (!session.user) return <ApiState error={{ status: 401 }} />;
  if ((staff && !session.staff) || (founder && !session.founder))
    return <ApiState error={{ status: 403 }} />;
  return children;
}