/**
 * Payment distributions (D-60): settle several party hunts at once. The site computes the transfers (frontend
 * core/payouts.ts); the API only checks the shape before saving, and keeps which transfers were already paid.
 */

export const PAYOUT_LIMIT = 300;
export const ROUND_OPTIONS = [1, 100, 1000, 10000] as const;
const MAX_GOLD = 1e12;

export interface PayoutTransfer {
  from: string;
  to: string;
  amount: number;
  paid: boolean;
}

export interface PayoutPlayer {
  name: string;
  chars: string[];
  net: number;
}

export interface PayoutInput {
  name: string;
  hunt_ids: string[];
  aliases: Record<string, string>;
  round_to: number;
  players: PayoutPlayer[];
  transfers: PayoutTransfer[];
  total: number;
}

const str = (v: unknown, max: number): string | null => {
  if (typeof v !== 'string') return null;
  const s = v.replace(/[\u0000-\u001f]/g, ' ').trim();
  return s && s.length <= max ? s : null;
};
const int = (v: unknown, min: number, max: number): number | null =>
  typeof v === 'number' && Number.isInteger(v) && v >= min && v <= max ? v : null;

export const isHuntId = (v: unknown): v is string => typeof v === 'string' && /^[A-Za-z0-9_-]{6,16}$/.test(v);

export function validatePayout(b: Record<string, unknown> | null | undefined): PayoutInput | null {
  if (!b || typeof b !== 'object') return null;
  const name = str(b.name, 120);
  if (!name) return null;

  if (!Array.isArray(b.hunt_ids) || !b.hunt_ids.length || b.hunt_ids.length > 100 || !b.hunt_ids.every(isHuntId)) return null;
  const hunt_ids = [...new Set(b.hunt_ids as string[])];

  const aliases: Record<string, string> = {};
  if (b.aliases != null) {
    if (typeof b.aliases !== 'object' || Array.isArray(b.aliases)) return null;
    const entries = Object.entries(b.aliases as Record<string, unknown>);
    if (entries.length > 50) return null;
    for (const [k, v] of entries) {
      const key = str(k, 40);
      const val = str(v, 40);
      if (!key || !val) return null;
      aliases[key] = val;
    }
  }

  const round_to = b.round_to == null ? 1 : Number(b.round_to);
  if (!(ROUND_OPTIONS as readonly number[]).includes(round_to)) return null;

  if (!Array.isArray(b.players) || b.players.length > 60) return null;
  const players: PayoutPlayer[] = [];
  for (const p of b.players as Record<string, unknown>[]) {
    const pname = str(p?.name, 40);
    const net = int(p?.net, -MAX_GOLD, MAX_GOLD);
    if (!pname || net === null) return null;
    const chars = Array.isArray(p.chars) ? (p.chars as unknown[]).map((c) => str(c, 40)).filter((c): c is string => !!c).slice(0, 10) : [];
    players.push({ name: pname, chars, net });
  }

  if (!Array.isArray(b.transfers) || b.transfers.length > 60) return null;
  const transfers: PayoutTransfer[] = [];
  for (const t of b.transfers as Record<string, unknown>[]) {
    const from = str(t?.from, 40);
    const to = str(t?.to, 40);
    const amount = int(t?.amount, 1, MAX_GOLD);
    if (!from || !to || amount === null || from.toLowerCase() === to.toLowerCase()) return null;
    transfers.push({ from, to, amount, paid: t.paid === true });
  }

  const total = int(b.total ?? 0, -MAX_GOLD * 10, MAX_GOLD * 10);
  if (total === null) return null;
  return { name, hunt_ids, aliases, round_to, players, transfers, total };
}

/** `paid` flags for a PATCH: one boolean per saved transfer */
export function validatePaidFlags(v: unknown, count: number): boolean[] | null {
  if (!Array.isArray(v) || v.length !== count || !v.every((x) => typeof x === 'boolean')) return null;
  return v as boolean[];
}
