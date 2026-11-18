import { describe, expect, it } from 'vitest';
import { extendPremium, normalizeCode, promoBlocker, validatePromo } from './promo.js';

describe('promo codes', () => {
  it('normalises codes', () => {
    expect(normalizeCode(' bp-tester 2026 ')).toBe('BP-TESTER2026');
    expect(normalizeCode('ab')).toBeNull();
    expect(normalizeCode('bad!code')).toBeNull();
  });

  it('validates discount and trial codes', () => {
    expect(validatePromo({ code: 'pro20', kind: 'discount', percent_off: 20 })).toMatchObject({ code: 'PRO20', percent_off: 20, premium_days: null });
    expect(validatePromo({ code: 'tester', kind: 'trial', premium_days: 30, max_uses: 10 })).toMatchObject({ premium_days: 30, max_uses: 10 });
    expect(validatePromo({ code: 'x1x', kind: 'discount', percent_off: 0 })).toEqual({ error: 'invalid_percent' });
    expect(validatePromo({ code: 'x1x', kind: 'trial' })).toEqual({ error: 'invalid_days' });
    expect(validatePromo({ code: 'x1x', kind: 'gift' })).toEqual({ error: 'invalid_kind' });
    expect(validatePromo({ code: 'x1x', kind: 'trial', premium_days: 7, expires_at: '2000-01-01' })).toEqual({ error: 'invalid_expiry' });
  });

  it('adds days on top of the Premium time left', () => {
    const now = new Date('2026-11-06T00:00:00Z');
    expect(extendPremium(null, 30, now).toISOString()).toBe('2026-12-06T00:00:00.000Z');
    expect(extendPremium(new Date('2026-11-10T00:00:00Z'), 10, now).toISOString()).toBe('2026-11-20T00:00:00.000Z');
    expect(extendPremium(new Date('2026-01-01T00:00:00Z'), 1, now).toISOString()).toBe('2026-11-07T00:00:00.000Z');
  });

  it('tells why a code is blocked', () => {
    const ok = { active: true, uses: 0, max_uses: 2, expires_at: null };
    expect(promoBlocker(ok)).toBeNull();
    expect(promoBlocker({ ...ok, active: false })).toBe('inactive');
    expect(promoBlocker({ ...ok, uses: 2 })).toBe('used_up');
    expect(promoBlocker({ ...ok, expires_at: new Date('2000-01-01') })).toBe('expired');
  });
});
