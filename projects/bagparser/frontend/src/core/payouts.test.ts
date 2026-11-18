import { describe, expect, it } from 'vitest';
import { groupByPayer, huntNets, payoutPlayers, payoutText, resolveAlias, separateTransferCount, settleNets, transferCommand, type PayoutHunt } from './payouts';

const h1: PayoutHunt = {
  id: 'h1',
  members: [
    { name: 'Knight A', balance: 900_000, isLeader: true },
    { name: 'Druid B', balance: 100_000 },
    { name: 'Paladin C', balance: 200_000 },
  ],
  expenses: [],
};
const h2: PayoutHunt = {
  id: 'h2',
  members: [
    { name: 'Druid B', balance: 700_000 },
    { name: 'Knight A', balance: 100_000 },
    { name: 'Sorc D', balance: 400_000 },
  ],
  expenses: [],
};

const sum = (xs: { net: number }[]) => xs.reduce((s, x) => s + x.net, 0);

describe('payouts', () => {
  it('computes each member net in one hunt', () => {
    const n = huntNets(h1);
    expect(n.find((x) => x.name === 'Knight A')?.net).toBe(500_000);
    expect(sum(n)).toBe(0);
  });

  it('adds nets across hunts and settles them with few transfers', () => {
    const players = payoutPlayers([h1, h2]);
    expect(sum(players)).toBe(0);
    // Knight A: +500k in h1, -300k in h2 → pays 200k overall
    expect(players.find((p) => p.name === 'Knight A')?.net).toBe(200_000);
    expect(players.find((p) => p.name === 'Knight A')?.perHunt).toEqual({ h1: 500_000, h2: -300_000 });
    const transfers = settleNets(players);
    expect(transfers.length).toBeLessThanOrEqual(players.length - 1);
    expect(transfers.length).toBeLessThan(separateTransferCount([h1, h2]));
    // Applying the transfers leaves everyone at zero
    const left = new Map(players.map((p) => [p.name, p.net]));
    for (const t of transfers) {
      left.set(t.from, left.get(t.from)! - t.amount);
      left.set(t.to, left.get(t.to)! + t.amount);
    }
    expect([...left.values()].every((v) => v === 0)).toBe(true);
  });

  it('merges characters of the same player', () => {
    const alt: PayoutHunt = { id: 'h3', members: [{ name: 'knight a alt', balance: 0 }, { name: 'Sorc D', balance: 600_000 }], expenses: [] };
    const players = payoutPlayers([h1, alt], { 'Knight A Alt': 'Knight A' });
    const a = players.find((p) => p.name === 'Knight A')!;
    expect(a.chars).toEqual(['Knight A', 'knight a alt']);
    expect(a.net).toBe(500_000 - 300_000);
    expect(players.some((p) => p.name.toLowerCase() === 'knight a alt')).toBe(false);
  });

  it('follows alias chains without looping', () => {
    expect(resolveAlias('C', { c: 'B', b: 'A' })).toBe('A');
    expect(resolveAlias('X', { x: 'Y', y: 'X' })).toBe('X');
  });

  it('uses the extra expenses: whoever paid gets it back', () => {
    const h: PayoutHunt = {
      id: 'e',
      members: [
        { name: 'A', balance: 300_000, isLeader: true },
        { name: 'B', balance: 300_000 },
      ],
      expenses: [{ tc: 0, tc_price: 0, gold: 100_000, reason: 'prey', payer: 'B' }],
    };
    const [t] = settleNets(payoutPlayers([h]));
    expect(t).toEqual({ from: 'A', to: 'B', amount: 50_000 });
  });

  it('matches exact pairs first and rounds amounts', () => {
    const t = settleNets([
      { name: 'P1', net: 300 },
      { name: 'P2', net: 700 },
      { name: 'R1', net: -700 },
      { name: 'R2', net: -300 },
    ]);
    expect(t).toHaveLength(2);
    expect(settleNets([{ name: 'P', net: 123_456 }, { name: 'R', net: -123_456 }], 1000)[0].amount).toBe(123_000);
    expect(settleNets([{ name: 'P', net: 400 }, { name: 'R', net: -400 }], 1000)).toEqual([]);
  });

  it('formats bank commands and the text summary', () => {
    expect(transferCommand({ amount: 150000, to: 'Sir Bob' })).toBe('transfer 150000 to Sir Bob');
    const tr = [
      { from: 'A', to: 'B', amount: 10, paid: true },
      { from: 'A', to: 'C', amount: 5 },
    ];
    expect(groupByPayer(tr)[0]).toMatchObject({ from: 'A', total: 15 });
    expect(payoutText('W45', tr, String)).toBe('W45\n\nA → 15\n✅ transfer 10 to B\n▫️ transfer 5 to C');
  });
});
