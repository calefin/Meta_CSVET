/**
 * Hunt session v2 (D-59): the character build used in a hunt — equipment set (with imbuements), Wheel of Destiny,
 * charms, preys, stances and consumables — and the combat stats the set gives, summed per source.
 * Game facts only (names and numbers); equipment attributes come from shared/equipment.json (TibiaWiki, CC BY-SA).
 */

export const SLOTS = ['helmet', 'amulet', 'armor', 'weapon', 'shield', 'legs', 'ring', 'boots', 'ammo'] as const;
export type Slot = (typeof SLOTS)[number];

export interface Equipment {
  name: string;
  slot: Slot;
  type?: string;
  level?: number;
  voc?: string[];
  arm?: number;
  def?: number;
  atk?: number;
  imbue?: number;
  tier?: number;
  two?: boolean;
  /** attributes, e.g. { "magic level": 4, "critical hit chance": 5 } */
  a?: Record<string, number>;
  /** resistances, e.g. { physical: 4, fire: 8 } */
  r?: Record<string, number>;
  /** spell augments as written on the wiki */
  aug?: string;
}

export interface SetItem {
  name: string;
  tier?: number;
  imbues?: { type: string; tier: number }[];
}
export interface SetData {
  slots: Partial<Record<Slot, SetItem>>;
  consumables: string[];
}
export interface WodData {
  code: string | null;
  revelations: Record<string, number>;
  points: Record<string, number>;
  gems: string | null;
  notes: string | null;
}
export interface CharmPick {
  name: string;
  kind: 'major' | 'minor';
  tier: number;
  creature: string | null;
}
export type PreyType = 'xp' | 'damage' | 'defense' | 'loot';
export interface PreyPick {
  type: PreyType;
  grade: number;
  creature: string | null;
}
export interface HuntBuild {
  character: string | null;
  set: { id: number | null; name: string; data: SetData } | null;
  wod: { id: number | null; name: string; data: WodData } | null;
  charms: CharmPick[];
  preys: PreyPick[];
  stances: string[];
  consumables: string[];
}

/**
 * "My items" (D-61): every configured item (forge tier and/or imbuements) found in my saved sets, per slot,
 * so a weapon imbued once can be reused in the next set with one click. Same item + tier + imbuements = one entry.
 */
export function itemLibrary(sets: SetData[]): Partial<Record<Slot, SetItem[]>> {
  const out: Partial<Record<Slot, SetItem[]>> = {};
  const seen = new Set<string>();
  for (const s of sets)
    for (const [slot, item] of Object.entries(s.slots ?? {}) as [Slot, SetItem][]) {
      if (!item?.name || (!item.tier && !item.imbues?.length)) continue;
      const imbues = [...(item.imbues ?? [])].sort((a, b) => a.type.localeCompare(b.type));
      const key = `${slot}|${item.name.toLowerCase()}|${item.tier ?? 0}|${imbues.map((i) => i.type + i.tier).join(',')}`;
      if (seen.has(key)) continue;
      seen.add(key);
      (out[slot] ??= []).push({ name: item.name, ...(item.tier ? { tier: item.tier } : {}), ...(imbues.length ? { imbues } : {}) });
    }
  return out;
}

export const emptyBuild = (): HuntBuild => ({ character: null, set: null, wod: null, charms: [], preys: [], stances: [], consumables: [] });
export const emptySet = (): SetData => ({ slots: {}, consumables: [] });
export const emptyWod = (): WodData => ({ code: null, revelations: {}, points: {}, gems: null, notes: null });

export function buildIsEmpty(b: HuntBuild): boolean {
  return !b.set && !b.wod && !b.charms.length && !b.preys.length && !b.stances.length && !b.consumables.length;
}

// ---------------------------------------------------------------------------
// Charms (tier I / II / III values)
// ---------------------------------------------------------------------------

export type CharmGroup = 'damage' | 'crit' | 'defense' | 'leech' | 'loot' | 'utility';
export interface CharmInfo {
  name: string;
  kind: 'major' | 'minor';
  values: [number, number, number];
  group: CharmGroup;
  /** stat it adds against the selected creature (shown in the per-creature stats) */
  stat?: string;
  element?: string;
}

export const CHARMS: CharmInfo[] = [
  { name: 'Carnage', kind: 'major', values: [10, 20, 22], group: 'damage', element: 'physical' },
  { name: 'Curse', kind: 'major', values: [5, 10, 11], group: 'damage', element: 'death' },
  { name: 'Divine Wrath', kind: 'major', values: [5, 10, 11], group: 'damage', element: 'holy' },
  { name: 'Dodge', kind: 'major', values: [5, 10, 11], group: 'defense' },
  { name: 'Enflame', kind: 'major', values: [5, 10, 11], group: 'damage', element: 'fire' },
  { name: 'Freeze', kind: 'major', values: [5, 10, 11], group: 'damage', element: 'ice' },
  { name: 'Low Blow', kind: 'major', values: [4, 8, 9], group: 'crit', stat: 'critical hit chance' },
  { name: 'Overflux', kind: 'major', values: [5, 10, 11], group: 'damage' },
  { name: 'Overpower', kind: 'major', values: [5, 10, 11], group: 'damage' },
  { name: 'Parry', kind: 'major', values: [5, 10, 11], group: 'defense' },
  { name: 'Poison', kind: 'major', values: [5, 10, 11], group: 'damage', element: 'earth' },
  { name: 'Savage Blow', kind: 'major', values: [20, 40, 44], group: 'crit', stat: 'critical extra damage' },
  { name: 'Wound', kind: 'major', values: [5, 10, 11], group: 'damage', element: 'physical' },
  { name: 'Zap', kind: 'major', values: [5, 10, 11], group: 'damage', element: 'energy' },
  { name: 'Adrenaline Burst', kind: 'minor', values: [6, 9, 12], group: 'utility' },
  { name: 'Bless', kind: 'minor', values: [6, 9, 12], group: 'utility' },
  { name: 'Cleanse', kind: 'minor', values: [6, 9, 12], group: 'utility' },
  { name: 'Cripple', kind: 'minor', values: [6, 9, 12], group: 'utility' },
  { name: 'Fatal Hold', kind: 'minor', values: [30, 45, 60], group: 'utility' },
  { name: 'Gut', kind: 'minor', values: [6, 9, 12], group: 'loot', stat: 'creature products' },
  { name: 'Numb', kind: 'minor', values: [6, 9, 12], group: 'utility' },
  { name: 'Scavenge', kind: 'minor', values: [60, 90, 120], group: 'loot', stat: 'skinning' },
  { name: 'Vampiric Embrace', kind: 'minor', values: [1.6, 2.4, 3.2], group: 'leech', stat: 'life leech' },
  { name: 'Void Inversion', kind: 'minor', values: [20, 30, 40], group: 'utility' },
  { name: "Void's Call", kind: 'minor', values: [0.8, 1.2, 1.6], group: 'leech', stat: 'mana leech' },
];
export const charmInfo = (name: string): CharmInfo | undefined => CHARMS.find((c) => c.name === name);
export const charmValue = (c: CharmPick): number => charmInfo(c.name)?.values[Math.min(3, Math.max(1, c.tier)) - 1] ?? 0;

// ---------------------------------------------------------------------------
// Preys, stances, Wheel of Destiny, consumables
// ---------------------------------------------------------------------------

export const PREY_TYPES: PreyType[] = ['xp', 'damage', 'defense', 'loot'];

export type Voc = 'knight' | 'paladin' | 'sorcerer' | 'druid' | 'monk';
export const VOCS: Voc[] = ['knight', 'paladin', 'sorcerer', 'druid', 'monk'];

/** Stances per vocation (names as in the game) */
export const STANCES: Record<Voc, string[]> = {
  knight: ['Blood Rage', 'Protector'],
  paladin: ['Divine Defiance', 'Sharpshooter'],
  sorcerer: ['Aura of Exposed Weakness', 'Aura of Sapped Strength', 'Master of Decay', 'Master of Flames', 'Master of Thunder'],
  druid: ['Elemental Synthesis', 'Shared Conservation'],
  monk: ['Virtue of Harmony', 'Virtue of Justice', 'Virtue of Sustain'],
};

/** Revelation perks of the Wheel of Destiny per vocation (stage 0–3) */
export const REVELATIONS: Record<Voc, string[]> = {
  knight: ['Gift of Life', 'Avatar of Steel', "Executioner's Throw", 'Combat Mastery'],
  paladin: ['Gift of Life', 'Avatar of Light', 'Divine Grenade', 'Divine Empowerment'],
  sorcerer: ['Gift of Life', 'Avatar of Storm', 'Beam Mastery', 'Drain Body'],
  druid: ['Gift of Life', 'Avatar of Nature', 'Twin Bursts', 'Blessing of the Grove'],
  monk: ['Gift of Life', 'Avatar of Balance'],
};
export const WOD_DOMAINS = ['green', 'red', 'purple', 'blue'] as const;

export const CONSUMABLES = [
  'Berserk Potion',
  'Bullseye Potion',
  'Mastermind Potion',
  'Transcendence Potion',
  'Supreme Health Potion',
  'Ultimate Spirit Potion',
  'Ultimate Mana Potion',
  'Great Spirit Potion',
  'Roasted Dragon Wings',
  'Rotworm Stew',
  'Hydra Tongue Salad',
  'Tropical Fried Terrorbird',
  'Veggie Casserole',
  'Filled Jalapeño Peppers',
  'Blessed Steak',
  'Northern Fishburger',
  'Carrot Pie',
  'Demonic Candy Balls',
  'Sweet Mangonaise Elixir',
  'Wealth Duplex',
  'Stamina Extension',
];

/** Skill buffs of a few consumables (shown in the stats with the consumable as source) */
const CONSUMABLE_STATS: Record<string, Record<string, number>> = {
  'Berserk Potion': { 'sword fighting': 5, 'axe fighting': 5, 'club fighting': 5 },
  'Bullseye Potion': { 'distance fighting': 5 },
  'Mastermind Potion': { 'magic level': 3 },
};

// ---------------------------------------------------------------------------
// Imbuements (Basic / Intricate / Powerful)
// ---------------------------------------------------------------------------

export interface ImbueInfo {
  type: string;
  label: string;
  /** stat → values per tier */
  stats: Record<string, [number, number, number]>;
  /** which item kinds take it */
  on: ('helmet' | 'armor' | 'shield' | 'weapon' | 'boots' | 'spellbook' | 'magic')[];
}

const protection = (type: string, label: string, element: string, values: [number, number, number]): ImbueInfo => ({
  type,
  label,
  stats: { [`resist:${element}`]: values },
  on: ['armor', 'shield', 'spellbook'],
});
const skill = (type: string, label: string, stat: string, on: ImbueInfo['on']): ImbueInfo => ({ type, label, stats: { [stat]: [1, 2, 4] }, on });
const conversion = (type: string, label: string, element: string): ImbueInfo => ({
  type,
  label,
  stats: { [`conversion:${element}`]: [10, 25, 50] },
  on: ['weapon'],
});

export const IMBUEMENTS: ImbueInfo[] = [
  { type: 'strike', label: 'Strike', stats: { 'critical hit chance': [5, 5, 5], 'critical extra damage': [5, 15, 40] }, on: ['weapon', 'magic'] },
  { type: 'vampirism', label: 'Vampirism', stats: { 'life leech': [5, 10, 25] }, on: ['weapon', 'magic', 'armor'] },
  { type: 'void', label: 'Void', stats: { 'mana leech': [3, 5, 8] }, on: ['helmet', 'weapon', 'magic'] },
  skill('epiphany', 'Epiphany', 'magic level', ['magic', 'helmet']),
  skill('chop', 'Chop', 'axe fighting', ['helmet', 'weapon']),
  skill('slash', 'Slash', 'sword fighting', ['helmet', 'weapon']),
  skill('bash', 'Bash', 'club fighting', ['helmet', 'weapon']),
  skill('precision', 'Precision', 'distance fighting', ['helmet', 'weapon']),
  skill('punch', 'Punch', 'fist fighting', ['helmet', 'weapon']),
  skill('blockade', 'Blockade', 'shielding', ['helmet', 'shield', 'spellbook']),
  protection('dragon hide', 'Dragon Hide', 'fire', [3, 8, 15]),
  protection('quara scale', 'Quara Scale', 'ice', [3, 8, 15]),
  protection('snake skin', 'Snake Skin', 'earth', [3, 8, 15]),
  protection('cloud fabric', 'Cloud Fabric', 'energy', [3, 8, 15]),
  protection('demon presence', 'Demon Presence', 'holy', [3, 8, 15]),
  protection('lich shroud', 'Lich Shroud', 'death', [2, 5, 10]),
  conversion('scorch', 'Scorch', 'fire'),
  conversion('frost', 'Frost', 'ice'),
  conversion('venom', 'Venom', 'earth'),
  conversion('electrify', 'Electrify', 'energy'),
  conversion('reap', 'Reap', 'death'),
  { type: 'swiftness', label: 'Swiftness', stats: { speed: [10, 15, 30] }, on: ['boots'] },
  { type: 'vibrancy', label: 'Vibrancy', stats: { 'paralysis deflection': [15, 25, 50] }, on: ['boots'] },
];
export const IMBUE_TIERS = ['Basic', 'Intricate', 'Powerful'] as const;

/** Item kind for imbuement filtering */
export function itemKind(e: Pick<Equipment, 'slot' | 'type'>): ImbueInfo['on'][number] | null {
  const t = (e.type ?? '').toLowerCase();
  if (e.slot === 'weapon') return /wand|rod/.test(t) ? 'magic' : 'weapon';
  if (e.slot === 'shield') return /spellbook/.test(t) ? 'spellbook' : /quiver/.test(t) ? null : 'shield';
  if (e.slot === 'helmet' || e.slot === 'armor' || e.slot === 'boots') return e.slot;
  return null;
}

export function imbuementsFor(e: Pick<Equipment, 'slot' | 'type'>): ImbueInfo[] {
  const kind = itemKind(e);
  return kind ? IMBUEMENTS.filter((i) => i.on.includes(kind)) : [];
}

// ---------------------------------------------------------------------------
// Stats of a set, per source
// ---------------------------------------------------------------------------

export interface StatLine {
  key: string;
  total: number;
  sources: { from: string; value: number }[];
}

export const PERCENT_STATS = new Set(['critical hit chance', 'critical extra damage', 'life leech', 'mana leech', 'paralysis deflection']);

/** Display order; anything else follows alphabetically */
const ORDER = [
  'critical hit chance',
  'critical extra damage',
  'life leech',
  'mana leech',
  'magic level',
  'fire magic level',
  'ice magic level',
  'earth magic level',
  'energy magic level',
  'holy magic level',
  'death magic level',
  'healing magic level',
  'sword fighting',
  'axe fighting',
  'club fighting',
  'distance fighting',
  'fist fighting',
  'shielding',
  'speed',
  'paralysis deflection',
];
const RESIST_ORDER = ['physical', 'fire', 'ice', 'earth', 'energy', 'holy', 'death', 'life drain', 'mana drain', 'drowning'];

export interface SetStats {
  armor: number;
  stats: StatLine[];
  resist: StatLine[];
  conversions: StatLine[];
  augments: { from: string; text: string }[];
  missing: string[];
}

export function computeSetStats(set: SetData | null, index: Map<string, Equipment>, extra: { consumables?: string[] } = {}): SetStats {
  const stats = new Map<string, StatLine>();
  const add = (key: string, from: string, value: number) => {
    if (!value) return;
    const line = stats.get(key) ?? { key, total: 0, sources: [] };
    line.total = Math.round((line.total + value) * 100) / 100;
    line.sources.push({ from, value });
    stats.set(key, line);
  };
  let armor = 0;
  const augments: SetStats['augments'] = [];
  const missing: string[] = [];
  for (const slot of SLOTS) {
    const item = set?.slots[slot];
    if (!item) continue;
    const e = index.get(item.name.toLowerCase());
    if (!e) missing.push(item.name);
    else {
      armor += e.arm ?? 0;
      for (const [k, v] of Object.entries(e.a ?? {})) add(k, e.name, v);
      for (const [k, v] of Object.entries(e.r ?? {})) add(`resist:${k}`, e.name, v);
      if (e.aug) augments.push({ from: e.name, text: e.aug });
    }
    for (const im of item.imbues ?? []) {
      const info = IMBUEMENTS.find((i) => i.type === im.type);
      if (!info) continue;
      const label = `${info.label} ${IMBUE_TIERS[im.tier - 1] ?? ''}`.trim();
      for (const [k, values] of Object.entries(info.stats)) add(k, label, values[im.tier - 1] ?? 0);
    }
  }
  for (const c of [...(set?.consumables ?? []), ...(extra.consumables ?? [])]) {
    for (const [k, v] of Object.entries(CONSUMABLE_STATS[c] ?? {})) add(k, c, v);
  }
  const all = [...stats.values()];
  const rank = (k: string, order: string[]) => {
    const i = order.indexOf(k);
    return i < 0 ? 1000 : i;
  };
  return {
    armor,
    stats: all
      .filter((s) => !s.key.includes(':'))
      .sort((a, b) => rank(a.key, ORDER) - rank(b.key, ORDER) || a.key.localeCompare(b.key)),
    resist: all
      .filter((s) => s.key.startsWith('resist:'))
      .map((s) => ({ ...s, key: s.key.slice(7) }))
      .sort((a, b) => rank(a.key, RESIST_ORDER) - rank(b.key, RESIST_ORDER)),
    conversions: all.filter((s) => s.key.startsWith('conversion:')).map((s) => ({ ...s, key: s.key.slice(11) })),
    augments,
    missing,
  };
}

/** Stats the charms add against each creature (Low Blow, Savage Blow, leech, Gut…) */
export function charmCreatureStats(charms: CharmPick[]): { creature: string; lines: { stat: string; value: number; from: string }[] }[] {
  const by = new Map<string, { stat: string; value: number; from: string }[]>();
  for (const c of charms) {
    const info = charmInfo(c.name);
    if (!info) continue;
    const key = c.creature ?? '';
    const list = by.get(key) ?? [];
    list.push({ stat: info.stat ?? info.group, value: charmValue(c), from: c.name });
    by.set(key, list);
  }
  return [...by].map(([creature, lines]) => ({ creature, lines }));
}

// ---------------------------------------------------------------------------
// Extra expenses and the party split
// ---------------------------------------------------------------------------

export interface Expense {
  tc: number;
  tc_price: number;
  gold: number;
  reason: string | null;
  payer: string | null;
}

export const expenseValue = (e: Pick<Expense, 'tc' | 'tc_price' | 'gold'>): number => (e.tc || 0) * (e.tc_price || 0) + (e.gold || 0);
export const expensesTotal = (rows: Expense[]): number => rows.reduce((s, e) => s + expenseValue(e), 0);

/**
 * Party members' balances after extra expenses: whoever paid an expense spent that money, so it comes off their
 * balance before the equal split (they get it back in the transfers). Rows without a payer that matches a member
 * are charged to the leader (or the first member).
 */
export function balancesWithExpenses<T extends { name: string; balance: number; isLeader?: boolean }>(members: T[], rows: Expense[]): T[] {
  if (!members.length) return members;
  const fallback = members.find((m) => m.isLeader) ?? members[0];
  const paid = new Map<string, number>();
  for (const e of rows) {
    const m = members.find((x) => x.name.toLowerCase() === (e.payer ?? '').toLowerCase()) ?? fallback;
    paid.set(m.name, (paid.get(m.name) ?? 0) + expenseValue(e));
  }
  return members.map((m) => ({ ...m, balance: m.balance - (paid.get(m.name) ?? 0) }));
}

// ---------------------------------------------------------------------------
// Equipment catalog (lazy: ~23 KB gzip, only loaded by the set editor and the build view)
// ---------------------------------------------------------------------------

let catalog: Promise<{ list: Equipment[]; index: Map<string, Equipment> }> | null = null;
export function loadEquipment(): Promise<{ list: Equipment[]; index: Map<string, Equipment> }> {
  catalog ??= import('../../../shared/equipment.json').then((m) => {
    const list = (m.default as unknown as { items: Equipment[] }).items;
    return { list, index: new Map(list.map((e) => [e.name.toLowerCase(), e])) };
  });
  return catalog;
}

/** Item search for one slot: prefix matches first, then word matches; items for the vocation first */
export function searchEquipment(list: Equipment[], q: string, slot: Slot | null, voc: Voc | null, limit = 30): Equipment[] {
  const words = q.toLowerCase().split(/\s+/).filter(Boolean);
  const out = list.filter((e) => (!slot || e.slot === slot) && words.every((w) => e.name.toLowerCase().includes(w)));
  const score = (e: Equipment) =>
    (q && e.name.toLowerCase().startsWith(q.toLowerCase()) ? 0 : 2) + (voc && e.voc && !e.voc.includes(voc) ? 4 : 0) - (e.level ?? 0) / 10000;
  return out.sort((a, b) => score(a) - score(b) || a.name.localeCompare(b.name)).slice(0, limit);
}
