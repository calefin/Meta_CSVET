import { describe, expect, it } from 'vitest';
import { fillMinutes, smooth } from './timeline';

describe('fillMinutes', () => {
  it('fills the gaps with zeros', () => {
    const r = fillMinutes([
      { t: '10:00', xp: 100, dealt: 1000, taken: 50 },
      { t: '10:03', xp: 300, dealt: 2000, taken: 0 },
    ]);
    expect(r.map((p) => p.t)).toEqual(['10:00', '10:01', '10:02', '10:03']);
    expect(r.map((p) => p.xp)).toEqual([100, 0, 0, 300]);
    expect(r[3].i).toBe(3);
  });
  it('crosses midnight', () => {
    const r = fillMinutes([
      { t: '23:58', xp: 1, dealt: 1, taken: 1 },
      { t: '00:01', xp: 2, dealt: 2, taken: 2 },
    ]);
    expect(r.map((p) => p.t)).toEqual(['23:58', '23:59', '00:00', '00:01']);
  });
  it('ignores bad labels and empty input', () => {
    expect(fillMinutes([])).toEqual([]);
    expect(fillMinutes([{ t: 'x', xp: 1, dealt: 1, taken: 1 }])).toEqual([]);
  });
});

describe('smooth', () => {
  it('centered moving average', () => {
    expect(smooth([0, 3, 0, 3, 0], 3)).toEqual([1.5, 1, 2, 1, 1.5]);
    expect(smooth([1, 2], 1)).toEqual([1, 2]);
  });
});
