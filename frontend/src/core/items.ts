import catalog from '../../../shared/items.json';

export type Destination =
  | 'Rashid'
  | 'Yasir'
  | 'Green Djinn'
  | 'Blue Djinn'
  | 'Market / Imbuement Alerts'
  | 'Other NPCs'
  | 'Coins';

/** Display order of destinations (FR-CORE-07) */
export const DESTINATION_ORDER: Destination[] = [
  'Market / Imbuement Alerts',
  'Rashid',
  'Yasir',
  'Green Djinn',
  'Blue Djinn',
  'Other NPCs',
  'Coins',
];

/** Shape expected by parseTibiaSession (keys follow SPEC §5) */
export interface ItemInfo {
  name: string;
  npc_price: number;
  weight: number;
  vendor_category: Destination;
  is_imbuement: boolean;
  is_task_creature_product: boolean;
  market_priority_alert: boolean;
  /** NPCs that pay the full npc_price (TibiaWiki "sellto") */
  sell_to: string[];
}

export type LookupDb = Record<string, ItemInfo>;

interface CatalogEntry {
  name: string;
  npc_price: number;
  vendor: string;
  weight: number;
  imbuement?: boolean;
  task_product?: boolean;
  market_alert?: boolean;
  sell_to?: string[];
  plural?: string;
}

export function buildLookupDb(entries: CatalogEntry[]): LookupDb {
  const db: LookupDb = {};
  for (const e of entries) {
    const info: ItemInfo = {
      name: e.name,
      npc_price: e.npc_price,
      weight: e.weight,
      vendor_category: (DESTINATION_ORDER as string[]).includes(e.vendor) ? (e.vendor as Destination) : 'Other NPCs',
      is_imbuement: !!e.imbuement,
      is_task_creature_product: !!e.task_product,
      market_priority_alert: !!e.market_alert,
      sell_to: e.sell_to ?? [],
    };
    db[e.name.toLowerCase()] = info;
    // Official plural from TibiaWiki ("pieces of draconian steel"), without overriding a real item name
    const plural = e.plural?.toLowerCase();
    if (plural && !db[plural]) db[plural] = info;
  }
  return db;
}

export const defaultLookupDb: LookupDb = buildLookupDb((catalog as { items: CatalogEntry[] }).items);

function singularizeWord(word: string): string[] {
  const out: string[] = [];
  if (word.endsWith('ies')) out.push(word.slice(0, -3) + 'y');
  if (word.endsWith('ves')) out.push(word.slice(0, -3) + 'f', word.slice(0, -3) + 'fe');
  if (word.endsWith('es')) out.push(word.slice(0, -2));
  if (word.endsWith('s')) out.push(word.slice(0, -1));
  if (word === 'teeth') out.push('tooth');
  return out;
}

/**
 * Candidate catalog keys for a raw name: as-is, then plural → singular variants.
 * "pieces of draconian steel" → "piece of draconian steel" (head noun before "of").
 */
export function nameCandidates(rawName: string): string[] {
  const base = rawName
    .toLowerCase()
    .trim()
    .replace(/\.$/, '')
    .replace(/^(?:an?|the)\s+/, '')
    .replace(/\s+/g, ' ');

  const candidates = [base];
  const ofIdx = base.indexOf(' of ');
  if (ofIdx > 0) {
    const head = base.slice(0, ofIdx);
    const tail = base.slice(ofIdx);
    const headWords = head.split(' ');
    const last = headWords.pop()!;
    for (const s of singularizeWord(last)) candidates.push([...headWords, s].join(' ') + tail);
  } else {
    const words = base.split(' ');
    const last = words.pop()!;
    for (const s of singularizeWord(last)) candidates.push([...words, s].join(' '));
  }
  return candidates;
}

export function findItem(rawName: string, db: LookupDb): ItemInfo | undefined {
  for (const key of nameCandidates(rawName)) {
    const hit = db[key];
    if (hit) return hit;
  }
  return undefined;
}
