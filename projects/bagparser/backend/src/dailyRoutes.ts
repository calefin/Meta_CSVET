/**
 * "Today in Tibia" API (D-30): worlds and Dream Scar world offsets (from TibiaWiki).
 * Yasir sightings live in yasirRoutes.ts (D-40).
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import type { AuthGuards } from './accounts.js';

const USER_AGENT = 'BagParser/0.1 (+https://bagparser.com)';
const TIBIADATA = process.env.TIBIADATA_URL ?? 'https://api.tibiadata.com/v4';
const WIKI_API = 'https://tibia.fandom.com/api.php';

/** Server day (YYYY-MM-DD): the Tibia day starts at 10:00 Europe/Berlin. */
export function serverDay(now = new Date()): string {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/Berlin',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(now);
  const get = (t: string) => parseInt(parts.find((p) => p.type === t)!.value, 10);
  const d = new Date(Date.UTC(get('year'), get('month') - 1, get('day')));
  if (get('hour') < 10) d.setUTCDate(d.getUTCDate() - 1);
  return d.toISOString().slice(0, 10);
}

/** Parses "|World=N" lines of Template:Dream Scar Boss/Offsets (general offset added by the client). */
export function parseDreamOffsets(wikitext: string): { general: number; worlds: Record<string, { offset: number; uncertain: boolean }> } {
  const general = Number(/#expr:\s*(\d+)\s*\+/.exec(wikitext)?.[1] ?? 4);
  const worlds: Record<string, { offset: number; uncertain: boolean }> = {};
  for (const line of wikitext.split('\n')) {
    const m = /^\|([A-Z][a-z]+)=(\d+)(.*)$/.exec(line.trim());
    if (m) worlds[m[1]] = { offset: Number(m[2]), uncertain: /uncertain/i.test(m[3]) };
  }
  return { general, worlds };
}

async function cachedJson<T>(redis: Redis, key: string, ttl: number, load: () => Promise<T>): Promise<T> {
  try {
    const hit = await redis.get(key);
    if (hit) return JSON.parse(hit) as T;
  } catch {
    /* no cache */
  }
  const value = await load();
  await redis.set(key, JSON.stringify(value), 'EX', ttl).catch(() => null);
  return value;
}

/** Dream Scar world offsets (TibiaWiki, cached 6 h); without the wiki every world uses the general rotation */
export async function getDreamOffsets(redis: Redis): Promise<{ general: number; worlds: Record<string, { offset: number; uncertain: boolean }>; source: string | null }> {
  try {
    return await cachedJson(redis, 'wiki:dreamscar:offsets', 6 * 3600, async () => {
      const url = `${WIKI_API}?action=parse&page=Template:Dream_Scar_Boss/Offsets&prop=wikitext&format=json`;
      const res = await fetch(url, { headers: { 'User-Agent': USER_AGENT }, signal: AbortSignal.timeout(10_000) });
      if (!res.ok) throw new Error(`wiki HTTP ${res.status}`);
      const json = (await res.json()) as { parse: { wikitext: { '*': string } } };
      return { ...parseDreamOffsets(json.parse.wikitext['*']), source: 'TibiaWiki (CC BY-SA)' };
    });
  } catch {
    return { general: 4, worlds: {}, source: null };
  }
}

export function registerDailyRoutes(
  app: FastifyInstance,
  { db, redis, guards }: { db: pg.Pool; redis: Redis; guards: AuthGuards },
): void {
  /** Upserts TibiaData's world list into `worlds`; worlds missing from the list are marked inactive. */
  async function syncWorlds(): Promise<number> {
    const res = await fetch(`${TIBIADATA}/worlds`, { headers: { 'User-Agent': USER_AGENT }, signal: AbortSignal.timeout(10_000) });
    if (!res.ok) throw new Error(`worlds HTTP ${res.status}`);
    const data = (await res.json()) as {
      worlds: {
        regular_worlds: {
          name: string;
          location: string;
          pvp_type: string;
          transfer_type: string;
          battleye_protected: boolean;
          premium_only: boolean;
          game_world_type: string;
          status: string;
          players_online: number;
        }[];
      };
    };
    const list = data.worlds.regular_worlds;
    if (!list.length) return 0;
    for (const w of list) {
      await db.query(
        `INSERT INTO worlds (name, location, pvp_type, transfer_type, battleye_protected, premium_only, game_world_type,
                             status, players_online, last_seen, active)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, now(), TRUE)
         ON CONFLICT (name) DO UPDATE SET location = EXCLUDED.location, pvp_type = EXCLUDED.pvp_type,
           transfer_type = EXCLUDED.transfer_type, battleye_protected = EXCLUDED.battleye_protected,
           premium_only = EXCLUDED.premium_only, game_world_type = EXCLUDED.game_world_type, status = EXCLUDED.status,
           players_online = EXCLUDED.players_online, last_seen = now(), active = TRUE`,
        [w.name, w.location, w.pvp_type, w.transfer_type, w.battleye_protected, w.premium_only, w.game_world_type, w.status, w.players_online],
      );
    }
    await db.query('UPDATE worlds SET active = FALSE WHERE NOT (name = ANY($1::text[]))', [list.map((w) => w.name)]);
    return list.length;
  }

  async function worldNames(): Promise<string[]> {
    const { rows } = await db.query<{ name: string }>('SELECT name FROM worlds WHERE active ORDER BY name');
    if (rows.length) return rows.map((r) => r.name);
    await syncWorlds();
    return (await db.query<{ name: string }>('SELECT name FROM worlds WHERE active ORDER BY name')).rows.map((r) => r.name);
  }

  // Sync on start and every 6 hours (merges, renames and new worlds show up here)
  const runSync = () =>
    syncWorlds()
      .then((n) => app.log.info(`worlds: synced ${n}`))
      .catch((err) => app.log.warn({ err: (err as Error).message }, 'worlds sync failed'));
  setTimeout(runSync, 5_000).unref();
  setInterval(runSync, 6 * 3600_000).unref();

  app.get('/api/worlds', async () => worldNames());

  app.get('/api/worlds/details', async () => {
    const { rows } = await db.query(
      `SELECT name, location, pvp_type, transfer_type, battleye_protected, premium_only, game_world_type, status,
              players_online, active FROM worlds ORDER BY name`,
    );
    return rows;
  });

  app.get('/api/dreamscar', async () => getDreamOffsets(redis));
  // Yasir reports moved to yasirRoutes.ts (D-40)
}
