/**
 * Item sprite lookup (D-35). /sprites/items.json maps each item slug to its image path on TibiaWiki;
 * the browser loads the image from there (the wiki CDN refuses automated bulk downloads).
 */

/** Same rule as scripts/import-sprites.mjs */
export const spriteSlug = (name: string): string =>
  name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

export interface SpriteIndex {
  /** CipSoft image server, by client item id (Char Bazaar objects) */
  official: string;
  /** TibiaWiki image CDN (fallback) */
  wiki: string;
  sprites: Record<string, [number, string]>;
}

let index: SpriteIndex | null = null;
let loading: Promise<SpriteIndex> | null = null;

const EMPTY: SpriteIndex = { official: '', wiki: '', sprites: {} };

export function loadSpriteManifest(): Promise<SpriteIndex> {
  if (index) return Promise.resolve(index);
  loading ??= fetch('/sprites/items.json')
    .then((r) => (r.ok ? r.json() : EMPTY))
    .then((m: SpriteIndex) => (index = m))
    .catch(() => (index = EMPTY));
  return loading;
}

/**
 * The TibiaWiki CDN answers a bare file URL with a 300×171 grey placeholder; the real image only comes
 * through the revision endpoint (checked 2026-11-06).
 */
export const wikiImage = (url: string): string =>
  url.includes('/revision/latest') ? url : `${url}/revision/latest?path-prefix=en`;

/** Image URLs to try in order: official CipSoft server first, TibiaWiki second. */
export function spriteUrls(name: string, idx: SpriteIndex | null): string[] {
  const entry = idx?.sprites[spriteSlug(name)];
  if (!entry || !idx) return [];
  const [id, wikiPath] = entry;
  return [...(id ? [`${idx.official}${id}.gif`] : []), ...(wikiPath ? [wikiImage(idx.wiki + wikiPath)] : [])];
}
