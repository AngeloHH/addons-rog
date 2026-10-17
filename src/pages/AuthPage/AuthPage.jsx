import { useSession } from '../../context/SessionContext.jsx';
import { useAction } from '../../hooks/useApi.js';
import { api } from '../../api/client.js';
import { ApiState } from '../../components/ApiState/ApiState.jsx';
import { useNavigate, useSearchParams } from 'react-router-dom';
import AccountIntro from '../../components/AccountIntro/AccountIntro.jsx';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import LoginPanel from '../../components/LoginPanel/LoginPanel.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';

export default function AuthPage({ mode }) {
  const { language } = useI18n();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const session = useSession();
  const action = useAction();
  const { t } = useI18n();
  const token = params.get('invitationToken');

  function handleTabChange(nextMode) {
    navigate(
      `/${language}/${nextMode === 'register' ? 'register' : 'login'}${token ? `?invitationToken=${encodeURIComponent(token)}` : ''}`,
    );
  }

  function handleRegistered(result) {
    navigate(`/${language}/verify`, {
      state: {
        maskedEmail: result.maskedEmail,
        verificationId: result.verificationId,
      },
    });
  }

  return (
    <div className="site-frame">
      <AuthBar variant="back" />
      <main className="app-shell">
        <section className="account-preview">
          <img className="account-preview__image" src="/assets/slide1.png" alt="" />
          <div className="account-preview__shade account-preview__shade--side"></div>
          <div className="account-preview__shade account-preview__shade--bottom"></div>
          <div className="account-preview__inner">
            <div className="account-preview__grid">
              <div className="account-preview__intro">
                <AccountIntro />
              </div>
              {session.user && token ? (
                <div>
                  <h2>{t("ui.moderatorInvitation")}</h2>
                  <p>{session.user.email}</p>
                  <ApiState error={action.error} loading={action.busy} />
                  <button
                    disabled={action.busy}
                    onClick={() =>
                      action.run(async () => {
                        await api.post('/auth/invitations/accept', { invitationToken: token });
                        await session.refresh();
                        navigate(`/${language}/admin-pannel`);
                      })
                    }
                  >
                    {t("ui.acceptInvitation")}
                  </button>
                  <button disabled={action.busy} onClick={() => action.run(session.logout)}>
                    {t("ui.useAnotherAccount")}
                  </button>
                </div>
              ) : (
                <LoginPanel
                  activeTab={mode}
                  onRegistered={handleRegistered}
                  onTabChange={handleTabChange}
                />
              )}
            </div>
          </div>
        </section>
      </main>
      <Footer />
    </div>
  );
}
