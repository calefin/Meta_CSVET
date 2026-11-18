/**
 * Creature analysis (D-61): what the hunt's creatures are weak to, how much of the bestiary the hunt was worth,
 * which elemental charm fits best and a rough estimate of what the chosen charms did.
 * Data: shared/creatures.json (TibiaWiki, CC BY-SA; scripts/import-creatures.mjs), loaded on demand.
 */
import { CHARMS, charmInfo, charmValue, type CharmPick } from './build';

export type Element = 'physical' | 'earth' | 'fire' | 'death' | 'energy' | 'holy' | 'ice' | 'lifedrain' | 'drown' | 'healing';
export const ATTACK_ELEMENTS: Element[] = ['physical', 'earth', 'fire', 'energy', 'ice', 'holy', 'death'];

export interface CreatureStats {
  name: string;
  hp: number;
  exp: number;
  arm: number | null;
  mit: number | null;
  cls: string | null;
  lvl: string | null;
  occ: string | null;
  boss: boolean;
  spd: number | null;
  /** damage taken in % per element (missing = 100) */
  el: Partial<Record<Element, number>>;
  /** D-62: max damage per element it deals (TibiaWiki), e.g. { physical: 290, earth: 172 } */
  dmg?: Record<string, number> | null;
  /** D-80: share (%) of the damage it deals per element */
  out?: Record<string, number> | null;
  /** D-82: sprite path on the wiki CDN, for creatures the official library does not have */
  img?: string | null;
}

let catalog: Promise<Map<string, CreatureStats>> | null = null;
export function loadCreatures(): Promise<Map<string, CreatureStats>> {
  catalog ??= import('../../../shared/creatures.json').then((m) => {
    const list = (m.default as unknown as { creatures: CreatureStats[] }).creatures;
    return new Map(list.map((c) => [c.name.toLowerCase(), c]));
  });
  return catalog;
}

/** Hunt analysers print plurals and lower case ("dragon lords"): find the creature anyway */
export function findCreature(index: Map<string, CreatureStats>, name: string): CreatureStats | null {
  const k = name.trim().toLowerCase();
  if (index.has(k)) return index.get(k)!;
  const tries = [k.replace(/ies$/, 'y'), k.replace(/ves$/, 'f'), k.replace(/es$/, ''), k.replace(/s$/, ''), k.replace(/^(a|an|the) /, '')];
  for (const x of tries) if (index.has(x)) return index.get(x)!;
  return null;
}

/** Bestiary: kills to unlock everything and charm points earned, per difficulty */
export const BESTIARY: Record<string, { kills: number; points: number }> = {
  Harmless: { kills: 25, points: 1 },
  Trivial: { kills: 250, points: 5 },
  Easy: { kills: 500, points: 15 },
  Medium: { kills: 1000, points: 25 },
  Hard: { kills: 2500, points: 50 },
  Challenging: { kills: 5000, points: 100 },
};
export function bestiaryKills(c: CreatureStats): number | null {
  if (!c.lvl || !BESTIARY[c.lvl]) return null;
  return /very rare/i.test(c.occ ?? '') ? 5 : BESTIARY[c.lvl].kills;
}

export const mod = (c: CreatureStats, e: Element): number => c.el[e] ?? 100;

/** Elemental damage charms (major) and the element they deal */
export const ELEMENT_CHARMS: { name: string; element: Element }[] = CHARMS.filter((c) => c.group === 'damage' && c.element && c.name !== 'Carnage').map((c) => ({
  name: c.name,
  element: c.element as Element,
}));

/** Share of the creature's hit points one elemental charm hit deals (game rule: 5 % of max HP, before resistances) */
export const CHARM_HIT_PCT = 5;

export interface CreatureRow {
  name: string;
  kills: number;
  stats: CreatureStats | null;
  /** kills × hit points: how much of the hunt's killing this creature took */
  hpTotal: number;
  xpBase: number;
  weak: { element: Element; pct: number }[];
  strong: { element: Element; pct: number }[];
  bestCharm: { name: string; element: Element; pct: number } | null;
  bestiaryPct: number | null;
  charmPoints: number | null;
}

export function creatureRows(monsters: { name: string; count: number }[], index: Map<string, CreatureStats>): CreatureRow[] {
  return monsters
    .map((m) => {
      const c = findCreature(index, m.name);
      if (!c) return { name: m.name, kills: m.count, stats: null, hpTotal: 0, xpBase: 0, weak: [], strong: [], bestCharm: null, bestiaryPct: null, charmPoints: null };
      const els = ATTACK_ELEMENTS.map((e) => ({ element: e, pct: mod(c, e) }));
      const best = ELEMENT_CHARMS.map((ch) => ({ ...ch, pct: mod(c, ch.element) })).sort((a, b) => b.pct - a.pct)[0] ?? null;
      const need = bestiaryKills(c);
      return {
        name: c.name,
        kills: m.count,
        stats: c,
        hpTotal: c.hp * m.count,
        xpBase: c.exp * m.count,
        weak: els.filter((x) => x.pct > 100).sort((a, b) => b.pct - a.pct),
        strong: els.filter((x) => x.pct < 100).sort((a, b) => a.pct - b.pct),
        bestCharm: best,
        bestiaryPct: need ? Math.min(100, Math.round((m.count / need) * 1000) / 10) : null,
        charmPoints: c.lvl ? BESTIARY[c.lvl]?.points ?? null : null,
      };
    })
    .sort((a, b) => b.hpTotal - a.hpTotal || b.kills - a.kills);
}

/** Element that does the most against everything killed, weighted by the hit points removed */
export function bestElements(rows: CreatureRow[]): { element: Element; pct: number }[] {
  const known = rows.filter((r) => r.stats);
  const total = known.reduce((s, r) => s + r.hpTotal, 0);
  if (!total) return [];
  return ATTACK_ELEMENTS.map((e) => ({
    element: e,
    pct: Math.round(known.reduce((s, r) => s + mod(r.stats!, e) * r.hpTotal, 0) / total),
  })).sort((a, b) => b.pct - a.pct);
}

export interface CharmEstimate {
  charm: string;
  tier: number;
  creature: string | null;
  kills: number;
  /** % chance per attack (tier value) */
  chance: number;
  /** damage of one activation against that creature (5 % of max HP × element modifier) */
  perHit: number | null;
  /** element modifier against the creature (%) */
  elementPct: number | null;
  /** estimated activations and damage; only with the attack count from the Server Log */
  procs: number | null;
  damage: number | null;
  /** creature products / skinning: chance bonus, nothing to estimate in gold */
  note: 'crit' | 'loot' | 'defense' | 'leech' | 'utility' | null;
}

/**
 * Rough estimate of what the chosen charms did. Elemental charms trigger on attacks: with the Server Log we know
 * the attacks (hits) and how the damage was spread between creatures, so attacks on a creature ≈ hits × its share.
 */
export function charmEstimates(
  charms: CharmPick[],
  monsters: { name: string; count: number }[],
  index: Map<string, CreatureStats>,
  dealt?: { hits: number; by_target: { name: string; pct: number }[] } | null,
): CharmEstimate[] {
  return charms.map((c) => {
    const info = charmInfo(c.name);
    const chance = charmValue(c);
    const killed = c.creature ? monsters.find((m) => m.name.toLowerCase() === c.creature) ?? monsters.find((m) => findCreature(index, m.name)?.name.toLowerCase() === c.creature) : null;
    const stats = c.creature ? findCreature(index, c.creature) : null;
    const base: CharmEstimate = { charm: c.name, tier: c.tier, creature: stats?.name ?? c.creature, kills: killed?.count ?? 0, chance, perHit: null, elementPct: null, procs: null, damage: null, note: null };
    if (!info) return base;
    if (info.group !== 'damage' || !info.element || c.name === 'Carnage') {
      return { ...base, note: info.group === 'damage' ? 'utility' : (info.group as CharmEstimate['note']) };
    }
    if (!stats) return base;
    const elementPct = mod(stats, info.element as Element);
    const perHit = Math.round(((stats.hp * CHARM_HIT_PCT) / 100) * (elementPct / 100));
    let procs: number | null = null;
    if (dealt && dealt.hits > 0) {
      const share = dealt.by_target.find((x) => findCreature(index, x.name)?.name === stats.name)?.pct ?? 0;
      const attacks = (dealt.hits * share) / 100;
      procs = Math.round((attacks * chance) / 100);
    }
    return { ...base, elementPct, perHit, procs, damage: procs != null ? procs * perHit : null };
  });
}

// ---------------------------------------------------------------------------
// D-62: damage received per creature AND damage type (the Input Analyser only lists each one separately)
// ---------------------------------------------------------------------------

/** "Life Drain" → "lifedrain", "Physical" → "physical" */
export const elementKey = (name: string): string => name.toLowerCase().replace(/[^a-z]/g, '');

/** Symbol per element (damage types in the analysers, creature weaknesses) */
export const ELEMENT_ICON: Record<string, string> = {
  physical: '⚔️',
  earth: '🌿',
  fire: '🔥',
  energy: '⚡',
  ice: '❄️',
  holy: '✨',
  death: '💀',
  lifedrain: '🩸',
  manadrain: '🔷',
  drown: '💧',
  healing: '💚',
  agony: '🌀',
};
export const elementIcon = (name: string): string => ELEMENT_ICON[elementKey(name)] ?? '•';

/** Protection imbuement for an element (tier III value) */
export const PROTECTION: Record<string, { imbue: string; pct: number }> = {
  fire: { imbue: 'Dragon Hide', pct: 15 },
  ice: { imbue: 'Quara Scale', pct: 15 },
  earth: { imbue: 'Snake Skin', pct: 15 },
  energy: { imbue: 'Cloud Fabric', pct: 15 },
  holy: { imbue: 'Demon Presence', pct: 15 },
  death: { imbue: 'Lich Shroud', pct: 10 },
};

export interface DamageRow {
  /** name as the analyser printed it */
  name: string;
  /** catalog name (for the sprite), null if unknown */
  creature: string | null;
  total: number;
  /** damage per type name as printed ("Earth": 83) */
  types: Record<string, number>;
}
export interface DamageMatrix {
  types: string[];
  rows: DamageRow[];
  /** false when every creature only hits with one of the listed types (the split is exact) */
  estimated: boolean;
}

/**
 * Splits the received damage into creature × type so both lists of the Input Analyser add up. Each creature can
 * only take the types it hits with (TibiaWiki "Max Damage", weighted by it); then the cells are scaled until the
 * row (creature) and column (type) totals match (iterative proportional fitting).
 */
export function damageMatrix(
  input: { types: { name: string; value: number }[]; sources: { name: string; value: number }[] },
  index: Map<string, CreatureStats>,
): DamageMatrix {
  const types = input.types.map((x) => x.name);
  const cols = input.types.map((x) => x.value);
  let guessed = false;
  const rows = input.sources.map((src) => {
    const c = findCreature(index, src.name);
    let w = types.map((tn) => (c?.dmg ? c.dmg[elementKey(tn)] ?? 0 : 1));
    if (!c?.dmg || w.every((x) => x === 0)) {
      guessed = true;
      w = types.map(() => 1);
    }
    return { src, c, w };
  });
  const m = rows.map((r) => {
    const sum = r.w.reduce((a, b) => a + b, 0);
    return r.w.map((x) => (sum ? (r.src.value * x) / sum : 0));
  });
  for (let it = 0; it < 200; it++) {
    for (let j = 0; j < types.length; j++) {
      const s = m.reduce((a, row) => a + row[j], 0);
      if (s > 0) for (const row of m) row[j] *= cols[j] / s;
    }
    for (let i = 0; i < m.length; i++) {
      const s = m[i].reduce((a, b) => a + b, 0);
      if (s > 0) m[i] = m[i].map((x) => (x * rows[i].src.value) / s);
    }
  }
  const out: DamageRow[] = rows.map((r, i) => {
    const cells = m[i].map((x) => Math.round(x));
    // Rounding drift goes to the biggest cell so the row still adds up
    const drift = r.src.value - cells.reduce((a, b) => a + b, 0);
    if (drift && cells.length) cells[cells.indexOf(Math.max(...cells))] += drift;
    const typesOut: Record<string, number> = {};
    cells.forEach((v, j) => {
      if (v > 0) typesOut[types[j]] = v;
    });
    return { name: r.src.name, creature: r.c?.name ?? null, total: r.src.value, types: typesOut };
  });
  const mixed = out.some((r) => Object.keys(r.types).length > 1);
  return { types, rows: out, estimated: guessed || mixed };
}

type InputLike = {
  types: { name: string; value: number }[];
  sources: { name: string; value: number }[];
  by_source_type?: { name: string; creature: string | null; total: number; types: Record<string, number> }[];
};

/** D-62: received damage added up over many hunts (per type and per creature, with the creature × type split) */
export function sumReceived(inputs: (InputLike | null | undefined)[]): {
  hunts: number;
  total: number;
  types: { name: string; value: number; pct: number }[];
  sources: { name: string; creature: string | null; value: number; pct: number; types: Record<string, number> }[];
} {
  const types = new Map<string, number>();
  const sources = new Map<string, { name: string; creature: string | null; value: number; types: Record<string, number> }>();
  let hunts = 0;
  let total = 0;
  for (const i of inputs) {
    if (!i || (!i.types.length && !i.sources.length)) continue;
    hunts++;
    for (const ty of i.types) {
      types.set(ty.name, (types.get(ty.name) ?? 0) + ty.value);
      total += ty.value;
    }
    const split = new Map((i.by_source_type ?? []).map((r) => [r.name.toLowerCase(), r]));
    for (const src of i.sources) {
      const row = split.get(src.name.toLowerCase());
      const key = (row?.creature ?? src.name).toLowerCase();
      const cur = sources.get(key) ?? { name: row?.creature ?? src.name, creature: row?.creature ?? null, value: 0, types: {} };
      cur.value += src.value;
      for (const [k, v] of Object.entries(row?.types ?? {})) cur.types[k] = (cur.types[k] ?? 0) + v;
      sources.set(key, cur);
    }
  }
  const pct = (v: number) => (total ? Math.round((v / total) * 1000) / 10 : 0);
  return {
    hunts,
    total,
    types: [...types].map(([name, value]) => ({ name, value, pct: pct(value) })).sort((a, b) => b.value - a.value),
    sources: [...sources.values()].map((s) => ({ ...s, pct: pct(s.value) })).sort((a, b) => b.value - a.value),
  };
}
