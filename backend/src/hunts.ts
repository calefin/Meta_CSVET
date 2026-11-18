/**
 * Hunt sessions (D-49): validation of what the browser sends after parsing the client's analysers.
 * The browser does the parsing (frontend/src/core/huntLog.ts); the API only checks shapes and bounds.
 */
import { createHash, randomBytes } from 'node:crypto';
import { sanitizeServerLog } from './serverLog.js';
import { friendIds, validateBuild, validateExpenses, type Expense, type HuntBuild } from './huntBuild.js';

export const HUNT_LIMIT = { free: 30, premium: 5000 };
/** Hunts shorter than this stay out of rankings and place statistics (one-minute spikes) */
export const RANKING_MIN_SECONDS = 20 * 60;

export interface NameCount {
  name: string;
  count: number;
}

export interface HuntInput {
  kind: 'solo' | 'party';
  started_at: string | null;
  ended_at: string | null;
  duration_s: number;
  raw_xp: number;
  xp: number;
  raw_xp_h: number;
  xp_h: number;
  loot: number;
  supplies: number;
  balance: number;
  balance_h: number;
  damage: number;
  damage_h: number;
  healing: number;
  healing_h: number;
  monsters: NameCount[];
  items: NameCount[];
  party: { loot_type: string | null; members: { name: string; isLeader: boolean; loot: number; supplies: number; balance: number; damage: number; healing: number }[] } | null;
  input: {
    total: number;
    max_dps: number;
    types: { name: string; value: number; pct: number }[];
    sources: { name: string; value: number; pct: number }[];
    /** D-62: damage per creature and type (split on the site from the two lists + creature data) */
    by_source_type?: { name: string; creature: string | null; total: number; types: Record<string, number> }[];
    estimated?: boolean;
  } | null;
  server_log: Record<string, unknown> | null;
  character_id: number | null;
  character_name: string | null;
  vocation: string | null;
  level: number | null;
  world: string | null;
  place_slug: string | null;
  place_name: string | null;
  visibility: 'private' | 'public';
  anonymous: boolean;
  notes: string | null;
  setup: Record<string, string | boolean>;
  // D-59
  title: string | null;
  description: string | null;
  expenses: Expense[];
  extra_expenses: number;
  build: HuntBuild | null;
  shared_with: number[];
}

const MAX_GP = 50_000_000_000; // 50kk per session is already absurd; reject garbage
const int = (v: unknown, min: number, max: number): number | null => {
  const n = Number(v);
  return Number.isFinite(n) && Number.isInteger(n) && n >= min && n <= max ? n : null;
};
const str = (v: unknown, max: number): string | null => {
  if (typeof v !== 'string') return null;
  const s = v.replace(/[\u0000-\u001f]/g, ' ').trim();
  return s ? s.slice(0, max) : null;
};
const DATE_RE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/;

function list(v: unknown, max = 300): NameCount[] | null {
  if (!Array.isArray(v) || v.length > max) return null;
  const out: NameCount[] = [];
  for (const e of v) {
    const name = str((e as NameCount)?.name, 80);
    const count = int((e as NameCount)?.count, 1, 10_000_000);
    if (!name || count == null) return null;
    out.push({ name: name.toLowerCase(), count });
  }
  return out;
}

export const VOCATIONS = ['knight', 'paladin', 'sorcerer', 'druid', 'monk'] as const;

/** "Elite Knight" → "knight"; anything else → null */
export function baseVocation(v: unknown): (typeof VOCATIONS)[number] | null {
  const s = String(v ?? '').toLowerCase();
  return VOCATIONS.find((x) => s.includes(x)) ?? null;
}

export type HuntError = { error: string; field?: string };

export function validateHunt(body: unknown): HuntInput | HuntError {
  const b = (body ?? {}) as Record<string, unknown>;
  const kind = b.kind === 'party' ? 'party' : b.kind === 'solo' ? 'solo' : null;
  if (!kind) return { error: 'invalid_hunt', field: 'kind' };
  const duration = int(b.duration_s, 30, 3 * 86400);
  if (duration == null) return { error: 'invalid_hunt', field: 'duration_s' };

  const nums = {} as Record<string, number>;
  for (const [k, min] of [
    ['raw_xp', 0],
    ['xp', 0],
    ['raw_xp_h', 0],
    ['xp_h', 0],
    ['loot', 0],
    ['supplies', 0],
    ['balance', -MAX_GP],
    ['balance_h', -MAX_GP],
    ['damage', 0],
    ['damage_h', 0],
    ['healing', 0],
    ['healing_h', 0],
  ] as const) {
    const n = int(b[k] ?? 0, min, MAX_GP);
    if (n == null) return { error: 'invalid_hunt', field: k };
    nums[k] = n;
  }
  if (!nums.xp && !nums.loot && !nums.supplies && !nums.damage) return { error: 'empty_hunt' };

  const monsters = list(b.monsters ?? []);
  const items = list(b.items ?? []);
  if (!monsters || !items) return { error: 'invalid_hunt', field: 'lists' };

  const started = typeof b.started_at === 'string' && DATE_RE.test(b.started_at) ? b.started_at : null;
  const ended = typeof b.ended_at === 'string' && DATE_RE.test(b.ended_at) ? b.ended_at : null;

  let party: HuntInput['party'] = null;
  if (kind === 'party' && b.party && typeof b.party === 'object') {
    const p = b.party as { loot_type?: unknown; members?: unknown };
    if (!Array.isArray(p.members) || p.members.length > 20) return { error: 'invalid_hunt', field: 'party' };
    const members = [];
    for (const m of p.members as Record<string, unknown>[]) {
      const name = str(m?.name, 40);
      if (!name) return { error: 'invalid_hunt', field: 'party' };
      const n = (k: string, min = 0) => int(m[k] ?? 0, min, MAX_GP) ?? 0;
      members.push({
        name,
        isLeader: m.isLeader === true,
        loot: n('loot'),
        supplies: n('supplies'),
        balance: n('balance', -MAX_GP),
        damage: n('damage'),
        healing: n('healing'),
      });
    }
    party = { loot_type: str(p.loot_type, 20), members };
  }

  let input: HuntInput['input'] = null;
  if (b.input && typeof b.input === 'object') {
    const i = b.input as Record<string, unknown>;
    const rows = (v: unknown) =>
      Array.isArray(v)
        ? v.slice(0, 50).map((r: Record<string, unknown>) => ({
            name: str(r?.name, 60) ?? '?',
            value: int(r?.value, 0, MAX_GP) ?? 0,
            pct: Math.max(0, Math.min(100, Number(r?.pct) || 0)),
          }))
        : [];
    input = { total: int(i.total, 0, MAX_GP) ?? 0, max_dps: int(i.max_dps, 0, 10_000_000) ?? 0, types: rows(i.types), sources: rows(i.sources) };
    if (Array.isArray(i.by_source_type)) {
      input.by_source_type = i.by_source_type.slice(0, 50).map((r: Record<string, unknown>) => {
        const types: Record<string, number> = {};
        if (r?.types && typeof r.types === 'object')
          for (const [k, v] of Object.entries(r.types as Record<string, unknown>).slice(0, 12)) {
            const key = str(k, 20);
            const val = int(v, 0, MAX_GP);
            if (key && val) types[key] = val;
          }
        return { name: str(r?.name, 60) ?? '?', creature: str(r?.creature, 60), total: int(r?.total, 0, MAX_GP) ?? 0, types };
      });
      input.estimated = i.estimated === true;
    }
  }

  // Server Log summary (Premium): stored as given, but size-capped
  let serverLog: Record<string, unknown> | null = null;
  if (b.server_log && typeof b.server_log === 'object') {
    const json = JSON.stringify(b.server_log);
    if (json.length > 200_000) return { error: 'invalid_hunt', field: 'server_log' };
    serverLog = sanitizeServerLog(b.server_log);
  }

  const setup: Record<string, string | boolean> = {};
  if (b.setup && typeof b.setup === 'object') {
    for (const [k, v] of Object.entries(b.setup as Record<string, unknown>).slice(0, 12)) {
      if (!/^[a-z_]{1,24}$/.test(k)) continue;
      if (typeof v === 'boolean') setup[k] = v;
      else {
        const s = str(v, 120);
        if (s) setup[k] = s;
      }
    }
  }

  const expenses = validateExpenses(b.expenses);
  if (!expenses) return { error: 'invalid_hunt', field: 'expenses' };

  return {
    kind,
    started_at: started,
    ended_at: ended,
    duration_s: duration,
    raw_xp: nums.raw_xp,
    xp: nums.xp,
    raw_xp_h: nums.raw_xp_h,
    xp_h: nums.xp_h,
    loot: nums.loot,
    supplies: nums.supplies,
    balance: nums.balance,
    balance_h: nums.balance_h,
    damage: nums.damage,
    damage_h: nums.damage_h,
    healing: nums.healing,
    healing_h: nums.healing_h,
    monsters,
    items,
    party,
    input,
    server_log: serverLog,
    character_id: int(b.character_id, 1, 2 ** 31 - 1),
    character_name: str(b.character_name, 29),
    vocation: baseVocation(b.vocation),
    level: int(b.level, 1, 5000),
    world: str(b.world, 40),
    place_slug: typeof b.place_slug === 'string' && /^[a-z0-9-]{1,160}$/.test(b.place_slug) ? b.place_slug : null,
    place_name: str(b.place_name, 160),
    visibility: b.visibility === 'public' ? 'public' : 'private',
    anonymous: b.anonymous === true,
    notes: str(b.notes, 1000),
    setup,
    title: str(b.title, 120),
    description: typeof b.description === 'string' ? b.description.replace(/[ -	-]/g, ' ').trim().slice(0, 2000) || null : null,
    expenses: expenses.rows,
    extra_expenses: expenses.total,
    build: validateBuild(b.build),
    shared_with: friendIds(b.shared_with),
  };
}

/** Same session pasted twice (or the .txt and the .json of it) → same fingerprint */
export function huntFingerprint(userId: number, h: Pick<HuntInput, 'started_at' | 'xp' | 'loot' | 'supplies' | 'character_name' | 'duration_s'>): string {
  const key = [userId, h.started_at ?? '', h.xp, h.loot, h.supplies, (h.character_name ?? '').toLowerCase(), h.started_at ? '' : h.duration_s].join('|');
  return createHash('sha256').update(key).digest('hex').slice(0, 40);
}

/** Short public id for share links (/h/<id>) */
export const newPublicId = (): string => randomBytes(8).toString('base64url').slice(0, 10);
