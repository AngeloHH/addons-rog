import Requests from '../components/Requests/Requests.jsx';
import AuthBar from '../components/AuthBar/AuthBar.jsx';
import SiteHeader from '../components/SiteHeader/SiteHeader.jsx';
import Footer from '../components/Footer/Footer.jsx';
import { Navigate, Route, Routes, useParams, useLocation } from 'react-router-dom';
import { defaultLanguage, languages } from '../i18n/languages.js';
import { I18nProvider } from '../i18n/I18nProvider.jsx';
import AboutPage from '../pages/AboutPage/AboutPage.jsx';
import AddonPage from '../pages/AddonPage/AddonPage.jsx';
import AdminPage, { AdminIndex } from '../pages/AdminPage/AdminPage.jsx';
import AuthPage from '../pages/AuthPage/AuthPage.jsx';
import ContactPage from '../pages/ContactPage/ContactPage.jsx';
import DonePage from '../pages/DonePage/DonePage.jsx';
import ExplorePage from '../pages/ExplorePage/ExplorePage.jsx';
import HomePage from '../pages/HomePage/HomePage.jsx';
import DocsPage, { DocsIndex } from '../pages/DocsPage/DocsPage.jsx';
import NotFoundPage from '../pages/NotFoundPage/NotFoundPage.jsx';
import ProfilePage from '../pages/ProfilePage/ProfilePage.jsx';
import RulesPage from '../pages/RulesPage/RulesPage.jsx';
import SetupPage from '../pages/SetupPage/SetupPage.jsx';
import UploadPage from '../pages/UploadPage/UploadPage.jsx';
import VerifyPage from '../pages/VerifyPage/VerifyPage.jsx';

const languageCodes = languages.map((language) => language.code);

function LanguageRoute({ children }) {
  const { lang } = useParams();

  // Treat unsupported language codes as missing routes.
  // Fall back to the default language so the error page remains readable.
  if (!languageCodes.includes(lang)) {
    return (
      <I18nProvider>
        <NotFoundPage />
      </I18nProvider>
    );
  }

  return <I18nProvider>{children}</I18nProvider>;
}

function InvitationEntry() {
  const { search } = useLocation();
  return <Navigate replace to={`/${defaultLanguage}/register${search}`} />;
}

export default function AppRouter() {
  return (
    <Routes>
      <Route path="/register" element={<InvitationEntry />} />
      <Route
        path="/:lang/requests"
        element={
          <LanguageRoute>
            <div className="site-frame">
              <AuthBar />
              <SiteHeader />
              <main className="app-shell">
                <Requests />
              </main>
              <Footer />
            </div>
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/profile/:username"
        element={
          <LanguageRoute>
            <ProfilePage />
          </LanguageRoute>
        }
      />
      <Route path="/" element={<Navigate to={`/${defaultLanguage}`} replace />} />
      <Route
        path="/:lang"
        element={
          <LanguageRoute>
            <HomePage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/login"
        element={
          <LanguageRoute>
            <AuthPage mode="login" />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/register"
        element={
          <LanguageRoute>
            <AuthPage mode="register" />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/explore"
        element={
          <LanguageRoute>
            <ExplorePage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/explore/:expansion/:category/:slug"
        element={
          <LanguageRoute>
            <AddonPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/about"
        element={
          <LanguageRoute>
            <AboutPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/contact"
        element={
          <LanguageRoute>
            <ContactPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/admin-pannel"
        element={
          <LanguageRoute>
            <AdminIndex />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/admin-pannel/:section"
        element={
          <LanguageRoute>
            <AdminPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/docs"
        element={
          <LanguageRoute>
            <DocsIndex />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/docs/:slug"
        element={
          <LanguageRoute>
            <DocsPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/install"
        element={
          <LanguageRoute>
            <DocsIndex />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/setup"
        element={
          <LanguageRoute>
            <SetupPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/profile"
        element={
          <LanguageRoute>
            <ProfilePage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/rules"
        element={
          <LanguageRoute>
            <RulesPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/upload"
        element={
          <LanguageRoute>
            <UploadPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/verify"
        element={
          <LanguageRoute>
            <VerifyPage />
          </LanguageRoute>
        }
      />
      <Route
        path="/:lang/done"
        element={
          <LanguageRoute>
            <DonePage />
          </LanguageRoute>
        }
      />
      {/* Use the URL language for localized missing routes and the default language otherwise. */}
      <Route
        path="/:lang/*"
        element={
          <LanguageRoute>
            <NotFoundPage />
          </LanguageRoute>
        }
      />
      <Route
        path="*"
        element={
          <I18nProvider>
            <NotFoundPage />
          </I18nProvider>
        }
      />
    </Routes>
  );
}
