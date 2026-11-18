import { createHmac } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import { mpConfigFromEnv, mpDate, PIX_PLANS, pixIdFromReference, pixPaymentBody, verifyMpSignature } from './mercadopago.js';

const SECRET = 'whsec_mp_test_0123456789abcdef';
const sign = (id: string, rid: string, ts: number) => createHmac('sha256', SECRET).update(`id:${id};request-id:${rid};ts:${ts};`).digest('hex');

describe('Mercado Pago Pix (D-69)', () => {
  it('needs a real-looking token and a secret', () => {
    expect(mpConfigFromEnv({})).toBeNull();
    expect(mpConfigFromEnv({ MP_ACCESS_TOKEN: 'abc', MP_WEBHOOK_SECRET: SECRET })).toBeNull();
    expect(mpConfigFromEnv({ MP_ACCESS_TOKEN: 'APP_USR-1234567890-abcdef', MP_WEBHOOK_SECRET: 'short' })).toBeNull();
    expect(mpConfigFromEnv({ MP_ACCESS_TOKEN: 'APP_USR-1234567890-abcdef', MP_WEBHOOK_SECRET: SECRET })).not.toBeNull();
  });

  it('builds the Pix payment with our reference, the price of the plan and a 30-minute expiry', () => {
    const now = new Date('2026-11-07T15:00:00Z');
    const b = pixPaymentBody({ plan: 'yearly', pixId: 42, userId: 7, email: 'a@x.com', notificationUrl: 'https://bagparser.com/api/billing/pix/webhook', now });
    expect(b.transaction_amount).toBe(89.9);
    expect(b.payment_method_id).toBe('pix');
    expect(b.external_reference).toBe('bagparser:42');
    expect(b.date_of_expiration).toBe('2026-11-07T12:30:00.000-03:00');
    expect(PIX_PLANS.monthly).toEqual({ days: 30, cents: 990 });
    expect(mpDate(now)).toBe('2026-11-07T12:00:00.000-03:00');
    expect(pixIdFromReference('bagparser:42')).toBe(42);
    expect(pixIdFromReference('csvet:42')).toBeNull();
    expect(pixIdFromReference(undefined)).toBeNull();
  });

  it('accepts only correctly signed, recent notices', () => {
    const now = Date.now();
    const ts = Math.floor(now / 1000);
    const ok = { signature: `ts=${ts},v1=${sign('123456', 'req-1', ts)}`, requestId: 'req-1', dataId: '123456', secret: SECRET, now };
    expect(verifyMpSignature(ok)).toBe(true);
    expect(verifyMpSignature({ ...ok, dataId: '999' })).toBe(false); // other payment
    expect(verifyMpSignature({ ...ok, requestId: 'req-2' })).toBe(false);
    expect(verifyMpSignature({ ...ok, secret: 'another_secret_value_123' })).toBe(false);
    expect(verifyMpSignature({ ...ok, now: now + 20 * 60_000 })).toBe(false); // replay
    expect(verifyMpSignature({ ...ok, signature: undefined })).toBe(false);
    expect(verifyMpSignature({ ...ok, signature: 'ts=1,v1=zz' })).toBe(false);
  });
});

describe('Orders API (D-69b)', () => {
  it('builds the Pix order and reads an order back as a charge', async () => {
    const { pixOrderBody, chargeFromOrder, isOrderId, mpMode } = await import('./mercadopago.js');
    const b = pixOrderBody({ plan: 'monthly', pixId: 7, email: 'a@x.com' });
    expect(b).toMatchObject({ type: 'online', processing_mode: 'automatic', external_reference: 'bagparser:7', total_amount: '9.90' });
    expect(b.transactions.payments[0]).toEqual({ amount: '9.90', payment_method: { id: 'pix', type: 'bank_transfer' }, expiration_time: 'PT30M' });
    const pending = chargeFromOrder({
      id: 'ORD01JABCDEF123',
      status: 'action_required',
      external_reference: 'bagparser:7',
      total_amount: '9.90',
      transactions: { payments: [{ amount: '9.90', payment_method: { id: 'pix', qr_code: '000201…', qr_code_base64: 'iVBOR', ticket_url: 'https://x' } }] },
    });
    expect(pending).toMatchObject({ id: 'ORD01JABCDEF123', status: 'pending', cents: 990, qr_code: '000201…', qr_base64: 'iVBOR' });
    expect(chargeFromOrder({ id: 'ORD1234567', status: 'processed', total_amount: '89.90', last_updated_date: '2026-11-07T12:00:00Z' })).toMatchObject({ status: 'approved', cents: 8990, approvedAt: '2026-11-07T12:00:00Z' });
    expect(chargeFromOrder({ id: 'ORD1234567', status: 'refunded' }).status).toBe('refunded');
    expect(isOrderId('ORD01JABCDEF123')).toBe(true);
    expect(isOrderId('123456')).toBe(false);
    expect(mpMode({})).toBe('orders');
    expect(mpMode({ MP_API_MODE: 'payments' })).toBe('payments');
  });

  it('checks the signature of order notices (id in lower case in the manifest)', () => {
    const now = Date.now();
    const ts = Math.floor(now / 1000);
    const v1 = createHmac('sha256', SECRET).update(`id:ord01jabcdef123;request-id:r1;ts:${ts};`).digest('hex');
    expect(verifyMpSignature({ signature: `ts=${ts},v1=${v1}`, requestId: 'r1', dataId: 'ORD01JABCDEF123', secret: SECRET, now })).toBe(true);
  });
});

describe('Pix settings check (D-69b)', () => {
  it('refuses the Public Key in place of the Access Token', async () => {
    const { mpConfigFromEnv, mpConfigProblem } = await import('./mercadopago.js');
    const secret = 'abcdefghijklmnopqrstuvwxyz123456';
    const publicKey = 'APP_USR-8417a025-7c25-4043-bac5-ed9b1ae71772';
    expect(mpConfigFromEnv({ MP_ACCESS_TOKEN: publicKey, MP_WEBHOOK_SECRET: secret })).toBeNull();
    expect(mpConfigProblem({ MP_ACCESS_TOKEN: publicKey, MP_WEBHOOK_SECRET: secret })).toContain('Public Key');
    expect(mpConfigProblem({ MP_ACCESS_TOKEN: 'APP_USR-1234567890123456-110712-0123456789abcdef0123456789abcdef-123456789', MP_WEBHOOK_SECRET: 'short' })).toContain('MP_WEBHOOK_SECRET');
    expect(mpConfigProblem({})).toBeNull();
    expect(mpConfigFromEnv({ MP_ACCESS_TOKEN: 'APP_USR-1234567890123456-110712-0123456789abcdef0123456789abcdef-123456789', MP_WEBHOOK_SECRET: secret })).not.toBeNull();
  });
});

describe('Mercado Pago events (D-73)', () => {
  it('classifies notifications and summarises complaints and chargebacks', async () => {
    const { classifyMpEvent, claimSummary, chargebackSummary } = await import('./mercadopago.js');
    expect(classifyMpEvent('order', 'order.processed')).toBe('order');
    expect(classifyMpEvent('payment', 'payment.updated')).toBe('payment');
    expect(classifyMpEvent('topic_claims_integration_wh', 'created')).toBe('claim');
    expect(classifyMpEvent('topic_chargebacks_wh', 'created')).toBe('chargeback');
    expect(classifyMpEvent('stop_delivery_op_wh')).toBe('other');
    expect(classifyMpEvent('mp-connect', 'application.authorized')).toBe('other');
    const c = claimSummary({ id: 5, resource: 'payment', resource_id: 9000000001, status: 'opened', stage: 'dispute', reason_id: 'PNR' });
    expect(c).toEqual({ status: 'opened/dispute', summary: 'Reclamação aberta · disputa (mediação do MP) · motivo PNR', paymentId: '9000000001' });
    const cb = chargebackSummary({ id: 'CB1', payments: [123], amount: 9.9, currency: 'BRL', documentation_required: true, documentation_status: 'pending', date_documentation_deadline: '2026-11-20T00:00:00Z' });
    expect(cb).toEqual({ status: 'pending', summary: 'Contestação de 9.90 BRL · documentação pending até 2026-11-20', paymentId: '123', cents: 990, deadline: '2026-11-20T00:00:00Z' });
  });
});
