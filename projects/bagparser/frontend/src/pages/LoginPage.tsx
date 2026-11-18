import { useState } from 'react';
import { flushSync } from 'react-dom';
import { ApiError, auth } from '../api';
import { useAuth } from '../auth';
import { OAuthButtons, oauthErrorText } from '../components/OAuthButtons';
import { TurnstileWidget } from '../components/TurnstileWidget';
import { useI18n } from '../i18n';
import { linkHandler, navigate } from '../router';

/** Sign in / create account (D-24). `mode` comes from the route: /login or /register. */
export function LoginPage({ mode }: { mode: 'login' | 'register' }) {
  const { t } = useI18n();
  const { setUser } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [name, setName] = useState('');
  const [terms, setTerms] = useState(false);
  const [turnstileToken, setTurnstileToken] = useState('');
  const [honeypot, setHoneypot] = useState('');
  const [busy, setBusy] = useState(false);
  // ?oauth_error=<code> after a failed Google/Discord round trip; ?next=<path> to return after signing in
  const query = new URLSearchParams(window.location.search);
  const next = query.get('next') ?? undefined;
  const [error, setError] = useState<string | null>(() => oauthErrorText(t, query.get('oauth_error')));

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const opts = { turnstile_token: turnstileToken, hp: honeypot };
      const res =
        mode === 'login'
          ? await auth.login(email, password, opts)
          : await auth.register(email, password, name, terms, opts);
      // navigate() fires popstate, which React renders at a higher priority than this update;
      // commit the user first so /account does not see a signed-out state and bounce to /login.
      flushSync(() => setUser(res.user));
      navigate(next?.startsWith('/') && !next.startsWith('//') ? next : mode === 'register' ? '/account' : '/');
    } catch (err) {
      if (err instanceof ApiError && err.code === 'banned') {
        setError(t('auth_err_banned') + (err.data.reason ? ` (${String(err.data.reason)})` : ''));
      } else if (err instanceof ApiError) {
        setError(t(`auth_err_${err.code}`) === `auth_err_${err.code}` ? t('api_error') : t(`auth_err_${err.code}`));
      } else {
        setError(t('api_error'));
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="page auth-page">
      <div className="tabs" role="tablist">
        <a href="/login" role="tab" aria-selected={mode === 'login'} className={mode === 'login' ? 'active' : ''} onClick={linkHandler('/login')}>
          {t('login')}
        </a>
        <a href="/register" role="tab" aria-selected={mode === 'register'} className={mode === 'register' ? 'active' : ''} onClick={linkHandler('/register')}>
          {t('register')}
        </a>
      </div>

      <form className="card auth-form" onSubmit={onSubmit}>
        <h1>{mode === 'login' ? t('login_title') : t('register_title')}</h1>
        {mode === 'register' && <p className="muted small">{t('register_intro')}</p>}

        <OAuthButtons next={next} />

        {mode === 'register' && (
          <label className="field">
            <span>{t('display_name')}</span>
            <input value={name} onChange={(e) => setName(e.target.value)} required minLength={2} maxLength={30} autoComplete="nickname" />
          </label>
        )}
        <label className="field">
          <span>{t('email')}</span>
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" />
        </label>
        <label className="field">
          <span>{t('password')}</span>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            minLength={mode === 'register' ? 8 : 1}
            maxLength={200}
            autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
          />
          {mode === 'register' && <small className="muted">{t('password_hint')}</small>}
        </label>

        {mode === 'register' && (
          <label className="check">
            <input type="checkbox" checked={terms} onChange={(e) => setTerms(e.target.checked)} required />
            <span>
              {t('accept_terms_prefix')}{' '}
              <a href="/terms" target="_blank" rel="noopener">
                {t('terms_of_use')}
              </a>{' '}
              {t('and')}{' '}
              <a href="/privacy" target="_blank" rel="noopener">
                {t('privacy_policy')}
              </a>
              {t('age_confirm')}
            </span>
          </label>
        )}

        {/* Honeypot field for bot trap */}
        <div style={{ display: 'none', position: 'absolute', left: '-9999px' }} aria-hidden="true">
          <input
            type="text"
            name="hp_extra_check"
            tabIndex={-1}
            autoComplete="off"
            value={honeypot}
            onChange={(e) => setHoneypot(e.target.value)}
          />
        </div>

        {/* Cloudflare Turnstile */}
        <TurnstileWidget
          action={mode === 'login' ? 'login' : 'register'}
          onVerify={(tok) => setTurnstileToken(tok)}
          onExpire={() => setTurnstileToken('')}
        />

        {error && <p className="alert">{error}</p>}

        <button type="submit" disabled={busy}>
          {busy ? t('loading') : mode === 'login' ? t('login') : t('register')}
        </button>
        {mode === 'login' && (
          <p className="small">
            <a href="/forgot" onClick={linkHandler('/forgot')}>
              🔑 {t('forgot_link')}
            </a>
          </p>
        )}
      </form>
    </div>
  );
}
