import AccountIntro from '../../components/AccountIntro/AccountIntro.jsx';
import AuthBar from '../../components/AuthBar/AuthBar.jsx';
import AuthSuccess from '../../components/AuthSuccess/AuthSuccess.jsx';
import Footer from '../../components/Footer/Footer.jsx';

export default function DonePage() {
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
                <AuthSuccess />
              </div>
            </div>
          </div>
        </section>
      </main>
      <Footer />
    </div>
  );
}
