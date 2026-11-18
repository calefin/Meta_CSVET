/**
 * TibiaData API v4 client (https://tibiadata.com) — FR-TD-*.
 * Provides daily boosted creature/boss and the creature library (race ids + images).
 * Responses are cached in Redis until the next server save (10:00 Europe/Berlin).
 */
import type { Redis } from 'ioredis';

const BASE_URL = process.env.TIBIADATA_URL ?? 'https://api.tibiadata.com/v4';
const USER_AGENT = 'BagParser/0.1 (+https://github.com/calefin/bagparser)';

export interface Boosted {
  creature: { name: string; race: string; image_url: string } | null;
  boss: { name: string; image_url: string } | null;
}

export interface LibraryCreature {
  name: string;
  race: string;
  image_url: string;
}

async function getJson<T>(path: string): Promise<T> {
  const res = await fetch(`${BASE_URL}${path}`, {
    headers: { 'User-Agent': USER_AGENT, Accept: 'application/json' },
    signal: AbortSignal.timeout(10_000),
  });
  if (!res.ok) throw new Error(`TibiaData ${path} → HTTP ${res.status}`);
  return (await res.json()) as T;
}

/** Seconds until the next 10:00 Europe/Berlin (min 60 s). */
export function secondsUntilServerSave(now: Date = new Date()): number {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/Berlin',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(now);
  const get = (t: string) => parseInt(parts.find((p) => p.type === t)!.value, 10);
  const nowSec = get('hour') * 3600 + get('minute') * 60 + get('second');
  const ss = 10 * 3600;
  const diff = nowSec < ss ? ss - nowSec : 86400 - nowSec + ss;
  return Math.max(60, diff + 120); // small margin: TibiaData refreshes shortly after SS
}

async function cached<T>(redis: Redis, key: string, ttl: number, load: () => Promise<T>): Promise<T> {
  try {
    const hit = await redis.get(key);
    if (hit) return JSON.parse(hit) as T;
  } catch {
    /* cache unavailable: fall through */
  }
  const value = await load();
  try {
    await redis.set(key, JSON.stringify(value), 'EX', ttl);
  } catch {
    /* ignore */
  }
  return value;
}

/** Retry window when TibiaData fails or answers only half of the boosted pair. */
export const BOOSTED_RETRY_SECONDS = 300;

/**
 * Boosted creature + boss. A complete answer is cached until the next server save; a partial or failed one
 * only for a few minutes (it used to stay cached until the next server save, hiding the cards for a day).
 * While TibiaData is down, the last complete answer is served instead of nothing.
 */
export async function getBoosted(redis: Redis): Promise<Boosted> {
  try {
    const hit = await redis.get('tibiadata:boosted');
    if (hit) return JSON.parse(hit) as Boosted;
  } catch {
    /* cache unavailable */
  }
  const [c, b] = await Promise.allSettled([
    getJson<{ creatures: { boosted: { name: string; race: string; image_url: string } } }>('/creatures'),
    getJson<{ boostable_bosses: { boosted: { name: string; image_url: string } } }>('/boostablebosses'),
  ]);
  const fresh: Boosted = {
    creature: c.status === 'fulfilled' ? (c.value.creatures?.boosted ?? null) : null,
    boss: b.status === 'fulfilled' ? (b.value.boostable_bosses?.boosted ?? null) : null,
  };
  const complete = !!fresh.creature && !!fresh.boss;
  let value = fresh;
  if (!complete) {
    const last = await redis.get('tibiadata:boosted:last').catch(() => null);
    const prev = last ? (JSON.parse(last) as Boosted) : null;
    value = { creature: fresh.creature ?? prev?.creature ?? null, boss: fresh.boss ?? prev?.boss ?? null };
  }
  // Right after the server save TibiaData may still serve yesterday's pair: keep re-checking for 30 minutes
  const untilSave = secondsUntilServerSave();
  const justAfterSave = untilSave > 86400 - 1800;
  const ttl = !complete ? BOOSTED_RETRY_SECONDS : justAfterSave ? 600 : untilSave;
  await redis.set('tibiadata:boosted', JSON.stringify(value), 'EX', ttl).catch(() => null);
  if (complete) await redis.set('tibiadata:boosted:last', JSON.stringify(fresh), 'EX', 3 * 86400).catch(() => null);
  return value;
}

export async function getCreatureLibrary(redis: Redis): Promise<LibraryCreature[]> {
  return cached(redis, 'tibiadata:creature_list', 7 * 86400, async () => {
    const data = await getJson<{ creatures: { creature_list: LibraryCreature[] } }>('/creatures');
    return data.creatures.creature_list;
  });
}

/** "Dragon Lord" → "dragonlord" — TibiaData race ids are lowercase names without spaces/punctuation. */
export function raceKey(name: string): string {
  return name.toLowerCase().replace(/[^a-z]/g, '');
}

/** Case-insensitive match ignoring spaces/punctuation ("The Brainstealer" vs DB name). */
export function sameName(a: string | null | undefined, b: string | null | undefined): boolean {
  return !!a && !!b && raceKey(a) === raceKey(b);
}

// ---------------------------------------------------------------------------
// Characters (D-28)
// ---------------------------------------------------------------------------

export class TibiaDataUnavailable extends Error {}

/**
 * Raw /character response, or null when the character does not exist.
 * TibiaData answers unknown names with an error status (seen as HTTP 502 through Cloudflare),
 * so a failure is retried once and then reported as "not found or unavailable" by the caller.
 */
export async function fetchCharacter(name: string): Promise<unknown | null> {
  for (let attempt = 0; attempt < 2; attempt++) {
    const res = await fetch(`${BASE_URL}/character/${encodeURIComponent(name)}`, {
      headers: { 'User-Agent': USER_AGENT, Accept: 'application/json' },
      signal: AbortSignal.timeout(25_000),
    }).catch(() => null);
    if (res?.ok) {
      const json = (await res.json()) as { information?: { status?: { http_code?: number } } };
      const code = json.information?.status?.http_code;
      if (code === 404) return null;
      return json;
    }
    if (res?.status === 404) return null;
    if (attempt === 0) await new Promise((r) => setTimeout(r, 800));
  }
  throw new TibiaDataUnavailable(`character ${name}`);
}

export interface RawHighscorePage {
  entries: { name: string; level: number; value: number }[];
  totalPages: number;
}

/** One page of a world's experience highscores (all vocations), cached 30 min. */
export function getExperiencePage(redis: Redis, world: string, page: number): Promise<RawHighscorePage> {
  const key = `tibiadata:hs:exp:${world.toLowerCase()}:${page}`;
  return cached(redis, key, 1800, async () => {
    const data = await getJson<{
      highscores?: {
        highscore_list?: { name: string; level: number; value: number }[];
        highscore_page?: { total_pages?: number };
      };
    }>(`/highscores/${encodeURIComponent(world)}/experience/all/${page}`);
    return {
      entries: (data.highscores?.highscore_list ?? []).map((e) => ({ name: e.name, level: e.level, value: e.value })),
      totalPages: data.highscores?.highscore_page?.total_pages ?? 1,
    };
  });
}

// ---------------------------------------------------------------------------
// Daily bulletin (D-75): kill statistics of a world and the latest tibia.com news
// ---------------------------------------------------------------------------

export interface KillEntry {
  race: string;
  last_day_killed: number;
  last_day_players_killed: number;
}

/** Kill statistics of one world (refreshed by CipSoft at the server save) */
export async function getKillStatistics(redis: Redis, world: string): Promise<KillEntry[]> {
  return cached(redis, `tibiadata:kills:${world.toLowerCase()}`, secondsUntilServerSave(), async () => {
    const data = await getJson<{ killstatistics?: { entries?: KillEntry[] } }>(`/killstatistics/${encodeURIComponent(world)}`);
    return (data.killstatistics?.entries ?? []).map((e) => ({
      race: String(e.race ?? ''),
      last_day_killed: Number(e.last_day_killed) || 0,
      last_day_players_killed: Number(e.last_day_players_killed) || 0,
    }));
  });
}

export interface TibiaNews {
  id: number;
  date: string;
  news: string;
  type: string;
  url: string;
}

/** News and tickers of the last days on tibia.com (cached for 1 hour) */
export async function getLatestNews(redis: Redis): Promise<TibiaNews[]> {
  return cached(redis, 'tibiadata:news:latest', 3600, async () => {
    const data = await getJson<{ news?: TibiaNews[] }>('/news/latest');
    return (data.news ?? []).filter((n) => n && typeof n.news === 'string' && /^https:\/\/www\.tibia\.com\//.test(String(n.url ?? '')));
  });
}

// ---------------------------------------------------------------------------
// D-76: guild members (for the guild XP ranking)
// ---------------------------------------------------------------------------

export interface GuildInfo {
  name: string;
  world: string;
  members: { name: string; level: number; vocation: string }[];
}

/** Guild with its members (cached 1 hour); null when it does not exist */
export async function getGuild(redis: Redis, name: string): Promise<GuildInfo | null> {
  return cached(redis, `tibiadata:guild:${name.toLowerCase()}`, 3600, async () => {
    const data = await getJson<{ guild?: { name?: string; world?: string; members?: { name: string; level: number; vocation: string }[] } }>(
      `/guild/${encodeURIComponent(name)}`,
    );
    const g = data.guild;
    if (!g?.name || !g.world) return null;
    return {
      name: g.name,
      world: g.world,
      members: (g.members ?? []).map((m) => ({ name: String(m.name), level: Number(m.level) || 0, vocation: String(m.vocation ?? '') })),
    };
  });
}
