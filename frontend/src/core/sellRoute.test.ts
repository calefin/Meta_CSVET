import { describe, expect, it } from 'vitest';
import { buildSellRoute } from './sellRoute';
import { parseTibiaSession } from './parser';
import { defaultLookupDb } from './items';
import { npcInfo } from './npcs';
import { SAMPLE_SOLO_LOG } from './samples';

const place = (npc: string) => {
  const n = npcInfo(npc);
  return n && !n.travelling ? { city: n.city, area: n.area } : undefined;
};

describe('buildSellRoute', () => {
  const out = parseTibiaSession(SAMPLE_SOLO_LOG, defaultLookupDb);

  it('covers every sellable item exactly once', () => {
    const route = buildSellRoute(out.destinations, { place, rashidCity: 'Liberty Bay', yasirHere: false });
    const routed = route.cities.flatMap((c) => c.stops.flatMap((s) => s.items.map((i) => i.name)));
    expect(new Set(routed).size).toBe(routed.length);
    expect(routed).toEqual(expect.arrayContaining(['dragon scale mail', 'royal helmet', 'life crystal', 'small diamond']));
    // Coins and Market items are never routed to NPCs
    expect(routed).not.toContain('gold coin');
    expect(routed).not.toContain('strong mana potion');
  });

  it('orders cities by value and puts Rashid in today’s city', () => {
    const route = buildSellRoute(out.destinations, { place, rashidCity: 'Liberty Bay', yasirHere: false });
    const values = route.cities.map((c) => c.value);
    expect([...values].sort((a, b) => b - a)).toEqual(values);
    const lb = route.cities.find((c) => c.city === 'Liberty Bay');
    expect(lb?.stops.some((s) => s.npc === 'Rashid')).toBe(true);
  });

  it('leaves Yasir-only items unrouted while Yasir is away', () => {
    const fake = parseTibiaSession('Looted Items:\n  1x dragon shield\n', defaultLookupDb);
    fake.destinations['Yasir'].items.push({
      name: 'yasir thing', count: 1, unitNpcPrice: 10, totalValue: 10, weightOz: 1, isImbuement: false, marketAlert: false, sellTo: ['Yasir'],
    });
    const away = buildSellRoute(fake.destinations, { place, rashidCity: 'Edron', yasirHere: false });
    expect(away.unrouted.map((u) => u.name)).toEqual(['yasir thing']);
    const here = buildSellRoute(fake.destinations, { place, rashidCity: 'Edron', yasirHere: true });
    expect(here.unrouted).toEqual([]);
  });
});

describe('Yasir from player reports (D-67)', () => {
  it('puts the Yasir stop in the reported city and leaves him out without a report', async () => {
    const { buildSellRoute } = await import('./sellRoute');
    const bucket = (items: { name: string; sellTo: string[]; totalValue: number }[]) => ({ items: items.map((i) => ({ ...i, count: 1 })), total: 0 });
    const dest = {
      Rashid: bucket([]),
      Yasir: bucket([{ name: 'Rare Gem', sellTo: ['Yasir'], totalValue: 1000 }]),
      'Green Djinn': bucket([]),
      'Blue Djinn': bucket([]),
      'Other NPCs': bucket([{ name: 'Sword', sellTo: ['Tezila'], totalValue: 50 }]),
      Market: bucket([]),
      Trash: bucket([]),
    } as never;
    const place = (npc: string) => (npc === 'Tezila' ? { city: 'Carlin' } : undefined);
    const here = buildSellRoute(dest, { place, rashidCity: 'Edron', yasirHere: true, yasirCity: 'Carlin' });
    expect(here.cities.map((c) => c.city)).toEqual(['Carlin']);
    const unknown = buildSellRoute(dest, { place, rashidCity: 'Edron', yasirHere: true, yasirCity: null });
    expect(unknown.cities.map((c) => c.city).sort()).toEqual(['Carlin', 'Yasir']);
    const away = buildSellRoute(dest, { place, rashidCity: 'Edron', yasirHere: false });
    expect(away.unrouted.map((u) => u.name)).toEqual(['Rare Gem']);
  });
});
