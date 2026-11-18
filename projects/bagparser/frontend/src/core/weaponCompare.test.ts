import { describe, expect, it } from 'vitest';
import { compareWeapons, MIN_HITS, weaponKey, type WeaponHunt } from './weaponCompare';

const hunt = (w: string, tier: number, total: number, hits: number, crits: number, critTotal: number, minutes: number, place = 'cobra-bastion', damage_h = 1e6): WeaponHunt => ({
  public_id: Math.random().toString(36).slice(2),
  place_name: 'Cobra Bastion',
  place_slug: place,
  character_name: 'Knight A',
  duration_s: 3600,
  damage: damage_h,
  damage_h,
  weapon: { name: w, tier, imbues: [{ type: 'strike', tier: 3 }] },
  dealt: { total, hits, crits, crit_total: critTotal, by_target: [] },
  combat_minutes: minutes,
});

describe('weapon comparison (D-72)', () => {
  it('compares damage per minute in combat, per hit and crits', () => {
    const list = [
      hunt('Falcon Longsword', 2, 600_000, 1200, 120, 90_000, 50),
      hunt('Falcon Longsword', 2, 550_000, 1100, 110, 82_000, 48),
      hunt('Soulcutter', 1, 720_000, 1150, 115, 110_000, 50),
    ];
    const r = compareWeapons(list);
    expect(r[0].label).toBe('Falcon Longsword T2 (strike III)'); // most used = reference
    expect(r[0].hunts).toBe(2);
    expect(r[0].perCombatMin).toBe(Math.round(1_150_000 / 98));
    expect(r[0].perHit).toBe(Math.round(1_150_000 / 2300));
    expect(r[0].critRate).toBe(10);
    expect(r[0].diff).toBe(0);
    const soul = r.find((w) => w.label.startsWith('Soulcutter'))!;
    expect(soul.diff).toBeGreaterThan(20); // 14,400/min vs 11,735/min
    expect(soul.enoughData).toBe(1150 >= MIN_HITS);
    expect(soul.critBonus).toBeGreaterThan(0);
  });

  it('filters by place and falls back to damage/h without Server Log', () => {
    const noLog = (w: string, dh: number): WeaponHunt => ({ ...hunt(w, 0, 0, 0, 0, 0, 0, 'issavi', dh), dealt: null });
    const r = compareWeapons([noLog('Axe A', 1_000_000), noLog('Axe A', 1_000_000), noLog('Axe B', 1_200_000), hunt('Axe A', 0, 1, 1, 0, 0, 1)], 'issavi');
    expect(r.map((w) => w.hunts)).toEqual([2, 1]);
    expect(r[0].perCombatMin).toBeNull();
    expect(r[1].diff).toBe(20);
    expect(weaponKey({ name: 'Axe', imbues: [{ type: 'b', tier: 1 }, { type: 'a', tier: 3 }] })).toBe('axe|0|a3+b1');
  });
});
