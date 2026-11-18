/**
 * Creature sheet (D-80): TibiaWiki details from shared/creature-wiki.json (abilities, drops, traits, notes), the stats
 * from shared/creatures.json and the hunting places where it spawns. Kept in memory and served one creature at a time
 * so the browser does not download 2,5 MB.
 *
 * GET /api/creature-info/:slug → one creature
 */
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import type { FastifyInstance } from 'fastify';
import { creatureKey } from './creatureNames.js';
import { minLevel, slugify, type HuntingPlace } from './huntingPlaces.js';

interface WikiEntry {
  name: string;
  [key: string]: unknown;
}
interface StatsEntry {
  name: string;
  [key: string]: unknown;
}
export interface CreaturePlace {
  slug: string;
  name: string;
  city: string | null;
  level: number;
}
export type CreatureSheet = WikiEntry & { stats: StatsEntry | null; places: CreaturePlace[] };

/** How many places a sheet lists (common creatures spawn in dozens) */
const MAX_PLACES = 30;

export function buildCreatureSheets(
  wiki: Record<string, WikiEntry>,
  stats: StatsEntry[],
  places: Pick<HuntingPlace, 'name' | 'city' | 'level' | 'creatures'>[],
): Map<string, CreatureSheet> {
  const statsByKey = new Map(stats.map((s) => [creatureKey(s.name), s]));
  const placesByKey = new Map<string, CreaturePlace[]>();
  for (const p of places) {
    const entry: CreaturePlace = { slug: slugify(p.name), name: p.name, city: p.city, level: minLevel(p) };
    for (const key of new Set(p.creatures.map(creatureKey))) placesByKey.set(key, [...(placesByKey.get(key) ?? []), entry]);
  }
  const sheets = new Map<string, CreatureSheet>();
  for (const [slug, entry] of Object.entries(wiki)) {
    const key = creatureKey(entry.name);
    const where = [...(placesByKey.get(key) ?? [])].sort((a, b) => a.level - b.level || a.name.localeCompare(b.name)).slice(0, MAX_PLACES);
    sheets.set(slug, { ...entry, stats: statsByKey.get(key) ?? null, places: where });
  }
  return sheets;
}

/** Missing files are not fatal: the rest of the site works without the sheets */
export async function loadCreatureSheets(sharedDir: string): Promise<Map<string, CreatureSheet>> {
  const read = async <T>(file: string): Promise<T> => JSON.parse(await readFile(path.join(sharedDir, file), 'utf8')) as T;
  try {
    const [{ creatures: wiki }, { creatures: stats }, { places }] = await Promise.all([
      read<{ creatures: Record<string, WikiEntry> }>('creature-wiki.json'),
      read<{ creatures: StatsEntry[] }>('creatures.json'),
      read<{ places: HuntingPlace[] }>('hunting-places.json'),
    ]);
    return buildCreatureSheets(wiki, stats, places);
  } catch {
    return new Map();
  }
}

export function registerCreatureInfoRoutes(app: FastifyInstance, opts: { sheets: Map<string, CreatureSheet> }) {
  app.get<{ Params: { slug: string } }>('/api/creature-info/:slug', async (req, reply) => {
    const sheet = opts.sheets.get(req.params.slug.toLowerCase());
    if (!sheet) return reply.code(404).send({ error: 'not_found' });
    reply.header('Cache-Control', 'public, max-age=3600');
    return sheet;
  });
}
