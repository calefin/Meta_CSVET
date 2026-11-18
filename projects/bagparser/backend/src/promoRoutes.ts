/** Promo codes (D-50): admin CRUD, code check for the pricing page, trial redemption, tester grants. */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';
import { extendPremium, normalizeCode, promoBlocker, validatePromo } from './promo.js';

interface PromoRow {
  id: number;
  code: string;
  kind: 'discount' | 'trial';
  percent_off: number | null;
  premium_days: number | null;
  max_uses: number | null;
  uses: number;
  expires_at: Date | null;
  active: boolean;
}

export function registerPromoRoutes(app: FastifyInstance, { db, guards }: { db: pg.Pool; guards: AuthGuards }): void {
  const { requireUser, requireAdmin, limited } = guards;

  async function audit(adminId: number, adminEmail: string, action: string, details: object, target?: { id: number; email: string }) {
    await db
      .query(
        `INSERT INTO admin_audit (admin_id, admin_email, target_user_id, target_email, action, details) VALUES ($1, $2, $3, $4, $5, $6)`,
        [adminId, adminEmail, target?.id ?? null, target?.email ?? null, action, JSON.stringify(details)],
      )
      .catch(() => null);
  }

  // ---------------------------------------------------------------------------
  // Admin
  // ---------------------------------------------------------------------------

  app.get('/api/admin/promo', { preHandler: requireAdmin }, async () => {
    const { rows } = await db.query(
      `SELECT p.*, (SELECT COUNT(*)::int FROM promo_redemptions r WHERE r.code_id = p.id) AS redemptions
         FROM promo_codes p ORDER BY p.created_at DESC LIMIT 500`,
    );
    return { codes: rows };
  });

  app.post<{ Body: Record<string, unknown> }>('/api/admin/promo', { preHandler: requireAdmin }, async (req, reply) => {
    const p = validatePromo(req.body);
    if ('error' in p) return reply.code(400).send(p);
    const r = await db.query(
      `INSERT INTO promo_codes (code, kind, percent_off, premium_days, max_uses, expires_at, note, created_by)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8) ON CONFLICT (code) DO NOTHING RETURNING id`,
      [p.code, p.kind, p.percent_off, p.premium_days, p.max_uses, p.expires_at, p.note, req.user!.id],
    );
    if (!r.rowCount) return reply.code(409).send({ error: 'code_exists' });
    await audit(req.user!.id, req.user!.email, 'promo_create', p);
    return reply.code(201).send({ id: r.rows[0].id, code: p.code });
  });

  app.patch<{ Params: { id: string }; Body: { active?: unknown } }>('/api/admin/promo/:id', { preHandler: requireAdmin }, async (req, reply) => {
    if (typeof req.body?.active !== 'boolean') return reply.code(400).send({ error: 'nothing_to_change' });
    const r = await db.query('UPDATE promo_codes SET active = $2 WHERE id = $1 RETURNING code', [Number(req.params.id), req.body.active]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    await audit(req.user!.id, req.user!.email, req.body.active ? 'promo_enable' : 'promo_disable', { code: r.rows[0].code });
    return { ok: true };
  });

  app.delete<{ Params: { id: string } }>('/api/admin/promo/:id', { preHandler: requireAdmin }, async (req, reply) => {
    const r = await db.query('DELETE FROM promo_codes WHERE id = $1 RETURNING code', [Number(req.params.id)]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    await audit(req.user!.id, req.user!.email, 'promo_delete', { code: r.rows[0].code });
    return { ok: true };
  });

  /**
   * Tester grant: N days of free Premium + "tester" label, in one click from the user page.
   * The label shows up in the users list filter (testers) and survives the end of the period.
   */
  app.post<{ Params: { id: string }; Body: { days?: unknown; note?: unknown } }>(
    '/api/admin/users/:id/grant',
    { preHandler: requireAdmin },
    async (req, reply) => {
      const days = Number(req.body?.days);
      if (!Number.isInteger(days) || days < 1 || days > 366) return reply.code(400).send({ error: 'invalid_days' });
      const note = typeof req.body?.note === 'string' ? req.body.note.trim().slice(0, 60) || 'tester' : 'tester';
      const { rows } = await db.query<{ id: number; email: string; premium_until: Date | null }>(
        'SELECT id, email, premium_until FROM users WHERE id = $1',
        [Number(req.params.id)],
      );
      const u = rows[0];
      if (!u) return reply.code(404).send({ error: 'not_found' });
      const until = extendPremium(u.premium_until, days);
      await db.query('UPDATE users SET premium_until = $2, premium_note = $3 WHERE id = $1', [u.id, until, note]);
      await audit(req.user!.id, req.user!.email, 'grant_premium', { days, note, premium_until: until.toISOString() }, u);
      return { ok: true, premium_until: until.toISOString() };
    },
  );

  // ---------------------------------------------------------------------------
  // Users
  // ---------------------------------------------------------------------------

  async function findCode(code: string): Promise<PromoRow | null> {
    const { rows } = await db.query<PromoRow>(
      `SELECT id, code, kind, percent_off, premium_days, max_uses, uses, expires_at, active FROM promo_codes WHERE code = $1`,
      [code],
    );
    return rows[0] ?? null;
  }

  /** Pricing page: shows the discount before checkout (no account needed, rate-limited per IP) */
  app.post<{ Body: { code?: unknown } }>('/api/promo/check', async (req, reply) => {
    if (await limited(`ratelimit:promocheck:${req.ip}`, 20, 600)) return reply.code(429).send({ error: 'rate_limited' });
    const code = normalizeCode(req.body?.code);
    const row = code ? await findCode(code) : null;
    if (!row) return reply.code(404).send({ error: 'invalid_code' });
    const blocker = promoBlocker(row);
    if (blocker) return reply.code(409).send({ error: `code_${blocker}` });
    return { code: row.code, kind: row.kind, percent_off: row.percent_off, premium_days: row.premium_days };
  });

  /** Trial codes: adds the days to the signed-in account (once per account) */
  app.post<{ Body: { code?: unknown } }>('/api/promo/redeem', { preHandler: requireUser }, async (req, reply) => {
    const user = req.user!;
    if (await limited(`ratelimit:promoredeem:${user.id}`, 10, 600)) return reply.code(429).send({ error: 'rate_limited' });
    const code = normalizeCode(req.body?.code);
    if (!code) return reply.code(404).send({ error: 'invalid_code' });
    const client = await db.connect();
    try {
      await client.query('BEGIN');
      const { rows } = await client.query<PromoRow>(
        `SELECT id, code, kind, percent_off, premium_days, max_uses, uses, expires_at, active FROM promo_codes WHERE code = $1 FOR UPDATE`,
        [code],
      );
      const row = rows[0];
      if (!row) {
        await client.query('ROLLBACK');
        return reply.code(404).send({ error: 'invalid_code' });
      }
      const blocker = promoBlocker(row);
      if (blocker) {
        await client.query('ROLLBACK');
        return reply.code(409).send({ error: `code_${blocker}` });
      }
      if (row.kind !== 'trial') {
        await client.query('ROLLBACK');
        return { kind: row.kind, percent_off: row.percent_off, applied: false };
      }
      const ins = await client.query('INSERT INTO promo_redemptions (code_id, user_id) VALUES ($1, $2) ON CONFLICT DO NOTHING', [row.id, user.id]);
      if (!ins.rowCount) {
        await client.query('ROLLBACK');
        return reply.code(409).send({ error: 'code_already_used' });
      }
      const until = extendPremium(user.premium_until, row.premium_days!);
      await client.query('UPDATE users SET premium_until = $2 WHERE id = $1', [user.id, until]);
      await client.query('UPDATE promo_codes SET uses = uses + 1 WHERE id = $1', [row.id]);
      await client.query('COMMIT');
      return { kind: 'trial', applied: true, premium_days: row.premium_days, premium_until: until.toISOString() };
    } catch (err) {
      await client.query('ROLLBACK').catch(() => null);
      throw err;
    } finally {
      client.release();
    }
  });
}
