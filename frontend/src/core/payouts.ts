/**
 * Payment distributions (D-60): settle several party hunts with one set of bank transfers.
 *
 * Per hunt, every member should end with the same profit: (sum of balances after extra expenses) / members.
 * A member's "net" in that hunt is what they hold above that share (positive = pays, negative = receives).
 * Nets add up across hunts per player; characters of the same player can be merged first, so a player who
 * hunted with two characters pays or receives once. The combined nets are settled with as few transfers as
 * possible (exact pairs first, then largest debtor with largest creditor), usually far fewer than settling
 * every hunt on its own.
 */
import { balancesWithExpenses, type Expense } from './build';
import { computeLootSplit } from './party';

export interface PayoutMember {
  name: string;
  balance: number;
  isLeader?: boolean;
}

export interface PayoutHunt {
  id: string;
  members: PayoutMember[];
  expenses: Expense[];
}

export interface PayoutPlayer {
  /** Name used in the transfers (the main character) */
  name: string;
  /** Characters merged into this player */
  chars: string[];
  /** Total held above the fair share: > 0 pays, < 0 receives */
  net: number;
  /** Net per hunt id (only hunts the player was in) */
  perHunt: Record<string, number>;
  /** Fair share summed over the hunts the player was in */
  share: number;
}

export interface PayoutTransfer {
  from: string;
  to: string;
  amount: number;
  paid?: boolean;
}

/** Follows alias chains (Alt → Main), case-insensitive, never loops */
export function resolveAlias(name: string, aliases: Record<string, string>): string {
  const lower = new Map(Object.entries(aliases).map(([k, v]) => [k.toLowerCase(), v]));
  let cur = name;
  const seen = new Set<string>();
  while (lower.has(cur.toLowerCase()) && !seen.has(cur.toLowerCase())) {
    seen.add(cur.toLowerCase());
    const next = lower.get(cur.toLowerCase())!;
    if (next.toLowerCase() === cur.toLowerCase()) break;
    cur = next;
  }
  return cur;
}

/** Each member's net in one hunt (exact, before rounding) */
export function huntNets(h: PayoutHunt): { name: string; net: number; share: number }[] {
  const members = balancesWithExpenses(h.members, h.expenses ?? []);
  if (!members.length) return [];
  const share = members.reduce((s, m) => s + m.balance, 0) / members.length;
  return members.map((m) => ({ name: m.name, net: m.balance - share, share }));
}

/** Combined nets per player over many hunts; rounded to whole gold, summing exactly to zero */
export function payoutPlayers(hunts: PayoutHunt[], aliases: Record<string, string> = {}): PayoutPlayer[] {
  const byKey = new Map<string, PayoutPlayer & { exact: number }>();
  for (const h of hunts) {
    for (const m of huntNets(h)) {
      const main = resolveAlias(m.name, aliases);
      const key = main.toLowerCase();
      let p = byKey.get(key);
      if (!p) {
        p = { name: main, chars: [], net: 0, exact: 0, perHunt: {}, share: 0 };
        byKey.set(key, p);
      }
      if (!p.chars.some((c) => c.toLowerCase() === m.name.toLowerCase())) p.chars.push(m.name);
      p.exact += m.net;
      p.share += m.share;
      p.perHunt[h.id] = Math.round((p.perHunt[h.id] ?? 0) + m.net);
    }
  }
  const list = [...byKey.values()];
  for (const p of list) {
    p.net = Math.round(p.exact);
    p.share = Math.round(p.share);
  }
  // Rounding may leave a few gold over: give the difference to the largest net so the sum is exactly zero
  const drift = list.reduce((s, p) => s + p.net, 0);
  if (drift && list.length) {
    const big = list.reduce((a, b) => (Math.abs(b.net) > Math.abs(a.net) ? b : a));
    big.net -= drift;
  }
  return list
    .map(({ exact: _e, ...p }) => p)
    .sort((a, b) => b.net - a.net || a.name.localeCompare(b.name));
}

/**
 * Fewest transfers for the nets: exact matches first (one transfer settles two players), then greedy.
 * `roundTo` (100, 1k, 10k) rounds every amount to the nearest multiple; amounts that round to 0 are dropped.
 */
export function settleNets(players: { name: string; net: number }[], roundTo = 1): PayoutTransfer[] {
  const debtors = players.filter((p) => p.net > 0).map((p) => ({ name: p.name, left: p.net }));
  const creditors = players.filter((p) => p.net < 0).map((p) => ({ name: p.name, left: -p.net }));
  const out: PayoutTransfer[] = [];

  for (const d of debtors) {
    const c = creditors.find((x) => x.left > 0 && x.left === d.left);
    if (c) {
      out.push({ from: d.name, to: c.name, amount: d.left });
      c.left = 0;
      d.left = 0;
    }
  }
  const byLeft = (a: { left: number }, b: { left: number }) => b.left - a.left;
  for (;;) {
    const d = debtors.filter((x) => x.left > 0).sort(byLeft)[0];
    const c = creditors.filter((x) => x.left > 0).sort(byLeft)[0];
    if (!d || !c) break;
    const amount = Math.min(d.left, c.left);
    out.push({ from: d.name, to: c.name, amount });
    d.left -= amount;
    c.left -= amount;
  }

  const step = roundTo > 1 ? roundTo : 1;
  return out
    .map((t) => ({ ...t, amount: Math.round(t.amount / step) * step }))
    .filter((t) => t.amount > 0)
    .sort((a, b) => a.from.localeCompare(b.from) || b.amount - a.amount);
}

/** How many transfers settling every hunt separately would take (to show what merging saves) */
export function separateTransferCount(hunts: PayoutHunt[]): number {
  return hunts.reduce((s, h) => s + computeLootSplit(balancesWithExpenses(h.members, h.expenses ?? [])).transfers.length, 0);
}

/** What the payer says to the bank NPC */
export const transferCommand = (t: Pick<PayoutTransfer, 'amount' | 'to'>): string => `transfer ${t.amount} to ${t.to}`;

/** Transfers grouped by who pays */
export function groupByPayer<T extends PayoutTransfer>(transfers: T[]): { from: string; total: number; items: (T & { index: number })[] }[] {
  const groups = new Map<string, { from: string; total: number; items: (T & { index: number })[] }>();
  transfers.forEach((t, index) => {
    const g = groups.get(t.from) ?? { from: t.from, total: 0, items: [] };
    g.total += t.amount;
    g.items.push({ ...t, index });
    groups.set(t.from, g);
  });
  return [...groups.values()];
}

/** Plain text for Discord / WhatsApp: one block per payer with the bank commands */
export function payoutText(title: string, transfers: PayoutTransfer[], fmt: (n: number) => string): string {
  const lines = [title];
  for (const g of groupByPayer(transfers)) {
    lines.push('', `${g.from} → ${fmt(g.total)}`);
    for (const t of g.items) lines.push(`${t.paid ? '✅' : '▫️'} ${transferCommand(t)}`);
  }
  return lines.join('\n');
}

/** Every character name in the hunts (case-insensitive, first spelling kept), sorted */
export function allPlayerNames(hunts: PayoutHunt[]): string[] {
  const seen = new Map<string, string>();
  for (const h of hunts) for (const m of h.members) if (!seen.has(m.name.toLowerCase())) seen.set(m.name.toLowerCase(), m.name);
  return [...seen.values()].sort((a, b) => a.localeCompare(b));
}
