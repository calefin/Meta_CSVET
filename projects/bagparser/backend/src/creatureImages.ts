/**
 * Creature sprites (D-57).
 *
 * 1. Official CipSoft library (via TibiaData). TibiaData lists creatures in the PLURAL ("Acolytes Of The Cult")
 *    with race ids that do not always follow the name ("cultacolyte"), so we match our singular names against
 *    the race AND every singular spelling of the official plural name.
 * 2. Everything the official library does not have (bosses, quest and newer creatures) comes from TibiaWiki:
 *    its API returns the real, existing file URL (no guessing); the URL already carries /revision/latest
 *    (a bare file URL answers a grey placeholder). Looked up once and stored in creatures.image_url.
 */
const key = (s: string) => s.toLowerCase().replace(/[^a-z]/g, '');

function singularWord(w: string): string[] {
  const l = w.toLowerCase();
  const out = [w];
  if (/men$/.test(l)) out.push(w.slice(0, -3) + 'man');
  if (/ies$/.test(l)) out.push(w.slice(0, -3) + 'y');
  if (/ves$/.test(l)) out.push(w.slice(0, -3) + 'f', w.slice(0, -3) + 'fe');
  if (/(ches|shes|sses|xes|zes|oes)$/.test(l)) out.push(w.slice(0, -2));
  if (/s$/.test(l) && !/ss$/.test(l)) out.push(w.slice(0, -1));
  if (l === 'mice') out.push('mouse');
  if (l === 'geese') out.push('goose');
  return out;
}

/** Keys of an official (plural) library name: "Acolytes Of The Cult" → acolytesofthecult, acolyteofthecult… */
export function libraryNameKeys(pluralName: string): Set<string> {
  const words = pluralName.split(/\s+/).filter(Boolean);
  const keys = new Set([key(pluralName)]);
  words.forEach((w, i) => {
    for (const s of singularWord(w)) keys.add(key([...words.slice(0, i), s, ...words.slice(i + 1)].join(' ')));
  });
  return keys;
}

export interface LibraryEntry {
  name: string;
  race: string;
  image_url: string;
}

/** key → library entry (race first, then the name variants; the race wins on conflicts) */
export function libraryIndex(list: LibraryEntry[]): Map<string, LibraryEntry> {
  const map = new Map<string, LibraryEntry>();
  for (const c of list) map.set(c.race, c);
  for (const c of list) for (const k of libraryNameKeys(c.name)) if (!map.has(k)) map.set(k, c);
  return map;
}

export const libraryLookup = (index: Map<string, LibraryEntry>, name: string) => index.get(key(name));

const WIKI_API = 'https://tibia.fandom.com/api.php';

/** TibiaWiki file URLs for creature names (50 per request). Names without a file are left out. */
export async function wikiCreatureImages(names: string[], userAgent: string): Promise<Map<string, string>> {
  const out = new Map<string, string>();
  for (let i = 0; i < names.length; i += 50) {
    const batch = names.slice(i, i + 50);
    const titles = batch.map((n) => `File:${n}.gif`).join('|');
    const url = `${WIKI_API}?action=query&prop=imageinfo&iiprop=url|size&format=json&titles=${encodeURIComponent(titles)}`;
    const res = await fetch(url, { headers: { 'User-Agent': userAgent }, signal: AbortSignal.timeout(15_000) });
    if (!res.ok) throw new Error(`wiki HTTP ${res.status}`);
    const json = (await res.json()) as {
      query?: {
        normalized?: { from: string; to: string }[];
        pages?: Record<string, { title: string; imageinfo?: { url: string; width: number; height: number }[] }>;
      };
    };
    // The wiki normalises titles ("File:Bog raider.gif" → "File:Bog Raider.gif"): map back to our name
    const back = new Map((json.query?.normalized ?? []).map((n) => [n.to, n.from]));
    for (const p of Object.values(json.query?.pages ?? {})) {
      const info = p.imageinfo?.[0];
      // Creature sprites are small; skip artwork or anything that is not a sprite
      if (!info?.url || info.width > 160 || info.height > 160) continue;
      const original = (back.get(p.title) ?? p.title).replace(/^File:/, '').replace(/\.gif$/, '');
      out.set(original, info.url);
    }
  }
  return out;
}
