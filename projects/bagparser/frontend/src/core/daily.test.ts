import { describe, expect, it } from 'vitest';
import { berlinOffsetHours, countdownParts, dreamScarBoss, dromeRotation, DREAM_BOSSES } from './daily';

describe('dromeRotation', () => {
  it('ends on a Wednesday at server save (10:00 Berlin)', () => {
    const r = dromeRotation(Date.UTC(2026, 10, 4, 12));
    const end = new Date(r.endsAt);
    expect(end.getUTCDay()).toBe(3); // Wednesday
    // 10:00 CET in winter = 09:00 UTC
    expect(end.getUTCHours()).toBe(9);
    expect(r.endsAt).toBe(Date.UTC(2026, 10, 11, 9));
    expect(r.endsAt).toBeGreaterThan(Date.UTC(2026, 10, 4, 12));
  });
  it('first rotation starts at the epoch', () => {
    expect(dromeRotation(Date.UTC(2021, 6, 15)).number).toBe(1);
  });
});

describe('dreamScarBoss', () => {
  it('changes exactly at server save and cycles through 5 bosses', () => {
    const before = Date.UTC(2026, 10, 4, 8, 59); // 09:59 CET
    const after = Date.UTC(2026, 10, 4, 9, 1); // 10:01 CET
    const a = DREAM_BOSSES.indexOf(dreamScarBoss(0, before));
    const b = DREAM_BOSSES.indexOf(dreamScarBoss(0, after));
    expect((a + 1) % 5).toBe(b);
    expect(dreamScarBoss(0, after + 5 * 86_400_000)).toBe(dreamScarBoss(0, after));
  });
  it('world offsets shift the rotation', () => {
    const now = Date.UTC(2026, 10, 4, 12);
    const i = DREAM_BOSSES.indexOf(dreamScarBoss(0, now));
    expect(dreamScarBoss(2, now)).toBe(DREAM_BOSSES[(i + 2) % 5]);
  });
});

describe('helpers', () => {
  it('Berlin offset is 1 in winter and 2 in summer', () => {
    expect(berlinOffsetHours(Date.UTC(2026, 0, 15))).toBe(1);
    expect(berlinOffsetHours(Date.UTC(2026, 6, 15))).toBe(2);
  });
  it('countdown parts', () => {
    expect(countdownParts((2 * 1440 + 3 * 60 + 7) * 60_000)).toEqual({ d: 2, h: 3, m: 7 });
  });
});
