import { createHmac } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import {
  checkoutIdempotency,
  formEncode,
  periodEnd,
  premiumUntilFor,
  splitDuplicates,
  stripeConfigFromEnv,
  verifyStripeSignature,
} from './stripe.js';

describe('stripe helpers', () => {
  it('encodes nested params like Stripe expects', () => {
    const s = formEncode({
      mode: 'subscription',
      line_items: [{ quantity: 1, price_data: { currency: 'brl', unit_amount: 990, recurring: { interval: 'month' } } }],
      discounts: [{ coupon: 'BP-X-20' }],
      customer: undefined,
    });
    expect(decodeURIComponent(s)).toBe(
      'mode=subscription&line_items[0][quantity]=1&line_items[0][price_data][currency]=brl&line_items[0][price_data][unit_amount]=990' +
        '&line_items[0][price_data][recurring][interval]=month&discounts[0][coupon]=BP-X-20',
    );
  });

  it('verifies webhook signatures and rejects tampering and replays', () => {
    const secret = 'whsec_test';
    const body = '{"id":"evt_1"}';
    const t = 1_800_000_000;
    const sig = createHmac('sha256', secret).update(`${t}.${body}`).digest('hex');
    const now = t * 1000;
    expect(verifyStripeSignature(body, `t=${t},v1=${sig}`, secret, now)).toBe(true);
    expect(verifyStripeSignature(body + ' ', `t=${t},v1=${sig}`, secret, now)).toBe(false);
    expect(verifyStripeSignature(body, `t=${t},v1=${sig}`, 'other', now)).toBe(false);
    expect(verifyStripeSignature(body, `t=${t},v1=${sig}`, secret, now + 600_000)).toBe(false);
    expect(verifyStripeSignature(body, undefined, secret, now)).toBe(false);
  });

  it('reads the period end in old and new API shapes and adds grace for active plans', () => {
    expect(periodEnd({ id: 's', customer: 'c', status: 'active', current_period_end: 1_800_000_000 })!.getTime()).toBe(1_800_000_000_000);
    expect(periodEnd({ id: 's', customer: 'c', status: 'active', items: { data: [{ current_period_end: 1_800_000_000 }] } })).not.toBeNull();
    const active = premiumUntilFor({ id: 's', customer: 'c', status: 'active', current_period_end: 1_800_000_000 })!;
    expect(active.getTime()).toBe(1_800_000_000_000 + 2 * 86_400_000);
    const canceled = premiumUntilFor({ id: 's', customer: 'c', status: 'canceled', current_period_end: 1_800_000_000 })!;
    expect(canceled.getTime()).toBe(1_800_000_000_000);
  });

  it('stays disabled without a secret key', () => {
    expect(stripeConfigFromEnv({})).toBeNull();
    expect(stripeConfigFromEnv({ STRIPE_SECRET_KEY: 'sk_test_1', STRIPE_PRICE_PRO_BRL_MONTHLY: 'price_1' })!.priceIds).toEqual({ brl_monthly: 'price_1' });
  });
});

describe('double charge protection', () => {
  it('gives the same checkout key and expiry to repeated clicks in the same window', () => {
    const t = Date.UTC(2026, 10, 6, 12, 1, 0);
    const a = checkoutIdempotency(7, 'monthly', 'brl', undefined, t);
    const b = checkoutIdempotency(7, 'monthly', 'brl', undefined, t + 60_000);
    expect(b).toEqual(a);
    // Stripe accepts 30 min – 24 h
    const ahead = a.expiresAt - t / 1000;
    expect(ahead).toBeGreaterThanOrEqual(30 * 60);
    expect(ahead).toBeLessThanOrEqual(45 * 60);
    // Different plan, user or code → different checkout
    expect(checkoutIdempotency(7, 'yearly', 'brl', undefined, t).key).not.toBe(a.key);
    expect(checkoutIdempotency(8, 'monthly', 'brl', undefined, t).key).not.toBe(a.key);
    expect(checkoutIdempotency(7, 'monthly', 'brl', 'PRO20', t).key).not.toBe(a.key);
  });

  it('keeps the oldest live subscription and marks the others as duplicates', () => {
    const subs = [
      { id: 'sub_new', status: 'active', created: 200 },
      { id: 'sub_old', status: 'active', created: 100 },
      { id: 'sub_gone', status: 'canceled', created: 50 },
      { id: 'sub_late', status: 'past_due', created: 300 },
    ];
    const r = splitDuplicates(subs);
    expect(r.keep?.id).toBe('sub_old');
    expect(r.extra.map((s) => s.id)).toEqual(['sub_new', 'sub_late']);
    expect(splitDuplicates([{ id: 'x', status: 'canceled', created: 1 }])).toEqual({ keep: null, extra: [] });
  });
});

describe('subscription events for the billing log (D-63)', () => {
  it('detects new, scheduled cancellation, reactivation, failure and end', async () => {
    const { subscriptionEvent, planOf } = await import('./stripe.js');
    const prev = (status: string | null, cancel = false, id: string | null = 'sub_1') => ({ stripe_status: status, stripe_cancel_at_period_end: cancel, stripe_subscription_id: id });
    const next = (status: string, cancel = false, id = 'sub_1') => ({ id, status, cancel_at_period_end: cancel });
    expect(subscriptionEvent(null, next('active'))).toBe('subscribed');
    expect(subscriptionEvent(prev('canceled'), next('active', false, 'sub_2'))).toBe('subscribed'); // came back
    expect(subscriptionEvent(prev('active'), next('active', true))).toBe('cancel_scheduled');
    expect(subscriptionEvent(prev('active', true), next('active', false))).toBe('resumed');
    expect(subscriptionEvent(prev('active'), next('past_due'))).toBe('payment_failed');
    expect(subscriptionEvent(prev('active', true), next('canceled'))).toBe('subscription_ended');
    expect(subscriptionEvent(prev('active'), next('active'))).toBeNull();
    expect(planOf({ id: 's', customer: 'c', status: 'active', items: { data: [{ price: { unit_amount: 990, currency: 'BRL', recurring: { interval: 'month' } } }] } })).toEqual({
      amount: 990,
      currency: 'brl',
      interval: 'month',
    });
  });
});

describe('card subscription after Pix days (D-69)', () => {
  it('starts charging only when the days already paid end', async () => {
    const { trialUntilPaidEnds } = await import('./billingRoutes.js');
    const now = Date.UTC(2026, 10, 7);
    expect(trialUntilPaidEnds(null, now)).toEqual({});
    expect(trialUntilPaidEnds(new Date(now + 3_600_000), now)).toEqual({}); // ends in 1 h: charge now
    const in30 = new Date(now + 30 * 86_400_000);
    expect(trialUntilPaidEnds(in30, now)).toEqual({ trial_end: Math.floor(in30.getTime() / 1000) });
    expect(trialUntilPaidEnds(new Date(now + 3000 * 86_400_000), now)).toEqual({}); // owner account until 2099
  });
});
