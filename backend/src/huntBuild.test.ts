import { describe, expect, it } from 'vitest';
import { friendIds, validateBuild, validateExpenses, validateSet, validateTemplate, validateWod } from './huntBuild.js';
import { validateHunt } from './hunts.js';

describe('validateExpenses', () => {
  it('sums Tibia Coins × price and gold, skipping empty rows', () => {
    const r = validateExpenses([
      { tc: 25, tc_price: 38000, reason: '1x Prey wildcard', payer: 'Elder Himanen' },
      { gold: 150000, reason: 'blessings' },
      { tc: 0, gold: 0 },
    ]);
    expect(r?.total).toBe(25 * 38000 + 150000);
    expect(r?.rows).toHaveLength(2);
    expect(r?.rows[0].payer).toBe('Elder Himanen');
  });

  it('rejects broken input', () => {
    expect(validateExpenses([{ tc: -1 }])).toBeNull();
    expect(validateExpenses([{ tc: 1.5 }])).toBeNull();
    expect(validateExpenses(new Array(11).fill({ gold: 1 }))).toBeNull();
    expect(validateExpenses('x')).toBeNull();
    expect(validateExpenses(undefined)).toEqual({ rows: [], total: 0 });
  });
});

describe('validateSet / validateWod', () => {
  it('keeps known slots and imbuements only', () => {
    const s = validateSet({
      slots: {
        helmet: { name: 'Zaoan Helmet', imbues: [{ type: 'Void', tier: 3 }, { type: 'hack', tier: 3 }] },
        weapon: { name: 'Soulcutter', tier: 2, imbues: [{ type: 'strike', tier: 4 }] },
        cape: { name: 'x' },
      },
      consumables: ['Bullseye Potion', 'Bullseye Potion', ''],
    });
    expect(s?.slots.helmet).toEqual({ name: 'Zaoan Helmet', imbues: [{ type: 'void', tier: 3 }] });
    expect(s?.slots.weapon).toEqual({ name: 'Soulcutter', tier: 2 });
    expect(Object.keys(s?.slots ?? {})).toEqual(['helmet', 'weapon']);
    expect(s?.consumables).toEqual(['Bullseye Potion']);
  });

  it('validates the wheel', () => {
    const w = validateWod({ code: 'D0Y2BgYJh2Akh4pwA', revelations: { 'Gift of Life': 2, Bad: 9 }, points: { green: 50, red: 2000 } });
    expect(w?.code).toBe('D0Y2BgYJh2Akh4pwA');
    expect(w?.revelations).toEqual({ 'Gift of Life': 2 });
    expect(w?.points).toEqual({ green: 50 });
    expect(validateWod({ code: '<script>' })?.code).toBeNull();
  });
});

describe('validateBuild', () => {
  it('normalises charms, preys and stances', () => {
    const b = validateBuild({
      charms: [
        { name: 'Savage Blow', kind: 'major', tier: 3, creature: 'Wardragon' },
        { name: 'Savage Blow', kind: 'major', tier: 1 },
        { name: 'Gut', kind: 'minor', tier: 2 },
        { name: 'Bad', tier: 5 },
      ],
      preys: [{ type: 'xp', grade: 10, creature: 'Dragolisk' }, { type: 'nope', grade: 1 }],
      stances: ['Blood Rage', 'Protector', 'Third'],
    });
    expect(b?.charms).toEqual([
      { name: 'Savage Blow', kind: 'major', tier: 3, creature: 'wardragon' },
      { name: 'Gut', kind: 'minor', tier: 2, creature: null },
    ]);
    expect(b?.preys).toEqual([{ type: 'xp', grade: 10, creature: 'dragolisk' }]);
    expect(b?.stances).toEqual(['Blood Rage', 'Protector']);
  });

  it('returns null for an empty build', () => {
    expect(validateBuild({})).toBeNull();
    expect(validateBuild(null)).toBeNull();
  });
});

describe('templates, friends and hunts v2', () => {
  it('validates a template', () => {
    const t = validateTemplate({ description: 'Wardragon +1', place_slug: 'bad slug!', expenses: [{ gold: 10 }], shared_with: [3, 3, 'x', 4] });
    expect(t?.place_slug).toBeNull();
    expect(t?.expenses).toHaveLength(1);
    expect(t?.shared_with).toEqual([3, 4]);
    expect(validateTemplate({ expenses: [{ tc: -1 }] })).toBeNull();
  });

  it('dedupes friend ids', () => {
    expect(friendIds([1, '2', 2, 0, -1, 'a'])).toEqual([1, 2]);
  });

  it('carries title, description, expenses and build in a hunt', () => {
    const h = validateHunt({
      kind: 'solo',
      duration_s: 3600,
      xp: 1000,
      monsters: [],
      items: [],
      title: '  Wardragon +1  ',
      description: 'line 1\nline 2',
      expenses: [{ tc: 25, tc_price: 38000 }],
      build: { stances: ['Blood Rage'] },
      shared_with: [5],
    });
    if ('error' in h) throw new Error(h.error);
    expect(h.title).toBe('Wardragon +1');
    expect(h.description).toBe('line 1\nline 2');
    expect(h.extra_expenses).toBe(950000);
    expect(h.build?.stances).toEqual(['Blood Rage']);
    expect(h.shared_with).toEqual([5]);
    expect(validateHunt({ kind: 'solo', duration_s: 3600, xp: 1, expenses: [{ tc: -5 }] })).toEqual({ error: 'invalid_hunt', field: 'expenses' });
  });
});
