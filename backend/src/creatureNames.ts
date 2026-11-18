/**
 * Creature name hygiene (D-52). The hunting places import (TibiaWiki) wrote some creatures with other spellings
 * ("Bog_Raider", "Bone Beast", "Nightmare scion") next to the existing rows ("Bog Raider", "Bonebeast"…).
 * Both map to the same TibiaData race, so the image sync hit the unique index on `race` and stopped.
 * Here: one key per creature, a rule for which spelling wins, and a merge of the duplicates.
 */
import type pg from 'pg';

/** Same rule as TibiaData's race id: letters only, lower case ("Bog_Raider" → "bograider") */
export const creatureKey = (name: string): string => name.toLowerCase().replace(/[^a-z]/g, '');

/** Wiki page title → display name: "Bog_Raider" → "Bog Raider" */
export const cleanCreatureName = (name: string): string => name.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();

export interface CreatureRow {
  id: number;
  name: string;
  race: string | null;
}

/**
 * Which row of a duplicate group survives: already linked to TibiaData (official spelling) first, then no
 * underscores, then the official capitalisation style (every word capitalised, small words excepted), then the oldest.
 */
export function pickCanonical<T extends CreatureRow>(group: T[]): T {
  const titleCase = (n: string) =>
    n.split(' ').every((w, i) => (i > 0 && /^(of|the|and|in|on|a)$/.test(w)) || /^[A-Z'"(]/.test(w));
  const score = (r: T) => (r.race ? 8 : 0) + (r.name.includes('_') ? 0 : 4) + (titleCase(r.name) ? 2 : 0);
  return [...group].sort((a, b) => score(b) - score(a) || a.id - b.id)[0];
}

/** Groups rows that are the same creature; only groups with more than one row */
export function duplicateGroups<T extends CreatureRow>(rows: T[]): T[][] {
  const groups = new Map<string, T[]>();
  for (const r of rows) {
    const k = creatureKey(r.name);
    if (!k) continue;
    groups.set(k, [...(groups.get(k) ?? []), r]);
  }
  return [...groups.values()].filter((g) => g.length > 1);
}

/** Merges duplicate creature rows into one (spawns, race, image, task data). Returns how many rows were removed. */
export async function mergeDuplicateCreatures(db: pg.Pool): Promise<number> {
  const { rows } = await db.query<CreatureRow>('SELECT id, name, race FROM creatures');
  const groups = duplicateGroups(rows);
  if (!groups.length && !rows.some((r) => r.name.includes('_'))) return 0;
  const client = await db.connect();
  let removed = 0;
  try {
    await client.query('BEGIN');
    for (const group of groups) {
      const keep = pickCanonical(group);
      for (const other of group) {
        if (other.id === keep.id) continue;
        // Spawns move to the surviving row unless that place already lists it
        await client.query(
          `UPDATE spawn_densities SET creature_id = $1
            WHERE creature_id = $2
              AND hunting_zone_id NOT IN (SELECT hunting_zone_id FROM spawn_densities WHERE creature_id = $1)`,
          [keep.id, other.id],
        );
        await client.query('DELETE FROM spawn_densities WHERE creature_id = $1', [other.id]);
        // Keep whatever data only the duplicate had (race is unique: free it first)
        const { rows: dup } = await client.query<{ race: string | null; image_url: string | null; grizzly_task_points: number | null; is_weekly_bounty: boolean | null }>(
          'UPDATE creatures SET race = NULL WHERE id = $1 RETURNING $2::text AS race, image_url, grizzly_task_points, is_weekly_bounty',
          [other.id, other.race],
        );
        const d = dup[0];
        await client.query(
          `UPDATE creatures SET race = COALESCE(race, $2), image_url = COALESCE(image_url, $3),
                  grizzly_task_points = GREATEST(COALESCE(grizzly_task_points, 0), COALESCE($4, 0)),
                  is_weekly_bounty = COALESCE(is_weekly_bounty, FALSE) OR COALESCE($5, FALSE)
            WHERE id = $1`,
          [keep.id, d?.race ?? null, d?.image_url ?? null, d?.grizzly_task_points ?? null, d?.is_weekly_bounty ?? null],
        );
        await client.query('DELETE FROM creatures WHERE id = $1', [other.id]);
        removed++;
      }
      // "Bog_Raider" alone in its group could still be the survivor: give it a clean name
      const clean = cleanCreatureName(keep.name);
      if (clean !== keep.name) await client.query('UPDATE creatures SET name = $2 WHERE id = $1', [keep.id, clean]);
    }
    // No duplicates are left, so a clean spelling cannot collide with another row
    await client.query(`UPDATE creatures SET name = regexp_replace(replace(name, '_', ' '), '\\s+', ' ', 'g') WHERE name LIKE '%\\_%'`);
    await client.query('COMMIT');
  } catch (err) {
    await client.query('ROLLBACK').catch(() => undefined);
    throw err;
  } finally {
    client.release();
  }
  return removed;
}
