/**
 * E-mail confirmation page (D-65): /verify?token=... from the e-mail. Confirms with a button (a POST), so the
 * link scanners of mail providers that open links do not confirm on their own. Expired link → resend.
 */
import { useEffect, useState } from 'react';
import { auth } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { useResendVerification } from '../components/VerifyEmailBar';

export function VerifyPage() {
  const { t } = useI18n();
  const { user, refresh } = useAuth();
  const [token] = useState(() => new URLSearchParams(window.location.search).get('token') ?? '');
  const [state, setState] = useState<'ready' | 'busy' | 'done' | 'invalid'>(token ? 'ready' : 'invalid');
  const [email, setEmail] = useState('');
  const { wait, state: rs, resend } = useResendVerification();

  useEffect(() => {
    // The token must not stay in the address bar / history
    if (token) window.history.replaceState(null, '', '/verify');
  }, [token]);

  const confirm = async () => {
    setState('busy');
    try {
      const r = await auth.verifyEmail(token);
      setEmail(r.email);
      setState('done');
      void refresh();
    } catch {
      setState('invalid');
    }
  };

  return (
    <div className="page auth-page">
      <div className="card auth-form">
        <h1>📧 {t('verify_title')}</h1>
        {state === 'ready' || state === 'busy' ? (
          <>
            <p className="muted">{t('verify_intro')}</p>
            <button type="button" className="button" onClick={() => void confirm()} disabled={state === 'busy'}>
              {state === 'busy' ? t('loading') : t('verify_confirm')}
            </button>
          </>
        ) : state === 'done' ? (
          <>
            <p className="notice">✓ {t('verify_done', { email })}</p>
            <a className="button" href={user ? '/hunt-analyser' : '/login'} onClick={linkHandler(user ? '/hunt-analyser' : '/login')}>
              {user ? t('verify_continue') : t('login')}
            </a>
          </>
        ) : (
          <>
            <p className="alert">{t('verify_invalid')}</p>
            {user && user.email_verified === false ? (
              <>
                <button type="button" className="button" onClick={() => void resend()} disabled={rs === 'sending' || wait > 0}>
                  {wait > 0 ? `${t('verify_resend')} (${wait >= 60 ? `${Math.ceil(wait / 60)} min` : `${wait}s`})` : t('verify_resend')}
                </button>
                {rs === 'sent' && <p className="small">{t('verify_sent')}</p>}
              </>
            ) : user ? (
              <p className="small">{t('verify_already')}</p>
            ) : (
              <p className="small">
                <a href="/login" onClick={linkHandler('/login')}>
                  {t('login')}
                </a>{' '}
                · {t('verify_login_to_resend')}
              </p>
            )}
          </>
        )}
      </div>
    </div>
  );
}
