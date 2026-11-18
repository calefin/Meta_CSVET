/**
 * Hunting places directory (D-42). Source: shared/hunting-places.json (scripts/import-hunting-places.mjs, TibiaWiki
 * CC BY-SA) + manual fixes in shared/hunting-places.overrides.json. Seeded into hunting_zones / spawn_densities on
 * start (only when the files changed), which also feeds SpawnRank.
 *
 * GET /api/hunting-places        → compact list for the directory (filters run in the browser)
 * GET /api/hunting-places/:slug  → one place with its creatures
 */
import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import { cleanCreatureName, creatureKey } from './creatureNames.js';

export interface HuntingPlace {
  name: string;
  wikiTitle: string;
  city: string | null;
  location: string | null;
  vocation: string | null;
  implemented: string | null;
  level: { knights: number | null; paladins: number | null; mages: number | null };
  skill: { knights: number | null; paladins: number | null; mages: number | null };
  expStars: number | null;
  lootStars: number | null;
  exp: string | null;
  loot: string | null;
  mainCreature: string | null;
  coords: { x: number; y: number; z: number } | null;
  creatures: string[];
  bestLoot: string[];
  accessQuests: string[];
}

export const slugify = (name: string): string =>
  name
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/'/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');

/** Density 1..5 is not on the wiki: the infobox picture creature is the main one, the first listed come next. */
export function estimateDensity(place: Pick<HuntingPlace, 'mainCreature' | 'creatures'>, creature: string, index: number): number {
  if (place.mainCreature && creature.toLowerCase() === place.mainCreature.toLowerCase()) return 5;
  if (place.creatures.length <= 3) return 4;
  return index < 3 ? 4 : 3;
}

const clamp = (v: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, v));

/** Lowest recommended level among vocations (0 when the wiki gives none) */
export function minLevel(p: Pick<HuntingPlace, 'level'>): number {
  const levels = [p.level.knights, p.level.paladins, p.level.mages].filter((v): v is number => v != null);
  return levels.length ? Math.min(...levels) : 0;
}

export async function loadHuntingPlaces(file: string, overridesFile: string): Promise<{ places: HuntingPlace[]; hash: string }> {
  let raw = '';
  let over = '';
  try {
    raw = await readFile(file, 'utf8');
  } catch {
    return { places: [], hash: '' };
  }
  try {
    over = await readFile(overridesFile, 'utf8');
  } catch {
    /* optional */
  }
  const places = (JSON.parse(raw) as { places: HuntingPlace[] }).places ?? [];
  const fixes = over ? ((JSON.parse(over) as { places?: Record<string, Partial<HuntingPlace>> }).places ?? {}) : {};
  const merged = places.map((p) => ({ ...p, ...(fixes[p.name] ?? {}) }));
  return { places: merged, hash: createHash('sha256').update(raw).update(over).digest('hex').slice(0, 16) };
}

/** Upsert every place, its creatures and densities; drops the old sample zones. Skips when nothing changed. */
export async function seedHuntingPlaces(db: pg.Pool, places: HuntingPlace[], hash: string): Promise<number> {
  if (!places.length) return 0;
  const seen = await db.query<{ value: string }>(`SELECT value FROM app_meta WHERE key = 'hunting_places_hash'`);
  if (seen.rows[0]?.value === hash) return 0;

  const client = await db.connect();
  try {
    await client.query('BEGIN');
    // Wiki titles come as "Bog_Raider" / "Nightmare scion": reuse the creature that already exists under any
    // spelling (same letters, D-52) and only create the ones we really do not have, with a clean name
    const names = [...new Set(places.flatMap((p) => p.creatures))];
    const byKey = new Map(
      (await client.query<{ id: number; name: string }>('SELECT id, name FROM creatures')).rows.map((r) => [creatureKey(r.name), r.id]),
    );
    const creatureIds = new Map<string, number>();
    for (const raw of names) {
      const key = creatureKey(raw);
      if (!key) continue;
      let id = byKey.get(key);
      if (id == null) {
        const ins = await client.query<{ id: number }>(
          'INSERT INTO creatures (name) VALUES ($1) ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name RETURNING id',
          [cleanCreatureName(raw)],
        );
        id = ins.rows[0].id;
        byKey.set(key, id);
      }
      creatureIds.set(raw, id);
    }

    const slugs: string[] = [];
    for (const p of places) {
      const slug = slugify(p.name);
      if (!slug || slugs.includes(slug)) continue;
      slugs.push(slug);
      const { rows } = await client.query<{ id: number }>(
        `INSERT INTO hunting_zones (name, city, recommended_min_level, recommended_vocation, access_quest, map_coordinates,
                                    profitability_factor, access_friction, slug, source, wiki_title, location,
                                    lvl_knights, lvl_paladins, lvl_mages, sk_knights, sk_paladins, sk_mages,
                                    exp_stars, loot_stars, exp_text, loot_text, coord_x, coord_y, coord_z,
                                    best_loot, access_quests, main_creature)
         VALUES ($1, $2, $3, 'ALL', $4, $5, $6, $7, $8, 'wiki', $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20,
                 $21, $22, $23, $24, $25, $26)
         ON CONFLICT (slug) DO UPDATE SET
           name = EXCLUDED.name, city = EXCLUDED.city, recommended_min_level = EXCLUDED.recommended_min_level,
           access_quest = EXCLUDED.access_quest, map_coordinates = EXCLUDED.map_coordinates,
           profitability_factor = EXCLUDED.profitability_factor, access_friction = EXCLUDED.access_friction,
           source = 'wiki', wiki_title = EXCLUDED.wiki_title, location = EXCLUDED.location,
           lvl_knights = EXCLUDED.lvl_knights, lvl_paladins = EXCLUDED.lvl_paladins, lvl_mages = EXCLUDED.lvl_mages,
           sk_knights = EXCLUDED.sk_knights, sk_paladins = EXCLUDED.sk_paladins, sk_mages = EXCLUDED.sk_mages,
           exp_stars = EXCLUDED.exp_stars, loot_stars = EXCLUDED.loot_stars, exp_text = EXCLUDED.exp_text,
           loot_text = EXCLUDED.loot_text, coord_x = EXCLUDED.coord_x, coord_y = EXCLUDED.coord_y, coord_z = EXCLUDED.coord_z,
           best_loot = EXCLUDED.best_loot, access_quests = EXCLUDED.access_quests, main_creature = EXCLUDED.main_creature
         RETURNING id`,
        [
          p.name.slice(0, 150),
          (p.city ?? '—').slice(0, 80),
          minLevel(p),
          p.accessQuests[0]?.slice(0, 200) ?? null,
          p.coords ? `${p.coords.x},${p.coords.y},${p.coords.z}` : null,
          clamp(p.lootStars ?? 3, 1, 5),
          p.accessQuests.length ? 2 : 0,
          slug,
          p.wikiTitle,
          p.location,
          p.level.knights,
          p.level.paladins,
          p.level.mages,
          p.skill.knights,
          p.skill.paladins,
          p.skill.mages,
          p.expStars,
          p.lootStars,
          p.exp,
          p.loot,
          p.coords?.x ?? null,
          p.coords?.y ?? null,
          p.coords?.z ?? null,
          p.bestLoot,
          p.accessQuests,
          p.mainCreature,
        ],
      );
      const zoneId = rows[0].id;
      await client.query('DELETE FROM spawn_densities WHERE hunting_zone_id = $1', [zoneId]);
      const ids: number[] = [];
      const tiers: number[] = [];
      const positions: number[] = [];
      p.creatures.forEach((c, i) => {
        const id = creatureIds.get(c);
        if (!id || ids.includes(id)) return;
        ids.push(id);
        tiers.push(estimateDensity(p, c, i));
        positions.push(i);
      });
      await client.query(
        `INSERT INTO spawn_densities (hunting_zone_id, creature_id, density_tier, spawn_distribution_note, position)
         SELECT $1, c, t, NULL, pos FROM unnest($2::int[], $3::int[], $4::int[]) AS u(c, t, pos)`,
        [zoneId, ids, tiers, positions],
      );
    }
    // The four hand-written samples (init.sql) are replaced by the wiki data
    await client.query(`DELETE FROM hunting_zones WHERE source = 'sample' OR (source = 'wiki' AND NOT (slug = ANY($1::text[])))`, [slugs]);
    await client.query(
      `INSERT INTO app_meta (key, value) VALUES ('hunting_places_hash', $1)
       ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value`,
      [hash],
    );
    await client.query('COMMIT');
    return slugs.length;
  } catch (err) {
    await client.query('ROLLBACK').catch(() => undefined);
    throw err;
  } finally {
    client.release();
  }
}

export function registerHuntingPlaceRoutes(app: FastifyInstance, opts: { db: pg.Pool }) {
  const { db } = opts;

  app.get('/api/hunting-places', async (_req, reply) => {
    const { rows } = await db.query(
      `SELECT z.slug, z.name, z.city, z.lvl_knights, z.lvl_paladins, z.lvl_mages, z.exp_stars, z.loot_stars,
              z.access_quests, (z.coord_x IS NOT NULL) AS has_map,
              COALESCE((SELECT array_agg(c.name ORDER BY sd.position) FROM spawn_densities sd
                          JOIN creatures c ON c.id = sd.creature_id WHERE sd.hunting_zone_id = z.id), '{}') AS creatures
         FROM hunting_zones z WHERE z.slug IS NOT NULL ORDER BY z.name`,
    );
    reply.header('Cache-Control', 'public, max-age=3600');
    return rows;
  });

  app.get<{ Params: { slug: string } }>('/api/hunting-places/:slug', async (req, reply) => {
    const { rows } = await db.query(
      `SELECT id, slug, name, city, location, wiki_title, lvl_knights, lvl_paladins, lvl_mages, sk_knights, sk_paladins,
              sk_mages, exp_stars, loot_stars, exp_text, loot_text, coord_x, coord_y, coord_z, best_loot, access_quests,
              main_creature
         FROM hunting_zones WHERE slug = $1`,
      [req.params.slug],
    );
    const place = rows[0];
    if (!place) return reply.code(404).send({ error: 'not_found' });
    const creatures = await db.query(
      `SELECT c.id, c.name, c.image_url, c.grizzly_task_points, sd.density_tier
         FROM spawn_densities sd JOIN creatures c ON c.id = sd.creature_id
        WHERE sd.hunting_zone_id = $1 ORDER BY sd.position`,
      [place.id],
    );
    reply.header('Cache-Control', 'public, max-age=3600');
    const { id: _id, ...rest } = place;
    return { ...rest, creatures: creatures.rows };
  });
}
