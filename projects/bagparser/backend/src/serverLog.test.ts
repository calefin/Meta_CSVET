import { describe, expect, it } from 'vitest';
import { sanitizeServerLog } from './serverLog.js';

describe('sanitizeServerLog (D-64)', () => {
  it('keeps the known shape and drops anything else', () => {
    const out = sanitizeServerLog({
      minutes: 60,
      xp: { total: 1000, kills: 10, animus: 0, evil: '<script>' },
      skills: [{ skill: 'magic level', from: '100 (1%)', to: '100 (5%)', onclick: 'x' }],
      dealt: { total: 5, hits: 2, crits: 1, crit_total: 3, by_target: [{ name: { toString: 'x' }, value: 'NaN', pct: 900 }] },
      loot: [{ name: 'Dragon', corpses: 3, empty: 1, items: [{ name: 'Gold Coin', count: 100 }] }],
      hacker: { a: 1 },
    })!;
    expect(out.hacker).toBeUndefined();
    expect((out.xp as Record<string, unknown>).evil).toBeUndefined();
    expect(out.skills).toEqual([{ skill: 'magic level', from: '100 (1%)', to: '100 (5%)' }]);
    expect((out.dealt as { by_target: unknown[] }).by_target).toEqual([{ name: '?', value: 0, pct: 100 }]);
    expect(out.loot).toEqual([{ name: 'Dragon', corpses: 3, empty: 1, items: [{ name: 'Gold Coin', count: 100 }] }]);
    expect(Array.isArray(out.timeline)).toBe(true);
  });
  it('rejects non-objects and bounds lists', () => {
    expect(sanitizeServerLog('x')).toBeNull();
    expect(sanitizeServerLog([1, 2])).toBeNull();
    const big = sanitizeServerLog({ timeline: Array.from({ length: 5000 }, () => ({ t: '10:00', xp: 1 })) })!;
    expect((big.timeline as unknown[]).length).toBe(1500);
  });
});
