/** "Continue with Google / Discord" buttons (D-39). Only providers configured on the server are shown. */
import { useEffect, useState } from 'react';
import { auth, type OAuthProvider } from '../api';
import { useI18n } from '../i18n';

export const PROVIDER_NAMES: Record<OAuthProvider, string> = { google: 'Google', discord: 'Discord' };

let cached: Promise<OAuthProvider[]> | null = null;
export function useOAuthProviders(): OAuthProvider[] {
  const [list, setList] = useState<OAuthProvider[]>([]);
  useEffect(() => {
    let alive = true;
    cached ??= auth
      .providers()
      .then((r) => r.providers)
      .catch(() => {
        cached = null;
        return [];
      });
    void cached.then((p) => alive && setList(p));
    return () => {
      alive = false;
    };
  }, []);
  return list;
}

export function ProviderIcon({ provider }: { provider: OAuthProvider }) {
  if (provider === 'google') {
    return (
      <svg viewBox="0 0 48 48" width="18" height="18" aria-hidden="true">
        <path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.9z" />
        <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
        <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z" />
        <path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z" />
      </svg>
    );
  }
  return (
    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
      <path
        fill="#5865F2"
        d="M20.3 4.4A19.8 19.8 0 0 0 15.4 3l-.6 1.3a18.3 18.3 0 0 0-5.5 0L8.6 3a19.7 19.7 0 0 0-4.9 1.5C.5 9.2-.3 13.8.1 18.3a19.9 19.9 0 0 0 6 3l1.3-2.1c-.7-.3-1.4-.6-2-1l.5-.4a14.2 14.2 0 0 0 12.2 0l.5.4c-.6.4-1.3.7-2 1l1.3 2.1a19.8 19.8 0 0 0 6-3c.5-5.2-.9-9.8-3.6-13.9zM8 15.5c-1.2 0-2.2-1.1-2.2-2.4s1-2.4 2.2-2.4 2.2 1.1 2.2 2.4-1 2.4-2.2 2.4zm8 0c-1.2 0-2.2-1.1-2.2-2.4s1-2.4 2.2-2.4 2.2 1.1 2.2 2.4-1 2.4-2.2 2.4z"
      />
    </svg>
  );
}

/** Buttons for the login/register page; `next` is where to land after signing in. */
export function OAuthButtons({ next }: { next?: string }) {
  const { t } = useI18n();
  const providers = useOAuthProviders();
  if (!providers.length) return null;
  return (
    <div className="oauth">
      {providers.map((p) => (
        <a key={p} className="button secondary oauth-button" href={auth.oauthUrl(p, { next })}>
          <ProviderIcon provider={p} />
          <span>{t('oauth_continue', { provider: PROVIDER_NAMES[p] })}</span>
        </a>
      ))}
      <div className="oauth-or" aria-hidden="true">
        <span>{t('oauth_or')}</span>
      </div>
    </div>
  );
}

/** Message for ?oauth_error=<code> after a failed provider round trip */
export function oauthErrorText(t: (k: string) => string, code: string | null): string | null {
  if (!code) return null;
  const alias: Record<string, string> = { banned: 'auth_err_banned', rate_limited: 'auth_err_rate_limited' };
  const key = alias[code] ?? `oauth_err_${code}`;
  const text = t(key);
  return text === key ? t('oauth_err_provider') : text;
}
