/**
 * Sign in with Google / Discord (D-39): OAuth 2.0 authorization code flow with PKCE.
 * Pure helpers only (provider config, URLs, profile parsing); the routes live in oauthRoutes.ts.
 */
import { createHash, randomBytes } from 'node:crypto';
import { normalizeDisplayName, normalizeEmail } from './auth.js';

export const PROVIDER_IDS = ['google', 'discord'] as const;
export type ProviderId = (typeof PROVIDER_IDS)[number];

export interface ProviderConfig {
  id: ProviderId;
  authUrl: string;
  tokenUrl: string;
  profileUrl: string;
  scope: string;
  clientId: string;
  clientSecret: string;
  /** Extra query parameters for the consent screen */
  extra: Record<string, string>;
}

const STATIC: Record<ProviderId, Omit<ProviderConfig, 'clientId' | 'clientSecret'>> = {
  google: {
    id: 'google',
    authUrl: 'https://accounts.google.com/o/oauth2/v2/auth',
    tokenUrl: 'https://oauth2.googleapis.com/token',
    profileUrl: 'https://openidconnect.googleapis.com/v1/userinfo',
    scope: 'openid email profile',
    extra: { prompt: 'select_account' },
  },
  discord: {
    id: 'discord',
    authUrl: 'https://discord.com/oauth2/authorize',
    tokenUrl: 'https://discord.com/api/oauth2/token',
    profileUrl: 'https://discord.com/api/users/@me',
    scope: 'identify email',
    extra: {},
  },
};

/** Providers with both client id and secret set (GOOGLE_CLIENT_ID/SECRET, DISCORD_CLIENT_ID/SECRET). */
export function providersFromEnv(env: Record<string, string | undefined> = process.env): Map<ProviderId, ProviderConfig> {
  const out = new Map<ProviderId, ProviderConfig>();
  for (const id of PROVIDER_IDS) {
    const prefix = id.toUpperCase();
    const clientId = env[`${prefix}_CLIENT_ID`]?.trim();
    const clientSecret = env[`${prefix}_CLIENT_SECRET`]?.trim();
    if (clientId && clientSecret) out.set(id, { ...STATIC[id], clientId, clientSecret });
  }
  return out;
}

export const isProviderId = (raw: unknown): raw is ProviderId =>
  typeof raw === 'string' && (PROVIDER_IDS as readonly string[]).includes(raw);

/** PKCE (RFC 7636, S256) */
export function pkcePair(): { verifier: string; challenge: string } {
  const verifier = randomBytes(32).toString('base64url');
  return { verifier, challenge: createHash('sha256').update(verifier).digest('base64url') };
}

export function buildAuthUrl(
  cfg: ProviderConfig,
  p: { state: string; challenge: string; redirectUri: string },
): string {
  const q = new URLSearchParams({
    response_type: 'code',
    client_id: cfg.clientId,
    redirect_uri: p.redirectUri,
    scope: cfg.scope,
    state: p.state,
    code_challenge: p.challenge,
    code_challenge_method: 'S256',
    ...cfg.extra,
  });
  return `${cfg.authUrl}?${q}`;
}

/** Where to go after login: only same-site paths (no `//host`, no backslash tricks, no API routes). */
export function safeNext(raw: unknown, fallback = '/'): string {
  if (typeof raw !== 'string' || raw.length > 200) return fallback;
  if (!/^\/(?![/\\])/.test(raw) || /[\\\s]/.test(raw) || raw.startsWith('/api')) return fallback;
  return raw;
}

export interface OAuthProfile {
  subject: string;
  email: string | null;
  emailVerified: boolean;
  name: string | null;
}

/** Google OpenID userinfo / Discord /users/@me → common shape. Null when the answer is unusable. */
export function parseProfile(provider: ProviderId, raw: unknown): OAuthProfile | null {
  if (!raw || typeof raw !== 'object') return null;
  const j = raw as Record<string, unknown>;
  const str = (v: unknown) => (typeof v === 'string' && v.trim() ? v.trim() : null);
  if (provider === 'google') {
    const subject = str(j.sub);
    if (!subject) return null;
    return {
      subject,
      email: normalizeEmail(j.email),
      emailVerified: j.email_verified === true || j.email_verified === 'true',
      name: str(j.name) ?? str(j.given_name),
    };
  }
  const subject = str(j.id);
  if (!subject || !/^\d+$/.test(subject)) return null;
  return {
    subject,
    email: normalizeEmail(j.email),
    emailVerified: j.verified === true,
    name: str(j.global_name) ?? str(j.username),
  };
}

/** A valid BagParser display name from the provider name, else from the e-mail, else "Player". */
export function displayNameFrom(p: Pick<OAuthProfile, 'name' | 'email'>): string {
  const clean = (s: string) => s.replace(/[^\p{L}\p{N} '._-]+/gu, ' ').trim().slice(0, 30).trim();
  for (const cand of [p.name, p.email?.split('@')[0]]) {
    const name = cand ? normalizeDisplayName(clean(cand)) : null;
    if (name) return name;
  }
  return 'Player';
}

/**
 * D-65: what a "sign in with Google/Discord" does when the identity is not linked to any account yet.
 *  - provider without a verified e-mail → refuse (we cannot tell who it is)
 *  - no account with that e-mail → create one (e-mail counts as confirmed)
 *  - account with that e-mail AND the account confirmed it → same person: link and sign in
 *  - account with that e-mail NOT confirmed → refuse (someone may have pre-registered another person's e-mail);
 *    the owner signs in with the password and links the provider in /account
 */
export function oauthDecision(
  profile: Pick<OAuthProfile, 'email' | 'emailVerified'>,
  existing: { id: number; emailVerified: boolean } | null,
): 'email_unverified' | 'create' | 'link_existing' | 'email_exists' {
  if (!profile.email || !profile.emailVerified) return 'email_unverified';
  if (!existing) return 'create';
  return existing.emailVerified ? 'link_existing' : 'email_exists';
}
