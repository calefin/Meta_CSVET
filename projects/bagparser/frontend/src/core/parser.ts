/**
 * BagParser Core — client-side Hunt / Party Hunt Analyser parser (SPEC §5, FR-CORE-*).
 * Pure functions only: no DOM, no network.
 */
import { DESTINATION_ORDER, findItem, type Destination, type LookupDb } from './items';
import { computeLootSplit, type LootSplit } from './party';

export interface SessionMetrics {
  durationMinutes: number;
  xpTotal: number;
  xpHour: number;
  rawXpTotal?: number;
  rawXpHour?: number;
  lootTotal: number;
  suppliesTotal: number;
  balanceNet: number;
  balanceHour: number;
  damageTotal?: number;
  damageHour?: number;
  healingTotal?: number;
  healingHour?: number;
}

export interface ParsedItemRow {
  name: string;
  count: number;
  unitNpcPrice: number;
  totalValue: number;
  weightOz: number;
  isImbuement: boolean;
  marketAlert: boolean;
  sellTo: string[];
}

export interface DestinationBucket {
  items: ParsedItemRow[];
  subtotalGps: number;
  subtotalWeightOz: number;
}

export interface PartyMember {
  name: string;
  isLeader: boolean;
  loot: number;
  supplies: number;
  balance: number;
  damage: number;
  healing: number;
}

export type LogKind = 'solo' | 'party' | 'unknown';

export interface RoutingOutput {
  kind: LogKind;
  metrics: SessionMetrics;
  destinations: Record<Destination, DestinationBucket>;
  totalWeightOz: number;
  killedMonsters: { name: string; count: number }[];
  unrecognizedItems: string[];
  party: { members: PartyMember[]; split: LootSplit } | null;
}

/** "1,234,567" / "1.234.567" / "-12,000" → integer */
export function cleanInt(v?: string): number {
  if (!v) return 0;
  const n = parseInt(v.replace(/[,.\s]/g, ''), 10);
  return Number.isFinite(n) ? n : 0;
}

/** Line-anchored "Label: value" lookup (FR-CORE-04). Only non-indented lines. */
function topLevelField(text: string, label: string): string | undefined {
  const re = new RegExp(`^${label}:[ \\t]*(-?[\\d,.]+)`, 'im');
  return text.match(re)?.[1];
}

const BLOCK_HEADERS: Record<string, 'items' | 'monsters'> = {
  'looted items': 'items',
  'loot items': 'items',
  'killed monsters': 'monsters',
};

/** Splits "Looted Items:" / "Killed Monsters:" blocks into their entry lines. */
function extractBlocks(lines: string[]): { items: string[]; monsters: string[] } {
  const out = { items: [] as string[], monsters: [] as string[] };
  let current: 'items' | 'monsters' | null = null;

  for (const rawLine of lines) {
    const header = rawLine.trim().match(/^([A-Za-z ]+):\s*$/);
    if (header) {
      current = BLOCK_HEADERS[header[1].toLowerCase()] ?? null;
      continue;
    }
    if (!current) continue;

    const line = rawLine.trim();
    if (!line || line.toLowerCase() === 'none') continue;
    // A non-indented "Label: value" line ends the block
    if (!/^\s/.test(rawLine) && /^[A-Za-z /]+:/.test(line) && !/^[\d,.]+x\s/i.test(line)) {
      current = null;
      continue;
    }
    out[current].push(line);
  }
  return out;
}

// "4,120x gold coin" (analyser) or "2 platinum coins" (server log) or "a dragon shield"
const ENTRY_RE = /^(?:([\d,.]+)x\s+|(\d+)\s+)?(.+)$/i;

/** "12x small diamond" | "a dragon shield" | "dragon" → { count, name } */
export function parseEntry(line: string): { count: number; name: string } | null {
  const m = line.match(ENTRY_RE);
  if (!m) return null;
  const rawCount = m[1] ?? m[2];
  const count = rawCount ? cleanInt(rawCount) : 1;
  const name = m[3].trim().replace(/^(?:an?)\s+/i, '');
  return name ? { count, name } : null;
}

/** Party Hunt Analyser: player header line followed by indented Loot/Supplies/... lines. */
function parsePartyMembers(lines: string[]): PartyMember[] {
  const members: PartyMember[] = [];
  let current: PartyMember | null = null;

  for (const rawLine of lines) {
    const isIndented = /^\s+/.test(rawLine);
    const line = rawLine.trim();
    if (!line) continue;

    if (!isIndented && !line.includes(':')) {
      const isLeader = /\(leader\)/i.test(line);
      current = {
        name: line.replace(/\s*\(leader\)\s*/i, '').trim(),
        isLeader,
        loot: 0,
        supplies: 0,
        balance: 0,
        damage: 0,
        healing: 0,
      };
      members.push(current);
      continue;
    }

    if (current && isIndented) {
      const m = line.match(/^(Loot|Supplies|Balance|Damage|Healing):\s*(-?[\d,.]+)/i);
      if (m) {
        const key = m[1].toLowerCase() as 'loot' | 'supplies' | 'balance' | 'damage' | 'healing';
        current[key] = cleanInt(m[2]);
      }
    } else if (!isIndented) {
      current = null;
    }
  }
  return members;
}

function emptyDestinations(): Record<Destination, DestinationBucket> {
  const d = {} as Record<Destination, DestinationBucket>;
  for (const key of DESTINATION_ORDER) d[key] = { items: [], subtotalGps: 0, subtotalWeightOz: 0 };
  return d;
}

export function parseTibiaSession(rawText: string, lookupDb: LookupDb): RoutingOutput {
  const text = rawText.replace(/\r\n?/g, '\n');
  const lines = text.split('\n');

  const sessionMatch = text.match(/^Session:[ \t]*(\d+):(\d{2})h/im);
  let durationMinutes = 60;
  if (sessionMatch) {
    durationMinutes = parseInt(sessionMatch[1], 10) * 60 + parseInt(sessionMatch[2], 10);
    if (durationMinutes === 0) durationMinutes = 1;
  }

  const xpTotal = cleanInt(topLevelField(text, 'XP Gain'));
  const rawXpTotal = cleanInt(topLevelField(text, 'Raw XP Gain'));
  const lootTotal = cleanInt(topLevelField(text, 'Loot'));
  const suppliesTotal = cleanInt(topLevelField(text, 'Supplies'));
  const balanceNet = cleanInt(topLevelField(text, 'Balance'));
  const rawXpHour = cleanInt(topLevelField(text, 'Raw XP/h')) || (rawXpTotal > 0 ? Math.round((rawXpTotal / durationMinutes) * 60) : 0);
  const damageTotal = cleanInt(topLevelField(text, 'Damage'));
  const damageHour = cleanInt(topLevelField(text, 'Damage/h')) || (damageTotal > 0 ? Math.round((damageTotal / durationMinutes) * 60) : 0);
  const healingTotal = cleanInt(topLevelField(text, 'Healing'));
  const healingHour = cleanInt(topLevelField(text, 'Healing/h')) || (healingTotal > 0 ? Math.round((healingTotal / durationMinutes) * 60) : 0);

  const metrics: SessionMetrics = {
    durationMinutes,
    xpTotal,
    xpHour: Math.round((xpTotal / durationMinutes) * 60),
    rawXpTotal,
    rawXpHour,
    lootTotal,
    suppliesTotal,
    balanceNet,
    balanceHour: Math.round((balanceNet / durationMinutes) * 60),
    damageTotal,
    damageHour,
    healingTotal,
    healingHour,
  };

  const destinations = emptyDestinations();
  const unrecognizedItems: string[] = [];
  const blocks = extractBlocks(lines);

  for (const line of blocks.items) {
    const entry = parseEntry(line);
    if (!entry) continue;

    const info = findItem(entry.name, lookupDb);
    if (!info) {
      unrecognizedItems.push(`${entry.count}x ${entry.name}`);
      continue;
    }

    const totalValue = info.npc_price * entry.count;
    const weightOz = Number((info.weight * entry.count).toFixed(2));
    const target: Destination = info.is_imbuement ? 'Market / Imbuement Alerts' : info.vendor_category;

    const bucket = destinations[target];
    const existing = bucket.items.find((i) => i.name === info.name);
    if (existing) {
      existing.count += entry.count;
      existing.totalValue += totalValue;
      existing.weightOz = Number((existing.weightOz + weightOz).toFixed(2));
    } else {
      bucket.items.push({
        name: info.name,
        count: entry.count,
        unitNpcPrice: info.npc_price,
        totalValue,
        weightOz,
        isImbuement: info.is_imbuement,
        marketAlert: info.market_priority_alert || info.is_imbuement,
        sellTo: info.sell_to,
      });
    }
    bucket.subtotalGps += totalValue;
    bucket.subtotalWeightOz = Number((bucket.subtotalWeightOz + weightOz).toFixed(2));
  }

  for (const bucket of Object.values(destinations)) {
    bucket.items.sort((a, b) => b.totalValue - a.totalValue);
  }

  const totalWeightOz = Number(
    Object.values(destinations)
      .reduce((sum, b) => sum + b.subtotalWeightOz, 0)
      .toFixed(2),
  );

  const killedMonsters = blocks.monsters
    .map(parseEntry)
    .filter((e): e is { count: number; name: string } => !!e)
    .sort((a, b) => b.count - a.count);

  const isParty = /^Loot Type:/im.test(text) || /\(leader\)/i.test(text);
  const members = isParty ? parsePartyMembers(lines) : [];
  const party = members.length > 0 ? { members, split: computeLootSplit(members) } : null;

  const kind: LogKind = party ? 'party' : sessionMatch || blocks.items.length ? 'solo' : 'unknown';

  return { kind, metrics, destinations, totalWeightOz, killedMonsters, unrecognizedItems, party };
}
