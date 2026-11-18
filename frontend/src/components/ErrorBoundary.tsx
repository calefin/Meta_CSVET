/**
 * Page error boundary (D-62): a crash in one page shows a short message with "reload" instead of a blank app,
 * and the error goes to the admin error log. Keyed by path in App, so moving to another page clears it.
 */
import { Component, type ReactNode } from 'react';
import { useI18n } from '../i18n';
import { reportError } from '../errorReporter';

function Fallback() {
  const { t } = useI18n();
  return (
    <section className="card error-fallback" role="alert">
      <h2>😵 {t('crash_title')}</h2>
      <p>{t('crash_text')}</p>
      <div className="button-row">
        <button type="button" className="button" onClick={() => location.reload()}>
          {t('crash_reload')}
        </button>
        <a className="button secondary" href="/">
          {t('crash_home')}
        </a>
      </div>
    </section>
  );
}

export class ErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  componentDidCatch(error: Error, info: { componentStack?: string | null }) {
    reportError(error.message, `${error.stack ?? ''}\n--- component stack ---${info.componentStack ?? ''}`);
  }

  render() {
    return this.state.failed ? <Fallback /> : this.props.children;
  }
}
