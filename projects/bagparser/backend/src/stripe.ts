/**
 * Stripe billing helpers (D-51): every charge goes through Stripe (BRL and USD), no Mercado Pago.
 * Plain REST calls with fetch (no SDK dependency) + webhook signature check.
 */
import { createHmac, timingSafeEqual } from 'node:crypto';

export type Currency = 'brl' | 'usd';
export type Period = 'monthly' | 'yearly';

/** Pro plan prices in the smallest unit (centavos / cents). Must match the /pricing page. */
export const PRO_PRICES: Record<Currency, Record<Period, number>> = {
  brl: { monthly: 990, yearly: 8990 },
  usd: { monthly: 249, yearly: 1999 },
};

export interface StripeConfig {
  secretKey: string;
  webhookSecret: string;
  publicUrl: string;
  /** Optional Price ids created in the dashboard (STRIPE_PRICE_PRO_BRL_MONTHLY …); inline prices otherwise */
  priceIds: Partial<Record<`${Currency}_${Period}`, string>>;
}

export function stripeConfigFromEnv(env = process.env): StripeConfig | null {
  const secretKey = env.STRIPE_SECRET_KEY?.trim();
  if (!secretKey) return null;
  const priceIds: StripeConfig['priceIds'] = {};
  for (const c of ['brl', 'usd'] as const)
    for (const p of ['monthly', 'yearly'] as const) {
      const v = env[`STRIPE_PRICE_PRO_${c.toUpperCase()}_${p.toUpperCase()}`]?.trim();
      if (v) priceIds[`${c}_${p}`] = v;
    }
  return {
    secretKey,
    webhookSecret: env.STRIPE_WEBHOOK_SECRET?.trim() ?? '',
    publicUrl: (env.PUBLIC_URL ?? 'https://bagparser.com').replace(/\/+$/, ''),
    priceIds,
  };
}

/** Nested objects → Stripe's form encoding: line_items[0][price_data][currency]=brl */
export function formEncode(obj: Record<string, unknown>, prefix = ''): string {
  const parts: string[] = [];
  for (const [k, v] of Object.entries(obj)) {
    if (v === undefined || v === null) continue;
    const key = prefix ? `${prefix}[${k}]` : k;
    if (Array.isArray(v)) v.forEach((item, i) => parts.push(typeof item === 'object' ? formEncode(item as Record<string, unknown>, `${key}[${i}]`) : `${encodeURIComponent(`${key}[${i}]`)}=${encodeURIComponent(String(item))}`));
    else if (typeof v === 'object') parts.push(formEncode(v as Record<string, unknown>, key));
    else parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(v))}`);
  }
  return parts.filter(Boolean).join('&');
}

export class StripeError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
  ) {
    super(message);
  }
}

export async function stripeRequest<T>(
  cfg: StripeConfig,
  method: 'GET' | 'POST' | 'DELETE',
  path: string,
  body?: Record<string, unknown>,
  opts: { idempotencyKey?: string } = {},
): Promise<T> {
  // STRIPE_API_BASE only for local end-to-end tests against a fake Stripe server
  const base = process.env.STRIPE_API_BASE || 'https://api.stripe.com';
  const res = await fetch(`${base}/v1${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${cfg.secretKey}`,
      ...(body ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}),
      // Same key within 24 h → Stripe returns the first result instead of creating a second object
      ...(opts.idempotencyKey ? { 'Idempotency-Key': opts.idempotencyKey } : {}),
    },
    body: body ? formEncode(body) : undefined,
    signal: AbortSignal.timeout(15_000),
  });
  const json = (await res.json().catch(() => ({}))) as { error?: { code?: string; message?: string } };
  if (!res.ok) throw new StripeError(res.status, json.error?.code ?? `http_${res.status}`, json.error?.message ?? 'Stripe error');
  return json as T;
}

/**
 * Stripe-Signature: "t=<unix>,v1=<hex>[,v1=…]". The signed payload is "<t>.<raw body>" (HMAC-SHA256).
 * Rejects signatures older than `toleranceSeconds` (replays).
 */
export function verifyStripeSignature(rawBody: string, header: string | undefined, secret: string, now = Date.now(), toleranceSeconds = 300): boolean {
  if (!header || !secret) return false;
  const items = header.split(',').map((p) => p.trim().split('='));
  const t = Number(items.find(([k]) => k === 't')?.[1]);
  const sigs = items.filter(([k]) => k === 'v1').map(([, v]) => v);
  if (!Number.isFinite(t) || !sigs.length) return false;
  if (Math.abs(now / 1000 - t) > toleranceSeconds) return false;
  const expected = createHmac('sha256', secret).update(`${t}.${rawBody}`).digest();
  return sigs.some((s) => {
    const got = Buffer.from(s, 'hex');
    return got.length === expected.length && timingSafeEqual(got, expected);
  });
}

export interface StripeSubscription {
  id: string;
  customer: string;
  status: string;
  /** Unix seconds */
  created?: number;
  latest_invoice?: string | { id: string } | null;
  current_period_end?: number;
  cancel_at_period_end?: boolean;
  items?: { data: { current_period_end?: number; price?: { unit_amount?: number | null; currency?: string; recurring?: { interval?: string } | null } | null }[] };
  metadata?: Record<string, string>;
}

/** Period end of a subscription (newer API versions moved it to the items) */
export function periodEnd(sub: StripeSubscription): Date | null {
  const t = sub.current_period_end ?? sub.items?.data?.[0]?.current_period_end;
  return t ? new Date(t * 1000) : null;
}

/** Statuses that keep Premium on until the end of the paid period */
export const ACTIVE_STATUSES = new Set(['active', 'trialing', 'past_due']);

/**
 * Idempotency key for "open the checkout": a double click, a second tab or a retried request within the same
 * 15-minute window gets the SAME Checkout Session back, so the buyer cannot end up with two payment pages.
 */
export function checkoutIdempotency(
  userId: number,
  period: Period,
  currency: Currency,
  code: string | undefined,
  now = Date.now(),
): { key: string; expiresAt: number } {
  const bucket = Math.floor(now / 900_000);
  return {
    key: `bp-checkout-${userId}-${period}-${currency}-${code ?? 'none'}-${bucket}`,
    // Must be identical for every request with this key (Stripe rejects a reused key with other params):
    // end of the 15-min window + 30 min → always 30–45 min ahead, inside Stripe's allowed 30 min–24 h
    expiresAt: (bucket + 1) * 900 + 30 * 60,
  };
}

/**
 * Duplicate protection: when one account ends up with more than one live subscription (paid twice in two tabs,
 * an old customer record…), keep the OLDEST one (the first payment) and cancel + refund the others.
 */
export function splitDuplicates<T extends Pick<StripeSubscription, 'id' | 'status' | 'created'>>(subs: T[]): { keep: T | null; extra: T[] } {
  const live = subs.filter((s) => ACTIVE_STATUSES.has(s.status)).sort((a, b) => (a.created ?? 0) - (b.created ?? 0) || a.id.localeCompare(b.id));
  return { keep: live[0] ?? null, extra: live.slice(1) };
}

/** Premium end for a subscription: paid period + 2 days of grace for a late renewal charge */
export function premiumUntilFor(sub: StripeSubscription): Date | null {
  const end = periodEnd(sub);
  if (!end) return null;
  return ACTIVE_STATUSES.has(sub.status) ? new Date(end.getTime() + 2 * 86_400_000) : end;
}

/** D-63: plan price of a subscription (cents, currency, "month" | "year") for the financial report */
export function planOf(sub: StripeSubscription): { amount: number | null; currency: string | null; interval: string | null } {
  const price = sub.items?.data?.[0]?.price;
  return { amount: price?.unit_amount ?? null, currency: price?.currency?.toLowerCase() ?? null, interval: price?.recurring?.interval ?? null };
}

/** D-63: what changed between the stored state and Stripe's, for the billing log (null = nothing worth logging) */
export function subscriptionEvent(
  prev: { stripe_status: string | null; stripe_cancel_at_period_end: boolean; stripe_subscription_id: string | null } | null,
  next: { id: string; status: string; cancel_at_period_end: boolean },
): 'subscribed' | 'cancel_scheduled' | 'resumed' | 'payment_failed' | 'subscription_ended' | null {
  const wasLive = !!prev && ACTIVE_STATUSES.has(prev.stripe_status ?? '') && prev.stripe_subscription_id === next.id;
  const isLive = ACTIVE_STATUSES.has(next.status);
  if (isLive && !wasLive) return next.status === 'past_due' ? 'payment_failed' : 'subscribed';
  if (!isLive && wasLive) return ['canceled', 'incomplete_expired'].includes(next.status) ? 'subscription_ended' : 'payment_failed';
  if (isLive && wasLive) {
    if (next.status === 'past_due' && prev!.stripe_status !== 'past_due') return 'payment_failed';
    if (next.cancel_at_period_end && !prev!.stripe_cancel_at_period_end) return 'cancel_scheduled';
    if (!next.cancel_at_period_end && prev!.stripe_cancel_at_period_end) return 'resumed';
  }
  return null;
}
