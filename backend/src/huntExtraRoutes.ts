/**
 * Hunt session v2 (D-59): saved equipment sets / Wheels of Destiny per character, hunt templates and friends.
 *
 *   GET    /api/builds?character_id=   my sets and WoDs (optionally for one character + the ones without character)
 *   POST   /api/builds                 { kind: 'set'|'wod', name, character_id?, data }
 *   PUT    /api/builds/:id             { name?, data? }
 *   DELETE /api/builds/:id
 *   GET    /api/hunt-templates         my templates
 *   PUT    /api/hunt-templates         { name, data } (upsert by name)
 *   DELETE /api/hunt-templates/:id
 *   GET    /api/friends                friends I added + people who added me
 *   POST   /api/friends                { query } exact e-mail or display name (no partial search: no account enumeration)
 *   DELETE /api/friends/:id
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';
import { validateSet, validateTemplate, validateWod } from './huntBuild.js';

const BUILD_LIMIT = 200;
const TEMPLATE_LIMIT = 50;
const FRIEND_LIMIT = 100;

const cleanName = (v: unknown, max: number): string | null => {
  if (typeof v !== 'string') return null;
  const s = v.replace(/[\u0000-\u001f]/g, ' ').trim();
  return s ? s.slice(0, max) : null;
};

export function registerHuntExtraRoutes(app: FastifyInstance, { db, guards }: { db: pg.Pool; guards: AuthGuards }): void {
  const { requireUser, limited } = guards;

  // ---------------------------------------------------------------------------
  // Sets and Wheels of Destiny
  // ---------------------------------------------------------------------------

  app.get<{ Querystring: { character_id?: string } }>('/api/builds', { preHandler: requireUser }, async (req) => {
    const params: unknown[] = [req.user!.id];
    let where = 'user_id = $1';
    const cid = Number(req.query.character_id);
    if (Number.isInteger(cid) && cid > 0) {
      params.push(cid);
      where += ` AND (character_id = $2 OR character_id IS NULL)`;
    }
    const { rows } = await db.query(
      `SELECT id, kind, name, character_id, data, updated_at FROM character_builds WHERE ${where} ORDER BY updated_at DESC LIMIT ${BUILD_LIMIT}`,
      params,
    );
    return { builds: rows };
  });

  app.post<{ Body: Record<string, unknown> }>('/api/builds', { preHandler: requireUser }, async (req, reply) => {
    const userId = req.user!.id;
    const b = req.body ?? {};
    const kind = b.kind === 'set' ? 'set' : b.kind === 'wod' ? 'wod' : null;
    const name = cleanName(b.name, 80);
    const data = kind === 'set' ? validateSet(b.data) : kind === 'wod' ? validateWod(b.data) : null;
    if (!kind || !name || !data) return reply.code(400).send({ error: 'invalid_build' });
    const n = (await db.query<{ n: number }>('SELECT COUNT(*)::int AS n FROM character_builds WHERE user_id = $1', [userId])).rows[0].n;
    if (n >= BUILD_LIMIT) return reply.code(409).send({ error: 'build_limit', limit: BUILD_LIMIT });
    let characterId: number | null = null;
    const cid = Number(b.character_id);
    if (Number.isInteger(cid) && cid > 0) {
      const c = await db.query('SELECT 1 FROM characters WHERE id = $1 AND user_id = $2', [cid, userId]);
      if (c.rowCount) characterId = cid;
    }
    const { rows } = await db.query<{ id: number }>(
      'INSERT INTO character_builds (user_id, character_id, kind, name, data) VALUES ($1, $2, $3, $4, $5) RETURNING id',
      [userId, characterId, kind, name, JSON.stringify(data)],
    );
    return reply.code(201).send({ id: rows[0].id, kind, name, character_id: characterId, data });
  });

  app.put<{ Params: { id: string }; Body: Record<string, unknown> }>('/api/builds/:id', { preHandler: requireUser }, async (req, reply) => {
    const id = Number(req.params.id);
    const cur = await db.query<{ kind: 'set' | 'wod' }>('SELECT kind FROM character_builds WHERE id = $1 AND user_id = $2', [id, req.user!.id]);
    if (!cur.rowCount) return reply.code(404).send({ error: 'not_found' });
    const b = req.body ?? {};
    const name = 'name' in b ? cleanName(b.name, 80) : undefined;
    const data = 'data' in b ? (cur.rows[0].kind === 'set' ? validateSet(b.data) : validateWod(b.data)) : undefined;
    if (name === null || data === null) return reply.code(400).send({ error: 'invalid_build' });
    await db.query(
      `UPDATE character_builds SET name = COALESCE($3, name), data = COALESCE($4::jsonb, data), updated_at = now() WHERE id = $1 AND user_id = $2`,
      [id, req.user!.id, name ?? null, data ? JSON.stringify(data) : null],
    );
    return { ok: true };
  });

  app.delete<{ Params: { id: string } }>('/api/builds/:id', { preHandler: requireUser }, async (req, reply) => {
    const r = await db.query('DELETE FROM character_builds WHERE id = $1 AND user_id = $2', [Number(req.params.id), req.user!.id]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    return { ok: true };
  });

  // ---------------------------------------------------------------------------
  // Hunt templates
  // ---------------------------------------------------------------------------

  app.get('/api/hunt-templates', { preHandler: requireUser }, async (req) => {
    const { rows } = await db.query('SELECT id, name, data, updated_at FROM hunt_templates WHERE user_id = $1 ORDER BY updated_at DESC', [req.user!.id]);
    return { templates: rows };
  });

  app.put<{ Body: { name?: unknown; data?: unknown } }>('/api/hunt-templates', { preHandler: requireUser }, async (req, reply) => {
    const userId = req.user!.id;
    const name = cleanName(req.body?.name, 120);
    const data = validateTemplate(req.body?.data);
    if (!name || !data) return reply.code(400).send({ error: 'invalid_template' });
    const { rows: stat } = await db.query<{ n: number; exists: boolean }>(
      'SELECT COUNT(*)::int AS n, BOOL_OR(LOWER(name) = LOWER($2)) AS exists FROM hunt_templates WHERE user_id = $1',
      [userId, name],
    );
    if (stat[0].n >= TEMPLATE_LIMIT && !stat[0].exists) return reply.code(409).send({ error: 'template_limit', limit: TEMPLATE_LIMIT });
    const { rows } = await db.query<{ id: number }>(
      `INSERT INTO hunt_templates (user_id, name, data) VALUES ($1, $2, $3)
       ON CONFLICT (user_id, LOWER(name)) DO UPDATE SET data = EXCLUDED.data, name = EXCLUDED.name, updated_at = now()
       RETURNING id`,
      [userId, name, JSON.stringify(data)],
    );
    return { id: rows[0].id, name, data };
  });

  app.delete<{ Params: { id: string } }>('/api/hunt-templates/:id', { preHandler: requireUser }, async (req, reply) => {
    const r = await db.query('DELETE FROM hunt_templates WHERE id = $1 AND user_id = $2', [Number(req.params.id), req.user!.id]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    return { ok: true };
  });

  // ---------------------------------------------------------------------------
  // Friends
  // ---------------------------------------------------------------------------

  app.get('/api/friends', { preHandler: requireUser }, async (req) => {
    const userId = req.user!.id;
    const [added, addedMe] = await Promise.all([
      db.query(
        `SELECT u.id, u.display_name, f.created_at FROM friends f JOIN users u ON u.id = f.friend_id WHERE f.user_id = $1 ORDER BY LOWER(u.display_name)`,
        [userId],
      ),
      db.query(
        `SELECT u.id, u.display_name, f.created_at FROM friends f JOIN users u ON u.id = f.user_id WHERE f.friend_id = $1 ORDER BY LOWER(u.display_name)`,
        [userId],
      ),
    ]);
    return { friends: added.rows, added_me: addedMe.rows };
  });

  app.post<{ Body: { query?: unknown } }>('/api/friends', { preHandler: requireUser }, async (req, reply) => {
    const userId = req.user!.id;
    if (await limited(`ratelimit:friendadd:${userId}`, 30, 3600)) return reply.code(429).send({ error: 'rate_limited' });
    const q = cleanName(req.body?.query, 254);
    if (!q || q.length < 3) return reply.code(400).send({ error: 'friend_not_found' });
    const { rows } = await db.query<{ id: number; display_name: string }>(
      q.includes('@')
        ? 'SELECT id, display_name FROM users WHERE LOWER(email) = LOWER($1) LIMIT 2'
        : 'SELECT id, display_name FROM users WHERE LOWER(display_name) = LOWER($1) LIMIT 2',
      [q],
    );
    if (!rows.length) return reply.code(404).send({ error: 'friend_not_found' });
    if (rows.length > 1) return reply.code(409).send({ error: 'friend_ambiguous' });
    const friend = rows[0];
    if (friend.id === userId) return reply.code(400).send({ error: 'friend_self' });
    const n = (await db.query<{ n: number }>('SELECT COUNT(*)::int AS n FROM friends WHERE user_id = $1', [userId])).rows[0].n;
    if (n >= FRIEND_LIMIT) return reply.code(409).send({ error: 'friend_limit', limit: FRIEND_LIMIT });
    await db.query('INSERT INTO friends (user_id, friend_id) VALUES ($1, $2) ON CONFLICT DO NOTHING', [userId, friend.id]);
    return { id: friend.id, display_name: friend.display_name };
  });

  app.delete<{ Params: { id: string } }>('/api/friends/:id', { preHandler: requireUser }, async (req, reply) => {
    const userId = req.user!.id;
    const friendId = Number(req.params.id);
    const r = await db.query('DELETE FROM friends WHERE user_id = $1 AND friend_id = $2', [userId, friendId]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    // Stop sharing my hunts with them
    await db.query('UPDATE hunt_sessions SET shared_with = array_remove(shared_with, $2) WHERE user_id = $1 AND $2 = ANY(shared_with)', [userId, friendId]);
    await db.query('UPDATE hunt_payouts SET shared_with = array_remove(shared_with, $2) WHERE user_id = $1 AND $2 = ANY(shared_with)', [userId, friendId]);
    return { ok: true };
  });
}
