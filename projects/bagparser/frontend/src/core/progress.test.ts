import { describe, expect, it } from 'vitest';
import { dailyRates, expForLevel, fractionalLevel, goalReport, paceOver, startOfWeek, suggestions, type Snap } from './progress';

const DAY = 86_400_000;
const now = new Date('2026-11-04T12:00:00').getTime(); // Wednesday
// 30 days of history, 2,000,000 XP per day, starting at level 350
const base = expForLevel(350);
const snaps: Snap[] = Array.from({ length: 31 }, (_, i) => ({
  t: now - (30 - i) * DAY,
  level: 350,
  exp: base + i * 2_000_000,
}));

describe('levels', () => {
  it('fractional level sits between levels', () => {
    const mid = (expForLevel(350) + expForLevel(351)) / 2;
    expect(fractionalLevel(350, mid)).toBeCloseTo(350.5, 2);
    expect(fractionalLevel(350, expForLevel(350))).toBe(350);
  });
});

describe('pace', () => {
  it('averages XP per day over a window', () => {
    expect(paceOver(snaps, 7, now)).toBeCloseTo(2_000_000);
    expect(paceOver(snaps, 30, now)).toBeCloseTo(2_000_000);
  });
  it('needs at least 12 hours of history', () => {
    expect(paceOver([snaps[0]], 7, now)).toBeNull();
    expect(paceOver([{ ...snaps[30], t: now - 3600_000 }, snaps[30]], 7, now)).toBeNull();
  });
  it('spreads gains over gaps for the bar chart', () => {
    const rates = dailyRates([snaps[0], snaps[3], snaps[4]]);
    expect(rates).toHaveLength(2);
    expect(rates[0]).toMatchObject({ xp: 2_000_000, days: 3 });
  });
});

describe('goalReport', () => {
  const goal = {
    target_level: 400,
    target_date: null,
    start_level: 350,
    start_exp: base,
    created_at: new Date(now - 30 * DAY).toISOString(),
    achieved_at: null,
  };
  it('computes remaining XP and ETA from the recent pace', () => {
    const r = goalReport(goal, snaps, now)!;
    const remaining = expForLevel(400) - (base + 60_000_000);
    expect(r.remaining).toBe(remaining);
    expect(r.etaDays).toBeCloseTo(remaining / 2_000_000, 3);
    expect(r.onTrack).toBeNull(); // no deadline
    expect(r.week.target).toBeCloseTo(14_000_000);
    // Monday 00:00 → Wednesday 12:00: last snapshot before Monday is Sunday noon → 3 days of gains
    expect(r.week.gained).toBe(6_000_000);
  });
  it('flags a deadline that the pace cannot meet', () => {
    const r = goalReport({ ...goal, target_date: '2026-12-31' }, snaps, now)!;
    expect(r.onTrack).toBe(false);
    expect(r.requiredPerDay!).toBeGreaterThan(2_000_000);
    const tips = suggestions(r, snaps, [{ time: new Date(now - 2 * DAY).toISOString(), level: 360 }], now).map((s) => s.key);
    expect(tips).toContain('sug_behind');
    expect(tips).toContain('sug_deaths');
  });
  it('weeks start on Monday', () => {
    expect(new Date(startOfWeek(now)).getDay()).toBe(1);
  });
});
