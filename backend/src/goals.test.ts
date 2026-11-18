import { describe, expect, it } from 'vitest';
import { goalProgress, weekStart } from './goals.js';

describe('weekly goals', () => {
  it('finds the Monday of the week', () => {
    expect(weekStart(new Date('2026-11-06T12:00:00Z'))).toBe('2026-11-02'); // Friday
    expect(weekStart(new Date('2026-11-02T00:00:00Z'))).toBe('2026-11-02'); // Monday
    expect(weekStart(new Date('2026-11-08T23:59:00Z'))).toBe('2026-11-02'); // Sunday
  });

  it('counts the streak of reached weeks', () => {
    const now = new Date('2026-11-06T12:00:00Z');
    const weeks = [
      { week: '2026-10-05', value: 100 },
      { week: '2026-10-12', value: 10 }, // missed: breaks the older run
      { week: '2026-10-19', value: 120 },
      { week: '2026-10-26', value: 100 },
      { week: '2026-11-02', value: 40 }, // current week, not reached yet
    ];
    const p = goalProgress(weeks, 100, now);
    expect(p.current_week).toBe('2026-11-02');
    expect(p.current).toBe(40);
    expect(p.pct).toBe(40);
    expect(p.reached).toBe(false);
    expect(p.streak).toBe(2); // the two previous weeks still count while this one is open
    expect(p.best_streak).toBe(2);
    expect(p.history).toHaveLength(12);
    expect(p.history[11]).toEqual({ week: '2026-11-02', value: 40, reached: false });
  });

  it('adds the current week once it is reached and resets after a missed week', () => {
    const now = new Date('2026-11-06T12:00:00Z');
    expect(goalProgress([{ week: '2026-10-26', value: 200 }, { week: '2026-11-02', value: 200 }], 100, now).streak).toBe(2);
    expect(goalProgress([{ week: '2026-10-19', value: 200 }, { week: '2026-11-02', value: 50 }], 100, now).streak).toBe(0);
    expect(goalProgress([], 100, now)).toMatchObject({ streak: 0, best_streak: 0, pct: 0 });
  });
});
