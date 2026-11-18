/**
 * Weekly tasks per character (D-37) and "where does it drop" lookups.
 * Week = Monday 10:00 Europe/Berlin (server save) to next Monday; older weeks stay as history.
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import type { AuthGuards } from './accounts.js';
import { serverDay } from './dailyRoutes.js';

const WIKI_API = 'https://tibia.fandom.com/api.php';
const USER_AGENT = 'BagParser/0.2 (+https://bagparser.com)';
const MAX_TASKS_PER_WEEK = 40;

/** Monday (YYYY-MM-DD) of the server week containing `day` (server days start at 10:00 Berlin). */
export function weekStart(day = serverDay()): string {
  const d = new Date(`${day}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 6) % 7));
  return d.toISOString().slice(0, 10);
}

/** Creature names in an item's {{Dropped By|...}} template */
export function parseDroppedBy(wikitext: string): string[] {
  const m = /\|\s*droppedby\s*=\s*\{\{Dropped By\|([^}]*)\}\}/i.exec(wikitext);
  if (!m) return [];
  return m[1]
    .split('|')
    .map((s) => s.trim())
    .filter((s) => s && !s.includes('='));
}

/** Drop rate of `item` in a "Loot Statistics:<Creature>" page ({{Loot2 |kills=N |Item, times:T ...}}). */
export function parseLootRate(wikitext: string, item: string): { rate: number; kills: number } | null {
  const kills = Number(/\|\s*kills\s*=\s*(\d+)/.exec(wikitext)?.[1] ?? 0);
  if (!kills) return null;
  const want = item.toLowerCase();
  for (const line of wikitext.split('\n')) {
    const m = /^\|\s*([^,|=]+),\s*times:\s*(\d+)/.exec(line.trim());
    if (m && m[1].trim().toLowerCase() === want) return { rate: Number(m[2]) / kills, kills };
  }
  return { rate: 0, kills };
}

export function registerWeeklyRoutes(
  app: FastifyInstance,
  { db, redis, guards, wikiTitles }: { db: pg.Pool; redis: Redis; guards: AuthGuards; wikiTitles: Map<string, string> },
): void {
  const { requireUser, limited } = guards;

  async function ownChar(userId: number, idParam: string): Promise<number | null> {
    const id = Number(idParam);
    if (!Number.isInteger(id) || id <= 0) return null;
    const r = await db.query('SELECT id FROM characters WHERE id = $1 AND user_id = $2', [id, userId]);
    return r.rowCount ? id : null;
  }

  const taskCols = `id, week_start::text AS week_start, kind, name, target, progress, done_at, created_at`;

  app.get<{ Params: { id: string } }>('/api/characters/:id/weekly', { preHandler: requireUser }, async (req, reply) => {
    const id = await ownChar(req.user!.id, req.params.id);
    if (!id) return reply.code(404).send({ error: 'not_found' });
    const week = weekStart();
    const [current, history] = await Promise.all([
      db.query(`SELECT ${taskCols} FROM weekly_tasks WHERE character_id = $1 AND week_start = $2 ORDER BY kind, name`, [id, week]),
      db.query(
        `SELECT week_start::text AS week_start, COUNT(*)::int AS total, COUNT(done_at)::int AS done
           FROM weekly_tasks WHERE character_id = $1 AND week_start < $2
          GROUP BY week_start ORDER BY week_start DESC LIMIT 12`,
        [id, week],
      ),
    ]);
    return { week_start: week, tasks: current.rows, history: history.rows };
  });

  app.post<{ Params: { id: string }; Body: { kind?: unknown; name?: unknown; target?: unknown } }>(
    '/api/characters/:id/weekly',
    { preHandler: requireUser },
    async (req, reply) => {
      const id = await ownChar(req.user!.id, req.params.id);
      if (!id) return reply.code(404).send({ error: 'not_found' });
      const kind = req.body?.kind;
      const name = typeof req.body?.name === 'string' ? req.body.name.trim().replace(/\s+/g, ' ') : '';
      const target = Number(req.body?.target);
      if (kind !== 'creature' && kind !== 'item') return reply.code(400).send({ error: 'invalid_kind' });
      if (name.length < 2 || name.length > 80 || !/^[\p{L}\p{N} '.,()-]+$/u.test(name)) return reply.code(400).send({ error: 'invalid_name' });
      if (!Number.isInteger(target) || target < 1 || target > 100000) return reply.code(400).send({ error: 'invalid_target' });
      if (await limited(`ratelimit:weekly:${req.user!.id}`, 120, 3600)) return reply.code(429).send({ error: 'rate_limited' });
      const week = weekStart();
      const count = (await db.query<{ n: number }>('SELECT COUNT(*)::int AS n FROM weekly_tasks WHERE character_id = $1 AND week_start = $2', [id, week])).rows[0].n;
      if (count >= MAX_TASKS_PER_WEEK) return reply.code(409).send({ error: 'too_many_tasks' });
      const { rows } = await db.query(
        `INSERT INTO weekly_tasks (character_id, week_start, kind, name, target) VALUES ($1, $2, $3, $4, $5) RETURNING ${taskCols}`,
        [id, week, kind, name, target],
      );
      return reply.code(201).send(rows[0]);
    },
  );

  app.patch<{ Params: { id: string; taskId: string }; Body: { progress?: unknown; done?: unknown } }>(
    '/api/characters/:id/weekly/:taskId',
    { preHandler: requireUser },
    async (req, reply) => {
      const id = await ownChar(req.user!.id, req.params.id);
      if (!id) return reply.code(404).send({ error: 'not_found' });
      const sets: string[] = [];
      const params: unknown[] = [Number(req.params.taskId), id];
      if (req.body?.progress !== undefined) {
        const p = Number(req.body.progress);
        if (!Number.isInteger(p) || p < 0 || p > 100000) return reply.code(400).send({ error: 'invalid_progress' });
        params.push(p);
        sets.push(`progress = $${params.length}`);
        // Reaching the target marks it done; lowering it below reopens
        sets.push(`done_at = CASE WHEN $${params.length} >= target THEN COALESCE(done_at, now()) ELSE NULL END`);
      }
      if (typeof req.body?.done === 'boolean') {
        sets.splice(0, sets.length, ...sets.filter((s) => !s.startsWith('done_at')));
        sets.push(req.body.done ? 'done_at = COALESCE(done_at, now()), progress = GREATEST(progress, target)' : 'done_at = NULL');
      }
      if (!sets.length) return reply.code(400).send({ error: 'nothing_to_change' });
      const { rows } = await db.query(
        `UPDATE weekly_tasks SET ${sets.join(', ')} WHERE id = $1 AND character_id = $2 RETURNING ${taskCols}`,
        params,
      );
      if (!rows[0]) return reply.code(404).send({ error: 'not_found' });
      return rows[0];
    },
  );

  app.delete<{ Params: { id: string; taskId: string } }>(
    '/api/characters/:id/weekly/:taskId',
    { preHandler: requireUser },
    async (req, reply) => {
      const id = await ownChar(req.user!.id, req.params.id);
      if (!id) return reply.code(404).send({ error: 'not_found' });
      await db.query('DELETE FROM weekly_tasks WHERE id = $1 AND character_id = $2', [Number(req.params.taskId), id]);
      return { ok: true };
    },
  );

  async function wikiText(page: string): Promise<string> {
    const url = `${WIKI_API}?action=parse&page=${encodeURIComponent(page)}&redirects=1&prop=wikitext&format=json`;
    const res = await fetch(url, { headers: { 'User-Agent': USER_AGENT }, signal: AbortSignal.timeout(10_000) });
    const json = (await res.json()) as { parse?: { wikitext?: { '*': string } } };
    return json.parse?.wikitext?.['*'] ?? '';
  }

  async function cached<T>(key: string, ttl: number, load: () => Promise<T>): Promise<T> {
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

  /**
   * Where to get an item (D-37): creatures that drop it (TibiaWiki "Dropped By"), their measured drop
   * rate (TibiaWiki "Loot Statistics": times dropped / kills) and the best hunting places, scored by
   * spawn density × drop rate — the denser the creature and the less rare the drop, the easier.
   */
  app.get<{ Querystring: { item?: string } }>('/api/drops', async (req, reply) => {
    const item = String(req.query.item ?? '').trim().toLowerCase();
    if (item.length < 2 || item.length > 80) return reply.code(400).send({ error: 'invalid_item' });
    const known = await db.query('SELECT 1 FROM items WHERE name = $1', [item]);
    if (!known.rowCount) return reply.code(404).send({ error: 'unknown_item' });
    const title = wikiTitles.get(item) ?? item.replace(/\b\w/g, (c) => c.toUpperCase());

    let creatures: { name: string; rate: number | null; kills: number | null; boss: boolean }[];
    try {
      creatures = await cached(`wiki:drops2:${item}`, 7 * 86400, async () => {
        const names = parseDroppedBy(await wikiText(title)).slice(0, 25);
        // D-58: up to 5 creatures at a time (a first lookup used to read 25 pages one by one)
        const out: { name: string; rate: number | null; kills: number | null; boss: boolean }[] = [];
        for (let i = 0; i < names.length; i += 5) {
          const batch = await Promise.all(
            names.slice(i, i + 5).map(async (name) => {
              const [stats, isBoss] = await Promise.all([
                cached(`wiki:lootstats:${name.toLowerCase()}`, 7 * 86400, async () => wikiText(`Loot Statistics:${name}`)).catch(() => ''),
                // Bosses drop "100%" but appear rarely: useless for a delivery task, so they are ranked apart
                cached(`wiki:isboss:${name.toLowerCase()}`, 30 * 86400, async () => /\|\s*isboss\s*=\s*yes/i.test(await wikiText(name))).catch(
                  () => false,
                ),
              ]);
              const r = parseLootRate(stats, title);
              return { name, rate: r && r.kills >= 50 ? r.rate : null, kills: r?.kills ?? null, boss: isBoss };
            }),
          );
          out.push(...batch);
        }
        // Regular creatures first (by chance), bosses after
        return out.sort((a, b) => Number(a.boss) - Number(b.boss) || (b.rate ?? -1) - (a.rate ?? -1));
      });
    } catch {
      return reply.code(503).send({ error: 'wiki_unavailable' });
    }

    // Hunting places from SpawnRank for those creatures, scored by density × rate
    const { rows } = await db.query<{ creature: string; creature_id: number; zone: string; slug: string | null; city: string; density: number; min_level: number; image_url: string | null }>(
      `SELECT c.name AS creature, c.id AS creature_id, c.image_url, z.name AS zone, z.slug, z.city, sd.density_tier AS density,
              z.recommended_min_level AS min_level
         FROM spawn_densities sd JOIN creatures c ON c.id = sd.creature_id JOIN hunting_zones z ON z.id = sd.hunting_zone_id
        WHERE LOWER(c.name) = ANY($1::text[])`,
      [creatures.map((c) => c.name.toLowerCase())],
    );
    // Places are scored with regular creatures only; when only bosses drop the item, show their lairs
    const regular = creatures.filter((c) => !c.boss);
    const scoring = regular.length ? regular : creatures;
    const rateOf = new Map(scoring.map((c) => [c.name.toLowerCase(), c.rate]));
    const places = rows
      .filter((r) => rateOf.has(r.creature.toLowerCase()))
      .map((r) => {
        const rate = rateOf.get(r.creature.toLowerCase()) ?? null;
        return { ...r, rate, score: rate != null ? Number((r.density * rate * 100).toFixed(3)) : null };
      })
      .sort((a, b) => (b.score ?? -1) - (a.score ?? -1) || b.density - a.density)
      .slice(0, 15);
    const spawnIds = new Map(rows.map((r) => [r.creature.toLowerCase(), r.creature_id]));
    const imgs = await db.query<{ name: string; image_url: string }>(
      'SELECT name, image_url FROM creatures WHERE LOWER(name) = ANY($1::text[]) AND image_url IS NOT NULL',
      [creatures.map((c) => c.name.toLowerCase())],
    );
    const imageOf = new Map(imgs.rows.map((r) => [r.name.toLowerCase(), r.image_url]));
    return {
      item,
      creatures: creatures.map((c) => ({ ...c, spawnrank_id: spawnIds.get(c.name.toLowerCase()) ?? null, image_url: imageOf.get(c.name.toLowerCase()) ?? null })),
      places,
      source: 'TibiaWiki (CC BY-SA): Dropped By and Loot Statistics',
    };
  });

  /**
   * Item search for the drop finder (D-58): creature products (the items delivery tasks ask for) first,
   * then the rest of the catalog; words in any order ("scale dragon" finds "green dragon scale").
   */
  app.get<{ Querystring: { q?: string } }>('/api/drops/suggest', async (req, reply) => {
    const words = String(req.query.q ?? '')
      .toLowerCase()
      .trim()
      .split(/\s+/)
      .filter((w) => w.length >= 2)
      .slice(0, 4);
    if (!words.length) return reply.send({ items: [] });
    const esc = (w: string) => w.replace(/[\\%_]/g, (ch) => '\\' + ch);
    const params = words.map((w) => `%${esc(w)}%`);
    const where = params.map((_, i) => `name LIKE $${i + 1}`).join(' AND ');
    // What the user typed, as a prefix ("green dragon le" → "green dragon leather" before "green dragon scale")
    params.push(`${esc(words.join(' '))}%`);
    const { rows } = await db.query<{ name: string; creature_product: boolean; npc_price: number }>(
      `SELECT name, is_task_creature_product AS creature_product, npc_price FROM items WHERE ${where}
        ORDER BY (name LIKE $${params.length}) DESC, is_task_creature_product DESC, length(name), name LIMIT 12`,
      params,
    );
    reply.header('Cache-Control', 'public, max-age=3600');
    return { items: rows };
  });

  /** Creature id for SpawnRank links by name */
  app.get<{ Querystring: { name?: string } }>('/api/creatures/lookup', async (req) => {
    const name = String(req.query.name ?? '').trim();
    const { rows } = await db.query<{ id: number; name: string }>(
      `SELECT c.id, c.name FROM creatures c
        WHERE LOWER(c.name) = LOWER($1) AND EXISTS (SELECT 1 FROM spawn_densities sd WHERE sd.creature_id = c.id)`,
      [name],
    );
    return rows[0] ?? null;
  });
}
