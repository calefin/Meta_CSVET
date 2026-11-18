/**
 * "Report a problem" (D-70): a small button on every page (not in the admin). The message goes to the support
 * tickets with the page (path only, never query strings), version, browser, screen and the last errors of
 * this visit, so testers do not need to describe where they were.
 */
import { useState } from 'react';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { recentErrors } from '../errorReporter';
import { Modal } from './Modal';

export function ReportButton({ path }: { path: string }) {
  const { t } = useI18n();
  const { user } = useAuth();
  const [open, setOpen] = useState(false);
  const [kind, setKind] = useState<'bug' | 'suggestion' | 'other'>('bug');
  const [message, setMessage] = useState('');
  const [email, setEmail] = useState('');
  const [state, setState] = useState<'idle' | 'sending' | 'sent' | 'error' | 'limited'>('idle');
  if (path.startsWith('/admin')) return null;

  const send = async () => {
    setState('sending');
    try {
      const res = await fetch('/api/support/message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          name: user?.display_name ?? 'Visitante',
          email: user?.email ?? email.trim(),
          category: kind,
          subject: `${kind === 'bug' ? 'Problema' : kind === 'suggestion' ? 'Sugestão' : 'Mensagem'} em ${path}`.slice(0, 200),
          message: message.trim(),
          client_info: {
            page: path,
            version: __APP_VERSION__,
            userAgent: navigator.userAgent,
            screen: `${window.innerWidth}x${window.innerHeight}`,
            language: navigator.language,
            standalone: window.matchMedia?.('(display-mode: standalone)').matches ?? false,
            recent_errors: recentErrors(),
            from: 'report-button',
          },
        }),
      });
      if (res.status === 429) return setState('limited');
      if (!res.ok) return setState('error');
      setState('sent');
      setMessage('');
    } catch {
      setState('error');
    }
  };

  return (
    <>
      <button type="button" className="report-fab" onClick={() => (setOpen(true), setState('idle'))} aria-label={t('report_open')} title={t('report_open')}>
        💬
      </button>
      {open && (
        <Modal
          title={t('report_title')}
          onClose={() => setOpen(false)}
          footer={
            state === 'sent' ? (
              <button type="button" className="button" onClick={() => setOpen(false)}>
                {t('pix_close')}
              </button>
            ) : (
              <>
                <button type="button" className="button secondary" onClick={() => setOpen(false)}>
                  {t('cancel')}
                </button>
                <button
                  type="button"
                  className="button"
                  disabled={state === 'sending' || message.trim().length < 5 || (!user && !email.includes('@'))}
                  onClick={() => void send()}
                >
                  {state === 'sending' ? t('loading') : t('report_send')}
                </button>
              </>
            )
          }
        >
          {state === 'sent' ? (
            <p className="notice">✓ {t('report_thanks')}</p>
          ) : (
            <>
              <div className="seg report-kinds" role="group" aria-label={t('report_kind')}>
                {(['bug', 'suggestion', 'other'] as const).map((k) => (
                  <button key={k} type="button" className={kind === k ? 'active' : ''} onClick={() => setKind(k)}>
                    {t(`report_kind_${k}`)}
                  </button>
                ))}
              </div>
              <label className="field">
                <span>{t('report_message')}</span>
                <textarea rows={5} value={message} onChange={(e) => setMessage(e.target.value)} maxLength={4000} placeholder={t('report_placeholder')} autoFocus />
              </label>
              {!user && (
                <label className="field">
                  <span>{t('report_email')}</span>
                  <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} maxLength={254} autoComplete="email" />
                </label>
              )}
              <p className="muted small">{t('report_attached', { page: path })}</p>
              {state === 'error' && <p className="alert">{t('api_error')}</p>}
              {state === 'limited' && <p className="alert">{t('forgot_rate_limited')}</p>}
            </>
          )}
        </Modal>
      )}
    </>
  );
}
