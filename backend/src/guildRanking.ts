/**
 * Guild XP ranking (D-76): once per server day the members of each followed guild are stored with level and
 * experience (TibiaData guild page + the world's experience highscores, top 1000; below that only the level).
 * Yesterday's ranking = today's record − yesterday's record. Shown in the bulletin of groups/channels whose owner
 * is Pro.
 */
import type pg from 'pg';
import type { Redis } from 'ioredis';
import { serverDay } from './dailyRoutes.js';
import { addDays } from './tibiaDay.js';
import { getExperiencePage, getGuild } from './tibiadata.js';

export interface GuildMemberDay {
  name: string;
  level: number;
  experience: number | null;
  vocation: string | null;
}

export interface GuildRankRow {
  name: string;
  vocation: string | null;
  from: number;
  to: number;
  /** XP gained (null when one of the two days has no experience: shown by level only) */
  gain: number | null;
}

export interface GuildRanking {
  guild: string;
  /** No record of the day before yet: the ranking starts tomorrow */
  first: boolean;
  top: GuildRankRow[];
  fell: GuildRankRow[];
}

/** Pure: compares two days of the same guild */
export function rankGuild(guild: string, today: GuildMemberDay[], yesterday: GuildMemberDay[]): GuildRanking {
  if (!yesterday.length) return { guild, first: true, top: [], fell: [] };
  const before = new Map(yesterday.map((m) => [m.name.toLowerCase(), m]));
  const rows: GuildRankRow[] = [];
  for (const m of today) {
    const b = before.get(m.name.toLowerCase());
    if (!b) continue; // joined today
    const gain = m.experience != null && b.experience != null ? m.experience - b.experience : null;
    rows.push({ name: m.name, vocation: m.vocation, from: b.level, to: m.level, gain });
  }
  const score = (r: GuildRankRow) => r.gain ?? (r.to - r.from) * 1e-6; // levels only when XP is unknown
  const top = rows
    .filter((r) => (r.gain ?? 0) > 0 || (r.gain == null && r.to > r.from))
    .sort((a, b) => score(b) - score(a))
    .slice(0, 10);
  const fell = rows.filter((r) => r.to < r.from || (r.gain != null && r.gain < 0)).slice(0, 10);
  return { guild, first: false, top, fell };
}

export function createGuildRanking(o: { db: pg.Pool; redis: Redis }) {
  const { db, redis } = o;

  /** Stores today's record of a guild (once per server day) */
  async function snapshot(guildName: string, day = serverDay()): Promise<boolean> {
    const have = await db.query('SELECT 1 FROM guild_days WHERE guild = $1 AND day = $2 LIMIT 1', [guildName, day]);
    if (have.rowCount) return true;
    if ((await redis.set(`lock:guildday:${guildName.toLowerCase()}:${day}`, '1', 'EX', 600, 'NX').catch(() => 'OK')) !== 'OK') return false;
    const g = await getGuild(redis, guildName);
    if (!g || !g.members.length) return false;
    const want = new Map(g.members.map((m) => [m.name.toLowerCase(), m]));
    const exp = new Map<string, number>();
    // Highscores of the world, page by page until every member is found (at most 20 pages = top 1000)
    for (let page = 1; page <= 20 && exp.size < want.size; page++) {
      const p = await getExperiencePage(redis, g.world, page).catch(() => null);
      if (!p) break;
      for (const e of p.entries) if (want.has(e.name.toLowerCase())) exp.set(e.name.toLowerCase(), e.value);
      if (page >= p.totalPages) break;
    }
    const client = await db.connect();
    try {
      await client.query('BEGIN');
      for (const m of g.members) {
        await client.query(
          `INSERT INTO guild_days (guild, day, name, level, experience, vocation) VALUES ($1, $2, $3, $4, $5, $6) ON CONFLICT DO NOTHING`,
          [g.name, day, m.name, m.level, exp.get(m.name.toLowerCase()) ?? null, m.vocation || null],
        );
      }
      await client.query('COMMIT');
    } catch (err) {
      await client.query('ROLLBACK').catch(() => null);
      throw err;
    } finally {
      client.release();
    }
    return true;
  }

  async function day(guildName: string, d: string): Promise<GuildMemberDay[]> {
    const r = await db.query<{ name: string; level: number; experience: string | null; vocation: string | null }>(
      'SELECT name, level, experience::text AS experience, vocation FROM guild_days WHERE guild = $1 AND day = $2',
      [guildName, d],
    );
    return r.rows.map((x) => ({ name: x.name, level: x.level, experience: x.experience == null ? null : Number(x.experience), vocation: x.vocation }));
  }

  return {
    snapshot,
    /** Ranking of the previous server day for the bulletin */
    async ranking(guildName: string): Promise<GuildRanking | null> {
      const today = serverDay();
      await snapshot(guildName, today).catch(() => false);
      const t = await day(guildName, today);
      if (!t.length) return null;
      return rankGuild(guildName, t, await day(guildName, addDays(today, -1)));
    },
    /** Daily: records every followed guild (run from the notify job) and drops records older than 40 days */
    async daily(): Promise<number> {
      const guilds = (await db.query<{ guild: string }>('SELECT DISTINCT guild FROM notify_targets WHERE guild IS NOT NULL AND daily')).rows;
      let n = 0;
      for (const g of guilds) if (await snapshot(g.guild).catch(() => false)) n++;
      await db.query(`DELETE FROM guild_days WHERE day < CURRENT_DATE - 40`);
      return n;
    },
    /** Canonical guild name and world, or null */
    async lookup(name: string): Promise<{ name: string; world: string } | null> {
      const clean = name.trim().replace(/\s+/g, ' ');
      if (!/^[A-Za-z][A-Za-z' -]{1,40}$/.test(clean)) return null;
      const g = await getGuild(redis, clean).catch(() => null);
      return g ? { name: g.name, world: g.world } : null;
    },
  };
}

export type GuildRankingService = ReturnType<typeof createGuildRanking>;
