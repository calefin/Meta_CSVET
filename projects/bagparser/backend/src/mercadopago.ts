/**
 * Mercado Pago Pix (D-69): Checkout Transparente / Payments API, Pix only. Card payments stay on Stripe.
 * Pure helpers (tested): plans, request body, webhook signature check.
 *
 * Settings (server .env only): MP_ACCESS_TOKEN (APP_USR-…), MP_WEBHOOK_SECRET (the "assinatura secreta" of the
 * webhook). MP_API_BASE only for local tests against a fake server.
 */
import { createHmac, timingSafeEqual } from 'node:crypto';
import { PRO_PRICES } from './stripe.js';

export type PixPlan = 'monthly' | 'yearly';
/** Same prices as the card in BRL; Pix buys a fixed number of days (no automatic renewal) */
export const PIX_PLANS: Record<PixPlan, { days: number; cents: number }> = {
  monthly: { days: 30, cents: PRO_PRICES.brl.monthly },
  yearly: { days: 365, cents: PRO_PRICES.brl.yearly },
};
/** How long a Pix QR code can be paid */
export const PIX_MINUTES = 30;

export interface MpConfig {
  token: string;
  webhookSecret: string;
}

/** The Public Key also starts with APP_USR- but is a UUID ("APP_USR-xxxxxxxx-xxxx-…"): it cannot create payments */
export const looksLikePublicKey = (v: string): boolean => /^(APP_USR|TEST)-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(v.trim());

/** What is wrong with the Pix settings, for the admin panel (null = fine or Pix simply not set up) */
export function mpConfigProblem(env: NodeJS.ProcessEnv): string | null {
  const token = env.MP_ACCESS_TOKEN?.trim() ?? '';
  const secret = env.MP_WEBHOOK_SECRET?.trim() ?? '';
  if (!token && !secret) return null;
  if (looksLikePublicKey(token)) return 'Pix: MP_ACCESS_TOKEN contém a Public Key do Mercado Pago, não o Access Token';
  if (!/^(APP_USR|TEST)-[\w-]{10,}$/.test(token)) return 'Pix: MP_ACCESS_TOKEN vazio ou em formato estranho';
  if (secret.length < 16) return 'Pix: MP_WEBHOOK_SECRET (assinatura secreta do webhook) vazio ou curto demais';
  return null;
}

export function mpConfigFromEnv(env: NodeJS.ProcessEnv): MpConfig | null {
  const token = env.MP_ACCESS_TOKEN?.trim() ?? '';
  const webhookSecret = env.MP_WEBHOOK_SECRET?.trim() ?? '';
  if (mpConfigProblem(env) || !token) return null;
  return { token, webhookSecret };
}

/** "2026-11-07T16:45:00.000-03:00" — the format MP wants for date_of_expiration (Brasília offset) */
export function mpDate(d: Date): string {
  const local = new Date(d.getTime() - 3 * 3_600_000);
  return `${local.toISOString().slice(0, 23)}-03:00`;
}

/** D-76: a donation is a Pix of any amount that buys nothing */
export interface PixChargeInput {
  plan: PixPlan | 'donation';
  pixId: number;
  email: string;
  cents?: number;
  description?: string;
}
const chargeCents = (o: PixChargeInput) => o.cents ?? PIX_PLANS[o.plan as PixPlan].cents;
const chargeDescription = (o: PixChargeInput) => o.description ?? `BagParser Pro — ${PIX_PLANS[o.plan as PixPlan].days} dias`;

export function pixPaymentBody(o: PixChargeInput & { userId: number; notificationUrl: string; now?: Date }) {
  const now = o.now ?? new Date();
  return {
    transaction_amount: chargeCents(o) / 100,
    description: chargeDescription(o),
    payment_method_id: 'pix',
    payer: { email: o.email },
    // Our marker: payments without it (other businesses on the same MP account) are ignored
    external_reference: `bagparser:${o.pixId}`,
    notification_url: o.notificationUrl,
    date_of_expiration: mpDate(new Date(now.getTime() + PIX_MINUTES * 60_000)),
    metadata: { site: 'bagparser', user_id: o.userId, pix_id: o.pixId, plan: o.plan },
  };
}

export const pixIdFromReference = (ref: unknown): number | null => {
  const m = typeof ref === 'string' ? ref.match(/^bagparser:(\d{1,12})$/) : null;
  return m ? Number(m[1]) : null;
};

/**
 * Webhook signature (MP docs): header x-signature "ts=<unix>,v1=<hex hmac>", header x-request-id, and the
 * notified id (query "data.id", lower case when alphanumeric). Manifest "id:<id>;request-id:<rid>;ts:<ts>;"
 * signed with HMAC-SHA256 and the webhook secret. Notices older than 10 minutes are refused (replays).
 */
export function verifyMpSignature(o: { signature?: string; requestId?: string; dataId?: string; secret: string; now?: number }): boolean {
  if (!o.signature || !o.dataId) return false;
  const parts = Object.fromEntries(o.signature.split(',').map((kv) => kv.trim().split('=') as [string, string]));
  const ts = parts.ts;
  const v1 = parts.v1;
  if (!ts || !v1 || !/^[0-9a-f]{64}$/i.test(v1)) return false;
  const tsMs = Number(ts) > 1e12 ? Number(ts) : Number(ts) * 1000;
  if (!Number.isFinite(tsMs) || Math.abs((o.now ?? Date.now()) - tsMs) > 10 * 60_000) return false;
  const id = /^[a-z0-9]+$/i.test(o.dataId) ? o.dataId.toLowerCase() : o.dataId;
  let manifest = `id:${id};`;
  if (o.requestId) manifest += `request-id:${o.requestId};`;
  manifest += `ts:${ts};`;
  const expected = createHmac('sha256', o.secret).update(manifest).digest();
  const got = Buffer.from(v1, 'hex');
  return got.length === expected.length && timingSafeEqual(got, expected);
}

export async function mpRequest<T>(cfg: MpConfig, method: 'GET' | 'POST', path: string, body?: unknown, idempotencyKey?: string): Promise<T> {
  const base = process.env.MP_API_BASE || 'https://api.mercadopago.com';
  const res = await fetch(`${base}${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${cfg.token}`,
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(idempotencyKey ? { 'X-Idempotency-Key': idempotencyKey } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(15_000),
  });
  const json = (await res.json().catch(() => ({}))) as T & { message?: string; error?: string };
  if (!res.ok) throw new Error(`mercadopago ${res.status}: ${json.message ?? json.error ?? 'error'}`);
  return json;
}

/** The parts of an MP payment we use */
export interface MpPayment {
  id: number;
  status: string; // pending | approved | rejected | cancelled | refunded | charged_back | in_process
  status_detail?: string;
  external_reference?: string | null;
  transaction_amount?: number;
  currency_id?: string;
  date_approved?: string | null;
  date_of_expiration?: string | null;
  point_of_interaction?: { transaction_data?: { qr_code?: string; qr_code_base64?: string; ticket_url?: string } };
}

// ---------------------------------------------------------------------------
// D-69b: Orders API (Mercado Pago marks the Payments API as being discontinued). Both are supported; Orders is
// the default (MP_API_MODE=payments switches back). Each answer becomes the same "charge" shape.
// ---------------------------------------------------------------------------

export type MpMode = 'orders' | 'payments';
export const mpMode = (env: NodeJS.ProcessEnv): MpMode => (env.MP_API_MODE?.trim().toLowerCase() === 'payments' ? 'payments' : 'orders');

/** The parts of an MP order we use */
export interface MpOrder {
  id: string;
  status: string; // created | action_required | processing | processed | expired | canceled | refunded | failed …
  status_detail?: string;
  external_reference?: string | null;
  total_amount?: string;
  currency?: string;
  last_updated_date?: string | null;
  transactions?: {
    payments?: {
      id?: string;
      amount?: string;
      status?: string;
      date_approved?: string | null;
      payment_method?: { id?: string; type?: string; qr_code?: string; qr_code_base64?: string; ticket_url?: string };
    }[];
  };
}

/** Normalized charge (one Pix): what the routes need, whatever API answered */
export interface MpCharge {
  id: string;
  /** pending | approved | rejected | cancelled | expired | refunded | charged_back */
  status: string;
  reference: string | null;
  cents: number;
  currency: string | null;
  approvedAt: string | null;
  qr_code: string | null;
  qr_base64: string | null;
  ticket_url: string | null;
}

const ORDER_STATUS: Record<string, string> = {
  processed: 'approved',
  accredited: 'approved',
  created: 'pending',
  action_required: 'pending',
  processing: 'pending',
  in_process: 'pending',
  expired: 'expired',
  canceled: 'cancelled',
  cancelled: 'cancelled',
  refunded: 'refunded',
  charged_back: 'charged_back',
  failed: 'rejected',
  rejected: 'rejected',
};

export function chargeFromOrder(o: MpOrder): MpCharge {
  const pay = o.transactions?.payments?.[0];
  const pm = pay?.payment_method ?? {};
  return {
    id: String(o.id),
    status: ORDER_STATUS[o.status] ?? o.status,
    reference: o.external_reference ?? null,
    cents: Math.round(Number(o.total_amount ?? pay?.amount ?? 0) * 100),
    currency: o.currency ?? null,
    approvedAt: pay?.date_approved ?? (o.status === 'processed' ? o.last_updated_date ?? null : null),
    qr_code: pm.qr_code ?? null,
    qr_base64: pm.qr_code_base64 ?? null,
    ticket_url: pm.ticket_url ?? null,
  };
}

export function chargeFromPayment(p: MpPayment): MpCharge {
  const td = p.point_of_interaction?.transaction_data ?? {};
  return {
    id: String(p.id),
    status: p.status,
    reference: p.external_reference ?? null,
    cents: Math.round((p.transaction_amount ?? 0) * 100),
    currency: p.currency_id ?? null,
    approvedAt: p.date_approved ?? null,
    qr_code: td.qr_code ?? null,
    qr_base64: td.qr_code_base64 ?? null,
    ticket_url: td.ticket_url ?? null,
  };
}

/** Pix order (Orders API): amounts as strings, expiry as an ISO 8601 duration */
export function pixOrderBody(o: PixChargeInput) {
  const amount = (chargeCents(o) / 100).toFixed(2);
  return {
    type: 'online',
    processing_mode: 'automatic',
    external_reference: `bagparser:${o.pixId}`,
    total_amount: amount,
    description: chargeDescription(o),
    payer: { email: o.email },
    transactions: { payments: [{ amount, payment_method: { id: 'pix', type: 'bank_transfer' }, expiration_time: `PT${PIX_MINUTES}M` }] },
  };
}

/** Orders have ids like "ORD01…"; payments are numbers */
export const isOrderId = (id: string): boolean => /^ORD[A-Z0-9]{6,40}$/i.test(id);

export async function createPixCharge(cfg: MpConfig, mode: MpMode, o: PixChargeInput & { userId: number; notificationUrl: string }): Promise<MpCharge> {
  if (mode === 'orders') return chargeFromOrder(await mpRequest<MpOrder>(cfg, 'POST', '/v1/orders', pixOrderBody(o), `bp-pix-${o.pixId}`));
  return chargeFromPayment(await mpRequest<MpPayment>(cfg, 'POST', '/v1/payments', pixPaymentBody(o), `bp-pix-${o.pixId}`));
}

export async function getCharge(cfg: MpConfig, id: string): Promise<MpCharge> {
  if (isOrderId(id)) return chargeFromOrder(await mpRequest<MpOrder>(cfg, 'GET', `/v1/orders/${encodeURIComponent(id)}`));
  return chargeFromPayment(await mpRequest<MpPayment>(cfg, 'GET', `/v1/payments/${encodeURIComponent(id)}`));
}

// ---------------------------------------------------------------------------
// D-73: every Mercado Pago notification is recorded; complaints (reclamações) and chargebacks (contestações)
// are read from MP, linked to the BagParser account when possible and sent to the admin.
// ---------------------------------------------------------------------------

export type MpEventKind = 'order' | 'payment' | 'claim' | 'chargeback' | 'other';

/** Webhook "type"/"topic" → what it is (MP names vary: "topic_claims_integration_wh", "topic_chargebacks_wh"…) */
export function classifyMpEvent(type: string, action = ''): MpEventKind {
  const t = `${type} ${action}`.toLowerCase();
  if (/chargeback/.test(t)) return 'chargeback';
  if (/claim/.test(t)) return 'claim';
  if (/^order\b/.test(type.toLowerCase())) return 'order';
  if (/^payment\b/.test(type.toLowerCase())) return 'payment';
  return 'other';
}

/** Claim (post-purchase API) — the parts we use */
export interface MpClaim {
  id: number | string;
  resource?: string;
  resource_id?: number | string;
  status?: string; // opened | closed
  stage?: string; // claim | dispute | recontact | none
  reason_id?: string;
  type?: string;
  date_created?: string;
  last_updated?: string;
}

/** Chargeback — the parts we use */
export interface MpChargeback {
  id: string | number;
  payments?: (number | string)[];
  amount?: number;
  currency?: string;
  coverage_applied?: boolean | null;
  coverage_eligible?: boolean | null;
  documentation_required?: boolean;
  documentation_status?: string;
  date_documentation_deadline?: string | null;
  date_created?: string;
}

const CLAIM_STAGE: Record<string, string> = { claim: 'reclamação', dispute: 'disputa (mediação do MP)', recontact: 'recontato', none: '' };

export function claimSummary(c: MpClaim): { status: string; summary: string; paymentId: string | null } {
  const stage = CLAIM_STAGE[c.stage ?? ''] ?? c.stage ?? '';
  return {
    status: [c.status, c.stage].filter(Boolean).join('/') || 'desconhecido',
    summary: `Reclamação ${c.status === 'closed' ? 'fechada' : 'aberta'}${stage ? ` · ${stage}` : ''}${c.reason_id ? ` · motivo ${c.reason_id}` : ''}`,
    paymentId: c.resource === 'payment' || c.resource_id != null ? String(c.resource_id ?? '') || null : null,
  };
}

export function chargebackSummary(cb: MpChargeback): { status: string; summary: string; paymentId: string | null; cents: number | null; deadline: string | null } {
  const cents = typeof cb.amount === 'number' ? Math.round(cb.amount * 100) : null;
  const deadline = cb.date_documentation_deadline ?? null;
  const doc = cb.documentation_required ? `documentação ${cb.documentation_status ?? 'pendente'}${deadline ? ` até ${deadline.slice(0, 10)}` : ''}` : 'sem documentação pedida';
  return {
    status: cb.coverage_applied ? 'coberta' : cb.documentation_status ?? 'aberta',
    summary: `Contestação${cents != null ? ` de ${(cents / 100).toFixed(2)} ${cb.currency ?? ''}`.trimEnd() : ''} · ${doc}${cb.coverage_applied ? ' · coberta pelo MP' : ''}`,
    paymentId: cb.payments?.length ? String(cb.payments[0]) : null,
    cents,
    deadline,
  };
}
