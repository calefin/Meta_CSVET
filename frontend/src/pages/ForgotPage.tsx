/**
 * "Forgot password" (D-65): the visitor types the e-mail and receives a one-time link valid for 1 hour.
 * The answer never says whether the account exists (no account enumeration).
 */
import { useState } from 'react';
import { ApiError, auth } from '../api';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

export function ForgotPage() {
  const { t, locale } = useI18n();
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await auth.forgot(email.trim(), locale);
      setSent(true);
    } catch (err) {
      setError(err instanceof ApiError && err.code === 'rate_limited' ? t('forgot_rate_limited') : err instanceof ApiError && err.code === 'invalid_email' ? t('forgot_invalid_email') : t('api_error'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="page auth-page">
      <div className="card auth-form">
        <h1>🔑 {t('forgot_title')}</h1>
        {sent ? (
          <>
            <p className="notice">{t('forgot_sent', { email: email.trim() })}</p>
            <ul className="small muted forgot-tips">
              <li>{t('forgot_tip_spam')}</li>
              <li>{t('forgot_tip_expire')}</li>
              <li>{t('forgot_tip_oauth')}</li>
            </ul>
            <button type="button" className="secondary" onClick={() => setSent(false)}>
              {t('forgot_again')}
            </button>
          </>
        ) : (
          <form onSubmit={submit}>
            <p className="muted small">{t('forgot_intro')}</p>
            <label className="field">
              <span>{t('email')}</span>
              <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required maxLength={254} autoComplete="email" autoFocus />
            </label>
            {error && <p className="alert">{error}</p>}
            <button type="submit" disabled={busy || !email.includes('@')}>
              {busy ? t('loading') : t('forgot_send')}
            </button>
          </form>
        )}
        <p className="small">
          <a href="/login" onClick={linkHandler('/login')}>
            ← {t('login')}
          </a>
        </p>
      </div>
    </div>
  );
}
