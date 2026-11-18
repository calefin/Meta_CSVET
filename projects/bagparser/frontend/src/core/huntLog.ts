/**
 * Hunt Analyser v2 (D-49): every format the Tibia client can copy or save.
 *  - Hunting Session Analyser: copied text, "Hunting_Session_*.txt" and the client's JSON export
 *  - Party Hunt Analyser (copied text)
 *  - Input Analyser ("Received Damage": damage types and sources)
 *  - Server Log channel saved to a file ("Server Log.txt"): per-kill XP, loot per creature, damage, potions…
 * Pastes may mix several of them and keep the chat prefix "[04/11/2026 07:36] Name: ".
 * Pure functions only (no DOM / network); the backend stores the normalised `HuntData`.
 */
import { cleanInt, parseEntry, parseTibiaSession, type PartyMember } from './parser';
import { findItem, type LookupDb } from './items';

export interface NameCount {
  name: string;
  count: number;
}

/** Normalised hunt session (what the API stores) */
export interface HuntData {
  kind: 'solo' | 'party';
  /** Client local time "YYYY-MM-DDTHH:MM:SS" (the client prints no time zone) */
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
  party: { loot_type: string | null; members: PartyMember[] } | null;
}

export interface ShareRow {
  name: string;
  value: number;
  pct: number;
}

/** Input Analyser ("Received Damage") */
export interface InputAnalysis {
  total: number;
  max_dps: number;
  types: ShareRow[];
  sources: ShareRow[];
  /** D-62: damage per creature and type, saved with the hunt (see core/creatures.ts damageMatrix) */
  by_source_type?: { name: string; creature: string | null; total: number; types: Record<string, number> }[];
  estimated?: boolean;
}

export interface PasteAnalysis {
  hunt: HuntData | null;
  input: InputAnalysis | null;
  serverLog: ServerLogSummary | null;
  formats: ('hunt' | 'json' | 'party' | 'input' | 'serverlog')[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** "[04/11/2026 07:36] Player: Session data: …" → "Session data: …" (chat copy) */
export function stripChatPrefixes(text: string): string {
  return text
    .replace(/\r\n?/g, '\n')
    .split('\n')
    .map((l) => l.replace(/^\[\d{1,2}[/.-]\d{1,2}[/.-]\d{2,4}[ ,]+\d{1,2}:\d{2}(?::\d{2})?\]\s*[^:\n]{1,40}:\s?/, ''))
    .join('\n');
}

/** "2026-11-05, 17:36:27" → "2026-11-05T17:36:27" */
function clientDate(v: string | undefined): string | null {
  const m = v?.match(/(\d{4}-\d{2}-\d{2}),?\s+(\d{2}:\d{2}:\d{2})/);
  return m ? `${m[1]}T${m[2]}` : null;
}

/** Seconds between two client timestamps (same clock, so no time zone needed) */
function secondsBetween(a: string | null, b: string | null): number | null {
  if (!a || !b) return null;
  const d = (Date.parse(b + 'Z') - Date.parse(a + 'Z')) / 1000;
  return Number.isFinite(d) && d >= 0 ? d : null;
}

const perHour = (v: number, seconds: number) => (seconds > 0 ? Math.round((v / seconds) * 3600) : 0);

/** "00:04h" → 240 s */
function sessionSeconds(v: string | undefined): number | null {
  const m = v?.match(/(\d+):(\d{2})h/);
  return m ? (parseInt(m[1], 10) * 60 + parseInt(m[2], 10)) * 60 : null;
}

function finish(h: Omit<HuntData, 'raw_xp_h' | 'xp_h' | 'balance_h' | 'damage_h' | 'healing_h'>, reported: Partial<HuntData>): HuntData {
  const s = Math.max(1, h.duration_s);
  return {
    ...h,
    // Prefer the client's own per-hour figures (exact seconds); fall back to our own
    raw_xp_h: reported.raw_xp_h || perHour(h.raw_xp, s),
    xp_h: reported.xp_h || perHour(h.xp, s),
    balance_h: perHour(h.balance, s),
    damage_h: reported.damage_h || perHour(h.damage, s),
    healing_h: reported.healing_h || perHour(h.healing, s),
  };
}

// ---------------------------------------------------------------------------
// Hunting Session Analyser (text) + Party Hunt Analyser
// ---------------------------------------------------------------------------

function field(text: string, label: string): string | undefined {
  return text.match(new RegExp(`^${label.replace(/[/]/g, '\\/')}:[ \\t]*(-?[\\d,.]+)`, 'im'))?.[1];
}

export function parseHuntText(text: string): HuntData | null {
  const clean = stripChatPrefixes(text);
  if (!/^Session data:/im.test(clean) && !/^Session:\s*\d+:\d{2}h/im.test(clean)) return null;
  // parseTibiaSession needs a lookup db only for item routing; pass an empty one
  const base = parseTibiaSession(clean, {});
  const range = clean.match(/^Session data:\s*From\s+(.+?)\s+to\s+(.+)$/im);
  const started = clientDate(range?.[1]);
  const ended = clientDate(range?.[2]);
  const seconds =
    secondsBetween(started, ended) ?? sessionSeconds(clean.match(/^Session:\s*(\d+:\d{2}h)/im)?.[1]) ?? base.metrics.durationMinutes * 60;

  const items = itemsBlock(clean);
  const party = base.party
    ? { loot_type: clean.match(/^Loot Type:\s*(.+)$/im)?.[1]?.trim() ?? null, members: base.party.members }
    : null;
  const m = base.metrics;
  return finish(
    {
      kind: party ? 'party' : 'solo',
      started_at: started,
      ended_at: ended,
      duration_s: Math.round(seconds),
      raw_xp: m.rawXpTotal ?? 0,
      xp: m.xpTotal,
      loot: m.lootTotal,
      supplies: m.suppliesTotal,
      balance: m.balanceNet,
      damage: m.damageTotal ?? 0,
      healing: m.healingTotal ?? 0,
      monsters: base.killedMonsters,
      items,
      party,
    },
    {
      raw_xp_h: cleanInt(field(clean, 'Raw XP/h')),
      xp_h: cleanInt(field(clean, 'XP/h')),
      damage_h: cleanInt(field(clean, 'Damage/h')),
      healing_h: cleanInt(field(clean, 'Healing/h')),
    },
  );
}

/** "Looted Items:" block as name/count (unknown items are kept: the catalog is only for prices) */
function itemsBlock(text: string): NameCount[] {
  const lines = text.split('\n');
  const start = lines.findIndex((l) => /^\s*Looted Items:\s*$/i.test(l));
  if (start < 0) return [];
  const out = new Map<string, number>();
  for (const raw of lines.slice(start + 1)) {
    if (!/^\s+\S/.test(raw)) {
      if (raw.trim()) break; // next top-level field
      continue;
    }
    const line = raw.trim();
    if (/^none$/i.test(line)) continue;
    const e = parseEntry(line);
    if (e) out.set(e.name, (out.get(e.name) ?? 0) + e.count);
  }
  return [...out].map(([name, count]) => ({ name, count })).sort((a, b) => b.count - a.count);
}

// ---------------------------------------------------------------------------
// Client JSON export (Hunting_Session_*.json)
// ---------------------------------------------------------------------------

export function parseHuntJson(text: string): HuntData | null {
  let j: Record<string, unknown>;
  try {
    j = JSON.parse(text.trim());
  } catch {
    return null;
  }
  if (!j || typeof j !== 'object' || !('XP Gain' in j || 'Session length' in j)) return null;
  const num = (k: string) => cleanInt(String(j[k] ?? '0'));
  const list = (k: string): NameCount[] =>
    Array.isArray(j[k])
      ? (j[k] as { Count?: number; Name?: string }[])
          .filter((e) => e && e.Name)
          .map((e) => ({ name: String(e.Name).replace(/^(?:an?)\s+/i, ''), count: Number(e.Count) || 1 }))
          .sort((a, b) => b.count - a.count)
      : [];
  const started = clientDate(String(j['Session start'] ?? ''));
  const ended = clientDate(String(j['Session end'] ?? ''));
  const seconds = secondsBetween(started, ended) ?? sessionSeconds(String(j['Session length'] ?? '')) ?? 3600;
  return finish(
    {
      kind: 'solo',
      started_at: started,
      ended_at: ended,
      duration_s: Math.round(seconds),
      raw_xp: num('Raw XP Gain'),
      xp: num('XP Gain'),
      loot: num('Loot'),
      supplies: num('Supplies'),
      balance: num('Balance'),
      damage: num('Damage'),
      healing: num('Healing'),
      monsters: list('Killed Monsters'),
      items: list('Looted Items'),
      party: null,
    },
    { raw_xp_h: num('Raw XP/h'), xp_h: num('XP/h'), damage_h: num('Damage/h'), healing_h: num('Healing/h') },
  );
}

// ---------------------------------------------------------------------------
// Input Analyser ("Received Damage")
// ---------------------------------------------------------------------------

export function parseInputAnalyser(text: string): InputAnalysis | null {
  const clean = stripChatPrefixes(text);
  const lines = clean.split('\n');
  const start = lines.findIndex((l) => /^\s*Received Damage\s*$/i.test(l));
  if (start < 0) return null;
  const out: InputAnalysis = { total: 0, max_dps: 0, types: [], sources: [] };
  let block: 'types' | 'sources' | null = null;
  for (const raw of lines.slice(start + 1)) {
    const line = raw.trim();
    if (!line) continue;
    if (/^Session data:/i.test(line) || /^Received Damage$/i.test(line)) break;
    let m: RegExpMatchArray | null;
    if ((m = line.match(/^Total:\s*([\d,.]+)/i))) out.total = cleanInt(m[1]);
    else if ((m = line.match(/^Max-DPS:\s*([\d,.]+)/i))) out.max_dps = cleanInt(m[1]);
    else if (/^Damage Types$/i.test(line)) block = 'types';
    else if (/^Damage Sources$/i.test(line)) block = 'sources';
    else if (/^No data$/i.test(line)) continue;
    else if (block && (m = line.match(/^(.+?)\s+([\d,.]+)\s*\(([\d.]+)%\)$/))) {
      out[block].push({ name: m[1].trim(), value: cleanInt(m[2]), pct: parseFloat(m[3]) });
    } else if (block && /^[A-Z][\w -]+:/.test(line)) break;
  }
  return out;
}

// ---------------------------------------------------------------------------
// Server Log (saved channel)
// ---------------------------------------------------------------------------

export interface CreatureLoot {
  name: string;
  /** "Loot of …" messages (one per opened corpse) */
  corpses: number;
  empty: number;
  items: NameCount[];
}

export interface ServerLogSummary {
  /** Save time of the newest segment ("Channel Server Log saved …") */
  saved_at: string | null;
  sessions: number;
  first_time: string | null;
  last_time: string | null;
  minutes: number;
  vocation: string | null;
  xp: { total: number; kills: number; animus: number };
  level_ups: { from: number; to: number }[];
  skills: { skill: string; from: string; to: string }[];
  dealt: { total: number; hits: number; crits: number; crit_total: number; by_target: ShareRow[] };
  taken: { total: number; hits: number; by_source: ShareRow[]; mana_shield: number };
  healing: { self: number; received: number; mana: number };
  supplies: NameCount[];
  loot: CreatureLoot[];
  reward_chest: { boss: string; items: NameCount[] }[];
  trade: { sold: { name: string; count: number; gold: number }[]; bought: { name: string; count: number; gold: number }[] };
  effects: NameCount[];
  boosted: { creature: string | null; boss: string | null };
  /** XP and damage per clock minute ("HH:MM") for the timeline chart */
  timeline: { t: string; xp: number; dealt: number; taken: number }[];
}

const SAVED_RE = /^Channel Server Log saved (.+)$/;
const LINE_RE = /^(\d{2}:\d{2})\s+(.*?)\s*$/;

/**
 * The file keeps every "Save" of the channel: a later save of the same session repeats the earlier lines.
 * Keep the longest copy of each session and drop the repeats.
 */
export function serverLogSegments(text: string): { saved: string | null; lines: string[] }[] {
  const segs: { saved: string | null; lines: string[] }[] = [];
  let cur: { saved: string | null; lines: string[] } | null = null;
  for (const raw of text.replace(/\r\n?/g, '\n').split('\n')) {
    const saved = raw.match(SAVED_RE);
    if (saved) {
      cur = { saved: saved[1].trim(), lines: [] };
      segs.push(cur);
      continue;
    }
    if (!LINE_RE.test(raw.trim())) continue;
    if (!cur) {
      cur = { saved: null, lines: [] };
      segs.push(cur);
    }
    cur.lines.push(raw.trim());
  }
  const out: typeof segs = [];
  for (const s of segs) {
    if (!s.lines.length) continue;
    const prev = out[out.length - 1];
    const head = s.lines.slice(0, 5).join('\n');
    if (prev && prev.lines.slice(0, 5).join('\n') === head) {
      if (s.lines.length >= prev.lines.length) out[out.length - 1] = s;
    } else out.push(s);
  }
  return out;
}

/** "a white lion" → "white lion"; keeps proper names ("Katex Blood Tongue") */
const creature = (s: string) => s.trim().replace(/^(?:an?|the)\s+(?=[a-z])/, '');

function addTo(map: Map<string, number>, key: string, v: number) {
  map.set(key, (map.get(key) ?? 0) + v);
}

function shares(map: Map<string, number>, total: number): ShareRow[] {
  return [...map]
    .map(([name, value]) => ({ name, value, pct: total ? Math.round((value / total) * 1000) / 10 : 0 }))
    .sort((a, b) => b.value - a.value);
}

const toList = (m: Map<string, number>): NameCount[] =>
  [...m].map(([name, count]) => ({ name, count })).sort((a, b) => b.count - a.count);

/** "a great spirit potion, 4 platinum coins, twin hooks" → entries */
function lootList(s: string): NameCount[] {
  if (/^nothing\.?$/i.test(s.trim())) return [];
  return s
    .replace(/\.$/, '')
    .split(/,\s*/)
    .map((p) => parseEntry(p.trim()))
    .filter((e): e is NameCount => !!e);
}

export function parseServerLog(text: string): ServerLogSummary | null {
  const segs = serverLogSegments(text);
  const total = segs.reduce((n, s) => n + s.lines.length, 0);
  if (!total) return null;
  // Needs at least one line that only the server log has
  const sample = segs.flatMap((s) => s.lines).slice(0, 4000);
  if (!sample.some((l) => /loses \d+ hitpoints|You gained \d+ experience|Loot of |Using one of|You lose \d+ hitpoint/.test(l))) return null;

  const xpByMin = new Map<string, { xp: number; dealt: number; taken: number }>();
  const minute = (t: string) => {
    let v = xpByMin.get(t);
    if (!v) xpByMin.set(t, (v = { xp: 0, dealt: 0, taken: 0 }));
    return v;
  };
  const dealtBy = new Map<string, number>();
  const takenBy = new Map<string, number>();
  const supplies = new Map<string, number>();
  const effects = new Map<string, number>();
  const loot = new Map<string, { corpses: number; empty: number; items: Map<string, number> }>();
  const sold = new Map<string, { count: number; gold: number }>();
  const bought = new Map<string, { count: number; gold: number }>();
  const out: ServerLogSummary = {
    saved_at: segs[segs.length - 1].saved,
    sessions: segs.length,
    first_time: null,
    last_time: null,
    minutes: 0,
    vocation: null,
    xp: { total: 0, kills: 0, animus: 0 },
    level_ups: [],
    skills: [],
    dealt: { total: 0, hits: 0, crits: 0, crit_total: 0, by_target: [] },
    taken: { total: 0, hits: 0, by_source: [], mana_shield: 0 },
    healing: { self: 0, received: 0, mana: 0 },
    supplies: [],
    loot: [],
    reward_chest: [],
    trade: { sold: [], bought: [] },
    effects: [],
    boosted: { creature: null, boss: null },
    timeline: [],
  };

  for (const seg of segs) {
    let prevMin = -1;
    let segMinutes = 0;
    for (const line of seg.lines) {
      const lm = line.match(LINE_RE);
      if (!lm) continue;
      const [, time, msg] = lm;
      const [hh, mm] = time.split(':').map(Number);
      const minOfDay = hh * 60 + mm;
      if (prevMin >= 0) segMinutes += (minOfDay - prevMin + 1440) % 1440;
      prevMin = minOfDay;
      out.first_time ??= time;
      out.last_time = time;
      let m: RegExpMatchArray | null;

      const effect = msg.match(/\(([^()]+?) Effect: ([^()]+)\)\s*$/);
      if (effect) addTo(effects, `${effect[1]}: ${effect[2]}`, 1);

      if ((m = msg.match(/^You gained (\d+) experience points?(?: \((.+)\))?\.?$/))) {
        const v = Number(m[1]);
        out.xp.total += v;
        if (m[2]) out.xp.animus += v;
        else out.xp.kills++;
        minute(time).xp += v;
      } else if ((m = msg.match(/^You advanced from Level (\d+) to Level (\d+)/i))) {
        out.level_ups.push({ from: Number(m[1]), to: Number(m[2]) });
      } else if ((m = msg.match(/^Your (.+?) skill changed from level (\d+) and ([\d.]+)% to (?:level (\d+) and )?([\d.]+)%/))) {
        const prev = out.skills.find((s) => s.skill === m![1]);
        const to = `${m[4] ?? m[2]} (${m[5]}%)`;
        if (prev) prev.to = to;
        else out.skills.push({ skill: m[1], from: `${m[2]} (${m[3]}%)`, to });
      } else if ((m = msg.match(/^You lose (\d+) hitpoints? due to an attack by (.+?)\.?\s*(?:\(.*\))?$/))) {
        const v = Number(m[1]);
        out.taken.total += v;
        out.taken.hits++;
        addTo(takenBy, creature(m[2]), v);
        minute(time).taken += v;
      } else if ((m = msg.match(/^You lose (\d+) mana due to an attack by (.+?)\.?\s*$/))) {
        out.taken.mana_shield += Number(m[1]);
      } else if ((m = msg.match(/^(.+?) loses (\d+) hitpoints? due to your (critical )?attack\.?/))) {
        const v = Number(m[2]);
        out.dealt.total += v;
        out.dealt.hits++;
        if (m[3]) {
          out.dealt.crits++;
          out.dealt.crit_total += v;
        }
        addTo(dealtBy, creature(m[1]), v);
        minute(time).dealt += v;
      } else if ((m = msg.match(/^You healed yourself for (\d+) hitpoints?/))) {
        out.healing.self += Number(m[1]);
      } else if ((m = msg.match(/^You were healed for (\d+) hitpoints?/))) {
        out.healing.received += Number(m[1]);
      } else if ((m = msg.match(/^You gained (\d+) mana\.?$/))) {
        out.healing.mana += Number(m[1]);
      } else if ((m = msg.match(/^Using (?:one of \d+|the last) (.+?)\.\.\.$/))) {
        const name = findSingular(m[1]);
        addTo(supplies, name, 1);
      } else if ((m = msg.match(/^Loot of (.+?):\s*(.*)$/))) {
        const c = creature(m[1]);
        let rec = loot.get(c);
        if (!rec) loot.set(c, (rec = { corpses: 0, empty: 0, items: new Map() }));
        rec.corpses++;
        const list = lootList(m[2]);
        if (!list.length) rec.empty++;
        for (const e of list) addTo(rec.items, e.name, e.count);
      } else if ((m = msg.match(/^The following items dropped by (.+?) are available in your reward chest:\s*(.*)$/))) {
        out.reward_chest.push({ boss: m[1].trim(), items: lootList(m[2]) });
      } else if ((m = msg.match(/^Sold (\d+)x (.+?) for (\d+) gold/))) {
        const r = sold.get(m[2]) ?? { count: 0, gold: 0 };
        sold.set(m[2], { count: r.count + Number(m[1]), gold: r.gold + Number(m[3]) });
      } else if ((m = msg.match(/^Bought (\d+)x (.+?) for (\d+) gold/))) {
        const r = bought.get(m[2]) ?? { count: 0, gold: 0 };
        bought.set(m[2], { count: r.count + Number(m[1]), gold: r.gold + Number(m[3]) });
      } else if ((m = msg.match(/^Today's boosted creature: (.+)$/))) {
        out.boosted.creature = m[1].trim();
      } else if ((m = msg.match(/^Today's boosted boss: (.+)$/))) {
        out.boosted.boss = m[1].trim();
      } else if ((m = msg.match(/^You see yourself\. You are an? (.+?)\./))) {
        out.vocation = m[1];
      }
    }
    out.minutes += Math.max(1, segMinutes);
  }

  out.dealt.by_target = shares(dealtBy, out.dealt.total);
  out.taken.by_source = shares(takenBy, out.taken.total);
  out.supplies = toList(supplies);
  out.effects = toList(effects);
  out.loot = [...loot]
    .map(([name, r]) => ({ name, corpses: r.corpses, empty: r.empty, items: toList(r.items) }))
    .sort((a, b) => b.corpses - a.corpses);
  out.trade.sold = [...sold].map(([name, r]) => ({ name, ...r })).sort((a, b) => b.gold - a.gold);
  out.trade.bought = [...bought].map(([name, r]) => ({ name, ...r })).sort((a, b) => b.gold - a.gold);
  out.timeline = [...xpByMin].map(([t, v]) => ({ t, ...v }));
  return out;
}

/** "great mana potions" → "great mana potion"; "squeezing gear of girlpower" stays */
function findSingular(name: string): string {
  const n = name.trim();
  if (/ies$/.test(n)) return n.replace(/ies$/, 'y');
  if (/(potion|rune|arrow|bolt|star|spear)s$/.test(n)) return n.replace(/s$/, '');
  return n;
}

// ---------------------------------------------------------------------------
// One entry point for any paste / file
// ---------------------------------------------------------------------------

export function analysePaste(text: string): PasteAnalysis {
  const out: PasteAnalysis = { hunt: null, input: null, serverLog: null, formats: [] };
  const trimmed = text.trim();
  if (!trimmed) return out;
  if (trimmed.startsWith('{')) {
    out.hunt = parseHuntJson(trimmed);
    if (out.hunt) out.formats.push('json');
    return out;
  }
  if (/Channel Server Log saved/.test(trimmed) || /^\d{2}:\d{2} (?:Loot of|You gained \d+ experience|.+ loses \d+ hitpoints)/m.test(trimmed)) {
    out.serverLog = parseServerLog(trimmed);
    if (out.serverLog) out.formats.push('serverlog');
  }
  out.hunt = parseHuntText(trimmed);
  if (out.hunt) out.formats.push(out.hunt.kind === 'party' ? 'party' : 'hunt');
  out.input = parseInputAnalyser(trimmed);
  if (out.input) out.formats.push('input');
  return out;
}

// ---------------------------------------------------------------------------
// Insights / comparison
// ---------------------------------------------------------------------------

/** NPC value of the looted items with our catalog (the client may value loot at market prices) */
export function npcLootValue(items: NameCount[], db: LookupDb): { value: number; unknown: number } {
  let value = 0;
  let unknown = 0;
  for (const it of items) {
    if (/^gold coins?$/i.test(it.name)) value += it.count;
    else if (/^platinum coins?$/i.test(it.name)) value += it.count * 100;
    else if (/^crystal coins?$/i.test(it.name)) value += it.count * 10_000;
    else {
      const info = findItem(it.name, db);
      if (info) value += info.npc_price * it.count;
      else unknown++;
    }
  }
  return { value, unknown };
}

export interface LootDiffRow {
  name: string;
  /** per hour in each hunt */
  a: number;
  b: number;
  delta: number;
}

/** Item-by-item loot per hour of two hunts (what changed between them) */
export function compareLoot(a: { items: NameCount[]; duration_s: number }, b: { items: NameCount[]; duration_s: number }): LootDiffRow[] {
  const rate = (h: { items: NameCount[]; duration_s: number }) => {
    const m = new Map<string, number>();
    const hours = Math.max(1, h.duration_s) / 3600;
    for (const it of h.items) m.set(it.name, (m.get(it.name) ?? 0) + it.count / hours);
    return m;
  };
  const ra = rate(a);
  const rb = rate(b);
  const names = new Set([...ra.keys(), ...rb.keys()]);
  return [...names]
    .map((name) => {
      const va = Math.round((ra.get(name) ?? 0) * 10) / 10;
      const vb = Math.round((rb.get(name) ?? 0) * 10) / 10;
      return { name, a: va, b: vb, delta: Math.round((vb - va) * 10) / 10 };
    })
    .sort((x, y) => Math.abs(y.delta) - Math.abs(x.delta));
}

export interface Insight {
  tone: 'good' | 'bad' | 'info';
  key: string;
  vars?: Record<string, string | number>;
}

/** Plain-language observations about one hunt (optionally against the character's average) */
export function huntInsights(h: HuntData, avg?: { xp_h: number; balance_h: number; count: number } | null, input?: InputAnalysis | null): Insight[] {
  const out: Insight[] = [];
  // D-62: per-hour numbers from a few minutes swing a lot (one rare drop, one respawn)
  if (h.duration_s > 0 && h.duration_s < 20 * 60) out.push({ tone: 'info', key: 'ins_short', vars: { min: Math.max(1, Math.round(h.duration_s / 60)) } });
  if (h.raw_xp > 0 && h.xp > 0) {
    const bonus = Math.round((h.xp / h.raw_xp - 1) * 100);
    if (bonus > 0) out.push({ tone: 'info', key: 'ins_xp_bonus', vars: { pct: bonus } });
  }
  if (h.loot > 0 && h.supplies > 0) {
    const ratio = h.supplies / h.loot;
    if (ratio > 1) out.push({ tone: 'bad', key: 'ins_waste', vars: { pct: Math.round((ratio - 1) * 100) } });
    else if (ratio < 0.5) out.push({ tone: 'good', key: 'ins_cheap', vars: { pct: Math.round(ratio * 100) } });
  }
  if (avg && avg.count >= 2) {
    if (avg.xp_h > 0) {
      const d = Math.round((h.xp_h / avg.xp_h - 1) * 100);
      if (Math.abs(d) >= 10) out.push({ tone: d > 0 ? 'good' : 'bad', key: d > 0 ? 'ins_xp_above' : 'ins_xp_below', vars: { pct: Math.abs(d), n: avg.count } });
    }
    if (avg.balance_h !== 0) {
      const d = h.balance_h - avg.balance_h;
      if (Math.abs(d) >= Math.abs(avg.balance_h) * 0.15) out.push({ tone: d > 0 ? 'good' : 'bad', key: d > 0 ? 'ins_profit_above' : 'ins_profit_below', vars: { gp: Math.abs(d) } });
    }
  }
  const kills = h.monsters.reduce((s, m) => s + m.count, 0);
  if (kills > 0 && h.duration_s > 0) out.push({ tone: 'info', key: 'ins_kills_h', vars: { n: perHour(kills, h.duration_s) } });
  if (h.healing > 0 && h.damage > 0) out.push({ tone: 'info', key: 'ins_heal_ratio', vars: { pct: Math.round((h.healing / h.damage) * 100) } });
  if (input && input.total > 0) {
    const top = input.types[0];
    if (top) out.push({ tone: 'info', key: 'ins_top_damage_type', vars: { type: top.name, pct: top.pct } });
    const src = input.sources[0];
    if (src && src.pct >= 40) out.push({ tone: 'bad', key: 'ins_top_source', vars: { name: src.name, pct: src.pct } });
  }
  return out;
}

/**
 * D-59: the new hunt form has one field per analyser (Party Hunt, Hunt Analyser, Input Analyser, Server Log).
 * Each field is analysed on its own (whatever was pasted in it) and the results are combined:
 * XP, kills and looted items come from the Hunt Analyser; loot/supplies/balance and the members from the Party Hunt.
 */
export function combineAnalyses(parts: PasteAnalysis[]): PasteAnalysis {
  const hunts = parts.map((p) => p.hunt).filter((h): h is HuntData => !!h);
  const party = hunts.find((h) => h.kind === 'party') ?? null;
  const solo = hunts.find((h) => h.kind === 'solo') ?? null;
  let hunt = solo ?? party;
  if (solo && party) {
    const s = Math.max(1, solo.duration_s);
    hunt = {
      ...solo,
      kind: 'party',
      party: party.party,
      loot: party.loot,
      supplies: party.supplies,
      balance: party.balance,
      balance_h: Math.round((party.balance / s) * 3600),
      started_at: solo.started_at ?? party.started_at,
      ended_at: solo.ended_at ?? party.ended_at,
    };
  }
  return {
    hunt,
    input: parts.find((p) => p.input)?.input ?? null,
    serverLog: parts.find((p) => p.serverLog)?.serverLog ?? null,
    formats: [...new Set(parts.flatMap((p) => p.formats))],
  };
}
