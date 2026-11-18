import { describe, expect, it } from 'vitest';
import { parseTibiaSession, parseEntry, cleanInt } from './parser';
import { defaultLookupDb, findItem, nameCandidates } from './items';
import { computeLootSplit } from './party';
import { rashidToday, tibiaServerDay, yasirStorageKey } from './npcSchedule';
import { SAMPLE_PARTY_LOG, SAMPLE_SOLO_LOG } from './samples';

describe('cleanInt', () => {
  it('handles separators and negatives', () => {
    expect(cleanInt('1,577,616')).toBe(1577616);
    expect(cleanInt('1.577.616')).toBe(1577616);
    expect(cleanInt('-12,000')).toBe(-12000);
    expect(cleanInt(undefined)).toBe(0);
  });
});

describe('parseEntry', () => {
  it('parses analyser, server-log and article forms', () => {
    expect(parseEntry('4,120x gold coin')).toEqual({ count: 4120, name: 'gold coin' });
    expect(parseEntry('2 platinum coins')).toEqual({ count: 2, name: 'platinum coins' });
    expect(parseEntry('a dragon shield')).toEqual({ count: 1, name: 'dragon shield' });
    expect(parseEntry('an energy ring')).toEqual({ count: 1, name: 'energy ring' });
  });
});

describe('item name normalisation', () => {
  it('maps plurals to catalog singulars', () => {
    expect(findItem('small diamonds', defaultLookupDb)?.name).toBe('small diamond');
    expect(findItem('red dragon scales', defaultLookupDb)?.name).toBe('red dragon scale');
    expect(findItem('pieces of draconian steel', defaultLookupDb)?.name).toBe('piece of draconian steel');
    expect(findItem('vampire teeth', defaultLookupDb)?.name).toBe('vampire teeth');
    expect(findItem('Dragon Shield', defaultLookupDb)?.name).toBe('dragon shield');
  });

  it('builds candidates without articles', () => {
    expect(nameCandidates('a stealth ring')[0]).toBe('stealth ring');
  });
});

describe('parseTibiaSession — solo Hunt Analyser', () => {
  const out = parseTibiaSession(SAMPLE_SOLO_LOG, defaultLookupDb);

  it('reads metrics line-anchored (XP Gain, not Raw XP Gain)', () => {
    expect(out.kind).toBe('solo');
    expect(out.metrics.durationMinutes).toBe(74);
    expect(out.metrics.xpTotal).toBe(1577616);
    expect(out.metrics.lootTotal).toBe(263226);
    expect(out.metrics.suppliesTotal).toBe(120839);
    expect(out.metrics.balanceNet).toBe(142387);
    expect(out.metrics.xpHour).toBe(Math.round((1577616 / 74) * 60));
  });

  it('routes items to destinations', () => {
    const d = out.destinations;
    // Destinations follow TibiaWiki "sellto" (see shared/items.json _comment)
    expect(d['Rashid'].items.map((i) => i.name)).toEqual(['dragon scale mail']);
    expect(d['Blue Djinn'].items.map((i) => i.name)).toEqual(
      expect.arrayContaining(['royal helmet', 'dragon shield', 'stealth ring']),
    );
    expect(d['Blue Djinn'].subtotalGps).toBe(3 * 30000 + 2 * 4000 + 2 * 200);
    expect(d['Other NPCs'].items.find((i) => i.name === 'red dragon scale')?.count).toBe(12);
    expect(d['Other NPCs'].items.map((i) => i.name)).toEqual(expect.arrayContaining(['life crystal']));
    expect(d['Coins'].subtotalGps).toBe(4120 + 37 * 100);
    expect(d['Other NPCs'].items.find((i) => i.name === 'small diamond')?.count).toBe(5);
  });

  it('reports unknown items and killed monsters', () => {
    // strong mana potion is in the TibiaWiki catalog (no NPC buys it → Market bucket)
    expect(out.unrecognizedItems).toEqual([]);
    expect(parseTibiaSession('Looted Items:\n  2x totally fake item\n', defaultLookupDb).unrecognizedItems).toEqual([
      '2x totally fake item',
    ]);
    expect(out.killedMonsters).toEqual([
      { count: 56, name: 'dragon' },
      { count: 9, name: 'dragon lord' },
    ]);
    expect(out.party).toBeNull();
  });

  it('computes total weight as the sum of buckets', () => {
    const sum = Object.values(out.destinations).reduce((s, b) => s + b.subtotalWeightOz, 0);
    expect(out.totalWeightOz).toBeCloseTo(sum, 2);
  });
});

describe('imbuement routing', () => {
  it('sends imbuement items to the market bucket', () => {
    const log = 'Session: 00:30h\nLooted Items:\n  10x swamp grass\n  3x lizard leather\n';
    const out = parseTibiaSession(log, defaultLookupDb);
    expect(out.destinations['Market / Imbuement Alerts'].items[0]).toMatchObject({
      name: 'swamp grass',
      count: 10,
      isImbuement: true,
    });
    expect(out.destinations['Other NPCs'].items[0].name).toBe('lizard leather');
  });

  it('keeps the NPCs that pay full price for each item', () => {
    const out = parseTibiaSession('Looted Items:\n  2x dragon shields\n', defaultLookupDb);
    expect(out.destinations['Blue Djinn'].items[0]).toMatchObject({ name: 'dragon shield', count: 2, sellTo: ["Nah'Bob"] });
  });

  it('defaults missing/zero duration', () => {
    expect(parseTibiaSession('XP Gain: 100', defaultLookupDb).metrics.durationMinutes).toBe(60);
    expect(parseTibiaSession('Session: 00:00h', defaultLookupDb).metrics.durationMinutes).toBe(1);
  });
});

describe('parseTibiaSession — Party Hunt Analyser', () => {
  const out = parseTibiaSession(SAMPLE_PARTY_LOG, defaultLookupDb);

  it('detects party and members', () => {
    expect(out.kind).toBe('party');
    expect(out.metrics.balanceNet).toBe(2909215);
    expect(out.party?.members.map((m) => m.name)).toEqual(['Calefi', 'Mage Friend', 'Healer Druid', 'Paladin Pal']);
    expect(out.party?.members[0].isLeader).toBe(true);
    expect(out.party?.members[1].balance).toBe(109445);
  });

  it('split leaves everyone at the share', () => {
    const { members, split } = out.party!;
    const final = new Map(members.map((m) => [m.name, m.balance]));
    for (const t of split.transfers) {
      final.set(t.from, final.get(t.from)! - t.amount);
      final.set(t.to, final.get(t.to)! + t.amount);
    }
    for (const v of final.values()) expect(Math.abs(v - split.sharePerPlayer)).toBeLessThanOrEqual(members.length);
    expect(split.transfers.length).toBeLessThanOrEqual(members.length - 1);
  });
});

describe('computeLootSplit', () => {
  it('returns no transfers when even', () => {
    expect(computeLootSplit([{ name: 'a', balance: 10 }, { name: 'b', balance: 10 }]).transfers).toEqual([]);
  });
  it('handles negative balances', () => {
    const s = computeLootSplit([{ name: 'a', balance: 300 }, { name: 'b', balance: -100 }]);
    expect(s.sharePerPlayer).toBe(100);
    expect(s.transfers).toEqual([{ from: 'a', to: 'b', amount: 200 }]);
  });
});

describe('npc schedule', () => {
  it('uses 10:00 Berlin as day boundary', () => {
    // 2026-11-03 is a Tuesday. Berlin is UTC+1 in November.
    const before = new Date('2026-11-03T08:59:00Z'); // 09:59 Berlin → still Monday
    const after = new Date('2026-11-03T09:01:00Z'); // 10:01 Berlin → Tuesday
    expect(rashidToday(before).city).toBe('Svargrond');
    expect(rashidToday(after).city).toBe('Liberty Bay');
    expect(tibiaServerDay(after).toISOString().slice(0, 10)).toBe('2026-11-03');
    expect(yasirStorageKey(before)).toBe('bagparser_yasir_2026-11-02');
  });
});
