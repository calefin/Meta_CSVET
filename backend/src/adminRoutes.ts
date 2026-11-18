/**
 * Admin dashboard (D-62): overview, payments, support tickets and the error log.
 *
 *   POST   /api/errors                    browser error report (public, rate limited, no IP stored)
 *   GET    /api/admin/overview            numbers for the dashboard cards + badges
 *   GET    /api/admin/payments            subscribers (Stripe) and manual Premium grants
 *   GET    /api/admin/tickets?status=     support messages with counts per status
 *   PATCH  /api/admin/tickets/:id         { status?, admin_notes? }
 *   GET    /api/admin/errors?status=open|resolved|all
 *   PATCH  /api/admin/errors/:id          { resolved: boolean }
 *   DELETE /api/admin/errors/:id
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';
import { clientErrorInput, recordError } from './errorLog.js';
import { publicUrlWarning } from './siteOrigin.js';
import type { Alerts } from './alerts.js';
import type { Mailer } from './mailer.js';
import type { AdsConfig } from './ads.js';
import { testEmail } from './emails.js';
import { mpConfigProblem } from './mercadopago.js';
import { readFile } from 'node:fs/promises';

export const TICKET_STATUSES = ['pending', 'open', 'answered', 'closed'] as const;

export function registerAdminRoutes(app: FastifyInstance, { db, guards, mailer, ads, alerts }: { db: pg.Pool; guards: AuthGuards; mailer?: Mailer; ads?: AdsConfig | null; alerts?: Alerts | null }): void {
  const { requireAdmin, optionalUser, limited } = guards;

  app.post<{ Body: Record<string, unknown> }>('/api/errors', { preHandler: optionalUser, bodyLimit: 16 * 1024 }, async (req, reply) => {
    // Per IP in Redis only (rate limit), never in the table
    if (await limited(`ratelimit:errors:${req.ip}`, 20, 600)) return reply.code(204).send();
    const e = clientErrorInput(req.body, req.user?.id ?? null, req.headers['user-agent'] ?? null);
    if (e) {
      const r = await recordError(db, e).catch((err) => {
        req.log.warn({ err: (err as Error).message }, 'error log insert failed');
        return 'repeat' as const;
      });
      // D-70: e-mail the admins about new browser errors (or resolved ones that came back)
      if (r === 'new' || r === 'reopened')
        void alerts?.notify('error', `Erro no navegador: ${e.message.slice(0, 90)}`, [e.message, `Página: ${e.path ?? '—'} · versão ${e.version ?? '—'}`, r === 'reopened' ? 'Tinha sido marcado como resolvido e voltou.' : 'Primeira vez que acontece.']);
    }
    return reply.code(204).send();
  });

  app.get('/api/admin/overview', { preHandler: requireAdmin }, async (req) => {
    const [billing, tickets, errors, content] = await Promise.all([
      db.query(
        `SELECT COUNT(*) FILTER (WHERE stripe_status IN ('active', 'trialing'))::int AS paying,
                COUNT(*) FILTER (WHERE stripe_status IN ('active', 'trialing') AND stripe_cancel_at_period_end)::int AS cancelling,
                COUNT(*) FILTER (WHERE stripe_status IN ('past_due', 'unpaid', 'incomplete'))::int AS problems,
                COUNT(*) FILTER (WHERE premium_until > now() AND COALESCE(stripe_status, '') NOT IN ('active', 'trialing'))::int AS manual_premium,
                (SELECT COUNT(*)::int FROM billing_log WHERE created_at > now() - interval '7 days'
                   AND kind IN ('duplicate_refund_failed', 'unknown_customer', 'customer_mismatch', 'sync_error', 'payment_failed', 'pix_refunded', 'mp_claim', 'mp_chargeback')) AS alerts_7d
           FROM users`,
      ),
      db.query(`SELECT status, COUNT(*)::int AS n FROM support_messages GROUP BY status`),
      db.query(
        `SELECT COUNT(*) FILTER (WHERE resolved_at IS NULL)::int AS open,
                COUNT(*) FILTER (WHERE resolved_at IS NULL AND last_seen > now() - interval '24 hours')::int AS last_24h,
                COALESCE(SUM(count) FILTER (WHERE resolved_at IS NULL), 0)::int AS occurrences
           FROM app_errors`,
      ),
      db.query(
        `SELECT (SELECT COUNT(*)::int FROM hunt_sessions) AS hunts,
                (SELECT COUNT(*)::int FROM hunt_sessions WHERE created_at > now() - interval '7 days') AS hunts_7d,
                (SELECT COUNT(*)::int FROM hunt_payouts) AS payouts,
                (SELECT COUNT(*)::int FROM characters) AS characters`,
      ),
    ]);
    const t = Object.fromEntries(tickets.rows.map((r) => [r.status, r.n])) as Record<string, number>;
    // D-68: last database backup (written by the backup container)
    let backup: { ok: boolean; file: string; size: number; at: string; message: string } | null = null;
    try {
      backup = JSON.parse(await readFile('/backups/latest.json', 'utf8'));
    } catch {
      backup = null;
    }
    const backupAgeH = backup ? (Date.now() - Date.parse(backup.at)) / 3_600_000 : Infinity;
    // Settings that send visitors to the wrong place (e.g. a local PUBLIC_URL copied to the server)
    const warnings = [
      !backup ? 'Backup: nenhum backup do banco encontrado ainda' : !backup.ok ? `Backup: o último backup falhou (${backup.message})` : backupAgeH > 36 ? `Backup: o último backup tem ${Math.round(backupAgeH)} h` : null,
      publicUrlWarning(process.env.PUBLIC_URL, req.headers.host),
      mpConfigProblem(process.env),
      mailer?.info.mode === 'smtp' ? null : 'E-mail desligado (SMTP_* não configurado): "esqueci minha senha" e avisos de ticket não saem',
    ].filter((w): w is string => !!w);
    return {
      warnings,
      mail: mailer ? { mode: mailer.info.mode, from: mailer.info.from, support: mailer.info.support } : null,
      ads: { client: ads?.client ?? null, slot: ads?.slot ?? null },
      backup,
      billing: billing.rows[0],
      tickets: { pending: t.pending ?? 0, open: t.open ?? 0, answered: t.answered ?? 0, closed: t.closed ?? 0 },
      errors: errors.rows[0],
      content: content.rows[0],
    };
  });

  /** D-65: send a test e-mail to the admin's own address (checks SMTP and whether it lands in the inbox) */
  app.post('/api/admin/mail-test', { preHandler: requireAdmin }, async (req, reply) => {
    if (!mailer?.enabled) return reply.code(503).send({ error: 'mail_disabled' });
    if (await limited(`ratelimit:mailtest:${req.user!.id}`, 5, 600)) return reply.code(429).send({ error: 'rate_limited' });
    try {
      await mailer.send(req.user!.email, testEmail({ support: mailer.support, sentAt: new Date() }));
      return { ok: true, to: req.user!.email };
    } catch (err) {
      return reply.code(502).send({ error: 'mail_failed', detail: (err as Error).message.slice(0, 200) });
    }
  });

  // D-78: e-mail diagnosis step by step
  app.post('/api/admin/mail-check', { preHandler: requireAdmin }, async (req, reply) => {
    if (!mailer) return reply.code(503).send({ error: 'mail_disabled' });
    if (await limited(`ratelimit:mailcheck:${req.user!.id}`, 10, 600)) return reply.code(429).send({ error: 'rate_limited' });
    return { steps: await mailer.check() };
  });

  app.get('/api/admin/payments', { preHandler: requireAdmin }, async () => {
    const { rows } = await db.query(
      `SELECT id, email, display_name, stripe_status, stripe_cancel_at_period_end AS cancel_at_period_end,
              stripe_subscription_id, stripe_customer_id, premium_until, premium_note, tier, created_at,
              COALESCE(premium_until > now(), false) AS premium
         FROM users
        WHERE stripe_customer_id IS NOT NULL OR premium_until > now()
        ORDER BY (stripe_status IN ('active', 'trialing')) DESC NULLS LAST, premium_until DESC NULLS LAST
        LIMIT 500`,
    );
    return { users: rows };
  });

  app.get<{ Querystring: { status?: string } }>('/api/admin/tickets', { preHandler: requireAdmin }, async (req) => {
    const params: unknown[] = [];
    let where = '';
    if (req.query.status === 'active') where = `WHERE s.status IN ('pending', 'open')`;
    else if ((TICKET_STATUSES as readonly string[]).includes(req.query.status ?? '')) {
      params.push(req.query.status);
      where = 'WHERE s.status = $1';
    }
    const { rows } = await db.query(
      `SELECT s.id, s.user_id, s.name, s.email, s.category, s.subject, s.message, s.status, s.admin_notes, s.client_info,
              s.created_at, s.updated_at, u.display_name AS account,
              (SELECT COUNT(*) FROM support_replies r WHERE r.ticket_id = s.id)::int AS replies,
              (s.telegram_chat_id IS NOT NULL OR EXISTS (SELECT 1 FROM notify_targets t
                 WHERE t.user_id = s.user_id AND t.kind = 'telegram' AND t.chat_type = 'private')) AS telegram
         FROM support_messages s LEFT JOIN users u ON u.id = s.user_id ${where}
        ORDER BY (s.status = 'pending') DESC, s.created_at DESC LIMIT 200`,
      params,
    );
    return { tickets: rows };
  });

  app.patch<{ Params: { id: string }; Body: { status?: unknown; admin_notes?: unknown } }>(
    '/api/admin/tickets/:id',
    { preHandler: requireAdmin },
    async (req, reply) => {
      const id = Number(req.params.id);
      if (!Number.isInteger(id) || id <= 0) return reply.code(404).send({ error: 'not_found' });
      const status = req.body?.status;
      if (status !== undefined && !(TICKET_STATUSES as readonly unknown[]).includes(status)) return reply.code(400).send({ error: 'invalid_status' });
      const notes = req.body?.admin_notes;
      if (notes !== undefined && notes !== null && typeof notes !== 'string') return reply.code(400).send({ error: 'invalid_notes' });
      const r = await db.query(
        `UPDATE support_messages SET status = COALESCE($2, status),
                admin_notes = CASE WHEN $3::boolean THEN $4 ELSE admin_notes END, updated_at = now()
          WHERE id = $1 RETURNING id, status, admin_notes, updated_at`,
        [id, status ?? null, notes !== undefined, typeof notes === 'string' ? notes.slice(0, 5000) || null : null],
      );
      if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
      return { ticket: r.rows[0] };
    },
  );

  app.get<{ Querystring: { status?: string } }>('/api/admin/errors', { preHandler: requireAdmin }, async (req) => {
    // Housekeeping: resolved errors older than 90 days go away
    await db.query(`DELETE FROM app_errors WHERE resolved_at < now() - interval '90 days'`);
    const where = req.query.status === 'resolved' ? 'WHERE e.resolved_at IS NOT NULL' : req.query.status === 'all' ? '' : 'WHERE e.resolved_at IS NULL';
    const { rows } = await db.query(
      `SELECT e.id, e.kind, e.message, e.stack, e.path, e.user_agent, e.version, e.count, e.first_seen, e.last_seen, e.resolved_at,
              u.email AS last_user
         FROM app_errors e LEFT JOIN users u ON u.id = e.last_user_id ${where}
        ORDER BY e.last_seen DESC LIMIT 200`,
    );
    return { errors: rows };
  });

  app.patch<{ Params: { id: string }; Body: { resolved?: unknown } }>('/api/admin/errors/:id', { preHandler: requireAdmin }, async (req, reply) => {
    const id = Number(req.params.id);
    if (!Number.isInteger(id) || id <= 0 || typeof req.body?.resolved !== 'boolean') return reply.code(400).send({ error: 'bad_request' });
    const r = await db.query(`UPDATE app_errors SET resolved_at = ${req.body.resolved ? 'now()' : 'NULL'} WHERE id = $1`, [id]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    return { ok: true };
  });

  app.delete<{ Params: { id: string } }>('/api/admin/errors/:id', { preHandler: requireAdmin }, async (req, reply) => {
    const id = Number(req.params.id);
    if (!Number.isInteger(id) || id <= 0) return reply.code(404).send({ error: 'not_found' });
    const r = await db.query('DELETE FROM app_errors WHERE id = $1', [id]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    return { ok: true };
  });
}
