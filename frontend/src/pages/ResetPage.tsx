import { useEffect, useState } from 'react';
import { auth } from '../api';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

/**
 * New password from a one-time link (/reset?token=...): sent by e-mail ("forgot password", 1 hour, D-65) or made
 * in the admin panel (48 h). Typing the password twice avoids locking yourself out with a typo.
 */
export function ResetPage() {
  const { t, locale } = useI18n();
  const token = new URLSearchParams(window.location.search).get('token') ?? '';
  const [email, setEmail] = useState<string | null | undefined>(undefined);
  const [password, setPassword] = useState('');
  const [again, setAgain] = useState('');
  const [show, setShow] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    // The token must not stay in the address bar / history once read
    if (token) window.history.replaceState(null, '', '/reset');
    auth
      .checkReset(token)
      .then((r) => setEmail(r.email))
      .catch(() => setEmail(null));
  }, [token]);

  const mismatch = again.length > 0 && again !== password;

  const onSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    if (password !== again) {
      setError(t('reset_mismatch'));
      return;
    }
    try {
      await auth.reset(token, password, locale);
      setDone(true);
    } catch {
      setError(t('reset_invalid'));
    }
  };

  return (
    <div className="page auth-page">
      <div className="card auth-form">
        <h1>🔑 {t('reset_title')}</h1>
        {email === undefined && <p className="muted">{t('loading')}</p>}
        {email === null && (
          <>
            <p className="alert">{t('reset_invalid')}</p>
            <a className="button" href="/forgot" onClick={linkHandler('/forgot')}>
              {t('forgot_new_link')}
            </a>
          </>
        )}
        {done && (
          <>
            <p className="notice">{t('reset_done')}</p>
            <a className="button" href="/login" onClick={linkHandler('/login')}>
              {t('login')}
            </a>
          </>
        )}
        {email && !done && (
          <form onSubmit={onSubmit}>
            <p className="muted small">{email}</p>
            <label className="field">
              <span>{t('new_password')}</span>
              <input
                type={show ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength={8}
                maxLength={200}
                autoComplete="new-password"
                autoFocus
              />
              <small className="muted">{t('password_hint')}</small>
            </label>
            <label className="field">
              <span>{t('reset_confirm')}</span>
              <input
                type={show ? 'text' : 'password'}
                value={again}
                onChange={(e) => setAgain(e.target.value)}
                required
                minLength={8}
                maxLength={200}
                autoComplete="new-password"
                aria-invalid={mismatch}
              />
              {mismatch && <small className="error">{t('reset_mismatch')}</small>}
            </label>
            <label className="field inline small">
              <input type="checkbox" checked={show} onChange={(e) => setShow(e.target.checked)} /> {t('reset_show')}
            </label>
            {error && <p className="alert">{error}</p>}
            <button type="submit" disabled={password.length < 8 || mismatch}>
              {t('save')}
            </button>
            <p className="muted small">{t('reset_sessions_note')}</p>
          </form>
        )}
      </div>
    </div>
  );
}
