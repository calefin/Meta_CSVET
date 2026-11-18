/**
 * Hunt session v2 (D-59): validation of the "about / build / expenses" parts of a hunt session,
 * saved sets and Wheels of Destiny, and hunt templates. The browser computes the stats; the API only
 * checks shapes and bounds (everything here is user input that ends up in JSONB columns).
 */

const str = (v: unknown, max: number): string | null => {
  if (typeof v !== 'string') return null;
  const s = v.replace(/[\u0000-\u001f]/g, ' ').trim();
  return s ? s.slice(0, max) : null;
};
const int = (v: unknown, min: number, max: number): number | null => {
  const n = Number(v);
  return Number.isFinite(n) && Number.isInteger(n) && n >= min && n <= max ? n : null;
};

export const SLOTS = ['helmet', 'amulet', 'armor', 'weapon', 'shield', 'legs', 'ring', 'boots', 'ammo', 'trinket'] as const;
export const IMBUEMENTS = [
  'strike',
  'vampirism',
  'void',
  'epiphany',
  'chop',
  'slash',
  'bash',
  'precision',
  'punch',
  'blockade',
  'dragon hide',
  'quara scale',
  'snake skin',
  'cloud fabric',
  'lich shroud',
  'demon presence',
  'scorch',
  'frost',
  'venom',
  'electrify',
  'reap',
  'swiftness',
  'vibrancy',
  'featherweight',
] as const;
export const PREY_TYPES = ['xp', 'damage', 'defense', 'loot'] as const;

export interface Expense {
  tc: number;
  tc_price: number;
  gold: number;
  reason: string | null;
  payer: string | null;
}

/** Up to 10 rows; a row is Tibia Coins × price per coin and/or gold. Returns null when the shape is wrong. */
export function validateExpenses(v: unknown): { rows: Expense[]; total: number } | null {
  if (v == null) return { rows: [], total: 0 };
  if (!Array.isArray(v) || v.length > 10) return null;
  const rows: Expense[] = [];
  for (const e of v as Record<string, unknown>[]) {
    const tc = int(e?.tc ?? 0, 0, 1_000_000);
    const price = int(e?.tc_price ?? 0, 0, 1_000_000);
    const gold = int(e?.gold ?? 0, 0, 10_000_000_000);
    if (tc == null || price == null || gold == null) return null;
    if (!tc && !gold) continue;
    rows.push({ tc, tc_price: price, gold, reason: str(e?.reason, 120), payer: str(e?.payer, 40) });
  }
  return { rows, total: rows.reduce((s, r) => s + r.tc * r.tc_price + r.gold, 0) };
}

export interface SetItem {
  name: string;
  tier?: number;
  imbues?: { type: string; tier: number }[];
}
export interface SetData {
  slots: Partial<Record<(typeof SLOTS)[number], SetItem>>;
  consumables: string[];
}
export interface WodData {
  code: string | null;
  revelations: Record<string, number>;
  points: Record<string, number>;
  gems: string | null;
  notes: string | null;
}

function names(v: unknown, max: number, len = 60): string[] {
  if (!Array.isArray(v)) return [];
  const out: string[] = [];
  for (const x of v.slice(0, max)) {
    const s = str(x, len);
    if (s && !out.includes(s)) out.push(s);
  }
  return out;
}

export function validateSet(v: unknown): SetData | null {
  if (!v || typeof v !== 'object') return null;
  const b = v as { slots?: Record<string, unknown>; consumables?: unknown };
  const slots: SetData['slots'] = {};
  for (const slot of SLOTS) {
    const raw = b.slots?.[slot] as Record<string, unknown> | undefined;
    if (!raw) continue;
    const name = str(raw.name, 80);
    if (!name) continue;
    const item: SetItem = { name };
    const tier = int(raw.tier, 0, 10);
    if (tier) item.tier = tier;
    if (Array.isArray(raw.imbues)) {
      const imbues = (raw.imbues as Record<string, unknown>[])
        .slice(0, 3)
        .map((i) => ({ type: String(i?.type ?? '').toLowerCase(), tier: int(i?.tier, 1, 3) ?? 0 }))
        .filter((i) => (IMBUEMENTS as readonly string[]).includes(i.type) && i.tier);
      if (imbues.length) item.imbues = imbues;
    }
    slots[slot] = item;
  }
  return { slots, consumables: names(b.consumables, 12) };
}

export function validateWod(v: unknown): WodData | null {
  if (!v || typeof v !== 'object') return null;
  const b = v as Record<string, unknown>;
  const revelations: Record<string, number> = {};
  if (b.revelations && typeof b.revelations === 'object') {
    for (const [k, val] of Object.entries(b.revelations as Record<string, unknown>).slice(0, 8)) {
      const key = str(k, 40);
      const stage = int(val, 0, 3);
      if (key && stage) revelations[key] = stage;
    }
  }
  const points: Record<string, number> = {};
  if (b.points && typeof b.points === 'object') {
    for (const d of ['green', 'red', 'purple', 'blue']) {
      const p = int((b.points as Record<string, unknown>)[d], 0, 1000);
      if (p) points[d] = p;
    }
  }
  const code = typeof b.code === 'string' && /^[A-Za-z0-9+/=_-]{4,600}$/.test(b.code.trim()) ? b.code.trim() : null;
  return { code, revelations, points, gems: str(b.gems, 300), notes: str(b.notes, 500) };
}

export interface HuntBuild {
  character: string | null;
  set: { id: number | null; name: string; data: SetData } | null;
  wod: { id: number | null; name: string; data: WodData } | null;
  charms: { name: string; kind: 'major' | 'minor'; tier: number; creature: string | null }[];
  preys: { type: (typeof PREY_TYPES)[number]; grade: number; creature: string | null }[];
  stances: string[];
  consumables: string[];
}

/** The build snapshot stored with a hunt (or a template). Returns null when nothing usable was sent. */
export function validateBuild(v: unknown): HuntBuild | null {
  if (!v || typeof v !== 'object') return null;
  const b = v as Record<string, unknown>;
  const named = <T>(raw: unknown, check: (d: unknown) => T | null) => {
    if (!raw || typeof raw !== 'object') return null;
    const r = raw as Record<string, unknown>;
    const data = check(r.data);
    const name = str(r.name, 80);
    return data && name ? { id: int(r.id, 1, 2 ** 31 - 1), name, data } : null;
  };
  const charms: HuntBuild['charms'] = [];
  if (Array.isArray(b.charms)) {
    for (const c of (b.charms as Record<string, unknown>[]).slice(0, 30)) {
      const name = str(c?.name, 40);
      const tier = int(c?.tier, 1, 3);
      if (!name || !tier || charms.some((x) => x.name === name)) continue;
      charms.push({ name, kind: c?.kind === 'minor' ? 'minor' : 'major', tier, creature: str(c?.creature, 80)?.toLowerCase() ?? null });
    }
  }
  const preys: HuntBuild['preys'] = [];
  if (Array.isArray(b.preys)) {
    for (const p of (b.preys as Record<string, unknown>[]).slice(0, 3)) {
      const type = PREY_TYPES.find((t) => t === p?.type);
      const grade = int(p?.grade, 1, 10);
      if (type && grade) preys.push({ type, grade, creature: str(p?.creature, 80)?.toLowerCase() ?? null });
    }
  }
  const out: HuntBuild = {
    character: str(b.character, 29),
    set: named(b.set, validateSet),
    wod: named(b.wod, validateWod),
    charms,
    preys,
    stances: names(b.stances, 2, 40),
    consumables: names(b.consumables, 12),
  };
  const empty = !out.set && !out.wod && !charms.length && !preys.length && !out.stances.length && !out.consumables.length;
  return empty ? null : out;
}

export interface TemplateData {
  description: string | null;
  place_slug: string | null;
  place_name: string | null;
  character_id: number | null;
  visibility: 'private' | 'public';
  anonymous: boolean;
  expenses: Expense[];
  build: HuntBuild | null;
  shared_with: number[];
}

export function validateTemplate(v: unknown): TemplateData | null {
  if (!v || typeof v !== 'object') return null;
  const b = v as Record<string, unknown>;
  const expenses = validateExpenses(b.expenses);
  if (!expenses) return null;
  return {
    description: str(b.description, 2000),
    place_slug: typeof b.place_slug === 'string' && /^[a-z0-9-]{1,160}$/.test(b.place_slug) ? b.place_slug : null,
    place_name: str(b.place_name, 160),
    character_id: int(b.character_id, 1, 2 ** 31 - 1),
    visibility: b.visibility === 'public' ? 'public' : 'private',
    anonymous: b.anonymous === true,
    expenses: expenses.rows,
    build: validateBuild(b.build),
    shared_with: friendIds(b.shared_with),
  };
}

export function friendIds(v: unknown): number[] {
  if (!Array.isArray(v)) return [];
  return [...new Set(v.map((x) => int(x, 1, 2 ** 31 - 1)).filter((x): x is number => x != null))].slice(0, 20);
}
