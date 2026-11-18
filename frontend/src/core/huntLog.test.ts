import { describe, expect, it } from 'vitest';
import {
  analysePaste,
  compareLoot,
  huntInsights,
  parseHuntJson,
  parseHuntText,
  parseInputAnalyser,
  parseServerLog,
  serverLogSegments,
  stripChatPrefixes,
} from './huntLog';
import { SAMPLE_PARTY_LOG } from './samples';

// Real files from the Tibia client, bundled as text by Vite
const SAMPLES = import.meta.glob('../../../docs/samples/*.{txt,json}', { query: '?raw', import: 'default', eager: true }) as Record<string, string>;
const sample = (name: string) => SAMPLES[`../../../docs/samples/${name}`];

describe('Hunting Session Analyser', () => {
  it('parses the copied text with exact seconds from the session range', () => {
    const h = parseHuntText(sample('Hunt_analyser_copiado_do_client2.txt'))!;
    expect(h.kind).toBe('solo');
    expect(h.started_at).toBe('2026-11-05T17:36:27');
    expect(h.ended_at).toBe('2026-11-05T17:41:23');
    expect(h.duration_s).toBe(296);
    expect(h.raw_xp).toBe(19001);
    expect(h.xp).toBe(28498);
    expect(h.xp_h).toBe(346782); // client figure kept
    expect(h.balance).toBe(-57);
    expect(h.monsters[0]).toEqual({ name: 'broken shaper', count: 7 });
    expect(h.items).toContainEqual({ name: 'gold coin', count: 775 });
    expect(h.items).toContainEqual({ name: 'orichalcum pearl', count: 4 });
  });

  it('handles "Looted Items: None"', () => {
    const h = parseHuntText(sample('Hunt_analyser_copiado_do_client.txt'))!;
    expect(h.items).toEqual([]);
    expect(h.monsters).toHaveLength(2);
  });

  it('parses the JSON export the same way as the text', () => {
    const j = parseHuntJson(sample('Hunting_Session_2026-11-05_174134.json'))!;
    const t = parseHuntText(sample('Hunting_Session_2026-11-05_174132.txt'))!;
    expect(j.xp).toBe(t.xp);
    expect(j.items).toEqual(t.items);
    expect(j.monsters).toEqual(t.monsters);
    expect(j.duration_s).toBe(307);
  });

  it('reads party sessions', () => {
    const h = parseHuntText(SAMPLE_PARTY_LOG)!;
    expect(h.kind).toBe('party');
    expect(h.party!.loot_type).toBe('Market');
    expect(h.party!.members.length).toBeGreaterThanOrEqual(4);
    expect(h.party!.members[0]).toMatchObject({ name: 'Calefi', isLeader: true });
  });
});

describe('Input Analyser', () => {
  it('parses damage types and sources', () => {
    const i = parseInputAnalyser(sample('imput_analyser_copiado_do_client2.txt'))!;
    expect(i.total).toBe(3065);
    expect(i.max_dps).toBe(274);
    expect(i.types).toEqual([
      { name: 'Physical', value: 2628, pct: 85.7 },
      { name: 'Life Drain', value: 437, pct: 14.3 },
    ]);
    expect(i.sources[0]).toEqual({ name: 'broken shaper', value: 1974, pct: 64.4 });
  });

  it('accepts the empty analyser', () => {
    expect(parseInputAnalyser(sample('imput_analyser_copiado_do_client.txt'))).toEqual({ total: 0, max_dps: 0, types: [], sources: [] });
  });
});

describe('mixed chat paste', () => {
  it('strips chat prefixes and finds both analysers', () => {
    const text = sample('2026-11-04-hunt-and-input-analyser.txt');
    expect(stripChatPrefixes('[04/11/2026 07:36] Player: Received Damage')).toBe('Received Damage');
    const a = analysePaste(text);
    expect(a.formats).toEqual(['hunt', 'input']);
    expect(a.hunt!.xp).toBe(6010);
    expect(a.hunt!.monsters).toHaveLength(6);
    expect(a.input!.total).toBe(592);
    expect(a.input!.sources).toHaveLength(5);
  });

  it('detects the JSON export', () => {
    expect(analysePaste(sample('Hunting_Session_2026-11-05_173806.json')).formats).toEqual(['json']);
  });
});

describe('Server Log', () => {
  const text = sample('Server Log.txt');

  it('drops repeated saves of the same session', () => {
    const segs = serverLogSegments(text);
    expect(segs).toHaveLength(2);
    expect(segs[0].saved).toBe('Wed Nov 04 17:03:05 2026');
  });

  it('summarises xp, damage, loot, supplies and trade', () => {
    const s = parseServerLog(text)!;
    expect(s.sessions).toBe(2);
    expect(s.vocation).toBe('elite knight');
    expect(s.boosted.creature).toBe('Sineater Inferniarch');
    expect(s.xp.total).toBeGreaterThan(0);
    expect(s.xp.animus).toBeGreaterThan(0);
    expect(s.dealt.crits).toBeGreaterThan(0);
    expect(s.taken.by_source[0].name).toBe('werelioness');
    expect(s.supplies.find((x) => x.name === 'great mana potion')!.count).toBeGreaterThan(10);
    const lion = s.loot.find((l) => l.name === 'white lion')!;
    expect(lion.corpses).toBeGreaterThan(0);
    expect(lion.items.some((i) => i.name === 'platinum coin')).toBe(true);
    expect(s.reward_chest.map((r) => r.boss)).toContain('Yirkas Blue Scales');
    expect(s.trade.bought[0]).toEqual({ name: 'ultimate health potion', count: 475, gold: 180025 });
    expect(s.skills[0].skill).toBe('Sword Fighting');
    expect(s.effects[0].name).toBe('Bounty Talisman: More Damage Dealt');
    expect(s.timeline.length).toBeGreaterThan(3);
  });

  it('is detected by analysePaste', () => {
    expect(analysePaste(text).formats).toEqual(['serverlog']);
  });
});

describe('comparison and insights', () => {
  it('compares loot per hour item by item', () => {
    const rows = compareLoot(
      { items: [{ name: 'gold coin', count: 100 }], duration_s: 3600 },
      { items: [{ name: 'gold coin', count: 100 }, { name: 'ruby', count: 2 }], duration_s: 1800 },
    );
    expect(rows[0]).toEqual({ name: 'gold coin', a: 100, b: 200, delta: 100 });
    expect(rows[1]).toEqual({ name: 'ruby', a: 0, b: 4, delta: 4 });
  });

  it('explains a hunt against the average', () => {
    const h = parseHuntText(sample('Hunt_analyser_copiado_do_client2.txt'))!;
    const keys = huntInsights(h, { xp_h: 200000, balance_h: 50000, count: 5 }).map((i) => i.key);
    expect(keys).toContain('ins_xp_bonus');
    expect(keys).toContain('ins_xp_above');
    expect(keys).toContain('ins_profit_below');
  });
});
