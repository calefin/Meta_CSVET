/**
 * Billing with Stripe (D-51, hardened in D-55): Checkout (subscription), Customer Portal and the webhook that
 * turns payments into Premium time. Without STRIPE_SECRET_KEY the routes answer 503 "billing_unavailable".
 *
 * Safety rules (D-55):
 *  - One Stripe customer per account, created BEFORE the first checkout and stored on the user (never one per
 *    checkout). Its metadata carries the user id; the webhook maps events by customer, not by e-mail.
 *  - Before opening a checkout we ask Stripe (not our DB) whether the customer already has a live
 *    subscription; the checkout request uses an idempotency key, so double clicks / two tabs get the same page.
 *  - The webhook re-reads ALL subscriptions of the customer from Stripe on every event (out-of-order or stale
 *    events cannot overwrite the state). If an account ends up with two live subscriptions, the oldest one is
 *    kept and the extras are cancelled and refunded automatically; the admin sees it in the billing log.
 *  - Unknown customers or mismatched accounts are logged and acknowledged (200), so Stripe does not retry
 *    forever, and nothing is granted to the wrong account.
 *  - Deleting an account cancels its subscription.
 *  - Premium is never taken away by the webhook: paid time only extends premium_until (tester grants and
 *    trial codes stay intact); a cancelled subscription simply stops extending it.
 *
 * D-63: subscription events (new, cancellation scheduled, reactivated, payment failed, ended) go to the billing
 * log; every paid invoice and every refund is kept in billing_payments for the financial report; a subscriber
 * who scheduled a cancellation can undo it (POST /api/billing/resume); after the end they subscribe again.
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';
import { normalizeCode, promoBlocker } from './promo.js';
import { siteOrigin } from './siteOrigin.js';
import { BILLING_ALERTS, type Alerts } from './alerts.js';
import {
  ACTIVE_STATUSES,
  checkoutIdempotency,
  PRO_PRICES,
  planOf,
  premiumUntilFor,
  splitDuplicates,
  subscriptionEvent,
  stripeConfigFromEnv,
  StripeError,
  stripeRequest,
  verifyStripeSignature,
  type Currency,
  type Period,
  type StripeConfig,
  type StripeSubscription,
} from './stripe.js';

interface BillingRow {
  id: number;
  email: string;
  display_name: string;
  stripe_customer_id: string | null;
  stripe_subscription_id: string | null;
  stripe_status: string | null;
  /** D-69: Pro already paid (Pix, tester, code) */
  premium_until?: Date | null;
}

type BillingLogKind =
  | 'duplicate_refunded'
  | 'duplicate_refund_failed'
  | 'unknown_customer'
  | 'customer_mismatch'
  | 'cancelled_on_delete'
  | 'sync_error'
  | 'subscribed'
  | 'cancel_scheduled'
  | 'resumed'
  | 'payment_failed'
  | 'subscription_ended'
  | 'pix_paid'
  | 'pix_refunded';

/** Paid invoice as Stripe sends it (only the fields we keep) */
interface StripeInvoice {
  id: string;
  customer?: string | null;
  subscription?: string | null;
  amount_paid?: number;
  currency?: string;
  status?: string;
  created?: number;
  status_transitions?: { paid_at?: number | null };
  lines?: { data?: { period?: { start?: number; end?: number }; price?: { recurring?: { interval?: string } | null } | null }[] };
}

export interface BillingTools {
  /** Cancels every live subscription of an account (account deletion). Never throws. */
  cancelForUser: (userId: number, reason: string) => Promise<void>;
}

/**
 * D-69: Pro days already paid (Pix, tester, trial code) are not charged twice: a card subscription opened while
 * they last starts charging when they end. Stripe Checkout needs trial_end ≥ 48 h ahead and ≤ 730 days.
 */
/** D-70: e-mail the admins about billing events that matter (with the account e-mail) */
export async function alertBilling(db: pg.Pool, alerts: Alerts | null | undefined, userId: number | null, kind: string, details: object) {
  const a = BILLING_ALERTS[kind];
  if (!a || !alerts) return;
  const email = userId ? (await db.query<{ email: string }>('SELECT email FROM users WHERE id = $1', [userId]).catch(() => ({ rows: [] as { email: string }[] }))).rows[0]?.email : null;
  const lines = [email ? `Conta: ${email}` : 'Conta: (não identificada)', ...Object.entries(details).slice(0, 6).map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : String(v)}`)];
  await alerts.notify(a.kind, `${a.title}${email ? ` · ${email}` : ''}`, lines);
}

export function trialUntilPaidEnds(premiumUntil: Date | null | undefined, now = Date.now()): { trial_end?: number } {
  if (!premiumUntil) return {};
  const end = new Date(premiumUntil).getTime();
  if (end < now + 48 * 3_600_000 || end > now + 720 * 86_400_000) return {};
  return { trial_end: Math.floor(end / 1000) };
}

export function registerBillingRoutes(app: FastifyInstance, { db, guards, alerts }: { db: pg.Pool; guards: AuthGuards; alerts?: Alerts | null }): BillingTools {
  const { requireUser, requireAdmin, limited } = guards;
  const cfg = stripeConfigFromEnv();
  if (!cfg) app.log.info('billing: STRIPE_SECRET_KEY not set, checkout disabled');

  const billingRow = async (userId: number): Promise<BillingRow | undefined> =>
    (
      await db.query<BillingRow>(
        'SELECT id, email, display_name, stripe_customer_id, stripe_subscription_id, stripe_status, premium_until FROM users WHERE id = $1',
        [userId],
      )
    ).rows[0];

  async function logBilling(userId: number | null, kind: BillingLogKind, details: object) {
    app.log.warn({ userId, kind, details }, 'billing');
    await db.query('INSERT INTO billing_log (user_id, kind, details) VALUES ($1, $2, $3)', [userId, kind, JSON.stringify(details)]).catch(() => null);
    await alertBilling(db, alerts, userId, kind, details);
  }

  app.get('/api/billing/status', async () => ({
    enabled: !!cfg,
    publishable_key: process.env.STRIPE_PUBLISHABLE_KEY ?? null,
    prices: PRO_PRICES,
  }));

  /** Subscription state for /account ("manage subscription", renewal / cancellation notice) */
  app.get('/api/billing/me', { preHandler: requireUser }, async (req) => {
    const { rows } = await db.query<{ stripe_customer_id: string | null; stripe_status: string | null; stripe_cancel_at_period_end: boolean }>(
      'SELECT stripe_customer_id, stripe_status, stripe_cancel_at_period_end FROM users WHERE id = $1',
      [req.user!.id],
    );
    const r = rows[0];
    return {
      enabled: !!cfg,
      has_customer: !!r?.stripe_customer_id,
      status: r?.stripe_status ?? null,
      cancel_at_period_end: !!r?.stripe_cancel_at_period_end,
    };
  });

  // ---------------------------------------------------------------------------
  // Stripe helpers
  // ---------------------------------------------------------------------------

  /** The account's Stripe customer, created once (idempotent) and stored before any checkout */
  async function ensureCustomer(c: StripeConfig, row: BillingRow): Promise<string> {
    if (row.stripe_customer_id) return row.stripe_customer_id;
    const customer = await stripeRequest<{ id: string }>(
      c,
      'POST',
      '/customers',
      { email: row.email, name: row.display_name, metadata: { user_id: String(row.id), site: 'bagparser' } },
      { idempotencyKey: `bp-customer-${row.id}` },
    );
    // Only set it when still empty (two parallel requests end with the same customer thanks to the key)
    await db.query('UPDATE users SET stripe_customer_id = $2 WHERE id = $1 AND stripe_customer_id IS NULL', [row.id, customer.id]);
    const again = await billingRow(row.id);
    return again?.stripe_customer_id ?? customer.id;
  }

  /**
   * BagParser subscriptions of a customer. Only those created by our checkout (metadata.site = "bagparser"):
   * the Stripe account is shared, so anything else is never cancelled, refunded or counted here.
   */
  async function subscriptionsOf(c: StripeConfig, customer: string): Promise<StripeSubscription[]> {
    const list = await stripeRequest<{ data: StripeSubscription[] }>(c, 'GET', `/subscriptions?customer=${encodeURIComponent(customer)}&status=all&limit=20`);
    return (list.data ?? []).filter((s) => s.metadata?.site === 'bagparser');
  }

  /** Cancels a duplicate subscription now and refunds what it charged */
  async function cancelAndRefund(c: StripeConfig, userId: number, sub: StripeSubscription, keepId: string) {
    try {
      await stripeRequest(c, 'DELETE', `/subscriptions/${sub.id}`).catch((err) => {
        if (!(err instanceof StripeError) || err.status !== 404) throw err;
      });
      const invoiceId = typeof sub.latest_invoice === 'string' ? sub.latest_invoice : sub.latest_invoice?.id;
      let refunded = false;
      if (invoiceId) {
        const inv = await stripeRequest<{ payment_intent?: string | null; charge?: string | null; amount_paid?: number; currency?: string }>(
          c,
          'GET',
          `/invoices/${invoiceId}`,
        );
        if ((inv.amount_paid ?? 0) > 0 && (inv.payment_intent || inv.charge)) {
          const refund = await stripeRequest<{ id?: string; amount?: number; currency?: string }>(
            c,
            'POST',
            '/refunds',
            inv.payment_intent ? { payment_intent: inv.payment_intent, reason: 'duplicate' } : { charge: inv.charge, reason: 'duplicate' },
            { idempotencyKey: `bp-refund-${sub.id}` },
          );
          refunded = true;
          await recordPayment(userId, {
            stripe_id: refund.id ?? `refund-${sub.id}`,
            kind: 'refund',
            amount: -(refund.amount ?? inv.amount_paid ?? 0),
            currency: refund.currency ?? inv.currency ?? 'brl',
            subscription_id: sub.id,
            paid_at: new Date(),
            description: 'duplicate',
          });
        }
        await logBilling(userId, 'duplicate_refunded', { cancelled: sub.id, kept: keepId, refunded, amount: inv.amount_paid, currency: inv.currency });
      } else {
        await logBilling(userId, 'duplicate_refunded', { cancelled: sub.id, kept: keepId, refunded: false });
      }
    } catch (err) {
      await logBilling(userId, 'duplicate_refund_failed', { subscription: sub.id, kept: keepId, error: (err as Error).message });
    }
  }

  /** Keeps a paid invoice or a refund for the financial report (once per Stripe id) */
  async function recordPayment(
    userId: number | null,
    p: { stripe_id: string; kind: 'invoice' | 'refund'; amount: number; currency: string; subscription_id?: string | null; interval?: string | null; period_start?: Date | null; period_end?: Date | null; paid_at: Date; description?: string | null },
  ) {
    await db
      .query(
        `INSERT INTO billing_payments (user_id, email, stripe_id, kind, amount, currency, subscription_id, interval, period_start, period_end, paid_at, description)
         VALUES ($1, (SELECT email FROM users WHERE id = $1), $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
         ON CONFLICT (stripe_id) DO NOTHING`,
        [userId, p.stripe_id, p.kind, p.amount, p.currency.toLowerCase(), p.subscription_id ?? null, p.interval ?? null, p.period_start ?? null, p.period_end ?? null, p.paid_at, p.description ?? null],
      )
      .catch((err) => app.log.error({ err: (err as Error).message }, 'billing payment record failed'));
  }

  async function recordInvoice(userId: number, inv: StripeInvoice) {
    if (!inv.id || !inv.amount_paid || inv.status === 'draft') return;
    const line = inv.lines?.data?.[0];
    const ts = (v?: number | null) => (v ? new Date(v * 1000) : null);
    await recordPayment(userId, {
      stripe_id: inv.id,
      kind: 'invoice',
      amount: inv.amount_paid,
      currency: inv.currency ?? 'brl',
      subscription_id: inv.subscription ?? null,
      interval: line?.price?.recurring?.interval ?? null,
      period_start: ts(line?.period?.start),
      period_end: ts(line?.period?.end),
      paid_at: ts(inv.status_transitions?.paid_at) ?? ts(inv.created) ?? new Date(),
    });
  }

  /**
   * Source of truth = Stripe. Reads every subscription of the account's customer, resolves duplicates and
   * stores the surviving one on the user. Premium only grows (GREATEST).
   */
  async function syncUser(c: StripeConfig, userId: number): Promise<StripeSubscription | null> {
    const row = await billingRow(userId);
    if (!row?.stripe_customer_id) return null;
    const subs = await subscriptionsOf(c, row.stripe_customer_id);
    const { keep, extra } = splitDuplicates(subs);
    for (const dup of extra) await cancelAndRefund(c, userId, dup, keep!.id);
    // Nothing live: show the most recent one (cancelled / unpaid) so /account tells the truth
    const shown = keep ?? [...subs].sort((a, b) => (b.created ?? 0) - (a.created ?? 0))[0] ?? null;
    if (!shown) return null;
    const until = premiumUntilFor(shown);
    const prev = (
      await db.query<{ stripe_status: string | null; stripe_cancel_at_period_end: boolean; stripe_subscription_id: string | null }>(
        'SELECT stripe_status, stripe_cancel_at_period_end, stripe_subscription_id FROM users WHERE id = $1',
        [userId],
      )
    ).rows[0];
    const plan = planOf(shown);
    await db.query(
      `UPDATE users SET stripe_subscription_id = $2, stripe_status = $3, stripe_cancel_at_period_end = $4,
              premium_until = CASE WHEN $6 AND $5::timestamptz IS NOT NULL
                                   THEN GREATEST(COALESCE(premium_until, now()), $5::timestamptz) ELSE premium_until END,
              stripe_amount = COALESCE($7, stripe_amount), stripe_currency = COALESCE($8, stripe_currency),
              stripe_interval = COALESCE($9, stripe_interval)
        WHERE id = $1`,
      [userId, shown.id, shown.status, shown.cancel_at_period_end === true, until, ACTIVE_STATUSES.has(shown.status), plan.amount, plan.currency, plan.interval],
    );
    const ev = subscriptionEvent(prev ?? null, { id: shown.id, status: shown.status, cancel_at_period_end: shown.cancel_at_period_end === true });
    if (ev) await logBilling(userId, ev, { subscription: shown.id, status: shown.status, until: until?.toISOString() ?? null, ...plan });
    return keep;
  }

  /** Which account a Stripe customer belongs to: our stored link first, then the customer's own metadata */
  async function userForCustomer(c: StripeConfig, customer: string, hintUserId?: number): Promise<number | null> {
    const linked = await db.query<{ id: number }>('SELECT id FROM users WHERE stripe_customer_id = $1', [customer]);
    if (linked.rows[0]) {
      if (hintUserId && hintUserId !== linked.rows[0].id) {
        await logBilling(linked.rows[0].id, 'customer_mismatch', { customer, event_user_id: hintUserId });
      }
      return linked.rows[0].id;
    }
    // Not linked yet. The Stripe account is shared with other businesses (CSVET): only a customer created by
    // BagParser (metadata.site = "bagparser") may be linked, and only to the user id it carries. The event's own
    // client_reference_id / metadata are NOT trusted for this (another system may use the same field names).
    let cus: { metadata?: Record<string, string>; deleted?: boolean } | null = null;
    try {
      cus = await stripeRequest(c, 'GET', `/customers/${customer}`);
    } catch (err) {
      if (err instanceof StripeError && err.status === 404) return null;
      throw err;
    }
    if (!cus || cus.deleted || cus.metadata?.site !== 'bagparser') return null;
    const metaUser = Number(cus.metadata?.user_id) || undefined;
    if (!metaUser) return null;
    if (hintUserId && hintUserId !== metaUser) await logBilling(metaUser, 'customer_mismatch', { customer, event_user_id: hintUserId });
    const u = await db.query<{ id: number; stripe_customer_id: string | null }>('SELECT id, stripe_customer_id FROM users WHERE id = $1', [metaUser]);
    const user = u.rows[0];
    if (!user) return null;
    if (user.stripe_customer_id && user.stripe_customer_id !== customer) {
      // The account already has another customer: keep both under watch, sync the stored one, flag this one
      await logBilling(user.id, 'customer_mismatch', { customer, stored: user.stripe_customer_id });
      return null;
    }
    await db.query('UPDATE users SET stripe_customer_id = $2 WHERE id = $1 AND stripe_customer_id IS NULL', [user.id, customer]);
    return user.id;
  }

  // ---------------------------------------------------------------------------
  // Routes
  // ---------------------------------------------------------------------------

  /** Our promo code (discount kind) → a Stripe coupon with a stable id, created on first use */
  async function couponFor(c: StripeConfig, code: string, percent: number): Promise<string> {
    const id = `BP-${code}-${percent}`;
    try {
      await stripeRequest(c, 'GET', `/coupons/${encodeURIComponent(id)}`);
    } catch (err) {
      if (!(err instanceof StripeError) || err.status !== 404) throw err;
      await stripeRequest(c, 'POST', '/coupons', { id, percent_off: percent, duration: 'once', name: `${code} (-${percent}%)` }, { idempotencyKey: `bp-coupon-${id}` });
    }
    return id;
  }

  app.post<{ Body: { period?: unknown; currency?: unknown; code?: unknown } }>(
    '/api/billing/checkout',
    { preHandler: requireUser },
    async (req, reply) => {
      if (!cfg) return reply.code(503).send({ error: 'billing_unavailable' });
      const user = req.user!;
      if (await limited(`ratelimit:checkout:${user.id}`, 10, 600)) return reply.code(429).send({ error: 'rate_limited' });
      const period: Period = req.body?.period === 'yearly' ? 'yearly' : 'monthly';
      const currency: Currency = req.body?.currency === 'usd' ? 'usd' : 'brl';
      const row = await billingRow(user.id);
      if (!row) return reply.code(401).send({ error: 'unauthorized' });

      // Optional discount code from our admin panel
      let coupon: string | undefined;
      let promoCode: string | undefined;
      const code = normalizeCode(req.body?.code);
      if (code) {
        const p = await db.query<{ kind: string; percent_off: number | null; active: boolean; uses: number; max_uses: number | null; expires_at: Date | null }>(
          'SELECT kind, percent_off, active, uses, max_uses, expires_at FROM promo_codes WHERE code = $1',
          [code],
        );
        const promo = p.rows[0];
        if (!promo || promo.kind !== 'discount' || !promo.percent_off) return reply.code(400).send({ error: 'invalid_code' });
        const blocker = promoBlocker(promo);
        if (blocker) return reply.code(409).send({ error: `code_${blocker}` });
        promoCode = code;
        coupon = `pending:${promo.percent_off}`;
      }

      try {
        const customer = await ensureCustomer(cfg, row);
        // Ask Stripe, not our DB: the webhook of a payment made seconds ago may not have arrived yet
        const live = splitDuplicates(await subscriptionsOf(cfg, customer)).keep;
        if (live) {
          await syncUser(cfg, user.id);
          return reply.code(409).send({ error: 'already_subscribed' });
        }
        if (coupon?.startsWith('pending:')) coupon = await couponFor(cfg, promoCode!, Number(coupon.slice(8)));

        const priceId = cfg.priceIds[`${currency}_${period}`];
        const lineItem = priceId
          ? { price: priceId, quantity: 1 }
          : {
              quantity: 1,
              price_data: {
                currency,
                unit_amount: PRO_PRICES[currency][period],
                recurring: { interval: period === 'yearly' ? 'year' : 'month' },
                product_data: { name: 'BagParser Pro' },
              },
            };
        const idem = checkoutIdempotency(user.id, period, currency, promoCode);
        const session = await stripeRequest<{ url: string; id: string }>(
          cfg,
          'POST',
          '/checkout/sessions',
          {
            mode: 'subscription',
            customer,
            client_reference_id: String(user.id),
            line_items: [lineItem],
            success_url: `${siteOrigin(cfg.publicUrl, req)}/account?checkout=success`,
            cancel_url: `${siteOrigin(cfg.publicUrl, req)}/pricing?checkout=cancel`,
            metadata: { user_id: String(user.id), site: 'bagparser', promo_code: promoCode },
            subscription_data: { metadata: { user_id: String(user.id), site: 'bagparser' }, ...trialUntilPaidEnds(row.premium_until) },
            discounts: coupon ? [{ coupon }] : undefined,
            // Stripe-side promotion codes still work when we did not apply ours
            allow_promotion_codes: coupon ? undefined : 'true',
            // The page closes itself after 30 min (Stripe's minimum), so old tabs cannot pay later
            expires_at: idem.expiresAt,
            locale: 'auto',
          },
          { idempotencyKey: idem.key },
        );
        return { url: session.url };
      } catch (err) {
        app.log.error({ err: (err as Error).message }, 'stripe checkout failed');
        return reply.code(502).send({ error: 'billing_error' });
      }
    },
  );

  app.post('/api/billing/portal', { preHandler: requireUser }, async (req, reply) => {
    if (!cfg) return reply.code(503).send({ error: 'billing_unavailable' });
    const row = await billingRow(req.user!.id);
    if (!row?.stripe_customer_id) return reply.code(404).send({ error: 'no_subscription' });
    try {
      const s = await stripeRequest<{ url: string }>(cfg, 'POST', '/billing_portal/sessions', {
        customer: row.stripe_customer_id,
        return_url: `${siteOrigin(cfg.publicUrl, req)}/account`,
      });
      return { url: s.url };
    } catch (err) {
      app.log.error({ err: (err as Error).message }, 'stripe portal failed');
      return reply.code(502).send({ error: 'billing_error' });
    }
  });

  /** Billing alerts for the admin panel (duplicates refunded, unknown customers…) */
  app.get('/api/admin/billing-log', { preHandler: requireAdmin }, async () => {
    const { rows } = await db.query(
      `SELECT l.id, l.kind, l.details, l.created_at, u.email
         FROM billing_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 200`,
    );
    return { entries: rows };
  });

  /**
   * D-63: undo a scheduled cancellation ("cancel at period end") so the subscriber keeps the same plan and
   * renewal date. After the subscription has ended, the way back is a new checkout (/pricing).
   */
  app.post('/api/billing/resume', { preHandler: requireUser }, async (req, reply) => {
    if (!cfg) return reply.code(503).send({ error: 'billing_unavailable' });
    const userId = req.user!.id;
    if (await limited(`ratelimit:resume:${userId}`, 10, 600)) return reply.code(429).send({ error: 'rate_limited' });
    const row = await billingRow(userId);
    if (!row?.stripe_customer_id) return reply.code(404).send({ error: 'no_subscription' });
    try {
      const live = splitDuplicates(await subscriptionsOf(cfg, row.stripe_customer_id)).keep;
      if (!live) return reply.code(404).send({ error: 'subscription_ended' });
      if (live.cancel_at_period_end) await stripeRequest(cfg, 'POST', `/subscriptions/${live.id}`, { cancel_at_period_end: 'false' });
      await syncUser(cfg, userId);
      return { ok: true };
    } catch (err) {
      app.log.error({ err: (err as Error).message }, 'stripe resume failed');
      return reply.code(502).send({ error: 'billing_error' });
    }
  });

  // ---------------------------------------------------------------------------
  // D-63: financial report for the admin
  // ---------------------------------------------------------------------------

  app.get('/api/admin/finance', { preHandler: requireAdmin }, async () => {
    const [months, totals, subs, recent, churn] = await Promise.all([
      db.query(
        `SELECT to_char(date_trunc('month', paid_at), 'YYYY-MM') AS month, currency,
                COALESCE(SUM(amount) FILTER (WHERE kind = 'invoice'), 0)::bigint AS gross,
                COALESCE(-SUM(amount) FILTER (WHERE kind = 'refund'), 0)::bigint AS refunds,
                COUNT(*) FILTER (WHERE kind = 'invoice')::int AS payments,
                COUNT(DISTINCT user_id) FILTER (WHERE kind = 'invoice')::int AS payers
           FROM billing_payments WHERE paid_at > date_trunc('month', now()) - interval '11 months'
          GROUP BY 1, 2 ORDER BY 1 DESC, 2`,
      ),
      db.query(
        `SELECT currency, COALESCE(SUM(amount) FILTER (WHERE kind = 'invoice'), 0)::bigint AS gross,
                COALESCE(-SUM(amount) FILTER (WHERE kind = 'refund'), 0)::bigint AS refunds,
                COUNT(DISTINCT user_id) FILTER (WHERE kind = 'invoice')::int AS payers
           FROM billing_payments GROUP BY currency`,
      ),
      db.query(
        `SELECT stripe_currency AS currency, stripe_interval AS interval, stripe_cancel_at_period_end AS cancelling,
                COUNT(*)::int AS n, COALESCE(SUM(stripe_amount), 0)::bigint AS amount
           FROM users WHERE stripe_status IN ('active', 'trialing') GROUP BY 1, 2, 3`,
      ),
      db.query(
        `SELECT p.paid_at, p.kind, p.amount, p.currency, p.interval, p.period_end, p.email, p.user_id, p.stripe_id, p.description, p.provider
           FROM billing_payments p ORDER BY p.paid_at DESC LIMIT 100`,
      ),
      db.query(
        `SELECT to_char(date_trunc('month', created_at), 'YYYY-MM') AS month,
                COUNT(*) FILTER (WHERE kind = 'subscribed')::int AS subscribed,
                COUNT(*) FILTER (WHERE kind = 'cancel_scheduled')::int AS cancel_scheduled,
                COUNT(*) FILTER (WHERE kind = 'resumed')::int AS resumed,
                COUNT(*) FILTER (WHERE kind = 'subscription_ended')::int AS ended,
                COUNT(*) FILTER (WHERE kind = 'payment_failed')::int AS payment_failed
           FROM billing_log WHERE created_at > date_trunc('month', now()) - interval '11 months'
          GROUP BY 1 ORDER BY 1 DESC`,
      ),
    ]);
    const num = (r: Record<string, unknown>) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, typeof v === 'string' && /^-?\d+$/.test(v) && k !== 'month' ? Number(v) : v]));
    return {
      enabled: !!cfg,
      months: months.rows.map(num),
      totals: totals.rows.map(num),
      subscriptions: subs.rows.map(num),
      recent: recent.rows.map(num),
      churn: churn.rows,
    };
  });

  /** Imports past paid invoices of our customers from Stripe (only BagParser customers, never the whole account) */
  app.post('/api/admin/finance/sync', { preHandler: requireAdmin }, async (_req, reply) => {
    if (!cfg) return reply.code(503).send({ error: 'billing_unavailable' });
    const { rows } = await db.query<{ id: number; stripe_customer_id: string }>('SELECT id, stripe_customer_id FROM users WHERE stripe_customer_id IS NOT NULL');
    let invoices = 0;
    let failed = 0;
    for (const u of rows) {
      try {
        const list = await stripeRequest<{ data: StripeInvoice[] }>(cfg, 'GET', `/invoices?customer=${encodeURIComponent(u.stripe_customer_id)}&status=paid&limit=100`);
        for (const inv of list.data ?? []) {
          await recordInvoice(u.id, inv);
          invoices++;
        }
        await syncUser(cfg, u.id);
      } catch {
        failed++;
      }
    }
    return { customers: rows.length, invoices, failed };
  });

  // ---------------------------------------------------------------------------
  // Webhook: raw body for the signature, so it lives in its own scope with a string parser
  // ---------------------------------------------------------------------------

  app.register(async (scope) => {
    scope.addContentTypeParser('application/json', { parseAs: 'string' }, (_req, body, done) => done(null, body));

    scope.post('/api/billing/webhook', { bodyLimit: 1024 * 1024 }, async (req, reply) => {
      if (!cfg || !cfg.webhookSecret) return reply.code(503).send({ error: 'billing_unavailable' });
      const raw = typeof req.body === 'string' ? req.body : '';
      if (!verifyStripeSignature(raw, req.headers['stripe-signature'] as string | undefined, cfg.webhookSecret)) {
        return reply.code(400).send({ error: 'bad_signature' });
      }
      const event = JSON.parse(raw) as { id: string; type: string; data: { object: Record<string, unknown> } };
      // Idempotency: Stripe retries; each event is handled once
      const fresh = await db.query('INSERT INTO billing_events (id, type) VALUES ($1, $2) ON CONFLICT DO NOTHING', [event.id, event.type]);
      if (!fresh.rowCount) return { received: true, duplicate: true };

      const obj = event.data.object;
      const meta = (obj.metadata ?? {}) as Record<string, string>;
      const customer = typeof obj.customer === 'string' ? obj.customer : null;
      const relevant =
        event.type === 'checkout.session.completed' || event.type.startsWith('customer.subscription.') || event.type === 'invoice.paid';
      if (!relevant || !customer) return { received: true, ignored: true };

      try {
        const hint = Number(obj.client_reference_id ?? meta.user_id) || undefined;
        const userId = await userForCustomer(cfg, customer, hint);
        if (!userId) {
          // Events of the other businesses on this Stripe account are expected: acknowledge quietly.
          // Alert only when the event says it is ours (BagParser metadata) but no account matches.
          const ours = meta.site === 'bagparser' || (obj.subscription_details as { metadata?: Record<string, string> } | undefined)?.metadata?.site === 'bagparser';
          if (ours) await logBilling(null, 'unknown_customer', { customer, event: event.type, event_id: event.id });
          return { received: true, ignored: 'not_bagparser' };
        }
        await syncUser(cfg, userId);
        if (event.type === 'invoice.paid') await recordInvoice(userId, obj as unknown as StripeInvoice);
        if (event.type === 'checkout.session.completed' && meta.promo_code) {
          await db.query('UPDATE promo_codes SET uses = uses + 1 WHERE code = $1', [meta.promo_code]);
        }
      } catch (err) {
        // Let Stripe retry later
        await db.query('DELETE FROM billing_events WHERE id = $1', [event.id]).catch(() => null);
        await logBilling(null, 'sync_error', { event: event.type, event_id: event.id, error: (err as Error).message });
        return reply.code(500).send({ error: 'webhook_failed' });
      }
      return { received: true };
    });
  });

  return {
    async cancelForUser(userId: number, reason: string) {
      if (!cfg) return;
      try {
        const row = await billingRow(userId);
        if (!row?.stripe_customer_id) return;
        const subs = await subscriptionsOf(cfg, row.stripe_customer_id);
        for (const s of subs.filter((x) => ACTIVE_STATUSES.has(x.status) || x.status === 'incomplete')) {
          await stripeRequest(cfg, 'DELETE', `/subscriptions/${s.id}`).catch(() => null);
          await logBilling(userId, 'cancelled_on_delete', { subscription: s.id, reason, email: row.email });
        }
      } catch (err) {
        await logBilling(userId, 'sync_error', { action: 'cancel_on_delete', error: (err as Error).message });
      }
    },
  };
}
