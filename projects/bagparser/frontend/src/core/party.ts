/** FR-CORE-13: equal loot split for Party Hunt Analyser logs. */

export interface Transfer {
  from: string;
  to: string;
  amount: number;
}

export interface LootSplit {
  /** Profit each player should end up with */
  sharePerPlayer: number;
  transfers: Transfer[];
}

/**
 * Each player's balance (loot − supplies) is what they currently "hold".
 * Everyone should end with totalBalance / n; players above the share pay those below.
 * Greedy matching gives at most n−1 transfers.
 */
export function computeLootSplit(members: { name: string; balance: number }[]): LootSplit {
  const n = members.length;
  if (n === 0) return { sharePerPlayer: 0, transfers: [] };

  const total = members.reduce((s, m) => s + m.balance, 0);
  const share = Math.floor(total / n);

  const debtors = members
    .map((m) => ({ name: m.name, amount: m.balance - share }))
    .filter((m) => m.amount > 0)
    .sort((a, b) => b.amount - a.amount);
  const creditors = members
    .map((m) => ({ name: m.name, amount: share - m.balance }))
    .filter((m) => m.amount > 0)
    .sort((a, b) => b.amount - a.amount);

  const transfers: Transfer[] = [];
  let i = 0;
  let j = 0;
  while (i < debtors.length && j < creditors.length) {
    const amount = Math.min(debtors[i].amount, creditors[j].amount);
    if (amount > 0) transfers.push({ from: debtors[i].name, to: creditors[j].name, amount });
    debtors[i].amount -= amount;
    creditors[j].amount -= amount;
    if (debtors[i].amount === 0) i++;
    if (creditors[j].amount === 0) j++;
  }

  return { sharePerPlayer: share, transfers };
}
