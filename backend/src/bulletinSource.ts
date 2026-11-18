/**
 * Gathers the daily bulletin data (D-75): boosted pair, Rashid, Dream Scar, Yasir, Tibiadrome, the world's
 * kill statistics of the previous server day, tibia.com news and, for the owner, yesterday's hunts.
 * Every outside source is optional: when one fails, its lines are simply left out.
 */
import type pg from 'pg';
import type { Redis } from 'ioredis';
import { summarizeKills, type BulletinData, type CharDay, type NewsItem } from './bulletin.js';
import { serverDay } from './dailyRoutes.js';
import { addDays, dreamScarBoss, dromeEndsAt, rashidCity, serverDayRange } from './tibiaDay.js';
import { getBoosted, getKillStatistics, getLatestNews } from './tibiadata.js';
import type { WeekHunt } from './weekly.js';
import type { YasirLookup } from './yasirRoutes.js';

export interface BulletinSource {
  general(world: string | null, now?: Date): Promise<BulletinData>;
  personal(userId: number, day: string, boostedCreature?: string | null): Promise<{ hunts: WeekHunt[]; chars: CharDay[]; soulMissing: string[] }>;
  /** "antica" → "Antica" (active worlds only) */
  world(name: string): Promise<string | null>;
}

export function createBulletinSource(o: { db: pg.Pool; redis: Redis; yasir: YasirLookup; isBoss: (name: string) => boolean; isRareBoss: (name: string) => boolean; soulCore?: (name: string) => string | null }): BulletinSource {
  const { db, redis } = o;

  async function news(day: string): Promise<NewsItem[]> {
    const since = addDays(day, -3);
    const list = await getLatestNews(redis).catch(() => []);
    return list
      .filter((n) => n.date.slice(0, 10) >= since)
      .sort((a, b) => b.date.localeCompare(a.date))
      .slice(0, 3)
      .map((n) => ({ date: n.date.slice(0, 10), title: n.news.replace(/\s+/g, ' ').trim(), url: n.url, ticker: n.type === 'ticker' }));
  }

  /** D-76: one public supporter of the last 30 days, a different one each day */
  async function supporter(day: string): Promise<string | null> {
    const r = await db.query<{ public_name: string }>(
      `SELECT public_name FROM (SELECT DISTINCT ON (lower(public_name)) public_name, id FROM donations
          WHERE status = 'paid' AND public_name IS NOT NULL AND paid_at > now() - interval '30 days' ORDER BY lower(public_name), id) x ORDER BY id`,
    );
    if (!r.rows.length) return null;
    const n = Math.floor(Date.parse(`${day}T00:00:00Z`) / 86_400_000);
    return r.rows[n % r.rows.length].public_name;
  }

  return {
    async general(world, now = new Date()) {
      const day = serverDay(now);
      const [boosted, yasir, kills, latest, thanks] = await Promise.all([
        getBoosted(redis).catch(() => ({ creature: null, boss: null })),
        world ? o.yasir.today(world).catch(() => null) : Promise.resolve(null),
        world ? getKillStatistics(redis, world).catch(() => null) : Promise.resolve(null),
        news(day),
        supporter(day).catch(() => null),
      ]);
      return {
        day,
        now: now.getTime(),
        world,
        boosted: { creature: boosted.creature?.name ?? null, boss: boosted.boss?.name ?? null },
        rashid: rashidCity(day),
        dreamScar: dreamScarBoss(0, now.getTime()),
        yasir,
        dromeEndsAt: dromeEndsAt(now.getTime()),
        kills: kills ? summarizeKills(kills, o.isBoss, o.isRareBoss) : null,
        news: latest,
        personal: null,
        supporter: thanks,
      };
    },

    async personal(userId, day, boostedCreature) {
      const { start, end } = serverDayRange(day);
      const soul = boostedCreature && o.soulCore ? o.soulCore(boostedCreature) : null;
      const [hunts, chars, missing] = await Promise.all([
        db.query<WeekHunt>(
          `SELECT character_name, place_name, duration_s, xp::float8 AS xp, xp_h::float8 AS xp_h, balance::float8 AS balance,
                  extra_expenses::float8 AS extra_expenses
             FROM hunt_sessions WHERE user_id = $1 AND COALESCE(started_at, created_at) >= $2 AND COALESCE(started_at, created_at) < $3`,
          [userId, start, end],
        ),
        // Gain = last snapshot before the end − last snapshot before the start (daily snapshots come from the Pro
        // tracking); pace = XP since the oldest snapshot of the last 8 days, per day; goal = open goal of the character
        db.query<CharDay>(
          `SELECT c.name, COALESCE(a.level, b.level)::int AS level_from, b.level::int AS level_to,
                  GREATEST(0, b.experience - COALESCE(a.experience, b.experience))::float8 AS exp_gain,
                  b.experience::float8 AS exp_now,
                  CASE WHEN w.taken_at IS NOT NULL AND b.taken_at - w.taken_at >= interval '2 days'
                       THEN GREATEST(0, b.experience - w.experience) / (EXTRACT(EPOCH FROM b.taken_at - w.taken_at) / 86400)
                  END::float8 AS avg_day,
                  (SELECT COUNT(*) FROM character_deaths d WHERE d.character_id = c.id AND d.time >= $2 AND d.time < $3)::int AS deaths,
                  g.target_level AS goal_level, g.target_date::text AS goal_date
             FROM characters c
             JOIN LATERAL (SELECT level, experience, taken_at FROM character_snapshots s WHERE s.character_id = c.id AND s.taken_at < $3
                            ORDER BY taken_at DESC LIMIT 1) b ON TRUE
             LEFT JOIN LATERAL (SELECT level, experience FROM character_snapshots s WHERE s.character_id = c.id AND s.taken_at < $2
                                 ORDER BY taken_at DESC LIMIT 1) a ON TRUE
             LEFT JOIN LATERAL (SELECT experience, taken_at FROM character_snapshots s
                                 WHERE s.character_id = c.id AND s.taken_at >= $3::timestamptz - interval '8 days'
                                 ORDER BY taken_at ASC LIMIT 1) w ON TRUE
             LEFT JOIN character_goals g ON g.character_id = c.id AND g.achieved_at IS NULL
            WHERE c.user_id = $1`,
          [userId, start, end],
        ),
        // D-76: characters that use the soul core tracker (at least one) and still lack today's boosted creature
        soul
          ? db
              .query<{ name: string }>(
                `SELECT c.name FROM characters c
                  WHERE c.user_id = $1 AND EXISTS (SELECT 1 FROM character_soulcores s WHERE s.character_id = c.id)
                    AND NOT EXISTS (SELECT 1 FROM character_soulcores s WHERE s.character_id = c.id AND lower(s.creature) = lower($2))
                  ORDER BY c.name`,
                [userId, soul],
              )
              .then((r) => r.rows.map((x) => x.name))
          : Promise.resolve([] as string[]),
      ]);
      return { hunts: hunts.rows, chars: chars.rows.sort((x, y) => y.exp_gain - x.exp_gain || x.name.localeCompare(y.name)), soulMissing: missing };
    },

    async world(name) {
      const clean = name.trim();
      if (!/^[A-Za-z]{3,20}$/.test(clean)) return null;
      const r = await db.query<{ name: string }>('SELECT name FROM worlds WHERE lower(name) = lower($1) AND active LIMIT 1', [clean]);
      return r.rows[0]?.name ?? null;
    },
  };
}
