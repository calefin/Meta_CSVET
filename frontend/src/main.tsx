import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { I18nProvider } from './i18n';
import { App } from './App';
import { AuthProvider } from './auth';
import './styles.css';
import './styles-v2.css';
import { registerServiceWorker } from './pwa';
import { installErrorReporter } from './errorReporter';

installErrorReporter();

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <I18nProvider>
      <AuthProvider>
        <App />
      </AuthProvider>
    </I18nProvider>
  </StrictMode>,
);

registerServiceWorker();
