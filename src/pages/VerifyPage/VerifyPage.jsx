import { useLocation, useNavigate } from 'react-router-dom';
import AccountIntro from '../../components/AccountIntro/AccountIntro.jsx';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import Footer from '../../components/Footer/Footer.jsx';
import VerificationCodeForm from '../../components/VerificationCodeForm/VerificationCodeForm.jsx';
import { useI18n } from '../../i18n/I18nProvider.jsx';

export default function VerifyPage() {
  const { language } = useI18n();
  const location = useLocation();
  const navigate = useNavigate();
  const maskedEmail = location.state?.maskedEmail;

  function handleVerified() {
    navigate(`/${language}/done`);
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
              <div className="auth-box">
                <VerificationCodeForm
                  maskedEmail={maskedEmail}
                  verificationId={location.state?.verificationId}
                  onVerified={handleVerified}
                  onResent={(result) =>
                    navigate(location.pathname, {
                      replace: true,
                      state: {
                        ...location.state,
                        verificationId: result.verificationId,
                        maskedEmail: result.maskedEmail,
                      },
                    })
                  }
                />
              </div>
            </div>
          </div>
        </section>
      </main>
      <Footer />
    </div>
  );
}
