import { describe, expect, it } from 'vitest';
import { alertsOf, barWidth, bestHits, creatureSlug, damageDealt, lootByRarity, resistances, worstHits, type SheetTraits } from './creatureView';

const seer = { el: { earth: 105, fire: 85, death: 85, energy: 120, ice: 95 } };
const traits: SheetTraits = { para: true, invis: true, push: false, pushObj: true, illusion: false, summon: null, convince: null, through: null, around: null, runs: 0 };

describe('creature sheet (D-80)', () => {
  it('orders elements from most to least damage and fills the missing ones with 100', () => {
    const rows = resistances(seer);
    expect(rows[0]).toEqual({ element: 'energy', pct: 120, delta: 20 });
    expect(rows.map((r) => r.pct)).toEqual([120, 105, 100, 100, 95, 85, 85]);
  });

  it('picks at most two to hit with and two to avoid, only real differences', () => {
    const rows = resistances(seer);
    expect(bestHits(rows).map((r) => r.element)).toEqual(['energy', 'earth']);
    expect(worstHits(rows).map((r) => r.pct)).toEqual([85, 85]);
    const neutral = resistances({ el: {} });
    expect(bestHits(neutral)).toEqual([]);
    expect(worstHits(neutral)).toEqual([]);
  });

  it('scales bars to the widest difference, with a floor so small ones stay small', () => {
    const rows = resistances(seer);
    expect(barWidth(20, rows)).toBe(100);
    expect(barWidth(5, rows)).toBe(25);
    expect(barWidth(5, resistances({ el: { fire: 105 } }))).toBe(25);
    expect(barWidth(100, resistances({ el: { fire: 0 } }))).toBe(100);
  });

  it('lists damage dealt, largest first, and copes with no data', () => {
    expect(damageDealt({ out: { death: 35, physical: 50 } })).toEqual([['physical', 50], ['death', 35]]);
    expect(damageDealt({ out: null })).toEqual([]);
  });

  it('groups drops by rarity, rarest and most valuable first, without inventing a rarity', () => {
    const groups = lootByRarity(
      [{ i: 'Coin', r: 'common' }, { i: 'Cheap', r: 'rare' }, { i: 'Gem', r: 'rare' }, { i: 'Crystal' }],
      (n) => (n === 'Gem' ? 500 : 1),
    );
    expect(groups.map((g) => g.rarity)).toEqual(['rare', 'common', 'none']);
    expect(groups[0].items.map((x) => x.i)).toEqual(['Gem', 'Cheap']);
  });

  it('warns only about what changes the hunt', () => {
    expect(alertsOf({ tr: traits, ab: [{ n: 'Healing', r: '177-223', k: 'heal' }] })).toEqual([
      { key: 'cr_al_invis' },
      { key: 'cr_al_para' },
      { key: 'cr_al_heal_r', vars: { r: '177-223' } },
    ]);
    expect(alertsOf({ tr: { ...traits, para: false, invis: false }, ab: [] })).toEqual([]);
  });

  it('builds the same slugs as the API', () => {
    expect(creatureSlug('Mitmah Seer')).toBe('mitmah-seer');
    expect(creatureSlug("Dragon's Lord (x)")).toBe('dragons-lord-x');
  });
});
