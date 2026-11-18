import { describe, expect, it } from 'vitest';
import { validatePaidFlags, validatePayout } from './payouts.js';

const base = {
  name: 'Semana 45',
  hunt_ids: ['abcDEF12', 'abcDEF12', 'xyz_9876'],
  aliases: { 'Bob Alt': 'Sir Bob' },
  round_to: 1000,
  players: [
    { name: 'Sir Bob', chars: ['Sir Bob', 'Bob Alt'], net: -150000 },
    { name: 'Elder Himanen', net: 150000 },
  ],
  transfers: [{ from: 'Elder Himanen', to: 'Sir Bob', amount: 150000 }],
  total: 2400000,
};

describe('validatePayout', () => {
  it('accepts a distribution and removes repeated hunts', () => {
    const p = validatePayout(base);
    expect(p?.hunt_ids).toEqual(['abcDEF12', 'xyz_9876']);
    expect(p?.transfers[0]).toEqual({ from: 'Elder Himanen', to: 'Sir Bob', amount: 150000, paid: false });
    expect(p?.players[1].chars).toEqual([]);
  });

  it('rejects bad shapes', () => {
    expect(validatePayout({ ...base, name: '  ' })).toBeNull();
    expect(validatePayout({ ...base, hunt_ids: [] })).toBeNull();
    expect(validatePayout({ ...base, hunt_ids: ['../etc'] })).toBeNull();
    expect(validatePayout({ ...base, round_to: 7 })).toBeNull();
    expect(validatePayout({ ...base, transfers: [{ from: 'A', to: 'a', amount: 10 }] })).toBeNull();
    expect(validatePayout({ ...base, transfers: [{ from: 'A', to: 'B', amount: 0 }] })).toBeNull();
    expect(validatePayout({ ...base, transfers: [{ from: 'A', to: 'B', amount: 1.5 }] })).toBeNull();
    expect(validatePayout({ ...base, players: [{ name: 'A', net: 1e13 }] })).toBeNull();
    expect(validatePayout({ ...base, aliases: ['x'] })).toBeNull();
  });

  it('checks paid flags against the saved transfers', () => {
    expect(validatePaidFlags([true, false], 2)).toEqual([true, false]);
    expect(validatePaidFlags([true], 2)).toBeNull();
    expect(validatePaidFlags(['yes', false], 2)).toBeNull();
  });
});
