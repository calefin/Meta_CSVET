import { describe, expect, it } from 'vitest';
import { baseWeight, outcomeOf, outcomes, reputations, stats, type YasirReport } from './yasir.js';

const OLD = new Date('2026-01-01T00:00:00Z');
let nextUser = 1;
function rep(p: Partial<YasirReport> & Pick<YasirReport, 'day'>): YasirReport {
  return {
    world: 'Antica',
    userId: nextUser++,
    present: true,
    city: 'Carlin',
    reportedAt: new Date(`${p.day}T12:00:00Z`),
    accountCreatedAt: OLD,
    verifiedOnWorld: false,
    verifiedAnywhere: false,
    blocked: false,
    ...p,
  };
}

describe('Yasir sightings (D-40)', () => {
  it('weights reporters by trust', () => {
    const day = '2026-11-04';
    expect(baseWeight(rep({ day, verifiedOnWorld: true }))).toBe(2);
    expect(baseWeight(rep({ day, verifiedAnywhere: true }))).toBe(1);
    expect(baseWeight(rep({ day }))).toBe(0.75);
    expect(baseWeight(rep({ day, accountCreatedAt: new Date('2026-11-03T00:00:00Z') }))).toBe(0.25);
    expect(baseWeight(rep({ day, verifiedOnWorld: true, blocked: true }))).toBe(0);
  });

  it('confirms with one trusted report or enough agreeing ones', () => {
    const day = '2026-11-04';
    expect(outcomeOf([rep({ day, verifiedOnWorld: true })], baseWeight).status).toBe('confirmed');
    expect(outcomeOf([rep({ day })], baseWeight).status).toBe('unconfirmed');
    expect(outcomeOf([rep({ day }), rep({ day }), rep({ day })], baseWeight)).toMatchObject({
      status: 'confirmed',
      city: 'Carlin',
      support: 2.25,
    });
  });

  it('marks disagreement as contested and lone denials as absent', () => {
    const day = '2026-11-04';
    const mixed = [rep({ day }), rep({ day }), rep({ day }), rep({ day, city: 'Ankrahmun' }), rep({ day, present: false, city: null })];
    expect(outcomeOf(mixed, baseWeight).status).toBe('contested');
    expect(outcomeOf([rep({ day, present: false, city: null })], baseWeight).status).toBe('absent');
  });

  it('a liar loses weight on later days', () => {
    const liar = 999;
    const past: YasirReport[] = [];
    for (const day of ['2026-11-01', '2026-11-02', '2026-11-03']) {
      past.push(rep({ day, verifiedOnWorld: true }), rep({ day, userId: liar, city: 'Ankrahmun', verifiedOnWorld: true }));
      past.push(rep({ day, verifiedOnWorld: true }), rep({ day, verifiedOnWorld: true }));
    }
    expect(reputations(past, '2026-11-04').get(liar)).toBe(0.25);
    // Today the liar alone, even with a verified character, no longer confirms
    const today = [...past, rep({ day: '2026-11-04', userId: liar, city: 'Ankrahmun', verifiedOnWorld: true })];
    expect(outcomes(today, '2026-11-04').find((o) => o.day === '2026-11-04')?.status).toBe('unconfirmed');
  });

  it('builds patterns from confirmed days only', () => {
    const list = outcomes(
      [
        rep({ day: '2026-11-02', verifiedOnWorld: true }), // Monday
        rep({ day: '2026-11-09', verifiedOnWorld: true, city: 'Liberty Bay' }), // Monday
        rep({ day: '2026-11-05', world: 'Bona' }), // unconfirmed
      ],
      '2026-11-10',
    );
    const s = stats(list);
    expect(s.confirmedDays).toBe(2);
    expect(s.byWorld).toEqual([{ world: 'Antica', days: 2, lastDay: '2026-11-09' }]);
    expect(s.byCity).toEqual({ Carlin: 1, 'Liberty Bay': 1 });
    expect(s.byWeekday[0]).toBe(2);
    expect(s.avgGapDays).toEqual([{ world: 'Antica', days: 7 }]);
  });
});
