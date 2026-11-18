/**
 * Character tracking API (D-28, D-49).
 * D-49: a character is added by name only (public data from tibia.com, no proof needed) and gets its first
 * snapshot right away. The comment-code verification became optional: it only proves ownership (badge,
 * public rankings, Yasir trust). Automatic server-side tracking (snapshots without clicking) is Premium.
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import type { AuthGuards } from './accounts.js';
import type { CharEvent } from './personalAlerts.js';
import { isPremium } from './auth.js';
import {
  commentHasCode,
  expForLevel,
  findExperience,
  newVerificationCode,
  normalizeCharName,
  parseCharacter,
  type CharacterInfo,
} from './characters.js';
import { fetchCharacter, getExperiencePage, TibiaDataUnavailable } from './tibiadata.js';

/** Characters per account (pending + verified). Premium gets more: a monetisation hook (D-28). */
export const CHARACTER_LIMIT = { free: 1, premium: 10 };
const REFRESH_COOLDOWN_SECONDS = 10 * 60;
const SNAPSHOT_LIMIT = 1000;

const CHAR_COLUMNS = `id, name, verification_code, verified_at, created_at, last_refresh_at, world, vocation, level,
  residence, sex, guild, account_status, achievement_points, last_login, traded, auto_track, last_auto_at`;

interface CharRow {
  id: number;
  user_id: number;
  name: string;
  verification_code: string;
  verified_at: Date | null;
  world: string | null;
  level: number | null;
  traded: boolean;
}

export function registerCharacterRoutes(
  app: FastifyInstance,
  { db, redis, guards, events }: { db: pg.Pool; redis: Redis; guards: AuthGuards; events?: { onChar?: (userId: number, ev: CharEvent) => void } },
): void {
  const { requireUser, limited } = guards;

  /** Loads a character of the signed-in user, or null */
  async function own(userId: number, idParam: string): Promise<CharRow | null> {
    const id = Number(idParam);
    if (!Number.isInteger(id) || id <= 0) return null;
    const { rows } = await db.query<CharRow>(`SELECT ${CHAR_COLUMNS}, user_id FROM characters WHERE id = $1 AND user_id = $2`, [
      id,
      userId,
    ]);
    return rows[0] ?? null;
  }

  async function loadInfo(name: string): Promise<CharacterInfo | null> {
    const raw = await fetchCharacter(name);
    return raw ? parseCharacter(raw) : null;
  }

  /** Exact experience from the highscores when listed and not older than the level; else the level minimum. */
  async function experienceOf(info: CharacterInfo): Promise<{ experience: number; exact: boolean }> {
    const floor = expForLevel(info.level);
    try {
      const exact = await findExperience(info.name, info.level, (page) => getExperiencePage(redis, info.world, page));
      // Highscores update once a day: if the character levelled since, the value is stale
      if (exact != null && exact >= floor) return { experience: exact, exact: true };
    } catch (err) {
      app.log.warn({ err: (err as Error).message }, 'highscore lookup failed');
    }
    return { experience: floor, exact: false };
  }

  async function saveProfile(client: pg.PoolClient | pg.Pool, id: number, info: CharacterInfo) {
    // Record renames and world transfers before overwriting (D-33)
    const prev = await client.query<{ name: string; world: string | null }>('SELECT name, world FROM characters WHERE id = $1', [id]);
    const before = prev.rows[0];
    if (before && before.name !== info.name) {
      await client.query(`INSERT INTO character_changes (character_id, field, old_value, new_value) VALUES ($1, 'name', $2, $3)`, [
        id,
        before.name,
        info.name,
      ]);
    }
    if (before && before.world && before.world !== info.world) {
      await client.query(`INSERT INTO character_changes (character_id, field, old_value, new_value) VALUES ($1, 'world', $2, $3)`, [
        id,
        before.world,
        info.world,
      ]);
    }
    await client.query(
      `UPDATE characters SET name = $2, world = $3, vocation = $4, level = $5, residence = $6, sex = $7, guild = $8,
              account_status = $9, achievement_points = $10, last_login = $11, traded = $12, last_refresh_at = now()
        WHERE id = $1`,
      [
        id,
        info.name,
        info.world,
        info.vocation,
        info.level,
        info.residence,
        info.sex,
        info.guild,
        info.account_status,
        info.achievement_points,
        info.last_login,
        info.traded,
      ],
    );
  }

  async function snapshot(client: pg.PoolClient | pg.Pool, id: number, info: CharacterInfo) {
    const { experience, exact } = await experienceOf(info);
    // Skip identical snapshots taken within the last hour
    const last = await client.query<{ level: number; experience: string; recent: boolean }>(
      `SELECT level, experience, taken_at > now() - interval '1 hour' AS recent
         FROM character_snapshots WHERE character_id = $1 ORDER BY taken_at DESC LIMIT 1`,
      [id],
    );
    const prev = last.rows[0];
    // D-76: alerts need a previous snapshot (the first refresh of a character is not news)
    const owner = events?.onChar ? (await client.query<{ user_id: number; name: string }>('SELECT user_id, name FROM characters WHERE id = $1', [id])).rows[0] : undefined;
    const emit = (ev: CharEvent) => {
      if (owner && prev) events!.onChar!(owner.user_id, ev);
    };
    if (prev && info.level > prev.level) emit({ kind: 'level', character: info.name, from: prev.level, to: info.level });
    if (!(prev && prev.recent && prev.level === info.level && Number(prev.experience) === experience)) {
      await client.query(
        `INSERT INTO character_snapshots (character_id, level, experience, exp_exact, achievement_points, world)
         VALUES ($1, $2, $3, $4, $5, $6)`,
        [id, info.level, experience, exact, info.achievement_points, info.world],
      );
    }
    for (const d of info.deaths) {
      const ins = await client.query(
        `INSERT INTO character_deaths (character_id, time, level, reason) VALUES ($1, $2, $3, $4)
         ON CONFLICT DO NOTHING RETURNING time`,
        [id, d.time, d.level, d.reason],
      );
      if (ins.rowCount && new Date(d.time).getTime() > Date.now() - 36 * 3_600_000) emit({ kind: 'death', character: info.name, level: d.level, reason: d.reason });
    }
    const goal = await client.query<{ target_level: number }>(
      `UPDATE character_goals SET achieved_at = now()
        WHERE character_id = $1 AND achieved_at IS NULL AND target_level <= $2 RETURNING target_level`,
      [id, info.level],
    );
    if (goal.rows[0]) emit({ kind: 'goal', character: info.name, goal: goal.rows[0].target_level });
  }

  const unavailable = { error: 'tibiadata_unavailable' };

  // -------------------------------------------------------------------------

  app.get('/api/characters', { preHandler: requireUser }, async (req) => {
    const user = req.user!;
    const { rows } = await db.query(
      `SELECT ${CHAR_COLUMNS.split(',').map((c) => 'c.' + c.trim()).join(', ')},
              g.target_level, to_char(g.target_date, 'YYYY-MM-DD') AS target_date, g.achieved_at AS goal_achieved_at,
              (SELECT experience FROM character_snapshots s WHERE s.character_id = c.id ORDER BY taken_at DESC LIMIT 1) AS experience
         FROM characters c LEFT JOIN character_goals g ON g.character_id = c.id
        WHERE c.user_id = $1
        ORDER BY c.verified_at IS NULL, c.level DESC NULLS LAST, c.name`,
      [user.id],
    );
    const premium = isPremium(user);
    return {
      characters: rows.map((r) => ({ ...r, experience: r.experience == null ? null : Number(r.experience) })),
      limit: premium ? CHARACTER_LIMIT.premium : CHARACTER_LIMIT.free,
      limits: CHARACTER_LIMIT,
    };
  });

  app.post<{ Body: { name?: unknown } }>('/api/characters', { preHandler: requireUser }, async (req, reply) => {
    const user = req.user!;
    const name = normalizeCharName(req.body?.name);
    if (!name) return reply.code(400).send({ error: 'invalid_character' });
    if (await limited(`ratelimit:charadd:${user.id}`, 15, 3600)) return reply.code(429).send({ error: 'rate_limited' });

    const existing = await db.query<{ id: number }>(
      'SELECT id FROM characters WHERE user_id = $1 AND LOWER(name) = LOWER($2)',
      [user.id, name],
    );
    if (existing.rowCount) return { id: existing.rows[0].id, existing: true };

    const count = (await db.query<{ n: number }>('SELECT COUNT(*)::int AS n FROM characters WHERE user_id = $1', [user.id]))
      .rows[0].n;
    const limit = isPremium(user) ? CHARACTER_LIMIT.premium : CHARACTER_LIMIT.free;
    if (count >= limit) return reply.code(409).send({ error: 'character_limit', limit });

    let info: CharacterInfo | null;
    try {
      info = await loadInfo(name);
    } catch (err) {
      if (err instanceof TibiaDataUnavailable) return reply.code(404).send({ error: 'character_not_found' });
      throw err;
    }
    if (!info) return reply.code(404).send({ error: 'character_not_found' });

    const ins = await db.query<{ id: number }>(
      `INSERT INTO characters (user_id, name, verification_code, world, vocation, level, traded)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       ON CONFLICT (user_id, LOWER(name)) DO UPDATE SET name = EXCLUDED.name
       RETURNING id`,
      [user.id, info.name, newVerificationCode(), info.world, info.vocation, info.level, info.traded],
    );
    const id = ins.rows[0].id;
    // First snapshot right away, so the character page has data without any extra step
    try {
      await saveProfile(db, id, info);
      await snapshot(db, id, info);
      await redis.set(`charrefresh:${id}`, '1', 'EX', REFRESH_COOLDOWN_SECONDS).catch(() => null);
    } catch (err) {
      app.log.warn({ err: (err as Error).message }, 'first snapshot failed');
    }
    // Premium accounts get server tracking switched on by default
    if (isPremium(user)) await db.query('UPDATE characters SET auto_track = TRUE WHERE id = $1', [id]);
    return reply.code(201).send({ id });
  });

  app.post<{ Params: { id: string } }>('/api/characters/:id/verify', { preHandler: requireUser }, async (req, reply) => {
    const user = req.user!;
    const char = await own(user.id, req.params.id);
    if (!char) return reply.code(404).send({ error: 'not_found' });
    if (char.verified_at) return { verified: true };
    if (await limited(`ratelimit:charverify:${user.id}`, 6, 60)) return reply.code(429).send({ error: 'rate_limited' });

    let info: CharacterInfo | null;
    try {
      info = await loadInfo(char.name);
    } catch (err) {
      if (err instanceof TibiaDataUnavailable) return reply.code(503).send(unavailable);
      throw err;
    }
    if (!info) return reply.code(404).send({ error: 'character_not_found' });
    if (!commentHasCode(info.comment, char.verification_code)) {
      return reply.code(409).send({ error: 'code_not_found', code: char.verification_code });
    }

    const client = await db.connect();
    try {
      await client.query('BEGIN');
      // Proving control moves the character away from any previous owner (sold/traded characters)
      await client.query(
        `UPDATE characters SET verified_at = NULL WHERE LOWER(name) = LOWER($1) AND id <> $2 AND verified_at IS NOT NULL`,
        [info.name, char.id],
      );
      await client.query('UPDATE characters SET verified_at = now() WHERE id = $1', [char.id]);
      await saveProfile(client, char.id, info);
      await snapshot(client, char.id, info);
      await client.query('COMMIT');
    } catch (err) {
      await client.query('ROLLBACK');
      throw err;
    } finally {
      client.release();
    }
    await redis.set(`charrefresh:${char.id}`, '1', 'EX', REFRESH_COOLDOWN_SECONDS).catch(() => null);
    return { verified: true };
  });

  app.post<{ Params: { id: string } }>('/api/characters/:id/refresh', { preHandler: requireUser }, async (req, reply) => {
    const user = req.user!;
    const char = await own(user.id, req.params.id);
    if (!char) return reply.code(404).send({ error: 'not_found' });

    const key = `charrefresh:${char.id}`;
    const ok = await redis.set(key, '1', 'EX', REFRESH_COOLDOWN_SECONDS, 'NX').catch(() => 'OK');
    if (!ok) {
      const ttl = await redis.ttl(key).catch(() => REFRESH_COOLDOWN_SECONDS);
      return reply.code(429).send({ error: 'refresh_cooldown', retry_after: Math.max(1, ttl) });
    }

    let info: CharacterInfo | null;
    try {
      info = await loadInfo(char.name);
    } catch (err) {
      await redis.del(key).catch(() => 0);
      if (err instanceof TibiaDataUnavailable) return reply.code(503).send(unavailable);
      throw err;
    }
    if (!info) {
      await redis.del(key).catch(() => 0);
      // The name no longer exists: most likely renamed (or deleted). The user can tell us the new name.
      return reply.code(404).send({ error: 'character_renamed' });
    }

    await saveProfile(db, char.id, info);
    // A character traded through the Char Bazaar loses its "verified owner" badge (tracking continues)
    const lostBadge = info.traded && !char.traded && !!char.verified_at;
    if (lostBadge) await db.query('UPDATE characters SET verified_at = NULL WHERE id = $1', [char.id]);
    await snapshot(db, char.id, info);
    return { ok: true, traded_unverified: lostBadge };
  });

  app.get<{ Params: { id: string } }>('/api/characters/:id', { preHandler: requireUser }, async (req, reply) => {
    const char = await own(req.user!.id, req.params.id);
    if (!char) return reply.code(404).send({ error: 'not_found' });
    const [full, snaps, deaths, goal, changes] = await Promise.all([
      db.query(`SELECT ${CHAR_COLUMNS} FROM characters WHERE id = $1`, [char.id]),
      db.query(
        `SELECT * FROM (
           SELECT taken_at, level, experience::float8 AS experience, exp_exact, achievement_points, world
             FROM character_snapshots WHERE character_id = $1 ORDER BY taken_at DESC LIMIT ${SNAPSHOT_LIMIT}
         ) s ORDER BY taken_at`,
        [char.id],
      ),
      db.query(
        `SELECT time, level, reason FROM character_deaths WHERE character_id = $1 ORDER BY time DESC LIMIT 100`,
        [char.id],
      ),
      db.query(
        `SELECT target_level, to_char(target_date, 'YYYY-MM-DD') AS target_date, start_level, start_exp::float8 AS start_exp,
                created_at, achieved_at
           FROM character_goals WHERE character_id = $1`,
        [char.id],
      ),
      db.query(
        `SELECT field, old_value, new_value, detected_at FROM character_changes
          WHERE character_id = $1 ORDER BY detected_at DESC LIMIT 50`,
        [char.id],
      ),
    ]);
    const cooldown = await redis.ttl(`charrefresh:${char.id}`).catch(() => -1);
    return {
      character: full.rows[0],
      snapshots: snaps.rows,
      deaths: deaths.rows,
      goal: goal.rows[0] ?? null,
      changes: changes.rows,
      refresh_available_in: Math.max(0, cooldown),
    };
  });

  /**
   * Rename (D-33): the old name no longer resolves on tibia.com, so the user gives the new one.
   * Continuity is proven when the new character lists the old name in its former names.
   */
  app.post<{ Params: { id: string }; Body: { new_name?: unknown } }>(
    '/api/characters/:id/rename',
    { preHandler: requireUser },
    async (req, reply) => {
      const char = await own(req.user!.id, req.params.id);
      if (!char) return reply.code(404).send({ error: 'not_found' });
      const newName = normalizeCharName(req.body?.new_name);
      if (!newName) return reply.code(400).send({ error: 'invalid_character' });
      if (await limited(`ratelimit:charrename:${req.user!.id}`, 10, 3600)) return reply.code(429).send({ error: 'rate_limited' });
      let info: CharacterInfo | null;
      try {
        info = await loadInfo(newName);
      } catch (err) {
        if (err instanceof TibiaDataUnavailable) return reply.code(503).send(unavailable);
        throw err;
      }
      if (!info) return reply.code(404).send({ error: 'character_not_found' });
      const continuity = info.former_names.some((n) => n.toLowerCase() === char.name.toLowerCase());
      if (!continuity) return reply.code(409).send({ error: 'rename_not_proven' });
      const taken = await db.query(
        'SELECT 1 FROM characters WHERE user_id = $1 AND LOWER(name) = LOWER($2) AND id <> $3',
        [req.user!.id, info.name, char.id],
      );
      if (taken.rowCount) return reply.code(409).send({ error: 'already_tracked' });
      const client = await db.connect();
      try {
        await client.query('BEGIN');
        // Another account's stale verified row for the new name loses ownership
        if (char.verified_at) {
          await client.query(
            `UPDATE characters SET verified_at = NULL WHERE LOWER(name) = LOWER($1) AND id <> $2 AND verified_at IS NOT NULL`,
            [info.name, char.id],
          );
        }
        await saveProfile(client, char.id, info);
        await snapshot(client, char.id, info);
        await client.query('COMMIT');
      } catch (err) {
        await client.query('ROLLBACK');
        throw err;
      } finally {
        client.release();
      }
      return { ok: true, name: info.name };
    },
  );

  app.put<{ Params: { id: string }; Body: { target_level?: unknown; target_date?: unknown } }>(
    '/api/characters/:id/goal',
    { preHandler: requireUser },
    async (req, reply) => {
      const char = await own(req.user!.id, req.params.id);
      if (!char) return reply.code(404).send({ error: 'not_found' });
      const last = await db.query<{ level: number; experience: string }>(
        `SELECT level, experience FROM character_snapshots WHERE character_id = $1 ORDER BY taken_at DESC LIMIT 1`,
        [char.id],
      );
      const current = last.rows[0];
      if (!current) return reply.code(409).send({ error: 'no_data' });

      const target = Number(req.body?.target_level);
      if (!Number.isInteger(target) || target <= current.level || target > 5000) {
        return reply.code(400).send({ error: 'invalid_target' });
      }
      let date: string | null = null;
      if (req.body?.target_date != null && req.body.target_date !== '') {
        const raw = String(req.body.target_date);
        const d = new Date(`${raw}T00:00:00Z`);
        const today = new Date(new Date().toISOString().slice(0, 10) + 'T00:00:00Z');
        const max = new Date(today.getTime() + 5 * 365 * 86400_000);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(raw) || Number.isNaN(d.getTime()) || d <= today || d > max) {
          return reply.code(400).send({ error: 'invalid_date' });
        }
        date = raw;
      }
      await db.query(
        `INSERT INTO character_goals (character_id, target_level, target_date, start_level, start_exp)
         VALUES ($1, $2, $3, $4, $5)
         ON CONFLICT (character_id) DO UPDATE SET target_level = EXCLUDED.target_level, target_date = EXCLUDED.target_date,
           start_level = EXCLUDED.start_level, start_exp = EXCLUDED.start_exp, created_at = now(), achieved_at = NULL`,
        [char.id, target, date, current.level, current.experience],
      );
      return { ok: true };
    },
  );

  app.delete<{ Params: { id: string } }>('/api/characters/:id/goal', { preHandler: requireUser }, async (req, reply) => {
    const char = await own(req.user!.id, req.params.id);
    if (!char) return reply.code(404).send({ error: 'not_found' });
    await db.query('DELETE FROM character_goals WHERE character_id = $1', [char.id]);
    return { ok: true };
  });

  /** Server tracking on/off (Premium) */
  app.put<{ Params: { id: string }; Body: { enabled?: unknown } }>(
    '/api/characters/:id/tracking',
    { preHandler: requireUser },
    async (req, reply) => {
      const char = await own(req.user!.id, req.params.id);
      if (!char) return reply.code(404).send({ error: 'not_found' });
      const enabled = req.body?.enabled === true;
      if (enabled && !isPremium(req.user!)) return reply.code(402).send({ error: 'premium_required' });
      await db.query('UPDATE characters SET auto_track = $2 WHERE id = $1', [char.id, enabled]);
      return { ok: true, auto_track: enabled };
    },
  );

  // Server tracking job (D-49): every 30 min, refresh up to 40 Premium characters whose last automatic
  // snapshot is older than 6 hours. One TibiaData call every 2 s keeps us polite.
  const AUTO_INTERVAL_MS = 30 * 60_000;
  async function autoTrack() {
    const lock = await redis.set('lock:autotrack', '1', 'EX', 25 * 60, 'NX').catch(() => 'OK');
    if (!lock) return;
    const { rows } = await db.query<{ id: number; name: string; user_id: number }>(
      `SELECT c.id, c.name, c.user_id FROM characters c JOIN users u ON u.id = c.user_id
        WHERE c.auto_track AND u.status = 'active'
          AND (u.tier IN ('pro', 'guild', 'admin') OR u.premium_until > now() OR u.role = 'admin')
          AND (c.last_auto_at IS NULL OR c.last_auto_at < now() - interval '6 hours')
        ORDER BY c.last_auto_at NULLS FIRST LIMIT 40`,
    );
    let done = 0;
    for (const c of rows) {
      try {
        const info = await loadInfo(c.name);
        if (info) {
          await saveProfile(db, c.id, info);
          await snapshot(db, c.id, info);
          done++;
        }
        await db.query('UPDATE characters SET last_auto_at = now() WHERE id = $1', [c.id]);
      } catch (err) {
        app.log.warn({ err: (err as Error).message, character: c.id }, 'auto track failed');
        if (err instanceof TibiaDataUnavailable) break;
      }
      await new Promise((r) => setTimeout(r, 2000));
    }
    if (rows.length) app.log.info(`auto track: ${done}/${rows.length} characters`);
  }
  if (process.env.NODE_ENV !== 'test') {
    setTimeout(() => void autoTrack().catch(() => undefined), 60_000).unref();
    setInterval(() => void autoTrack().catch(() => undefined), AUTO_INTERVAL_MS).unref();
  }

  app.delete<{ Params: { id: string } }>('/api/characters/:id', { preHandler: requireUser }, async (req, reply) => {
    const char = await own(req.user!.id, req.params.id);
    if (!char) return reply.code(404).send({ error: 'not_found' });
    await db.query('DELETE FROM characters WHERE id = $1', [char.id]);
    return { ok: true };
  });
}
