/**
 * Promo codes (D-50).
 *  - "discount": percentage off the Pro price, validated now and applied by the checkout (Stripe Checkout coupon, D-51)
 *  - "trial":    redeeming adds N days of Premium to the account (testers, giveaways, streamers)
 * Each account can redeem a code once; codes can have a use limit and an expiry date.
 */
export type PromoKind = 'discount' | 'trial';

export interface PromoInput {
  code: string;
  kind: PromoKind;
  percent_off: number | null;
  premium_days: number | null;
  max_uses: number | null;
  expires_at: Date | null;
  note: string | null;
}

/** "  bp-tester 2026 " → "BP-TESTER2026"; null when invalid */
export function normalizeCode(v: unknown): string | null {
  if (typeof v !== 'string') return null;
  const s = v.toUpperCase().replace(/\s+/g, '');
  return /^[A-Z0-9_-]{3,32}$/.test(s) ? s : null;
}

export function validatePromo(b: Record<string, unknown> | null | undefined): PromoInput | { error: string } {
  const body = b ?? {};
  const code = normalizeCode(body.code);
  if (!code) return { error: 'invalid_code' };
  const kind = body.kind === 'trial' ? 'trial' : body.kind === 'discount' ? 'discount' : null;
  if (!kind) return { error: 'invalid_kind' };
  const int = (v: unknown, min: number, max: number) => {
    if (v === null || v === undefined || v === '') return null;
    const n = Number(v);
    return Number.isInteger(n) && n >= min && n <= max ? n : NaN;
  };
  const percent = kind === 'discount' ? int(body.percent_off, 1, 100) : null;
  const days = kind === 'trial' ? int(body.premium_days, 1, 366) : null;
  if (kind === 'discount' && (percent == null || Number.isNaN(percent))) return { error: 'invalid_percent' };
  if (kind === 'trial' && (days == null || Number.isNaN(days))) return { error: 'invalid_days' };
  const maxUses = int(body.max_uses, 1, 1_000_000);
  if (Number.isNaN(maxUses)) return { error: 'invalid_max_uses' };
  let expires: Date | null = null;
  if (body.expires_at) {
    expires = new Date(String(body.expires_at).length === 10 ? `${body.expires_at}T23:59:59Z` : String(body.expires_at));
    if (Number.isNaN(expires.getTime()) || expires.getTime() < Date.now()) return { error: 'invalid_expiry' };
  }
  const note = typeof body.note === 'string' ? body.note.trim().slice(0, 200) || null : null;
  return { code, kind, percent_off: percent, premium_days: days, max_uses: maxUses, expires_at: expires, note };
}

/** New Premium end date: days are added on top of any Premium time still left */
export function extendPremium(current: Date | null, days: number, now = new Date()): Date {
  const base = current && current.getTime() > now.getTime() ? current : now;
  return new Date(base.getTime() + days * 86_400_000);
}

/** Why a code cannot be used right now (null = usable) */
export function promoBlocker(
  row: { active: boolean; uses: number; max_uses: number | null; expires_at: Date | null },
  now = new Date(),
): 'inactive' | 'expired' | 'used_up' | null {
  if (!row.active) return 'inactive';
  if (row.expires_at && row.expires_at.getTime() < now.getTime()) return 'expired';
  if (row.max_uses != null && row.uses >= row.max_uses) return 'used_up';
  return null;
}
