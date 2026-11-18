/**
 * Yasir sightings API (D-40). Scoring lives in yasir.ts.
 *
 * GET    /api/yasir?world=W              → today's status for one world (home card)
 * POST   /api/yasir {world, present, city?} → report (signed in; one per account, world and server day)
 * GET    /api/yasir/public?days=30       → today's sightings, history and patterns (public page)
 * GET    /api/admin/yasir?days=7         → raw reports with reporter, weight and reputation
 * DELETE /api/admin/yasir/:day/:world/:userId
 * POST   /api/admin/users/:id/yasir-block {blocked}  → reports of that account weigh 0 (silently)
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import type { AuthGuards } from './accounts.js';
import { serverDay } from './dailyRoutes.js';
import { baseWeight, isYasirCity, outcomes, reputations, stats, type DayOutcome, type YasirReport } from './yasir.js';

/** Reports considered for reputation and statistics */
const WINDOW_DAYS = 180;
const CACHE_MS = 60_000;

const WORLD_RE = /^[A-Z][a-z]{2,20}$/;
const DAY_RE = /^\d{4}-\d{2}-\d{2}$/;

const daysAgo = (day: string, n: number) => {
  const d = new Date(`${day}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() - n);
  return d.toISOString().slice(0, 10);
};

interface ReportRow {
  day: string;
  world: string;
  user_id: number;
  present: boolean;
  city: string | null;
  created_at: Date;
  account_created: Date;
  yasir_blocked: boolean;
  on_world: boolean;
  any_world: boolean;
}

const toReport = (r: ReportRow): YasirReport => ({
  day: r.day,
  world: r.world,
  userId: r.user_id,
  present: r.present,
  city: isYasirCity(r.city) ? r.city : null,
  reportedAt: r.created_at,
  accountCreatedAt: r.account_created,
  verifiedOnWorld: r.on_world,
  verifiedAnywhere: r.any_world,
  blocked: r.yasir_blocked,
});

export interface YasirLookup {
  /** Today's status on a world (same as GET /api/yasir) */
  today(world: string): Promise<{ status: string; city: string | null; reports: number }>;
  /** A report from the site or the Telegram bot (D-76) */
  report(userId: number, world: string, present: boolean, city: unknown): Promise<'ok' | 'invalid_world' | 'invalid_city' | 'rate_limited'>;
}

export function registerYasirRoutes(
  app: FastifyInstance,
  opts: { db: pg.Pool; guards: AuthGuards; redis?: Redis; hooks?: { onConfirmed?: (world: string, city: string | null) => void } },
): YasirLookup {
  const { db, guards } = opts;

  async function loadReports(since: string): Promise<YasirReport[]> {
    const { rows } = await db.query<ReportRow>(
      `SELECT r.day::text AS day, r.world, r.user_id, r.present, r.city, r.created_at,
              u.created_at AS account_created, u.yasir_blocked,
              EXISTS (SELECT 1 FROM characters c
                       WHERE c.user_id = r.user_id AND c.verified_at IS NOT NULL AND c.world = r.world) AS on_world,
              EXISTS (SELECT 1 FROM characters c
                       WHERE c.user_id = r.user_id AND c.verified_at IS NOT NULL) AS any_world
         FROM yasir_reports r JOIN users u ON u.id = r.user_id
        WHERE r.day >= $1::date`,
      [since],
    );
    return rows.map(toReport);
  }

  async function report(userId: number, world: string, present: boolean, rawCity: unknown): Promise<'ok' | 'invalid_world' | 'invalid_city' | 'rate_limited'> {
    const city = present ? rawCity : null;
    if (present && !isYasirCity(city)) return 'invalid_city';
    const known = await db.query('SELECT 1 FROM worlds WHERE name = $1 AND active', [world]);
    if (!known.rowCount) return 'invalid_world';
    if (await guards.limited(`ratelimit:yasir:${userId}`, 20, 3600)) return 'rate_limited';
    await db.query(
      `INSERT INTO yasir_reports (day, world, user_id, present, city) VALUES ($1, $2, $3, $4, $5)
       ON CONFLICT (day, world, user_id) DO UPDATE SET present = EXCLUDED.present, city = EXCLUDED.city, created_at = now()`,
      [serverDay(), world, userId, present, city],
    );
    cache = null;
    void announceIfConfirmed(world).catch(() => null);
    return 'ok';
  }

  /** D-76: the first time a world reaches "confirmed" on a server day, subscribers of that world are told */
  async function announceIfConfirmed(world: string): Promise<void> {
    if (!opts.hooks || !opts.redis) return;
    const { day, list } = await allOutcomes();
    const o = list.find((x) => x.day === day && x.world === world);
    if (o?.status !== 'confirmed') return;
    if ((await opts.redis.set(`yasir:announced:${day}:${world}`, '1', 'EX', 2 * 86400, 'NX')) !== 'OK') return;
    opts.hooks.onConfirmed?.(world, o.city);
  }

  // Outcomes are recomputed at most once a minute, and right after a new report
  let cache: { at: number; day: string; list: DayOutcome[] } | null = null;
  async function allOutcomes(): Promise<{ day: string; list: DayOutcome[] }> {
    const day = serverDay();
    if (cache && cache.day === day && Date.now() - cache.at < CACHE_MS) return cache;
    const list = outcomes(await loadReports(daysAgo(day, WINDOW_DAYS)), day);
    cache = { at: Date.now(), day, list };
    return cache;
  }

  app.get<{ Querystring: { world?: string } }>('/api/yasir', async (req, reply) => {
    const world = String(req.query.world ?? '');
    if (!WORLD_RE.test(world)) return reply.code(400).send({ error: 'invalid_world' });
    const { day, list } = await allOutcomes();
    const o = list.find((x) => x.day === day && x.world === world);
    reply.header('Cache-Control', 'no-store');
    return { world, day, status: o?.status ?? 'none', city: o?.city ?? null, reports: o?.reports ?? 0 };
  });

  app.post<{ Body: { world?: unknown; present?: unknown; city?: unknown } }>(
    '/api/yasir',
    { preHandler: guards.requireUser },
    async (req, reply) => {
      const present = req.body?.present;
      if (typeof present !== 'boolean') return reply.code(400).send({ error: 'invalid_vote' });
      const r = await report(req.user!.id, String(req.body?.world ?? ''), present, req.body?.city);
      if (r === 'rate_limited') return reply.code(429).send({ error: r });
      if (r !== 'ok') return reply.code(400).send({ error: r });
      return { ok: true };
    },
  );

  app.get<{ Querystring: { days?: string } }>('/api/yasir/public', async (req, reply) => {
    const days = Math.min(Math.max(Number(req.query.days) || 30, 1), WINDOW_DAYS);
    const { day, list } = await allOutcomes();
    const since = daysAgo(day, days);
    const visible = list.filter((o) => o.status !== 'none');
    reply.header('Cache-Control', 'public, max-age=60');
    return {
      day,
      today: visible.filter((o) => o.day === day && o.status !== 'absent'),
      history: visible.filter((o) => o.day < day && o.day >= since),
      stats: stats(list.filter((o) => o.day < day)),
      window_days: WINDOW_DAYS,
    };
  });

  // ---------------------------------------------------------------------------
  // Moderation
  // ---------------------------------------------------------------------------

  app.get<{ Querystring: { days?: string } }>('/api/admin/yasir', { preHandler: guards.requireAdmin }, async (req) => {
    const days = Math.min(Math.max(Number(req.query.days) || 7, 1), 60);
    const day = serverDay();
    const reports = await loadReports(daysAgo(day, WINDOW_DAYS));
    const rep = reputations(reports, day);
    const since = daysAgo(day, days);
    const recent = reports.filter((r) => r.day >= since);
    const users = await db.query<{ id: number; email: string; display_name: string }>(
      'SELECT id, email, display_name FROM users WHERE id = ANY($1::int[])',
      [[...new Set(recent.map((r) => r.userId))]],
    );
    const byId = new Map(users.rows.map((u) => [u.id, u]));
    return recent
      .sort((a, b) => b.reportedAt.getTime() - a.reportedAt.getTime())
      .map((r) => ({
        day: r.day,
        world: r.world,
        user_id: r.userId,
        email: byId.get(r.userId)?.email ?? null,
        display_name: byId.get(r.userId)?.display_name ?? null,
        present: r.present,
        city: r.city,
        reported_at: r.reportedAt,
        verified_on_world: r.verifiedOnWorld,
        blocked: r.blocked,
        reputation: Math.round((rep.get(r.userId) ?? 1) * 100) / 100,
        weight: Math.round(baseWeight(r) * (rep.get(r.userId) ?? 1) * 100) / 100,
      }));
  });

  async function audit(adminId: number, adminEmail: string, target: { id: number; email: string } | null, action: string, details: object) {
    await db.query(
      `INSERT INTO admin_audit (admin_id, admin_email, target_user_id, target_email, action, details)
       VALUES ($1, $2, $3, $4, $5, $6)`,
      [adminId, adminEmail, target?.id ?? null, target?.email ?? null, action, JSON.stringify(details)],
    );
  }

  app.delete<{ Params: { day: string; world: string; userId: string } }>(
    '/api/admin/yasir/:day/:world/:userId',
    { preHandler: guards.requireAdmin },
    async (req, reply) => {
      const { day, world } = req.params;
      const userId = Number(req.params.userId);
      if (!DAY_RE.test(day) || !WORLD_RE.test(world) || !Number.isInteger(userId)) {
        return reply.code(400).send({ error: 'invalid' });
      }
      const del = await db.query<{ email: string }>(
        `DELETE FROM yasir_reports r USING users u
          WHERE r.day = $1::date AND r.world = $2 AND r.user_id = $3 AND u.id = r.user_id RETURNING u.email`,
        [day, world, userId],
      );
      if (!del.rowCount) return reply.code(404).send({ error: 'not_found' });
      await audit(req.user!.id, req.user!.email, { id: userId, email: del.rows[0].email }, 'yasir_delete_report', { day, world });
      cache = null;
      return { ok: true };
    },
  );

  app.post<{ Params: { id: string }; Body: { blocked?: unknown } }>(
    '/api/admin/users/:id/yasir-block',
    { preHandler: guards.requireAdmin },
    async (req, reply) => {
      const id = Number(req.params.id);
      if (!Number.isInteger(id) || typeof req.body?.blocked !== 'boolean') return reply.code(400).send({ error: 'invalid' });
      const upd = await db.query<{ email: string }>('UPDATE users SET yasir_blocked = $2 WHERE id = $1 RETURNING email', [
        id,
        req.body.blocked,
      ]);
      if (!upd.rowCount) return reply.code(404).send({ error: 'not_found' });
      await audit(req.user!.id, req.user!.email, { id, email: upd.rows[0].email }, req.body.blocked ? 'yasir_block' : 'yasir_unblock', {});
      cache = null;
      return { ok: true };
    },
  );

  return {
    report,
    async today(world) {
      const { day, list } = await allOutcomes();
      const o = list.find((x) => x.day === day && x.world === world);
      return { status: o?.status ?? 'none', city: o?.city ?? null, reports: o?.reports ?? 0 };
    },
  };
}
