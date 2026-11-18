/** Accounts (login/registration), admin panel API and the anonymous traffic counter (D-24..D-27). */
import { createHash } from 'node:crypto';
import type { FastifyInstance, FastifyReply, FastifyRequest } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import {
  DUMMY_HASH,
  LEGAL_VERSION,
  hashPassword,
  newToken,
  normalizeCharacter,
  normalizeDisplayName,
  normalizeEmail,
  parseCookies,
  publicUser,
  sessionCookie,
  tokenHash,
  validPassword,
  verifyPassword,
  type UserRow,
} from './auth.js';
import type { ProviderConfig, ProviderId } from './oauth.js';
import type { Mailer } from './mailer.js';
import { mailLang, passwordChangedEmail, resetEmail, ticketNoticeEmail, verifyEmail } from './emails.js';
import { siteOrigin } from './siteOrigin.js';
import { registerOAuthRoutes } from './oauthRoutes.js';

const COOKIE = 'bp_session';
const SESSION_DAYS = 30;
const SESSION_TOUCH_MS = 60 * 60 * 1000; // refresh last_seen / expiry at most once per hour
const RESET_HOURS = 48;
/** D-65: self-service links ("forgot password") are short-lived; admin-made links keep RESET_HOURS */
const SELF_RESET_MINUTES = 60;
const PAGE_SIZE = 25;

const USER_COLUMNS = `id, email, display_name, tibia_character, role, status, tier, premium_until, premium_note, created_at, terms_version, email_verified_at`;
/** D-65: e-mail confirmation link lifetime and resend limits */
const VERIFY_HOURS = 24;
const VERIFY_COOLDOWN_S = 60;

export interface AccountOptions {
  db: pg.Pool;
  redis: Redis;
  /** E-mails that are always admins (bootstrap, from ADMIN_EMAILS) */
  adminEmails: string[];
  /** 'auto' = Secure when the request came over HTTPS */
  cookieSecure: 'auto' | boolean;
  fingerprintSalt: string;
  /** Configured "sign in with" providers (D-39) */
  oauthProviders?: Map<ProviderId, ProviderConfig>;
  /** Canonical origin for OAuth redirects, e.g. https://bagparser.com (PUBLIC_URL) */
  publicUrl?: string;
  /** D-65: transactional e-mail (password reset, notices); disabled when SMTP is not configured */
  mailer?: Mailer;
  /** D-75: called after a ticket is created on the site (Telegram notice to the admins) */
  onTicket?: (t: { id: number; name: string; email: string | null; category: string; subject: string; message: string; userId: number | null }) => void;
}

declare module 'fastify' {
  interface FastifyRequest {
    user?: UserRow;
  }
}

export async function bootstrapAdmins(db: pg.Pool, emails: string[]): Promise<number> {
  if (!emails.length) return 0;
  const res = await db.query(`UPDATE users SET role = 'admin' WHERE email = ANY($1::text[]) AND role <> 'admin'`, [
    emails,
  ]);
  return res.rowCount ?? 0;
}

export const DEFAULT_PREMIUM_EMAILS = ['atilio_calefi@yahoo.com.br'];

export async function bootstrapPremiumUsers(db: pg.Pool, extraEmails: string[] = []): Promise<number> {
  const emails = Array.from(new Set([...DEFAULT_PREMIUM_EMAILS, ...extraEmails])).map((e) => e.toLowerCase());
  if (!emails.length) return 0;
  const res = await db.query(
    `UPDATE users SET tier = 'pro', premium_until = '2099-12-31 23:59:59Z' WHERE lower(email) = ANY($1::text[])`,
    [emails]
  );
  return res.rowCount ?? 0;
}

export interface AuthGuards {
  requireUser: (req: FastifyRequest, reply: FastifyReply) => Promise<unknown>;
  requireAdmin: (req: FastifyRequest, reply: FastifyReply) => Promise<unknown>;
  /** Loads req.user when signed in; never rejects (public pages that show more to the owner) */
  optionalUser: (req: FastifyRequest) => Promise<unknown>;
  limited: (key: string, max: number, windowSeconds: number) => Promise<boolean>;
  /** Runs before an account is deleted (D-55: the billing module cancels the Stripe subscription) */
  onUserDelete: (hook: (userId: number, reason: string) => Promise<void>) => void;
}

async function verifyTurnstile(token: unknown, expectedAction: string, clientIp: string): Promise<boolean> {
  const secret = process.env.TURNSTILE_SECRET || process.env.TURNSTILE_SECRET_KEY;
  if (!secret) return true; // Graceful passthrough when not configured

  const expectedHostnames = new Set(
    (process.env.TURNSTILE_HOSTNAMES ?? 'bagparser.com,localhost,127.0.0.1')
      .split(',')
      .map((h) => h.trim())
      .filter(Boolean),
  );

  if (typeof token !== 'string' || token.length === 0 || token.length > 2048 || expectedHostnames.size === 0) {
    return false;
  }

  try {
    const r = await fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      signal: AbortSignal.timeout(10_000),
      body: new URLSearchParams({
        secret,
        response: token,
        remoteip: clientIp,
      }),
    });
    if (!r.ok) return false;
    const result = (await r.json()) as { success: boolean; action?: string; hostname?: string };
    if (!result.success) return false;
    if (result.action && result.action !== expectedAction) return false;
    if (result.hostname && !expectedHostnames.has(result.hostname)) return false;
    return true;
  } catch {
    return false;
  }
}

export function registerAccountRoutes(app: FastifyInstance, opts: AccountOptions): AuthGuards {
  const deleteHooks: ((userId: number, reason: string) => Promise<void>)[] = [];
  const beforeDelete = async (userId: number, reason: string) => {
    for (const hook of deleteHooks) await hook(userId, reason).catch(() => undefined);
  };
  const { db, redis } = opts;

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  const secure = (req: FastifyRequest) => (opts.cookieSecure === 'auto' ? req.protocol === 'https' : opts.cookieSecure);

  const setSession = (req: FastifyRequest, reply: FastifyReply, token: string) =>
    reply.header('Set-Cookie', sessionCookie(COOKIE, token, { maxAgeSeconds: SESSION_DAYS * 86400, secure: secure(req) }));

  const clearSession = (req: FastifyRequest, reply: FastifyReply) =>
    reply.header('Set-Cookie', sessionCookie(COOKIE, '', { maxAgeSeconds: 0, secure: secure(req) }));

  async function createSession(req: FastifyRequest, reply: FastifyReply, userId: number) {
    const token = newToken();
    await db.query(
      `INSERT INTO sessions (id, user_id, expires_at, ip, user_agent)
       VALUES ($1, $2, now() + make_interval(days => $3), $4, $5)`,
      [tokenHash(token), userId, SESSION_DAYS, req.ip.slice(0, 64), String(req.headers['user-agent'] ?? '').slice(0, 255)],
    );
    await db.query('UPDATE users SET last_login_at = now() WHERE id = $1', [userId]);
    setSession(req, reply, token);
  }

  /** Fixed-window counter in Redis; true when over the limit. Fails open if Redis is down. */
  async function limited(key: string, max: number, windowSeconds: number): Promise<boolean> {
    try {
      const n = await redis.incr(key);
      if (n === 1) await redis.expire(key, windowSeconds);
      return n > max;
    } catch {
      return false;
    }
  }

  async function loadUser(req: FastifyRequest): Promise<UserRow | undefined> {
    const token = parseCookies(req.headers.cookie)[COOKIE];
    if (!token) return undefined;
    const id = tokenHash(token);
    const { rows } = await db.query<UserRow & { last_seen_at: Date }>(
      `SELECT ${USER_COLUMNS.split(', ').map((c) => 'u.' + c).join(', ')}, s.last_seen_at
         FROM sessions s JOIN users u ON u.id = s.user_id
        WHERE s.id = $1 AND s.expires_at > now()`,
      [id],
    );
    const row = rows[0];
    if (!row || row.status !== 'active') return undefined;
    if (Date.now() - row.last_seen_at.getTime() > SESSION_TOUCH_MS) {
      // Sliding expiry
      await db.query(
        `UPDATE sessions SET last_seen_at = now(), expires_at = now() + make_interval(days => $2) WHERE id = $1`,
        [id, SESSION_DAYS],
      );
    }
    return row;
  }

  async function requireUser(req: FastifyRequest, reply: FastifyReply) {
    req.user = await loadUser(req);
    if (!req.user) return reply.code(401).send({ error: 'unauthorized' });
  }

  async function optionalUser(req: FastifyRequest) {
    req.user = await loadUser(req);
  }

  async function requireAdmin(req: FastifyRequest, reply: FastifyReply) {
    req.user = await loadUser(req);
    if (!req.user) return reply.code(401).send({ error: 'unauthorized' });
    if (req.user.role !== 'admin') return reply.code(403).send({ error: 'forbidden' });
  }

  async function audit(admin: UserRow, action: string, target: { id: number; email: string } | null, details?: object) {
    await db.query(
      `INSERT INTO admin_audit (admin_id, admin_email, target_user_id, target_email, action, details)
       VALUES ($1, $2, $3, $4, $5, $6)`,
      [admin.id, admin.email, target?.id ?? null, target?.email ?? null, action, details ? JSON.stringify(details) : null],
    );
  }

  // CSRF: state-changing account/admin requests must be JSON (cannot be sent cross-site without a CORS
  // preflight) and, when the browser sends Origin, its hostname must match the Host header. Ports are
  // ignored because the web container's Nginx forwards `Host: $host` (no port). Cookies are also SameSite=Lax.
  app.addHook('onRequest', async (req, reply) => {
    if (req.method === 'GET' || req.method === 'HEAD') return;
    if (req.url.startsWith('/api/billing/webhook')) return;
    if (!/^\/api\/(auth|admin|characters|yasir|hunts|hunt-templates|builds|friends|payouts|errors|notify|promo|billing)(\/|$|\?)/.test(req.url)) return;
    const type = String(req.headers['content-type'] ?? '');
    if (!type.startsWith('application/json')) return reply.code(415).send({ error: 'json required' });
    const origin = req.headers.origin;
    if (origin) {
      let originHost = '';
      try {
        originHost = new URL(origin).hostname;
      } catch {
        /* invalid origin */
      }
      const host = String(req.headers.host ?? '').replace(/:\d+$/, '');
      if (!originHost || originHost !== host) return reply.code(403).send({ error: 'bad origin' });
    }
  });

  // -------------------------------------------------------------------------
  // Auth
  // -------------------------------------------------------------------------

  app.get('/api/auth/me', async (req, reply) => {
    reply.header('Cache-Control', 'no-store');
    const user = await loadUser(req);
    if (!user) return { user: null };
    const [login, ids] = await Promise.all([
      db.query<{ has_password: boolean }>('SELECT password_hash IS NOT NULL AS has_password FROM users WHERE id = $1', [
        user.id,
      ]),
      db.query<{ provider: string; email: string | null }>(
        'SELECT provider, email FROM user_identities WHERE user_id = $1 ORDER BY provider',
        [user.id],
      ),
    ]);
    return {
      user: { ...publicUser(user), has_password: login.rows[0]?.has_password ?? true, identities: ids.rows },
    };
  });

  app.post<{ Body: { email?: unknown; password?: unknown; display_name?: unknown; accept_terms?: unknown; turnstile_token?: unknown; hp?: unknown } }>(
    '/api/auth/register',
    async (req, reply) => {
      if (typeof req.body?.hp === 'string' && req.body.hp.trim().length > 0) {
        return reply.code(400).send({ error: 'invalid_submission' });
      }
      if (!(await verifyTurnstile(req.body?.turnstile_token, 'register', req.ip))) {
        return reply.code(403).send({ error: 'turnstile_failed' });
      }
      const email = normalizeEmail(req.body?.email);
      const displayName = normalizeDisplayName(req.body?.display_name);
      const password = req.body?.password;
      if (!email) return reply.code(400).send({ error: 'invalid_email' });
      if (!displayName) return reply.code(400).send({ error: 'invalid_name' });
      if (!validPassword(password)) return reply.code(400).send({ error: 'invalid_password' });
      if (req.body?.accept_terms !== true) return reply.code(400).send({ error: 'terms_required' });
      if (await limited(`ratelimit:register:${req.ip}`, 5, 3600)) return reply.code(429).send({ error: 'rate_limited' });

      const role = opts.adminEmails.includes(email) ? 'admin' : 'user';
      const isTestPremium = email.toLowerCase() === 'atilio_calefi@yahoo.com.br';
      const tier = isTestPremium ? 'pro' : 'free';
      const premiumUntil = isTestPremium ? new Date('2099-12-31T23:59:59Z') : null;
      const hash = await hashPassword(password);
      const ins = await db.query<{ id: number }>(
        `INSERT INTO users (email, display_name, password_hash, role, tier, premium_until, terms_version, terms_accepted_at)
         VALUES ($1, $2, $3, $4, $5, $6, $7, now())
         ON CONFLICT (email) DO NOTHING RETURNING id`,
        [email, displayName, hash, role, tier, premiumUntil, LEGAL_VERSION],
      );
      if (!ins.rowCount) return reply.code(409).send({ error: 'email_taken' });
      await createSession(req, reply, ins.rows[0].id);
      // D-65: confirmation e-mail (after the reply path; a mail problem never blocks the sign-up)
      if (opts.mailer?.enabled) {
        const lang = mailLang((req.body as { lang?: unknown })?.lang ?? req.headers['accept-language']);
        const origin = siteOrigin(opts.publicUrl, req);
        void sendVerification(ins.rows[0].id, email, displayName, lang, origin).catch((err) => app.log.error({ err: (err as Error).message }, 'verification e-mail failed'));
      }
      const { rows } = await db.query<UserRow>(`SELECT ${USER_COLUMNS} FROM users WHERE id = $1`, [ins.rows[0].id]);
      return reply.code(201).send({ user: publicUser(rows[0]) });
    },
  );

  app.post<{ Body: { email?: unknown; password?: unknown; turnstile_token?: unknown; hp?: unknown } }>('/api/auth/login', async (req, reply) => {
    if (typeof req.body?.hp === 'string' && req.body.hp.trim().length > 0) {
      return reply.code(400).send({ error: 'invalid_submission' });
    }
    if (!(await verifyTurnstile(req.body?.turnstile_token, 'login', req.ip))) {
      return reply.code(403).send({ error: 'turnstile_failed' });
    }
    const email = normalizeEmail(req.body?.email);
    const password = typeof req.body?.password === 'string' ? req.body.password.slice(0, 200) : '';
    if (!email || !password) return reply.code(400).send({ error: 'invalid_credentials' });

    const emailKey = createHash('sha256').update(email).digest('hex').slice(0, 32);
    if (
      (await limited(`ratelimit:login:ip:${req.ip}`, 20, 900)) ||
      (await limited(`ratelimit:login:email:${emailKey}`, 8, 900))
    ) {
      return reply.code(429).send({ error: 'rate_limited' });
    }

    const { rows } = await db.query<UserRow & { password_hash: string; ban_reason: string | null }>(
      `SELECT ${USER_COLUMNS}, password_hash, ban_reason FROM users WHERE email = $1`,
      [email],
    );
    const user = rows[0];
    const ok = await verifyPassword(password, user?.password_hash ?? DUMMY_HASH);
    if (!user || !ok) return reply.code(401).send({ error: 'invalid_credentials' });
    if (user.status === 'banned') return reply.code(403).send({ error: 'banned', reason: user.ban_reason });

    await createSession(req, reply, user.id);
    return { user: publicUser(user) };
  });

  app.post('/api/auth/logout', async (req, reply) => {
    const token = parseCookies(req.headers.cookie)[COOKIE];
    if (token) await db.query('DELETE FROM sessions WHERE id = $1', [tokenHash(token)]);
    clearSession(req, reply);
    return { ok: true };
  });

  app.patch<{ Body: { display_name?: unknown; tibia_character?: unknown } }>(
    '/api/auth/me',
    { preHandler: requireUser },
    async (req, reply) => {
      const user = req.user!;
      const displayName =
        req.body?.display_name === undefined ? user.display_name : normalizeDisplayName(req.body.display_name);
      const character =
        req.body?.tibia_character === undefined ? user.tibia_character : normalizeCharacter(req.body.tibia_character);
      if (!displayName) return reply.code(400).send({ error: 'invalid_name' });
      if (character === undefined) return reply.code(400).send({ error: 'invalid_character' });
      const { rows } = await db.query<UserRow>(
        `UPDATE users SET display_name = $2, tibia_character = $3 WHERE id = $1 RETURNING ${USER_COLUMNS}`,
        [user.id, displayName, character],
      );
      return { user: publicUser(rows[0]) };
    },
  );

  app.post<{ Body: { current_password?: unknown; new_password?: unknown } }>(
    '/api/auth/password',
    { preHandler: requireUser },
    async (req, reply) => {
      const user = req.user!;
      if (!validPassword(req.body?.new_password)) return reply.code(400).send({ error: 'invalid_password' });
      if (await limited(`ratelimit:password:${user.id}`, 10, 900)) return reply.code(429).send({ error: 'rate_limited' });
      const { rows } = await db.query<{ password_hash: string | null }>('SELECT password_hash FROM users WHERE id = $1', [
        user.id,
      ]);
      const current = typeof req.body?.current_password === 'string' ? req.body.current_password : '';
      // Accounts created with Google/Discord have no password yet: the first one needs no current password
      if (rows[0].password_hash !== null && !(await verifyPassword(current, rows[0].password_hash))) {
        return reply.code(401).send({ error: 'invalid_credentials' });
      }
      await db.query('UPDATE users SET password_hash = $2, password_changed_at = now() WHERE id = $1', [
        user.id,
        await hashPassword(req.body.new_password as string),
      ]);
      // Sign out everywhere else
      const token = parseCookies(req.headers.cookie)[COOKIE];
      await db.query('DELETE FROM sessions WHERE user_id = $1 AND id <> $2', [user.id, tokenHash(token ?? '')]);
      return { ok: true };
    },
  );

  /** Accept the current Terms/Privacy version (asked again after a relevant change) */
  app.post('/api/auth/accept-terms', { preHandler: requireUser }, async (req) => {
    const { rows } = await db.query<UserRow>(
      `UPDATE users SET terms_version = $2, terms_accepted_at = now() WHERE id = $1 RETURNING ${USER_COLUMNS}`,
      [req.user!.id, LEGAL_VERSION],
    );
    return { user: publicUser(rows[0]) };
  });

  /** LGPD art. 18 / GDPR art. 15 and 20: download every personal record we hold, as JSON */
  app.get('/api/auth/export', { preHandler: requireUser }, async (req, reply) => {
    const id = req.user!.id;
    const q = (sql: string) => db.query(sql, [id]).then((r) => r.rows);
    const [account, sessions, characters, snapshots, deaths, goals, yasir, identities, soulcores, notify] = await Promise.all([
      q(`SELECT email, display_name, tibia_character, role, status, premium_until, created_at, last_login_at,
                password_changed_at, terms_version, terms_accepted_at FROM users WHERE id = $1`),
      q(`SELECT created_at, last_seen_at, expires_at, ip, user_agent FROM sessions WHERE user_id = $1`),
      q(`SELECT name, world, vocation, level, verified_at, created_at, last_refresh_at, residence, guild,
                account_status, achievement_points, last_login FROM characters WHERE user_id = $1`),
      q(`SELECT c.name AS character, s.taken_at, s.level, s.experience, s.exp_exact, s.achievement_points
           FROM character_snapshots s JOIN characters c ON c.id = s.character_id WHERE c.user_id = $1 ORDER BY s.taken_at`),
      q(`SELECT c.name AS character, d.time, d.level, d.reason
           FROM character_deaths d JOIN characters c ON c.id = d.character_id WHERE c.user_id = $1 ORDER BY d.time`),
      q(`SELECT c.name AS character, g.target_level, g.target_date, g.created_at, g.achieved_at
           FROM character_goals g JOIN characters c ON c.id = g.character_id WHERE c.user_id = $1`),
      q(`SELECT day, world, present, created_at FROM yasir_reports WHERE user_id = $1 ORDER BY day`),
      q(`SELECT provider, subject, email, created_at, last_used_at FROM user_identities WHERE user_id = $1`),
      q(`SELECT c.name AS character, s.creature, s.added_at
           FROM character_soulcores s JOIN characters c ON c.id = s.character_id WHERE c.user_id = $1 ORDER BY c.name, s.creature`),
      q(`SELECT kind, chat_type, title, world, lang, tz, hour, daily, personal, weekly, created_at FROM notify_targets WHERE user_id = $1 ORDER BY id`),
    ]);
    reply.header('Content-Disposition', 'attachment; filename="bagparser-my-data.json"');
    return {
      exported_at: new Date().toISOString(),
      controller: 'BagParser (https://bagparser.com)',
      account: account[0],
      sessions,
      characters,
      character_snapshots: snapshots,
      character_deaths: deaths,
      character_goals: goals,
      yasir_reports: yasir,
      linked_logins: identities,
      soul_cores: soulcores,
      notifications: notify,
    };
  });

  /** LGPD: users can delete their own account */
  app.delete<{ Body: { password?: unknown; confirm_email?: unknown } }>('/api/auth/me', { preHandler: requireUser }, async (req, reply) => {
    const user = req.user!;
    const { rows } = await db.query<{ password_hash: string | null }>('SELECT password_hash FROM users WHERE id = $1', [
      user.id,
    ]);
    const password = typeof req.body?.password === 'string' ? req.body.password : '';
    // Without a password (Google/Discord account) the person confirms by typing the account e-mail
    const confirmed =
      rows[0].password_hash === null
        ? normalizeEmail(req.body?.confirm_email) === user.email
        : await verifyPassword(password, rows[0].password_hash);
    if (!confirmed) {
      return reply.code(401).send({ error: 'invalid_credentials' });
    }
    if (user.role === 'admin') {
      const admins = await db.query<{ n: number }>(`SELECT COUNT(*)::int AS n FROM users WHERE role = 'admin'`);
      if (admins.rows[0].n <= 1) return reply.code(409).send({ error: 'last_admin' });
    }
    await beforeDelete(user.id, 'self');
    await db.query('DELETE FROM users WHERE id = $1', [user.id]);
    clearSession(req, reply);
    return { ok: true };
  });

  /**
   * D-65: e-mail confirmation. A new link voids the previous ones; the page /verify confirms with a POST (mail
   * scanners that open links do not confirm anything by themselves).
   */
  async function sendVerification(userId: number, email: string, name: string, lang: ReturnType<typeof mailLang>, origin: string) {
    if (!opts.mailer?.enabled) throw new Error('mail_disabled');
    await db.query('UPDATE email_verifications SET used_at = now() WHERE user_id = $1 AND used_at IS NULL', [userId]);
    const token = newToken();
    await db.query(
      `INSERT INTO email_verifications (token_hash, user_id, email, expires_at) VALUES ($1, $2, $3, now() + make_interval(hours => $4))`,
      [tokenHash(token), userId, email, VERIFY_HOURS],
    );
    await opts.mailer.send(email, verifyEmail({ lang, name, url: `${origin}/verify?token=${token}`, hours: VERIFY_HOURS, support: opts.mailer.support }));
  }

  app.post<{ Body: { lang?: unknown } }>('/api/auth/verify-email/resend', { preHandler: requireUser }, async (req, reply) => {
    const u = req.user!;
    if (u.email_verified_at) return { ok: true, already: true };
    if (!opts.mailer?.enabled) return reply.code(503).send({ error: 'mail_disabled' });
    // One per minute and five per hour per account
    if (await limited(`ratelimit:verify:cool:${u.id}`, 1, VERIFY_COOLDOWN_S)) return reply.code(429).send({ error: 'rate_limited', retry_in: VERIFY_COOLDOWN_S });
    if (await limited(`ratelimit:verify:hour:${u.id}`, 5, 3600)) return reply.code(429).send({ error: 'rate_limited', retry_in: 3600 });
    try {
      await sendVerification(u.id, u.email, u.display_name, mailLang(req.body?.lang ?? req.headers['accept-language']), siteOrigin(opts.publicUrl, req));
    } catch (err) {
      app.log.error({ err: (err as Error).message }, 'verification e-mail failed');
      return reply.code(502).send({ error: 'mail_failed' });
    }
    return { ok: true, email: u.email, retry_in: VERIFY_COOLDOWN_S };
  });

  app.post<{ Body: { token?: unknown } }>('/api/auth/verify-email', async (req, reply) => {
    const token = typeof req.body?.token === 'string' ? req.body.token : '';
    if (!token || (await limited(`ratelimit:verify:ip:${req.ip}`, 30, 900))) return reply.code(400).send({ error: 'invalid_token' });
    const used = await db.query<{ user_id: number; email: string }>(
      `UPDATE email_verifications SET used_at = now()
        WHERE token_hash = $1 AND used_at IS NULL AND expires_at > now() RETURNING user_id, email`,
      [tokenHash(token)],
    );
    if (!used.rowCount) return reply.code(404).send({ error: 'invalid_token' });
    // The link confirms the address it was sent to; if the account changed e-mail since, it does not count
    const r = await db.query(
      'UPDATE users SET email_verified_at = COALESCE(email_verified_at, now()) WHERE id = $1 AND LOWER(email) = LOWER($2)',
      [used.rows[0].user_id, used.rows[0].email],
    );
    if (!r.rowCount) return reply.code(404).send({ error: 'invalid_token' });
    return { ok: true, email: used.rows[0].email };
  });

  /**
   * D-65: "forgot password". Always the same answer (no account enumeration); the e-mail goes out after the reply,
   * so the response time does not tell either. The link lasts SELF_RESET_MINUTES and asking again voids the old one.
   */
  app.post<{ Body: { email?: unknown; lang?: unknown } }>('/api/auth/forgot', async (req, reply) => {
    const email = normalizeEmail(req.body?.email);
    if (!email) return reply.code(400).send({ error: 'invalid_email' });
    if ((await limited(`ratelimit:forgot:ip:${req.ip}`, 6, 900)) || (await limited(`ratelimit:forgot:email:${email}`, 3, 3600))) {
      return reply.code(429).send({ error: 'rate_limited' });
    }
    const lang = mailLang(req.body?.lang ?? req.headers['accept-language']);
    const origin = siteOrigin(opts.publicUrl, req);
    void (async () => {
      const { rows } = await db.query<{ id: number; display_name: string; status: string }>('SELECT id, display_name, status FROM users WHERE email = $1', [email]);
      const u = rows[0];
      if (!u || u.status !== 'active' || !opts.mailer?.enabled) {
        if (u && !opts.mailer?.enabled) app.log.warn('forgot password: e-mail is not configured (SMTP_*)');
        return;
      }
      // Older self-service links stop working; admin-made links (created_by) are left alone
      await db.query('UPDATE password_resets SET used_at = now() WHERE user_id = $1 AND used_at IS NULL AND created_by IS NULL', [u.id]);
      const token = newToken();
      await db.query(
        `INSERT INTO password_resets (token_hash, user_id, created_by, expires_at) VALUES ($1, $2, NULL, now() + make_interval(mins => $3))`,
        [tokenHash(token), u.id, SELF_RESET_MINUTES],
      );
      const mail = resetEmail({ lang, name: u.display_name, url: `${origin}/reset?token=${token}`, minutes: SELF_RESET_MINUTES, support: opts.mailer.support });
      await opts.mailer.send(email, mail);
    })().catch((err) => app.log.error({ err: (err as Error).message }, 'forgot password e-mail failed'));
    return { ok: true };
  });

  app.get<{ Querystring: { token?: string } }>('/api/auth/reset', async (req, reply) => {
    reply.header('Cache-Control', 'no-store');
    const token = typeof req.query.token === 'string' ? req.query.token : '';
    const { rows } = await db.query<{ email: string }>(
      `SELECT u.email FROM password_resets r JOIN users u ON u.id = r.user_id
        WHERE r.token_hash = $1 AND r.used_at IS NULL AND r.expires_at > now()`,
      [tokenHash(token)],
    );
    if (!rows[0]) return reply.code(404).send({ error: 'invalid_token' });
    return { email: rows[0].email };
  });

  app.post<{ Body: { token?: unknown; password?: unknown } }>('/api/auth/reset', async (req, reply) => {
    const token = typeof req.body?.token === 'string' ? req.body.token : '';
    if (!validPassword(req.body?.password)) return reply.code(400).send({ error: 'invalid_password' });
    if (await limited(`ratelimit:reset:${req.ip}`, 10, 900)) return reply.code(429).send({ error: 'rate_limited' });
    const used = await db.query<{ user_id: number; created_by: number | null }>(
      `UPDATE password_resets SET used_at = now()
        WHERE token_hash = $1 AND used_at IS NULL AND expires_at > now() RETURNING user_id, created_by`,
      [tokenHash(token)],
    );
    if (!used.rowCount) return reply.code(404).send({ error: 'invalid_token' });
    const userId = used.rows[0].user_id;
    // D-65: a link that went by e-mail (not made by an admin) proves the person reads that inbox
    if (used.rows[0].created_by === null) await db.query('UPDATE users SET email_verified_at = COALESCE(email_verified_at, now()) WHERE id = $1', [userId]);
    await db.query('UPDATE users SET password_hash = $2, password_changed_at = now() WHERE id = $1', [
      userId,
      await hashPassword(req.body.password as string),
    ]);
    await db.query('DELETE FROM sessions WHERE user_id = $1', [userId]);
    // Every other open reset link of the account stops working too
    await db.query('UPDATE password_resets SET used_at = now() WHERE user_id = $1 AND used_at IS NULL', [userId]);
    if (opts.mailer?.enabled) {
      const origin = siteOrigin(opts.publicUrl, req);
      const lang = mailLang((req.body as { lang?: unknown })?.lang ?? req.headers['accept-language']);
      void (async () => {
        const u = (await db.query<{ email: string; display_name: string }>('SELECT email, display_name FROM users WHERE id = $1', [userId])).rows[0];
        if (u) await opts.mailer!.send(u.email, passwordChangedEmail({ lang, name: u.display_name, when: new Date(), support: opts.mailer!.support, loginUrl: `${origin}/login` }));
      })().catch((err) => app.log.error({ err: (err as Error).message }, 'password changed e-mail failed'));
    }
    return { ok: true };
  });

  // -------------------------------------------------------------------------
  // Traffic counter (fansite monthly report). Stores only per-day counts; visitors are
  // counted with a Redis HyperLogLog of salted hashes that rotate daily (no IP is kept).
  // -------------------------------------------------------------------------

  app.post<{ Body: { path?: unknown } }>('/api/pv', async (req, reply) => {
    const raw = typeof req.body?.path === 'string' ? req.body.path : '/';
    const ua = String(req.headers['user-agent'] ?? '');
    if (/bot|crawl|spider|slurp|headless|lighthouse|preview/i.test(ua)) return reply.code(204).send();
    // Group by section (/quickhunts/<slug> → /quickhunts) so the table stays small
    const section = '/' + (raw.split('?')[0].split('/')[1] ?? '').replace(/[^a-z0-9-]/gi, '').slice(0, 40);
    const day = new Date().toISOString().slice(0, 10);
    if (await limited(`ratelimit:pv:${req.ip}`, 120, 60)) return reply.code(204).send();
    await db.query(
      `INSERT INTO page_views (day, path, views) VALUES ($1, $2, 1)
       ON CONFLICT (day, path) DO UPDATE SET views = page_views.views + 1`,
      [day, section],
    );
    try {
      const visitor = createHash('sha256').update(`${opts.fingerprintSalt}|${day}|${req.ip}|${ua}`).digest('hex');
      const key = `pv:visitors:${day}`;
      if (await redis.pfadd(key, visitor)) {
        await redis.expire(key, 3 * 86400);
        const n = await redis.pfcount(key);
        await db.query(
          `INSERT INTO daily_visitors (day, visitors) VALUES ($1, $2)
           ON CONFLICT (day) DO UPDATE SET visitors = GREATEST(daily_visitors.visitors, EXCLUDED.visitors)`,
          [day, n],
        );
      }
    } catch {
      /* Redis down: views still counted */
    }
    return reply.code(204).send();
  });

  // -------------------------------------------------------------------------
  // Admin
  // -------------------------------------------------------------------------

  app.addHook('onSend', async (req, reply, payload) => {
    if (/^\/api\/(admin|auth|characters)/.test(req.url)) reply.header('Cache-Control', 'no-store');
    // D-74: a response to a signed-in request may carry that account's data: never kept by browsers or proxies
    else if (parseCookies(req.headers.cookie)[COOKIE] && !reply.hasHeader('Cache-Control')) reply.header('Cache-Control', 'private, no-store');
    return payload;
  });

  app.get('/api/admin/stats', { preHandler: requireAdmin }, async () => {
    const [users, traffic, days] = await Promise.all([
      db.query(
        `SELECT COUNT(*)::int AS total,
                COUNT(*) FILTER (WHERE premium_until > now())::int AS premium,
                COUNT(*) FILTER (WHERE status = 'banned')::int AS banned,
                COUNT(*) FILTER (WHERE role = 'admin')::int AS admins,
                COUNT(*) FILTER (WHERE created_at > now() - interval '7 days')::int AS new_7d,
                COUNT(*) FILTER (WHERE created_at > now() - interval '30 days')::int AS new_30d,
                (SELECT COUNT(DISTINCT user_id)::int FROM sessions WHERE last_seen_at > now() - interval '30 days') AS active_30d
           FROM users`,
      ),
      // Monthly totals for the report CipSoft asks for by the 3rd of each month
      db.query(
        `SELECT to_char(m.month, 'YYYY-MM') AS month,
                COALESCE((SELECT SUM(views) FROM page_views p WHERE date_trunc('month', p.day) = m.month), 0)::int AS views,
                COALESCE((SELECT SUM(visitors) FROM daily_visitors v WHERE date_trunc('month', v.day) = m.month), 0)::int AS visitor_days,
                COALESCE((SELECT MAX(visitors) FROM daily_visitors v WHERE date_trunc('month', v.day) = m.month), 0)::int AS peak_day
           FROM generate_series(date_trunc('month', now()) - interval '11 months', date_trunc('month', now()), interval '1 month') AS m(month)
          ORDER BY m.month DESC`,
      ),
      db.query(
        `SELECT path, SUM(views)::int AS views FROM page_views
          WHERE day >= date_trunc('month', now()) GROUP BY path ORDER BY views DESC LIMIT 10`,
      ),
    ]);
    return { users: users.rows[0], traffic: traffic.rows, top_paths: days.rows };
  });

  app.get<{ Querystring: { q?: string; filter?: string; page?: string } }>(
    '/api/admin/users',
    { preHandler: requireAdmin },
    async (req) => {
      const q = (req.query.q ?? '').trim().toLowerCase().slice(0, 100);
      const page = Math.max(1, Number(req.query.page) || 1);
      const where: string[] = [];
      const params: unknown[] = [];
      if (q) {
        params.push(`%${q.replace(/[\\%_]/g, (c) => '\\' + c)}%`);
        where.push(`(email LIKE $${params.length} OR LOWER(display_name) LIKE $${params.length} OR LOWER(tibia_character) LIKE $${params.length})`);
      }
      if (req.query.filter === 'premium') where.push('premium_until > now()');
      if (req.query.filter === 'banned') where.push(`status = 'banned'`);
      if (req.query.filter === 'admin') where.push(`role = 'admin'`);
      if (req.query.filter === 'testers') where.push(`premium_note IS NOT NULL`);
      const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
      const total = (await db.query<{ n: number }>(`SELECT COUNT(*)::int AS n FROM users ${whereSql}`, params)).rows[0].n;
      params.push(PAGE_SIZE, (page - 1) * PAGE_SIZE);
      const { rows } = await db.query(
        `SELECT ${USER_COLUMNS}, ban_reason, last_login_at,
                COALESCE(premium_until > now(), false) AS premium
           FROM users ${whereSql}
          ORDER BY created_at DESC
          LIMIT $${params.length - 1} OFFSET $${params.length}`,
        params,
      );
      return { total, page, page_size: PAGE_SIZE, users: rows };
    },
  );

  async function targetUser(idParam: string) {
    const id = Number(idParam);
    if (!Number.isInteger(id) || id <= 0) return null;
    const { rows } = await db.query<UserRow & { ban_reason: string | null }>(
      `SELECT ${USER_COLUMNS}, ban_reason, last_login_at, COALESCE(premium_until > now(), false) AS premium
         FROM users WHERE id = $1`,
      [id],
    );
    return rows[0] ?? null;
  }

  app.get<{ Params: { id: string } }>('/api/admin/users/:id', { preHandler: requireAdmin }, async (req, reply) => {
    const user = await targetUser(req.params.id);
    if (!user) return reply.code(404).send({ error: 'not_found' });
    const [sessions, log, chars] = await Promise.all([
      db.query(
        `SELECT created_at, last_seen_at, expires_at, ip, user_agent FROM sessions
          WHERE user_id = $1 AND expires_at > now() ORDER BY last_seen_at DESC LIMIT 20`,
        [user.id],
      ),
      db.query(
        `SELECT admin_email, action, details, created_at FROM admin_audit
          WHERE target_user_id = $1 ORDER BY created_at DESC LIMIT 30`,
        [user.id],
      ),
      db.query(
        `SELECT name, world, vocation, level, verified_at, last_refresh_at FROM characters
          WHERE user_id = $1 ORDER BY verified_at DESC NULLS LAST, name`,
        [user.id],
      ),
    ]);
    return { user, sessions: sessions.rows, audit: log.rows, characters: chars.rows };
  });

  app.patch<{
    Params: { id: string };
    Body: { role?: unknown; status?: unknown; ban_reason?: unknown; premium_until?: unknown; display_name?: unknown };
  }>('/api/admin/users/:id', { preHandler: requireAdmin }, async (req, reply) => {
    const admin = req.user!;
    const user = await targetUser(req.params.id);
    if (!user) return reply.code(404).send({ error: 'not_found' });
    const b = req.body ?? {};
    const sets: string[] = [];
    const params: unknown[] = [user.id];
    const changes: Record<string, unknown> = {};

    if (b.role !== undefined) {
      if (b.role !== 'user' && b.role !== 'admin') return reply.code(400).send({ error: 'invalid_role' });
      if (user.id === admin.id && b.role !== 'admin') return reply.code(409).send({ error: 'cannot_demote_self' });
      params.push(b.role);
      sets.push(`role = $${params.length}`);
      changes.role = b.role;
    }
    if (b.status !== undefined) {
      if (b.status !== 'active' && b.status !== 'banned') return reply.code(400).send({ error: 'invalid_status' });
      if (user.id === admin.id && b.status === 'banned') return reply.code(409).send({ error: 'cannot_ban_self' });
      params.push(b.status);
      sets.push(`status = $${params.length}`);
      const reason = b.status === 'banned' && typeof b.ban_reason === 'string' ? b.ban_reason.trim().slice(0, 500) : null;
      params.push(reason || null);
      sets.push(`ban_reason = $${params.length}`);
      changes.status = b.status;
      if (reason) changes.ban_reason = reason;
    }
    if (b.premium_until !== undefined) {
      let until: Date | null = null;
      if (b.premium_until !== null) {
        until = new Date(String(b.premium_until));
        if (Number.isNaN(until.getTime())) return reply.code(400).send({ error: 'invalid_date' });
      }
      params.push(until);
      sets.push(`premium_until = $${params.length}`);
      changes.premium_until = until?.toISOString() ?? null;
    }
    if (b.display_name !== undefined) {
      const name = normalizeDisplayName(b.display_name);
      if (!name) return reply.code(400).send({ error: 'invalid_name' });
      params.push(name);
      sets.push(`display_name = $${params.length}`);
      changes.display_name = name;
    }
    if (!sets.length) return reply.code(400).send({ error: 'nothing_to_change' });

    const { rows } = await db.query(
      `UPDATE users SET ${sets.join(', ')} WHERE id = $1
       RETURNING ${USER_COLUMNS}, ban_reason, last_login_at, COALESCE(premium_until > now(), false) AS premium`,
      params,
    );
    if (changes.status === 'banned') await db.query('DELETE FROM sessions WHERE user_id = $1', [user.id]);
    await audit(admin, 'update_user', user, changes);
    return { user: rows[0] };
  });

  app.post<{ Params: { id: string } }>('/api/admin/users/:id/reset-link', { preHandler: requireAdmin }, async (req, reply) => {
    const user = await targetUser(req.params.id);
    if (!user) return reply.code(404).send({ error: 'not_found' });
    const token = newToken();
    await db.query(
      `INSERT INTO password_resets (token_hash, user_id, created_by, expires_at)
       VALUES ($1, $2, $3, now() + make_interval(hours => $4))`,
      [tokenHash(token), user.id, req.user!.id, RESET_HOURS],
    );
    await audit(req.user!, 'reset_link', user);
    return { path: `/reset?token=${token}`, expires_hours: RESET_HOURS };
  });

  app.post<{ Params: { id: string } }>('/api/admin/users/:id/logout', { preHandler: requireAdmin }, async (req, reply) => {
    const user = await targetUser(req.params.id);
    if (!user) return reply.code(404).send({ error: 'not_found' });
    const res = await db.query('DELETE FROM sessions WHERE user_id = $1', [user.id]);
    await audit(req.user!, 'logout_all', user, { sessions: res.rowCount });
    return { ok: true, sessions: res.rowCount };
  });

  app.delete<{ Params: { id: string } }>('/api/admin/users/:id', { preHandler: requireAdmin }, async (req, reply) => {
    const user = await targetUser(req.params.id);
    if (!user) return reply.code(404).send({ error: 'not_found' });
    if (user.id === req.user!.id) return reply.code(409).send({ error: 'cannot_delete_self' });
    await beforeDelete(user.id, `admin:${req.user!.email}`);
    await db.query('DELETE FROM users WHERE id = $1', [user.id]);
    await audit(req.user!, 'delete_user', user);
    return { ok: true };
  });

  app.get('/api/admin/audit', { preHandler: requireAdmin }, async () => {
    const { rows } = await db.query(
      `SELECT admin_email, target_email, action, details, created_at FROM admin_audit
        ORDER BY created_at DESC LIMIT 100`,
    );
    return rows;
  });

  // Support / Problem reports
  app.post<{ Body: { name?: string; email?: string; category?: string; subject?: string; message?: string; client_info?: unknown } }>(
    '/api/support/message',
    async (req, reply) => {
      const user = await loadUser(req);
      const email = normalizeEmail(req.body?.email) || (user?.email ? normalizeEmail(user.email) : null);
      const name = typeof req.body?.name === 'string' ? req.body.name.trim() : user?.display_name ?? 'Anonymous';
      const category = typeof req.body?.category === 'string' ? req.body.category.slice(0, 40) : 'problem';
      const subject = typeof req.body?.subject === 'string' ? req.body.subject.trim().slice(0, 200) : 'Support request';
      const message = typeof req.body?.message === 'string' ? req.body.message.trim() : '';

      if (!message || message.length < 5) {
        return reply.code(400).send({ error: 'message_too_short' });
      }
      if (!email) {
        return reply.code(400).send({ error: 'invalid_email' });
      }

      if (await limited(`ratelimit:support:${req.ip}`, 5, 3600)) {
        return reply.code(429).send({ error: 'rate_limited' });
      }

      // D-70: technical details as sent by the page, without query strings (no tokens in tickets) and bounded
      const raw = (req.body?.client_info && typeof req.body.client_info === 'object' ? req.body.client_info : {}) as Record<string, unknown>;
      const clientInfo: Record<string, unknown> = { userAgent: String(req.headers['user-agent'] ?? '').slice(0, 300) };
      for (const [k, v] of Object.entries(raw).slice(0, 12)) {
        if (!/^[a-z_]{1,20}$/i.test(k)) continue;
        if (typeof v === 'string') clientInfo[k] = (k === 'url' ? v.split(/[?#]/)[0] : v).slice(0, 300);
        else if (typeof v === 'number' || typeof v === 'boolean') clientInfo[k] = v;
        else if (Array.isArray(v)) clientInfo[k] = v.slice(0, 5).map((x) => String(x).slice(0, 200));
      }

      const res = await db.query<{ id: number }>(
        `INSERT INTO support_messages (user_id, name, email, category, subject, message, client_info)
         VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING id`,
        [user?.id ?? null, name.slice(0, 100), email, category, subject, message.slice(0, 10000), JSON.stringify(clientInfo)],
      );

      const ticketId = res.rows[0].id;
      opts.onTicket?.({ id: ticketId, name: name.slice(0, 100), email, category, subject, message: message.slice(0, 4000), userId: user?.id ?? null });
      if (opts.mailer?.enabled) {
        const mail = ticketNoticeEmail({
          id: ticketId,
          name: name.slice(0, 100),
          email,
          category,
          subject,
          message: message.slice(0, 4000),
          adminUrl: `${siteOrigin(opts.publicUrl, req)}/admin/tickets`,
          support: opts.mailer.support,
        });
        void opts.mailer.send(opts.mailer.support, mail, { replyTo: email }).catch((err) => app.log.error({ err: (err as Error).message }, 'ticket notice e-mail failed'));
      }
      return { ok: true, id: ticketId };
    },
  );

  app.get('/api/admin/support', { preHandler: requireAdmin }, async () => {
    const { rows } = await db.query(
      `SELECT id, user_id, name, email, category, subject, message, status, admin_notes, created_at
       FROM support_messages ORDER BY created_at DESC LIMIT 100`,
    );
    return rows;
  });

  app.patch<{ Params: { id: string }; Body: { status?: string; admin_notes?: string } }>(
    '/api/admin/support/:id',
    { preHandler: requireAdmin },
    async (req, reply) => {
      const id = parseInt(req.params.id, 10);
      if (Number.isNaN(id)) return reply.code(400).send({ error: 'bad_id' });
      const status = req.body?.status;
      const notes = req.body?.admin_notes;
      const res = await db.query(
        `UPDATE support_messages
         SET status = COALESCE($1, status), admin_notes = COALESCE($2, admin_notes), updated_at = now()
         WHERE id = $3 RETURNING id, status, admin_notes`,
        [status ?? null, notes ?? null, id],
      );
      if (!res.rowCount) return reply.code(404).send({ error: 'not_found' });
      return { ok: true, message: res.rows[0] };
    },
  );

  registerOAuthRoutes(app, {
    db,
    redis,
    providers: opts.oauthProviders ?? new Map(),
    publicUrl: opts.publicUrl ?? '',
    adminEmails: opts.adminEmails,
    secure,
    loadUser,
    createSession,
    limited,
    requireUser,
  });

  return { requireUser, requireAdmin, optionalUser, limited, onUserDelete: (hook) => void deleteHooks.push(hook) };
}
