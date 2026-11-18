import { bossSeedRows } from './bossLog.js';
import { describe, expect, it } from 'vitest';
import { parseBossLogLine, payloadHash, playerFingerprint, utcHourKey } from './bossLog.js';
import { nameCandidates } from './normalize.js';
import { spawnScore } from './spawnrank.js';
import { raceKey, sameName, secondsUntilServerSave } from './tibiadata.js';

describe('parseBossLogLine', () => {
  it('parses a real server log line with timestamp and plurals', () => {
    const p = parseBossLogLine("12:34:56 Loot of Ferumbras: a ferumbras' hat, 2 platinum coins, 3x small diamond.");
    expect(p).toEqual({
      bossName: 'Ferumbras',
      drops: [
        { count: 1, name: "ferumbras' hat" },
        { count: 2, name: 'platinum coins' },
        { count: 3, name: 'small diamond' },
      ],
    });
  });

  it('handles (Double bonus) and server log brackets', () => {
    const p = parseBossLogLine('[2026-11-04 18:32:00] Loot of Scarlett Etzel: a cobra sword (Double bonus), 15 platinum coins.');
    expect(p).toEqual({
      bossName: 'Scarlett Etzel',
      drops: [
        { count: 1, name: 'cobra sword', doubleBonus: true },
        { count: 15, name: 'platinum coins' },
      ],
    });
  });

  it('handles multi-word bosses, "nothing" and invalid lines', () => {
    expect(parseBossLogLine('Loot of The Old Widow: nothing')).toEqual({ bossName: 'The Old Widow', drops: [] });
    expect(parseBossLogLine('You see a dragon.')).toBeNull();
  });
});

describe('payloadHash', () => {
  const at = new Date('2026-11-03T14:20:00Z');
  it('is order-independent and hour-bucketed', () => {
    const a = parseBossLogLine('Loot of Ferumbras: a gold coin, 2 platinum coins')!;
    const b = parseBossLogLine('Loot of Ferumbras: 2 platinum coins, a gold coin')!;
    expect(payloadHash(a, at)).toBe(payloadHash(b, at));
    expect(payloadHash(a, at)).not.toBe(payloadHash(a, new Date('2026-11-03T15:01:00Z')));
    expect(utcHourKey(at)).toBe('2026-11-03-14');
  });
});

describe('helpers', () => {
  it('fingerprint is salted', () => {
    expect(playerFingerprint('1.2.3.4', 'ua', 'a')).not.toBe(playerFingerprint('1.2.3.4', 'ua', 'b'));
  });
  it('name candidates singularise', () => {
    expect(nameCandidates('platinum coins')).toContain('platinum coin');
  });
  it('spawn score follows the spec formula', () => {
    expect(spawnScore({ density: 4, synergy: 2, profitability: 3, accessFriction: 1 })).toBe(2.85);
    expect(spawnScore({ density: 9, synergy: 9, profitability: 9, accessFriction: -1 })).toBe(4.5);
  });
  it('tibiadata name matching', () => {
    expect(raceKey('Dragon Lord')).toBe('dragonlord');
    expect(sameName('The Brainstealer', 'the brainstealer')).toBe(true);
    const s = secondsUntilServerSave(new Date('2026-11-03T08:00:00Z')); // 09:00 Berlin
    expect(s).toBe(3600 + 120);
  });
});

describe('boss catalogue (D-80)', () => {
  it('keeps only bosses and rounds cooldowns up to whole hours', () => {
    expect(
      bossSeedRows([
        { name: 'Rat', boss: false },
        { name: 'A', boss: true, cd: 20 },
        { name: 'B', boss: true, cd: 0.28 },
        { name: 'C', boss: true, cd: 0 },
        { name: 'D', boss: true },
      ]),
    ).toEqual([
      { name: 'A', hours: 20 },
      { name: 'B', hours: 1 },
      { name: 'C', hours: null },
      { name: 'D', hours: null },
    ]);
  });
});
