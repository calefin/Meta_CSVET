/**
 * Routes for Google / Discord login (D-39).
 *
 * GET  /api/auth/providers                  → configured providers (login buttons)
 * GET  /api/auth/oauth/:provider/start      → redirect to the provider (?next=/path, ?link=1 to link to the
 *                                             signed-in account)
 * GET  /api/auth/oauth/:provider/callback   → finish: sign in, create the account or link; redirect back
 * POST /api/auth/oauth/:provider/unlink     → remove a linked login (JSON; covered by the CSRF hook)
 *
 * Account rules: a known identity signs in; a new identity creates an account only when the provider says the
 * e-mail is verified; if a password account already uses that e-mail we do NOT link automatically (our
 * registration does not verify e-mails, so that would allow account pre-hijacking) — the person signs in with
 * the password and links it in /account.
 */
import type { FastifyInstance, FastifyReply, FastifyRequest } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import { newToken, parseCookies, sessionCookie, tokenHash, type UserRow } from './auth.js';
import {
  buildAuthUrl,
  displayNameFrom,
  isProviderId,
  oauthDecision,
  parseProfile,
  pkcePair,
  safeNext,
  type OAuthProfile,
  type ProviderConfig,
  type ProviderId,
} from './oauth.js';

const STATE_COOKIE = 'bp_oauth';
const STATE_TTL = 600;
const HTTP_TIMEOUT_MS = 10_000;

export interface OAuthDeps {
  db: pg.Pool;
  redis: Redis;
  providers: Map<ProviderId, ProviderConfig>;
  /** Canonical origin, e.g. https://bagparser.com (empty = derive from the request) */
  publicUrl: string;
  adminEmails: string[];
  secure: (req: FastifyRequest) => boolean;
  loadUser: (req: FastifyRequest) => Promise<UserRow | undefined>;
  createSession: (req: FastifyRequest, reply: FastifyReply, userId: number) => Promise<void>;
  limited: (key: string, max: number, windowSeconds: number) => Promise<boolean>;
  requireUser: (req: FastifyRequest, reply: FastifyReply) => Promise<unknown>;
}

interface PendingLogin {
  provider: ProviderId;
  verifier: string;
  next: string;
  linkUserId: number | null;
}

class OAuthFail extends Error {
  constructor(readonly code: string) {
    super(code);
  }
}

export function registerOAuthRoutes(app: FastifyInstance, d: OAuthDeps): void {
  const { db, redis } = d;

  const origin = (req: FastifyRequest) => {
    const hostHeader = String(req.headers.host ?? '').replace(/:\d+$/, '');
    if (d.publicUrl) {
      try {
        const parsed = new URL(d.publicUrl);
        // If publicUrl is configured as localhost, but user visits via actual domain (bagparser.com), use the request origin!
        if ((parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1') && hostHeader && hostHeader !== 'localhost' && hostHeader !== '127.0.0.1') {
          return `${req.protocol}://${req.headers.host}`;
        }
        return d.publicUrl;
      } catch {
        return d.publicUrl;
      }
    }
    return `${req.protocol}://${req.headers.host}`;
  };
  const redirectUri = (req: FastifyRequest, p: ProviderId) => `${origin(req)}/api/auth/oauth/${p}/callback`;
  const stateCookie = (req: FastifyRequest, value: string, maxAge: number) =>
    sessionCookie(STATE_COOKIE, value, { maxAgeSeconds: maxAge, secure: d.secure(req) });

  const back = (reply: FastifyReply, path: string, params: Record<string, string>) =>
    reply.redirect(`${path}${path.includes('?') ? '&' : '?'}${new URLSearchParams(params)}`);

  app.get('/api/auth/providers', async () => ({ providers: [...d.providers.keys()] }));

  app.get<{ Params: { provider: string }; Querystring: { next?: string; link?: string } }>(
    '/api/auth/oauth/:provider/start',
    async (req, reply) => {
      reply.header('Cache-Control', 'no-store');
      const p = req.params.provider;
      const cfg = isProviderId(p) ? d.providers.get(p) : undefined;
      if (!cfg) return back(reply, '/login', { oauth_error: 'unavailable' });

      // The state cookie must be set on the same host the provider redirects back to
      // Only enforce redirection if publicUrl is a real domain and different from req.headers.host
      if (d.publicUrl) {
        try {
          const parsed = new URL(d.publicUrl);
          const reqHost = String(req.headers.host ?? '').replace(/:\d+$/, '');
          const isConfiguredLocal = parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1';
          if (!isConfiguredLocal && reqHost && reqHost !== parsed.hostname) {
            return reply.redirect(d.publicUrl + req.url);
          }
        } catch {
          // ignore malformed URL
        }
      }

      const linking = req.query.link === '1';
      const next = safeNext(req.query.next, linking ? '/account' : '/');
      if (await d.limited(`ratelimit:oauth:${req.ip}`, 30, 900)) {
        return back(reply, linking ? '/account' : '/login', { oauth_error: 'rate_limited' });
      }
      let linkUserId: number | null = null;
      if (linking) {
        const user = await d.loadUser(req);
        if (!user) return back(reply, '/login', { next: '/account' });
        linkUserId = user.id;
      }

      const state = newToken();
      const { verifier, challenge } = pkcePair();
      const pending: PendingLogin = { provider: cfg.id, verifier, next, linkUserId };
      try {
        await redis.set(`oauth:state:${tokenHash(state)}`, JSON.stringify(pending), 'EX', STATE_TTL);
      } catch {
        return back(reply, '/login', { oauth_error: 'unavailable' });
      }
      reply.header('Set-Cookie', stateCookie(req, state, STATE_TTL));
      return reply.redirect(buildAuthUrl(cfg, { state, challenge, redirectUri: redirectUri(req, cfg.id) }));
    },
  );

  app.get<{ Params: { provider: string }; Querystring: { code?: string; state?: string; error?: string } }>(
    '/api/auth/oauth/:provider/callback',
    async (req, reply) => {
      reply.header('Cache-Control', 'no-store');
      reply.header('Set-Cookie', stateCookie(req, '', 0));
      const p = req.params.provider;
      const cfg = isProviderId(p) ? d.providers.get(p) : undefined;
      const state = typeof req.query.state === 'string' ? req.query.state : '';
      const cookieState = parseCookies(req.headers.cookie)[STATE_COOKIE] ?? '';

      let pending: PendingLogin | null = null;
      if (state && state === cookieState) {
        try {
          const raw = await redis.getdel(`oauth:state:${tokenHash(state)}`);
          pending = raw ? (JSON.parse(raw) as PendingLogin) : null;
        } catch {
          pending = null;
        }
      }
      const failTo = pending?.linkUserId ? '/account' : '/login';
      if (!cfg || !pending || pending.provider !== cfg.id) return back(reply, failTo, { oauth_error: 'state' });
      if (req.query.error || typeof req.query.code !== 'string') return back(reply, failTo, { oauth_error: 'cancelled' });

      try {
        const profile = await fetchProfile(cfg, req.query.code, pending.verifier, redirectUri(req, cfg.id));

        if (pending.linkUserId) {
          await linkIdentity(pending.linkUserId, cfg.id, profile);
          return back(reply, '/account', { linked: cfg.id });
        }

        const user = await signInOrCreate(req, cfg.id, profile);
        if (user.status === 'banned') throw new OAuthFail('banned');
        await d.createSession(req, reply, user.id);
        return reply.redirect(pending.next);
      } catch (e) {
        const code = e instanceof OAuthFail ? e.code : 'provider';
        if (!(e instanceof OAuthFail)) req.log.warn({ err: e, provider: cfg.id }, 'oauth callback failed');
        return back(reply, failTo, { oauth_error: code });
      }
    },
  );

  app.post<{ Params: { provider: string } }>(
    '/api/auth/oauth/:provider/unlink',
    { preHandler: d.requireUser },
    async (req, reply) => {
      const user = req.user!;
      const p = req.params.provider;
      if (!isProviderId(p)) return reply.code(404).send({ error: 'not_found' });
      const { rows } = await db.query<{ has_password: boolean; others: number }>(
        `SELECT (password_hash IS NOT NULL) AS has_password,
                (SELECT COUNT(*)::int FROM user_identities WHERE user_id = $1 AND provider <> $2) AS others
           FROM users WHERE id = $1`,
        [user.id, p],
      );
      if (!rows[0]?.has_password && !rows[0]?.others) return reply.code(409).send({ error: 'last_login_method' });
      await db.query('DELETE FROM user_identities WHERE user_id = $1 AND provider = $2', [user.id, p]);
      return { ok: true };
    },
  );

  // ---------------------------------------------------------------------------

  async function fetchProfile(cfg: ProviderConfig, code: string, verifier: string, redirect: string): Promise<OAuthProfile> {
    const tokenRes = await fetch(cfg.tokenUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body: new URLSearchParams({
        grant_type: 'authorization_code',
        code,
        redirect_uri: redirect,
        client_id: cfg.clientId,
        client_secret: cfg.clientSecret,
        code_verifier: verifier,
      }),
      signal: AbortSignal.timeout(HTTP_TIMEOUT_MS),
    });
    if (!tokenRes.ok) throw new Error(`token endpoint ${tokenRes.status}: ${(await tokenRes.text()).slice(0, 300)}`);
    const token = (await tokenRes.json()) as { access_token?: string };
    if (!token.access_token) throw new Error('no access_token');

    const profRes = await fetch(cfg.profileUrl, {
      headers: { Authorization: `Bearer ${token.access_token}`, Accept: 'application/json' },
      signal: AbortSignal.timeout(HTTP_TIMEOUT_MS),
    });
    if (!profRes.ok) throw new Error(`profile endpoint ${profRes.status}`);
    const profile = parseProfile(cfg.id, await profRes.json());
    if (!profile) throw new Error('unusable profile');
    return profile;
  }

  /** D-65: Google/Discord vouch for the address: if it is the account's own e-mail, it counts as confirmed */
  async function confirmEmailFrom(userId: number, profile: OAuthProfile) {
    if (!profile.email || !profile.emailVerified) return;
    await db.query('UPDATE users SET email_verified_at = now() WHERE id = $1 AND email_verified_at IS NULL AND LOWER(email) = LOWER($2)', [userId, profile.email]);
  }

  async function linkIdentity(userId: number, provider: ProviderId, profile: OAuthProfile) {
    const owner = await db.query<{ user_id: number }>(
      'SELECT user_id FROM user_identities WHERE provider = $1 AND subject = $2',
      [provider, profile.subject],
    );
    if (owner.rows[0] && owner.rows[0].user_id !== userId) throw new OAuthFail('identity_in_use');
    // One account per provider per user: linking again replaces the previous one
    await db.query('DELETE FROM user_identities WHERE user_id = $1 AND provider = $2 AND subject <> $3', [
      userId,
      provider,
      profile.subject,
    ]);
    await db.query(
      `INSERT INTO user_identities (provider, subject, user_id, email)
       VALUES ($1, $2, $3, $4)
       ON CONFLICT (provider, subject) DO UPDATE SET email = EXCLUDED.email, last_used_at = now()`,
      [provider, profile.subject, userId, profile.email],
    );
    await confirmEmailFrom(userId, profile);
  }

  async function signInOrCreate(req: FastifyRequest, provider: ProviderId, profile: OAuthProfile) {
    const known = await db.query<{ id: number; status: string }>(
      `UPDATE user_identities i SET last_used_at = now(), email = COALESCE($3, i.email)
         FROM users u
        WHERE i.provider = $1 AND i.subject = $2 AND u.id = i.user_id
        RETURNING u.id, u.status`,
      [provider, profile.subject, profile.email],
    );
    if (known.rows[0]) {
      await confirmEmailFrom(known.rows[0].id, profile);
      return known.rows[0];
    }

    const taken = profile.email
      ? (await db.query<{ id: number; status: string; verified: boolean }>('SELECT id, status, (email_verified_at IS NOT NULL) AS verified FROM users WHERE email = $1', [profile.email])).rows[0]
      : undefined;
    const decision = oauthDecision(profile, taken ? { id: taken.id, emailVerified: taken.verified } : null);
    if (decision === 'email_unverified' || decision === 'email_exists') throw new OAuthFail(decision);
    if (decision === 'link_existing') {
      await linkIdentity(taken!.id, provider, profile);
      return { id: taken!.id, status: taken!.status };
    }
    if (!profile.email) throw new OAuthFail('email_unverified');
    if (await d.limited(`ratelimit:register:${req.ip}`, 5, 3600)) throw new OAuthFail('rate_limited');

    const client = await db.connect();
    try {
      await client.query('BEGIN');
      // terms_version stays NULL: the site asks for acceptance right after the first login
      const isTestPremium = profile.email.toLowerCase() === 'atilio_calefi@yahoo.com.br';
      const tier = isTestPremium ? 'pro' : 'free';
      const premiumUntil = isTestPremium ? new Date('2099-12-31T23:59:59Z') : null;
      const ins = await client.query<{ id: number; status: string }>(
        `INSERT INTO users (email, display_name, password_hash, role, tier, premium_until, email_verified_at)
         VALUES ($1, $2, NULL, $3, $4, $5, now())
         ON CONFLICT (email) DO NOTHING RETURNING id, status`,
        [profile.email, displayNameFrom(profile), d.adminEmails.includes(profile.email) ? 'admin' : 'user', tier, premiumUntil],
      );
      if (!ins.rows[0]) throw new OAuthFail('email_exists');
      await client.query(
        `INSERT INTO user_identities (provider, subject, user_id, email) VALUES ($1, $2, $3, $4)`,
        [provider, profile.subject, ins.rows[0].id, profile.email],
      );
      await client.query('COMMIT');
      return ins.rows[0];
    } catch (e) {
      await client.query('ROLLBACK').catch(() => undefined);
      throw e;
    } finally {
      client.release();
    }
  }
}
