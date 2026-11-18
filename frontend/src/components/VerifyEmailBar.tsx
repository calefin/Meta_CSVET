/**
 * "Confirm your e-mail" bar (D-65): shown to signed-in accounts whose e-mail is not confirmed yet, on every page
 * except /verify. "Resend" respects the server limits (1 per minute, 5 per hour) and shows a countdown.
 */
import { useEffect, useState } from 'react';
import { ApiError, auth } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';

export function useResendVerification() {
  const { locale } = useI18n();
  const [wait, setWait] = useState(0);
  const [state, setState] = useState<'idle' | 'sending' | 'sent' | 'error' | 'limited' | 'off'>('idle');
  useEffect(() => {
    if (wait <= 0) return;
    const id = setTimeout(() => setWait((w) => w - 1), 1000);
    return () => clearTimeout(id);
  }, [wait]);
  const resend = async () => {
    setState('sending');
    try {
      const r = await auth.resendVerification(locale);
      setState('sent');
      setWait(r.retry_in ?? 60);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'rate_limited') {
        setState('limited');
        setWait(Math.min(3600, Number(e.data.retry_in) || 60));
      } else setState(e instanceof ApiError && e.code === 'mail_disabled' ? 'off' : 'error');
    }
  };
  return { wait, state, resend };
}

export function VerifyEmailBar({ path }: { path: string }) {
  const { t } = useI18n();
  const { user } = useAuth();
  const [hidden, setHidden] = useState(() => {
    try {
      return sessionStorage.getItem('bp_verify_bar') === 'hide';
    } catch {
      return false;
    }
  });
  const { wait, state, resend } = useResendVerification();
  if (!user || user.email_verified !== false || hidden || path.startsWith('/verify')) return null;
  const fmt = (s: number) => (s >= 60 ? `${Math.ceil(s / 60)} min` : `${s}s`);
  return (
    <div className="verify-bar" role="status">
      <span>
        📧 {t('verify_bar', { email: user.email })}
        {state === 'sent' && <strong> {t('verify_sent')}</strong>}
        {state === 'limited' && <strong> {t('verify_wait', { time: fmt(wait) })}</strong>}
        {state === 'error' && <strong> {t('api_error')}</strong>}
        {state === 'off' && <strong> {t('verify_mail_off')}</strong>}
      </span>
      <span className="verify-bar-actions">
        <button type="button" className="button small-btn" onClick={() => void resend()} disabled={state === 'sending' || wait > 0}>
          {state === 'sending' ? t('loading') : wait > 0 ? `${t('verify_resend')} (${fmt(wait)})` : t('verify_resend')}
        </button>
        <button
          type="button"
          className="icon-btn"
          aria-label={t('verify_later')}
          title={t('verify_later')}
          onClick={() => {
            setHidden(true);
            try {
              sessionStorage.setItem('bp_verify_bar', 'hide');
            } catch {
              /* private mode */
            }
          }}
        >
          ✕
        </button>
      </span>
    </div>
  );
}
