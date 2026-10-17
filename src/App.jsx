import InstallationGate from './components/InstallationGate/InstallationGate.jsx';
import AppRouter from './router/AppRouter.jsx';
import { SessionProvider } from './context/SessionContext.jsx';

export default function App() {
  return (
    <InstallationGate>
      <SessionProvider>
        <AppRouter />
      </SessionProvider>
    </InstallationGate>
  );
}
