/**
 * Pix via Mercado Pago (D-69). Pix buys Pro days (30 or 365, no automatic renewal); card subscriptions stay on
 * Stripe. Rules that keep the two from clashing:
 *  - no Pix while the account has a live Stripe subscription (no double charge);
 *  - days bought with Pix extend premium_until; a later Stripe subscription only starts charging when those days
 *    end (trial_end in billingRoutes);
 *  - Pro is released only after reading the payment from Mercado Pago itself (webhook signature checked, then
 *    GET /v1/orders/:id — or /v1/payments/:id with MP_API_MODE=payments), once per payment (credited flag), and only for payments created by BagParser
 *    (external_reference "bagparser:<id>"; the MP account may be shared with other businesses);
 *  - every Pix payment / refund goes to billing_payments (provider "mercadopago") and the billing log.
 *
 *   GET  /api/billing/pix              { enabled, plans }
 *   POST /api/billing/pix              { plan } → QR code (reuses an open one of the same plan)
 *   GET  /api/billing/pix/:id          status (asks MP when still pending; works even if the webhook is late)
 *   POST /api/billing/pix/webhook      Mercado Pago notification (signed). D-73: every notification is recorded in
 *                                      mp_events; orders/payments release Pro; complaints and chargebacks are read
 *                                      from MP, linked to the account and sent to the admin; other topics are kept
 *   GET  /api/admin/mp-events?kind=    admin list (claim | chargeback | order | payment | other | all)
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';
import { ACTIVE_STATUSES } from './stripe.js';
import {
  chargebackSummary,
  claimSummary,
  classifyMpEvent,
  createPixCharge,
  getCharge,
  isOrderId,
  mpConfigFromEnv,
  mpMode,
  mpRequest,
  PIX_MINUTES,
  PIX_PLANS,
  pixIdFromReference,
  verifyMpSignature,
  type MpChargeback,
  type MpClaim,
  type MpPayment,
  type PixPlan,
} from './mercadopago.js';
import { siteOrigin } from './siteOrigin.js';
import type { Alerts } from './alerts.js';
import { alertBilling } from './billingRoutes.js';

interface PixRow {
  id: number;
  user_id: number | null;
  mp_id: string | null;
  plan: PixPlan;
  days: number;
  amount: number;
  status: string;
  qr_code: string | null;
  qr_base64: string | null;
  ticket_url: string | null;
  expires_at: Date;
  credited: boolean;
  created_at: Date;
  approved_at: Date | null;
}

const publicRow = (r: PixRow, premiumUntil?: Date | null) => ({
  id: r.id,
  plan: r.plan,
  days: r.days,
  amount: r.amount,
  status: r.status,
  qr_code: r.qr_code,
  qr_base64: r.qr_base64,
  ticket_url: r.ticket_url,
  expires_at: r.expires_at,
  approved_at: r.approved_at,
  premium_until: premiumUntil ?? null,
});

export interface DonationPaid {
  id: number;
  userId: number | null;
  chatId: string | null;
  amount: number;
  currency: string;
}

export interface PixDonations {
  enabled: boolean;
  /** D-76: Pix of any amount (no Pro days) — the Telegram bot shows the copy-and-paste code */
  createDonation(o: { userId: number; email: string; cents: number; chatId: number | null }): Promise<{ id: number; qr_code: string; ticket_url: string | null; expires_at: Date }>;
}

export function registerPixRoutes(
  app: FastifyInstance,
  {
    db,
    guards,
    publicUrl,
    alerts,
    hooks,
  }: { db: pg.Pool; guards: AuthGuards; publicUrl?: string; alerts?: Alerts | null; hooks?: { onDonationPaid?: (d: DonationPaid) => void } },
): PixDonations {
  const { requireUser, limited } = guards;
  const cfg = mpConfigFromEnv(process.env);
  const mode = mpMode(process.env);
  app.log.info({ pix: !!cfg, mode }, 'mercadopago pix');

  async function log(userId: number | null, kind: string, details: object) {
    app.log.warn({ userId, kind, details }, 'billing');
    await db.query('INSERT INTO billing_log (user_id, kind, details) VALUES ($1, $2, $3)', [userId, kind, JSON.stringify(details)]).catch(() => null);
    await alertBilling(db, alerts, userId, kind, details);
  }

  /** Reads the payment from MP and applies it (idempotent). Returns the stored row or null when not ours. */
  async function syncPayment(mpId: string): Promise<PixRow | null> {
    if (!cfg) return null;
    const p = await getCharge(cfg, mpId);
    const pixId = pixIdFromReference(p.reference);
    if (!pixId) return null; // another business on the same Mercado Pago account
    const row = (await db.query<PixRow>('SELECT * FROM pix_payments WHERE id = $1', [pixId])).rows[0];
    if (!row) {
      await log(null, 'unknown_customer', { provider: 'mercadopago', payment: p.id, reference: p.reference });
      return null;
    }
    // The amount must be the one we asked for (a tampered or partial payment never releases Pro)
    const paidCents = p.cents;
    await db.query('UPDATE pix_payments SET status = $2, mp_id = COALESCE(mp_id, $3) WHERE id = $1', [row.id, p.status, p.id]);

    if (row.plan === ('donation' as PixPlan)) return syncDonation(row, p);
    if (p.status === 'approved' && !row.credited) {
      if (paidCents !== row.amount || (p.currency && p.currency !== 'BRL')) {
        await log(row.user_id, 'customer_mismatch', { provider: 'mercadopago', payment: p.id, expected: row.amount, paid: paidCents, currency: p.currency });
        return { ...row, status: p.status };
      }
      const client = await db.connect();
      try {
        await client.query('BEGIN');
        const claim = await client.query('UPDATE pix_payments SET credited = TRUE, approved_at = COALESCE($2::timestamptz, now()) WHERE id = $1 AND NOT credited RETURNING id', [
          row.id,
          p.approvedAt,
        ]);
        if (claim.rowCount && row.user_id) {
          const u = await client.query<{ premium_until: Date; email: string }>(
            `UPDATE users SET premium_until = GREATEST(COALESCE(premium_until, now()), now()) + make_interval(days => $2)
              WHERE id = $1 RETURNING premium_until, email`,
            [row.user_id, row.days],
          );
          await client.query(
            `INSERT INTO billing_payments (user_id, email, stripe_id, kind, amount, currency, subscription_id, interval, period_start, period_end, paid_at, description, provider)
             VALUES ($1, $2, $3, 'invoice', $4, 'brl', NULL, $5, now(), $6, COALESCE($7::timestamptz, now()), $8, 'mercadopago')
             ON CONFLICT (stripe_id) DO NOTHING`,
            [row.user_id, u.rows[0]?.email ?? null, `mp_${p.id}`, row.amount, row.plan === 'yearly' ? 'year' : 'month', u.rows[0]?.premium_until ?? null, p.approvedAt, `Pix ${row.days} dias`],
          );
        }
        await client.query('COMMIT');
        if (claim.rowCount) await log(row.user_id, 'pix_paid', { payment: p.id, days: row.days, amount: row.amount });
      } catch (err) {
        await client.query('ROLLBACK').catch(() => undefined);
        throw err;
      } finally {
        client.release();
      }
    } else if ((p.status === 'refunded' || p.status === 'charged_back') && row.credited) {
      const r = await db.query(
        `INSERT INTO billing_payments (user_id, email, stripe_id, kind, amount, currency, paid_at, description, provider)
         VALUES ($1, (SELECT email FROM users WHERE id = $1), $2, 'refund', $3, 'brl', now(), $4, 'mercadopago') ON CONFLICT (stripe_id) DO NOTHING`,
        [row.user_id, `mp_refund_${p.id}`, -row.amount, p.status],
      );
      if (r.rowCount) await log(row.user_id, 'pix_refunded', { payment: p.id, status: p.status, days: row.days });
    }
    return (await db.query<PixRow>('SELECT * FROM pix_payments WHERE id = $1', [row.id])).rows[0];
  }

  /** D-76: a paid donation is recorded once (no Pro days); refunds are recorded too */
  async function syncDonation(row: PixRow, p: Awaited<ReturnType<typeof getCharge>>): Promise<PixRow> {
    if (p.status === 'approved' && !row.credited) {
      if (p.cents !== row.amount || (p.currency && p.currency !== 'BRL')) {
        await log(row.user_id, 'customer_mismatch', { provider: 'mercadopago', payment: p.id, expected: row.amount, paid: p.cents, donation: true });
        return { ...row, status: p.status };
      }
      const claim = await db.query('UPDATE pix_payments SET credited = TRUE, approved_at = COALESCE($2::timestamptz, now()) WHERE id = $1 AND NOT credited RETURNING id', [row.id, p.approvedAt]);
      if (claim.rowCount) {
        const d = (
          await db.query<{ id: number; user_id: number | null; chat_id: string | null; amount: number; currency: string }>(
            `UPDATE donations SET status = 'paid', paid_at = now() WHERE pix_id = $1 RETURNING id, user_id, chat_id::text AS chat_id, amount, currency`,
            [row.id],
          )
        ).rows[0];
        await db.query(
          `INSERT INTO billing_payments (user_id, email, stripe_id, kind, amount, currency, paid_at, description, provider)
           VALUES ($1, (SELECT email FROM users WHERE id = $1), $2, 'invoice', $3, 'brl', COALESCE($4::timestamptz, now()), 'Doação Pix', 'mercadopago')
           ON CONFLICT (stripe_id) DO NOTHING`,
          [row.user_id, `mp_${p.id}`, row.amount, p.approvedAt],
        );
        await log(row.user_id, 'donation_paid', { payment: p.id, amount: row.amount });
        if (d) hooks?.onDonationPaid?.({ id: d.id, userId: d.user_id, chatId: d.chat_id, amount: d.amount, currency: d.currency });
      }
    } else if ((p.status === 'refunded' || p.status === 'charged_back') && row.credited) {
      await db.query(`UPDATE donations SET status = 'refunded', public_name = NULL WHERE pix_id = $1`, [row.id]);
      await db.query(
        `INSERT INTO billing_payments (user_id, email, stripe_id, kind, amount, currency, paid_at, description, provider)
         VALUES ($1, (SELECT email FROM users WHERE id = $1), $2, 'refund', $3, 'brl', now(), 'Doação devolvida', 'mercadopago') ON CONFLICT (stripe_id) DO NOTHING`,
        [row.user_id, `mp_refund_${p.id}`, -row.amount],
      );
    }
    return (await db.query<PixRow>('SELECT * FROM pix_payments WHERE id = $1', [row.id])).rows[0];
  }

  app.get('/api/billing/pix', async () => ({
    enabled: !!cfg,
    plans: Object.fromEntries(Object.entries(PIX_PLANS).map(([k, v]) => [k, { days: v.days, amount: v.cents }])),
  }));

  app.post<{ Body: { plan?: unknown } }>('/api/billing/pix', { preHandler: requireUser }, async (req, reply) => {
    if (!cfg) return reply.code(503).send({ error: 'pix_unavailable' });
    const user = req.user!;
    const plan: PixPlan = req.body?.plan === 'yearly' ? 'yearly' : 'monthly';
    if (await limited(`ratelimit:pix:${user.id}`, 10, 3600)) return reply.code(429).send({ error: 'rate_limited' });
    const st = (await db.query<{ stripe_status: string | null }>('SELECT stripe_status FROM users WHERE id = $1', [user.id])).rows[0];
    if (ACTIVE_STATUSES.has(st?.stripe_status ?? '')) return reply.code(409).send({ error: 'stripe_active' });

    // An open QR code of the same plan is reused (two clicks never create two charges)
    const open = (
      await db.query<PixRow>(
        `SELECT * FROM pix_payments WHERE user_id = $1 AND plan = $2 AND status = 'pending' AND qr_code IS NOT NULL AND expires_at > now() + interval '3 minutes'
          ORDER BY id DESC LIMIT 1`,
        [user.id, plan],
      )
    ).rows[0];
    if (open) return publicRow(open);

    const p = PIX_PLANS[plan];
    const row = (
      await db.query<PixRow>(
        `INSERT INTO pix_payments (user_id, plan, days, amount, expires_at) VALUES ($1, $2, $3, $4, now() + make_interval(mins => $5)) RETURNING *`,
        [user.id, plan, p.days, p.cents, PIX_MINUTES],
      )
    ).rows[0];
    try {
      const c = await createPixCharge(cfg, mode, { plan, pixId: row.id, userId: user.id, email: user.email, notificationUrl: `${siteOrigin(publicUrl, req)}/api/billing/pix/webhook` });
      const saved = (
        await db.query<PixRow>(
          `UPDATE pix_payments SET mp_id = $2, status = $3, qr_code = $4, qr_base64 = $5, ticket_url = $6 WHERE id = $1 RETURNING *`,
          [row.id, c.id, c.status, c.qr_code, c.qr_base64, c.ticket_url],
        )
      ).rows[0];
      if (!saved.qr_code) throw new Error('no qr code in the answer');
      return publicRow(saved);
    } catch (err) {
      await db.query(`UPDATE pix_payments SET status = 'error' WHERE id = $1`, [row.id]);
      app.log.error({ err: (err as Error).message }, 'pix create failed');
      return reply.code(502).send({ error: 'pix_error' });
    }
  });

  app.get<{ Params: { id: string } }>('/api/billing/pix/:id', { preHandler: requireUser }, async (req, reply) => {
    const id = Number(req.params.id);
    if (!Number.isInteger(id) || id <= 0) return reply.code(404).send({ error: 'not_found' });
    let row = (await db.query<PixRow>('SELECT * FROM pix_payments WHERE id = $1 AND user_id = $2', [id, req.user!.id])).rows[0];
    if (!row) return reply.code(404).send({ error: 'not_found' });
    // Still pending: ask Mercado Pago (at most every 5 s per payment), so a late webhook does not keep the buyer waiting
    if (cfg && row.mp_id && row.status === 'pending' && !(await limited(`ratelimit:pixpoll:${row.id}`, 1, 5))) {
      row = (await syncPayment(row.mp_id).catch(() => null)) ?? row;
    }
    const u = (await db.query<{ premium_until: Date | null }>('SELECT premium_until FROM users WHERE id = $1', [req.user!.id])).rows[0];
    return publicRow(row, u?.premium_until);
  });

  /** BagParser Pix + account behind an MP payment id (complaints and chargebacks point to payments) */
  async function linkPayment(paymentId: string | null): Promise<{ pixId: number | null; userId: number | null }> {
    if (!cfg || !paymentId || !/^\d{1,20}$/.test(paymentId)) return { pixId: null, userId: null };
    const local = (await db.query<{ id: number; user_id: number | null }>('SELECT id, user_id FROM pix_payments WHERE mp_id = $1', [paymentId])).rows[0];
    if (local) return { pixId: local.id, userId: local.user_id };
    try {
      const p = await mpRequest<MpPayment>(cfg, 'GET', `/v1/payments/${paymentId}`);
      const pixId = pixIdFromReference(p.external_reference);
      if (!pixId) return { pixId: null, userId: null };
      const row = (await db.query<{ user_id: number | null }>('SELECT user_id FROM pix_payments WHERE id = $1', [pixId])).rows[0];
      return { pixId, userId: row?.user_id ?? null };
    } catch {
      return { pixId: null, userId: null };
    }
  }

  /** Complaint / chargeback: read it from MP, link it, tell the admin (once per event) */
  async function handleDispute(eventId: number, kind: 'claim' | 'chargeback', dataId: string) {
    if (!cfg) return;
    try {
      const raw =
        kind === 'claim'
          ? await mpRequest<MpClaim>(cfg, 'GET', `/post-purchase/v1/claims/${encodeURIComponent(dataId)}`)
          : await mpRequest<MpChargeback>(cfg, 'GET', `/v1/chargebacks/${encodeURIComponent(dataId)}`);
      const s = kind === 'claim' ? { ...claimSummary(raw as MpClaim), cents: null, deadline: null } : chargebackSummary(raw as MpChargeback);
      const link = await linkPayment(s.paymentId);
      await db.query(
        `UPDATE mp_events SET status = $2, summary = $3, amount = $4, deadline = $5, user_id = $6, pix_id = $7, details = $8, processed = TRUE, error = NULL WHERE id = $1`,
        [eventId, s.status.slice(0, 60), s.summary.slice(0, 300), s.cents, s.deadline, link.userId, link.pixId, JSON.stringify(raw).slice(0, 20000)],
      );
      await log(link.userId, kind === 'claim' ? 'mp_claim' : 'mp_chargeback', { id: dataId, status: s.status, summary: s.summary, payment: s.paymentId, bagparser: !!link.pixId });
    } catch (err) {
      // Recorded anyway: the admin sees it in the list even when MP could not be read
      await db.query('UPDATE mp_events SET error = $2 WHERE id = $1', [eventId, (err as Error).message.slice(0, 300)]);
      await log(null, kind === 'claim' ? 'mp_claim' : 'mp_chargeback', { id: dataId, error: (err as Error).message.slice(0, 200) });
    }
  }

  app.get<{ Querystring: { kind?: string } }>('/api/admin/mp-events', { preHandler: guards.requireAdmin }, async (req) => {
    const kind = ['claim', 'chargeback', 'order', 'payment', 'other'].includes(req.query.kind ?? '') ? req.query.kind! : null;
    const { rows } = await db.query(
      `SELECT e.id, e.kind, e.type, e.action, e.data_id, e.status, e.amount, e.deadline, e.summary, e.details, e.processed, e.error, e.received_at, e.pix_id, u.email
         FROM mp_events e LEFT JOIN users u ON u.id = e.user_id
        ${kind ? 'WHERE e.kind = $1' : ''} ORDER BY e.received_at DESC LIMIT 200`,
      kind ? [kind] : [],
    );
    const counts = (await db.query<{ kind: string; n: number }>('SELECT kind, COUNT(*)::int AS n FROM mp_events GROUP BY kind')).rows;
    return { events: rows, counts: Object.fromEntries(counts.map((c) => [c.kind, c.n])) };
  });

  app.post<{ Querystring: Record<string, string | undefined>; Body: { type?: unknown; topic?: unknown; action?: unknown; data?: { id?: unknown }; id?: unknown } }>('/api/billing/pix/webhook', async (req, reply) => {
    if (!cfg) return reply.code(503).send({ error: 'pix_unavailable' });
    const dataId = String(req.query['data.id'] ?? req.body?.data?.id ?? req.query.id ?? '');
    const type = String(req.query.type ?? req.body?.type ?? req.query.topic ?? req.body?.topic ?? '');
    if (
      !verifyMpSignature({
        signature: req.headers['x-signature'] as string | undefined,
        requestId: req.headers['x-request-id'] as string | undefined,
        dataId,
        secret: cfg.webhookSecret,
      })
    ) {
      return reply.code(401).send({ error: 'bad_signature' });
    }
    const action = typeof req.body?.action === 'string' ? req.body.action.slice(0, 80) : null;
    const kind = classifyMpEvent(type, action ?? '');
    // D-73: keep every notification (once per MP request id; retries of the same request are not stored twice)
    const ins = await db.query<{ id: number }>(
      `INSERT INTO mp_events (request_id, kind, type, action, data_id, details, processed)
       VALUES ($1, $2, $3, $4, $5, $6, $7) ON CONFLICT (request_id) DO NOTHING RETURNING id`,
      [
        (req.headers['x-request-id'] as string | undefined)?.slice(0, 100) ?? null,
        kind,
        type.slice(0, 80) || 'unknown',
        action,
        dataId.slice(0, 80) || null,
        JSON.stringify(req.body ?? {}).slice(0, 20000),
        kind === 'other',
      ],
    );
    const eventId = ins.rows[0]?.id ?? null;

    if (kind === 'claim' || kind === 'chargeback') {
      if (eventId && dataId) await handleDispute(eventId, kind, dataId);
      return { received: true };
    }
    const known = (kind === 'payment' && /^\d{1,20}$/.test(dataId)) || (kind === 'order' && isOrderId(dataId));
    if (!known) return { received: true, stored: !!eventId };
    try {
      const row = await syncPayment(dataId);
      if (eventId) await db.query('UPDATE mp_events SET processed = TRUE, pix_id = $2, user_id = $3, status = $4 WHERE id = $1', [eventId, row?.id ?? null, row?.user_id ?? null, row?.status ?? null]);
    } catch (err) {
      if (eventId) await db.query('UPDATE mp_events SET error = $2 WHERE id = $1', [eventId, (err as Error).message.slice(0, 300)]);
      await log(null, 'sync_error', { provider: 'mercadopago', payment: dataId, error: (err as Error).message });
      return reply.code(500).send({ error: 'webhook_failed' }); // Mercado Pago retries
    }
    return { received: true };
  });

  return {
    enabled: !!cfg,
    async createDonation({ userId, email, cents, chatId }) {
      if (!cfg) throw new Error('pix_unavailable');
      const row = (
        await db.query<PixRow>(
          `INSERT INTO pix_payments (user_id, plan, days, amount, expires_at) VALUES ($1, 'donation', 0, $2, now() + make_interval(mins => $3)) RETURNING *`,
          [userId, cents, PIX_MINUTES],
        )
      ).rows[0];
      await db.query(`INSERT INTO donations (user_id, chat_id, provider, amount, currency, pix_id) VALUES ($1, $2, 'pix', $3, 'BRL', $4)`, [userId, chatId, cents, row.id]);
      try {
        const base = (publicUrl ?? '').replace(/\/+$/, '') || 'https://bagparser.com';
        const c = await createPixCharge(cfg, mode, {
          plan: 'donation',
          pixId: row.id,
          userId,
          email,
          cents,
          description: 'Apoio ao BagParser (doação)',
          notificationUrl: `${base}/api/billing/pix/webhook`,
        });
        await db.query(`UPDATE pix_payments SET mp_id = $2, status = $3, qr_code = $4, qr_base64 = $5, ticket_url = $6 WHERE id = $1`, [row.id, c.id, c.status, c.qr_code, c.qr_base64, c.ticket_url]);
        if (!c.qr_code) throw new Error('no qr code in the answer');
        return { id: row.id, qr_code: c.qr_code, ticket_url: c.ticket_url, expires_at: row.expires_at };
      } catch (err) {
        await db.query(`UPDATE pix_payments SET status = 'error' WHERE id = $1`, [row.id]);
        await db.query(`UPDATE donations SET status = 'error' WHERE pix_id = $1`, [row.id]);
        throw err;
      }
    },
  };
}
