import { describe, expect, it } from 'vitest';
import { commentHasCode, expForLevel, findExperience, newVerificationCode, normalizeCharName, parseCharacter } from './characters.js';

describe('expForLevel', () => {
  it('matches the official table', () => {
    expect(expForLevel(1)).toBe(0);
    expect(expForLevel(2)).toBe(100);
    expect(expForLevel(8)).toBe(4200);
    expect(expForLevel(100)).toBe(15694800);
    expect(expForLevel(400)).toBe(1050779800);
  });
});

describe('verification code', () => {
  it('has the BP- prefix and no ambiguous letters', () => {
    const code = newVerificationCode();
    expect(code).toMatch(/^BP-[A-HJ-NP-Z2-9]{8}$/);
  });
  it('is found in the comment ignoring case and spaces', () => {
    expect(commentHasCode('My char\nbp-7kq2 m9xa  hello', 'BP-7KQ2M9XA')).toBe(true);
    expect(commentHasCode('My char: BP 7KQ2M9XA', 'BP-7KQ2M9XA')).toBe(true);
    expect(commentHasCode('BP7KQ2M9XA', 'BP-7KQ2M9XA')).toBe(true);
    expect(commentHasCode('BP-7KQ2M9XB', 'BP-7KQ2M9XA')).toBe(false);
    expect(commentHasCode(null, 'BP-7KQ2M9XA')).toBe(false);
  });
});

describe('normalizeCharName', () => {
  it('accepts Tibia names and collapses spaces', () => {
    expect(normalizeCharName('  Arthur   Kamikaze ')).toBe('Arthur Kamikaze');
    expect(normalizeCharName("Bubble O'Neil")).toBe("Bubble O'Neil");
    expect(normalizeCharName('x')).toBeNull();
    expect(normalizeCharName('Name1')).toBeNull();
  });
});

describe('findExperience', () => {
  // 20 pages × 50 entries, levels descending from 1500 by 1 per entry
  const pages = Array.from({ length: 20 }, (_, p) =>
    Array.from({ length: 50 }, (_, i) => {
      const rank = p * 50 + i;
      return { name: `Player ${rank}`, level: 1500 - rank, value: expForLevel(1500 - rank) + 7 };
    }),
  );
  const load = async (page: number) => ({ entries: pages[page - 1] ?? [], totalPages: 20 });

  it('finds a character with few page loads', async () => {
    let calls = 0;
    const counted = async (p: number) => {
      calls++;
      return load(p);
    };
    expect(await findExperience('player 777', 1500 - 777, counted)).toBe(expForLevel(723) + 7);
    expect(calls).toBeLessThanOrEqual(6);
  });
  it('returns null below the last listed level', async () => {
    expect(await findExperience('Someone', 300, load)).toBeNull();
  });
  it('returns null when the level is listed but the name is not', async () => {
    expect(await findExperience('Ghost', 1200, load)).toBeNull();
  });
});

describe('parseCharacter', () => {
  it('reads the TibiaData v4 shape', () => {
    const info = parseCharacter({
      character: {
        character: { name: 'Bubble', level: 273, vocation: 'Knight', world: 'Refugia', comment: 'hi', guild: { name: 'G' } },
        deaths: [{ time: '2026-10-31T00:56:35Z', level: 272, reason: 'Died at Level 272 by a dragon.' }],
      },
    });
    expect(info?.name).toBe('Bubble');
    expect(info?.guild).toBe('G');
    expect(info?.deaths).toHaveLength(1);
    expect(parseCharacter({ character: { character: { name: '' } } })).toBeNull();
  });
});

import { parseDreamOffsets, serverDay } from './dailyRoutes.js';

describe('daily helpers', () => {
  it('parses Dream Scar offsets from the wiki template', () => {
    const r = parseDreamOffsets('{{#expr:4+{{#switch:{{{1}}}\n|Aethera=0 <!-- Uncertain -->\n|Antica=1\n}}}}');
    expect(r.general).toBe(4);
    expect(r.worlds.Antica).toEqual({ offset: 1, uncertain: false });
    expect(r.worlds.Aethera.uncertain).toBe(true);
  });
  it('server day starts at 10:00 Berlin', () => {
    expect(serverDay(new Date('2026-11-04T08:59:00Z'))).toBe('2026-11-03'); // 09:59 CET
    expect(serverDay(new Date('2026-11-04T09:01:00Z'))).toBe('2026-11-04');
  });
});

import { parseDroppedBy, weekStart } from './weeklyRoutes.js';

describe('weekly helpers', () => {
  it('week starts on Monday of the server week', () => {
    expect(weekStart('2026-11-04')).toBe('2026-11-02'); // Wednesday → Monday
    expect(weekStart('2026-11-02')).toBe('2026-11-02');
    expect(weekStart('2026-11-08')).toBe('2026-11-02'); // Sunday
  });
  it('reads the Dropped By template', () => {
    expect(parseDroppedBy('| droppedby = {{Dropped By|Dragon|Grand Mother Foulscale|Tazhadur}}\n')).toEqual([
      'Dragon',
      'Grand Mother Foulscale',
      'Tazhadur',
    ]);
    expect(parseDroppedBy('no template')).toEqual([]);
  });
});

import { parseLootRate } from './weeklyRoutes.js';

describe('parseLootRate', () => {
  it('computes times / kills for the item', () => {
    const page = '{{Loot2\n|kills=16829\n|name=Dragon\n|Gold Coin, times:15147, amount:1-104\n|Dragon Ham, times:10967, amount:1-2\n}}';
    expect(parseLootRate(page, 'Dragon Ham')!.rate).toBeCloseTo(10967 / 16829);
    expect(parseLootRate(page, 'dragon shield')).toEqual({ rate: 0, kills: 16829 });
    expect(parseLootRate('no stats', 'x')).toBeNull();
  });
});
