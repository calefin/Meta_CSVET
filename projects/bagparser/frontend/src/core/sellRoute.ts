/**
 * Sell route: which cities to visit to sell the loot to NPCs, visiting as few cities as possible.
 * Greedy weighted set cover: repeatedly pick the city whose NPCs buy the most remaining value.
 * Pure function: city data is injected so it stays testable.
 */
import type { Destination } from './items';
import type { DestinationBucket } from './parser';

export interface RouteStop {
  npc: string;
  /** Sub-area inside the city (e.g. Ashta'daramai) */
  area?: string;
  items: { name: string; count: number; value: number }[];
  value: number;
}

export interface RouteCity {
  city: string;
  value: number;
  stops: RouteStop[];
}

export interface SellRoute {
  cities: RouteCity[];
  /** Items no reachable NPC buys today (e.g. Yasir-only items when Yasir is away) */
  unrouted: { name: string; count: number; value: number }[];
}

export interface RouteOptions {
  /** NPC → { city, area } for fixed NPCs; travelling NPCs resolved below */
  place: (npc: string) => { city: string; area?: string } | undefined;
  rashidCity: string;
  yasirHere: boolean;
  /** D-67: city where players reported Yasir today (his stop joins that city); null/undefined = unknown city */
  yasirCity?: string | null;
}

/** Buckets whose items are meant to be sold to NPCs */
const ROUTED: Destination[] = ['Rashid', 'Yasir', 'Green Djinn', 'Blue Djinn', 'Other NPCs'];
const YASIR_PLACE = 'Yasir';

export function buildSellRoute(destinations: Record<Destination, DestinationBucket>, opts: RouteOptions): SellRoute {
  interface Entry {
    name: string;
    count: number;
    value: number;
    /** city → NPCs in that city that buy it */
    byCity: Map<string, { npc: string; area?: string }[]>;
  }

  const entries: Entry[] = [];
  const unrouted: SellRoute['unrouted'] = [];

  for (const dest of ROUTED) {
    for (const it of destinations[dest].items) {
      const byCity = new Map<string, { npc: string; area?: string }[]>();
      for (const npc of it.sellTo) {
        let where: { city: string; area?: string } | undefined;
        if (npc === 'Rashid') where = { city: opts.rashidCity };
        else if (npc === 'Yasir') where = opts.yasirHere ? { city: opts.yasirCity || YASIR_PLACE, area: opts.yasirCity ? 'Yasir' : undefined } : undefined;
        else where = opts.place(npc);
        if (!where?.city) continue;
        const list = byCity.get(where.city) ?? [];
        list.push({ npc, area: where.area });
        byCity.set(where.city, list);
      }
      const row = { name: it.name, count: it.count, value: it.totalValue };
      if (byCity.size === 0) unrouted.push(row);
      else entries.push({ ...row, byCity });
    }
  }

  const cities: RouteCity[] = [];
  let remaining = entries;
  while (remaining.length) {
    // City covering the most remaining value (ties: more items, then name for stable output)
    const score = new Map<string, { value: number; items: number }>();
    for (const e of remaining) {
      for (const city of e.byCity.keys()) {
        const s = score.get(city) ?? { value: 0, items: 0 };
        s.value += e.value;
        s.items += 1;
        score.set(city, s);
      }
    }
    const [bestCity] = [...score.entries()].sort(
      ([ca, a], [cb, b]) => b.value - a.value || b.items - a.items || ca.localeCompare(cb),
    )[0];

    const stops = new Map<string, RouteStop>();
    const next: Entry[] = [];
    for (const e of remaining) {
      const npcs = e.byCity.get(bestCity);
      if (!npcs) {
        next.push(e);
        continue;
      }
      // Prefer an NPC already on this city's list, so the player talks to fewer NPCs
      const pick = npcs.find((n) => stops.has(n.npc)) ?? npcs[0];
      const stop = stops.get(pick.npc) ?? { npc: pick.npc, area: pick.area, items: [], value: 0 };
      stop.items.push({ name: e.name, count: e.count, value: e.value });
      stop.value += e.value;
      stops.set(pick.npc, stop);
    }
    const stopList = [...stops.values()].sort((a, b) => b.value - a.value);
    cities.push({ city: bestCity, value: stopList.reduce((s, x) => s + x.value, 0), stops: stopList });
    remaining = next;
  }

  return { cities, unrouted };
}
