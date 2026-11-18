/**
 * What a creature sheet shows first (D-80): what to hit it with, what to avoid, what to protect from and its drops by
 * rarity. Pure functions over shared/creatures.json and the sheet from /api/creature-info.
 */
import { ATTACK_ELEMENTS, mod, type CreatureStats, type Element } from './creatures';

/** Same rule as backend/src/huntingPlaces.ts */
export const creatureSlug = (name: string): string =>
  name
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/'/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');

export interface Resistance {
  element: Element;
  pct: number;
  /** Difference from normal damage: +20 = takes 20 % more */
  delta: number;
}

/** Every attack element, from the one that hurts it most to the one that hurts it least */
export function resistances(c: Pick<CreatureStats, 'el'>): Resistance[] {
  return ATTACK_ELEMENTS.map((element) => {
    const pct = mod(c as CreatureStats, element);
    return { element, pct, delta: pct - 100 };
  }).sort((a, b) => b.pct - a.pct);
}

export const bestHits = (rows: Resistance[]): Resistance[] => rows.filter((r) => r.delta > 0).slice(0, 2);
export const worstHits = (rows: Resistance[]): Resistance[] => rows.filter((r) => r.delta < 0).slice(-2).reverse();

/** Bar length in % of its half of the chart; the widest difference on the sheet fills it (at least a 20-point scale) */
export function barWidth(delta: number, rows: Resistance[]): number {
  const widest = Math.max(20, ...rows.map((r) => Math.abs(r.delta)));
  return Math.min(100, (Math.abs(delta) * 100) / widest);
}

/** Damage it deals per element, largest first */
export const damageDealt = (c: { out?: Record<string, number> | null }): [string, number][] =>
  Object.entries(c.out ?? {}).sort((a, b) => b[1] - a[1]);

export interface LootEntry {
  i: string;
  q?: string;
  r?: string;
}
export const RARITIES = ['extremely rare', 'very rare', 'rare', 'semi-rare', 'uncommon', 'common', 'always', 'none'] as const;
export type Rarity = (typeof RARITIES)[number];

/** Rarest first; inside a group the most valuable first. The wiki often has no rarity: that is its own group, never a guess */
export function lootByRarity(loot: LootEntry[], price: (name: string) => number): { rarity: Rarity; items: LootEntry[] }[] {
  return RARITIES.map((rarity) => ({
    rarity,
    items: loot.filter((l) => (l.r ?? 'none') === rarity).sort((a, b) => price(b.i) - price(a.i) || a.i.localeCompare(b.i)),
  })).filter((g) => g.items.length > 0);
}

export interface SheetAbility {
  n: string;
  r?: string;
  e?: string;
  k?: string;
}
export interface SheetTraits {
  para: boolean | null;
  invis: boolean | null;
  push: boolean | null;
  pushObj: boolean | null;
  illusion: boolean | null;
  summon: number | null;
  convince: number | null;
  through: string | null;
  around: string | null;
  runs: number | null;
}

/** Only what changes how you play the hunt */
export function alertsOf(s: { tr: SheetTraits; ab: SheetAbility[] }): { key: string; vars?: Record<string, string> }[] {
  const out: { key: string; vars?: Record<string, string> }[] = [];
  if (s.tr.invis) out.push({ key: 'cr_al_invis' });
  if (s.tr.para) out.push({ key: 'cr_al_para' });
  const heal = s.ab.find((a) => a.k === 'heal');
  if (heal) out.push(heal.r ? { key: 'cr_al_heal_r', vars: { r: heal.r } } : { key: 'cr_al_heal' });
  if (s.ab.some((a) => a.k === 'summon')) out.push({ key: 'cr_al_summon' });
  return out;
}
