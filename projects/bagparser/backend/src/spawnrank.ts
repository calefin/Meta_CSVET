/** SpawnRank score (SPEC §6, FR-SR-01). */

export interface ScoreInput {
  density: number; // D 1..5
  synergy: number; // S 0..5
  profitability: number; // P 1..5
  accessFriction: number; // A 0..5
}

const clamp = (v: number, min: number, max: number) => Math.min(max, Math.max(min, v));

export function spawnScore({ density, synergy, profitability, accessFriction }: ScoreInput): number {
  const D = clamp(density, 1, 5);
  const S = clamp(synergy, 0, 5);
  const P = clamp(profitability, 1, 5);
  const A = clamp(accessFriction, 0, 5);
  return Math.round((D * 0.5 + S * 0.25 + P * 0.15 - A * 0.1) * 100) / 100;
}

/**
 * Zones where the creature spawns, with S = number of *other* Grizzly Adams task creatures
 * in the same zone (capped at 5 by spawnScore). Weekly tasks are per character (D-34).
 */
export const SPAWNRANK_SQL = `
  SELECT z.id, z.slug, z.name, z.city, z.recommended_min_level, z.recommended_vocation, z.access_quest, z.access_quests,
         z.exp_stars, z.loot_stars, z.lvl_knights, z.lvl_paladins, z.lvl_mages,
         z.profitability_factor, z.access_friction,
         sd.density_tier, sd.spawn_distribution_note,
         (SELECT COUNT(*) FROM spawn_densities o
            JOIN creatures oc ON oc.id = o.creature_id
           WHERE o.hunting_zone_id = z.id AND o.creature_id <> $1
             AND oc.grizzly_task_points > 0)::int AS synergy
    FROM spawn_densities sd
    JOIN hunting_zones z ON z.id = sd.hunting_zone_id
   WHERE sd.creature_id = $1
`;
