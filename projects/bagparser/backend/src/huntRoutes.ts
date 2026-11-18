/**
 * Hunt Analyser API (D-49): save hunt sessions per account, public feed, rankings and place statistics.
 * Public rows never carry the user id or e-mail; "anonymous" hides the character name as well.
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';
import { isPremium } from './auth.js';
import { goalProgress, type GoalMetric } from './goals.js';
import { friendIds, validateBuild, validateExpenses } from './huntBuild.js';
import { sanitizeServerLog } from './serverLog.js';
import { HUNT_LIMIT, RANKING_MIN_SECONDS, baseVocation, huntFingerprint, newPublicId, validateHunt } from './hunts.js';

/** Columns for lists (no item lists: those come with the detail) */
const LIST_COLUMNS = `h.public_id, h.kind, h.visibility, h.anonymous, to_char(h.started_at, 'YYYY-MM-DD"T"HH24:MI:SS') AS started_at, to_char(h.ended_at, 'YYYY-MM-DD"T"HH24:MI:SS') AS ended_at, h.duration_s, h.raw_xp, h.xp,
  h.raw_xp_h, h.xp_h, h.loot, h.supplies, h.balance, h.balance_h, h.damage_h, h.healing_h, h.character_name, h.vocation,
  h.level, h.world, h.place_slug, h.place_name, h.created_at, h.monsters->0->>'name' AS top_monster,
  (h.server_log IS NOT NULL) AS has_server_log, (h.input IS NOT NULL) AS has_input,
  h.title, h.extra_expenses, (h.build IS NOT NULL) AS has_build, h.build->'wod'->>'name' AS wod_name, h.build->'set'->>'name' AS set_name,
  CASE WHEN h.party IS NULL THEN 1 ELSE jsonb_array_length(COALESCE(h.party->'members', '[]'::jsonb)) END AS party_size`;

const PUBLIC_NAME = `CASE WHEN h.anonymous THEN NULL ELSE h.character_name END AS character_name,
  (c.verified_at IS NOT NULL AND NOT h.anonymous) AS verified`;

const PUBLIC_COLUMNS = LIST_COLUMNS.replace('h.character_name,', '');

export function registerHuntRoutes(app: FastifyInstance, { db, guards }: { db: pg.Pool; guards: AuthGuards }): void {
  const { requireUser, optionalUser, limited } = guards;

  // Server Log summaries can be large: allow more than the global 64 KB body limit here only
  app.post('/api/hunts', { preHandler: requireUser, bodyLimit: 512 * 1024 }, async (req, reply) => {
    const user = req.user!;
    if (await limited(`ratelimit:huntsave:${user.id}`, 120, 3600)) return reply.code(429).send({ error: 'rate_limited' });
    const h = validateHunt(req.body);
    if ('error' in h) return reply.code(400).send(h);
    const premium = isPremium(user);

    const count = (await db.query<{ n: number }>('SELECT COUNT(*)::int AS n FROM hunt_sessions WHERE user_id = $1', [user.id])).rows[0].n;
    const limit = premium ? HUNT_LIMIT.premium : HUNT_LIMIT.free;
    if (count >= limit) return reply.code(409).send({ error: 'hunt_limit', limit });

    // The character must belong to the account; its profile fills in vocation/level/world when missing
    let characterId: number | null = null;
    if (h.character_id) {
      const c = await db.query<{ id: number; name: string; vocation: string | null; level: number | null; world: string | null }>(
        'SELECT id, name, vocation, level, world FROM characters WHERE id = $1 AND user_id = $2',
        [h.character_id, user.id],
      );
      const row = c.rows[0];
      if (row) {
        characterId = row.id;
        h.character_name = row.name;
        h.vocation ??= baseVocation(row.vocation);
        h.level ??= row.level;
        h.world ??= row.world;
      }
    }
    if (h.place_slug && !h.place_name) {
      const p = await db.query<{ name: string }>('SELECT name FROM hunting_zones WHERE slug = $1', [h.place_slug]);
      h.place_name = p.rows[0]?.name ?? null;
      if (!p.rowCount) h.place_slug = null;
    }

    const fingerprint = huntFingerprint(user.id, h);
    const dup = await db.query<{ public_id: string }>('SELECT public_id FROM hunt_sessions WHERE user_id = $1 AND fingerprint = $2', [
      user.id,
      fingerprint,
    ]);
    if (dup.rowCount) return reply.code(200).send({ id: dup.rows[0].public_id, duplicate: true });

    // Only friends the user added can receive the hunt
    const sharedWith = h.shared_with.length
      ? (await db.query<{ friend_id: number }>('SELECT friend_id FROM friends WHERE user_id = $1 AND friend_id = ANY($2::int[])', [user.id, h.shared_with])).rows.map((r) => r.friend_id)
      : [];

    const { rows } = await db.query<{ public_id: string }>(
      `INSERT INTO hunt_sessions (public_id, user_id, character_id, character_name, world, vocation, level, place_slug, place_name,
         kind, visibility, anonymous, started_at, ended_at, duration_s, raw_xp, xp, raw_xp_h, xp_h, loot, supplies, balance,
         balance_h, damage, damage_h, healing, healing_h, monsters, items, party, input, server_log, notes, setup, fingerprint,
         title, description, expenses, extra_expenses, build, shared_with)
       VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19,$20,$21,$22,$23,$24,$25,$26,$27,$28,$29,$30,$31,$32,$33,$34,$35,
               $36,$37,$38,$39,$40,$41)
       RETURNING public_id`,
      [
        newPublicId(),
        user.id,
        characterId,
        h.character_name,
        h.world,
        h.vocation,
        h.level,
        h.place_slug,
        h.place_name,
        h.kind,
        h.visibility,
        h.anonymous,
        h.started_at,
        h.ended_at,
        h.duration_s,
        h.raw_xp,
        h.xp,
        h.raw_xp_h,
        h.xp_h,
        h.loot,
        h.supplies,
        h.balance,
        h.balance_h,
        h.damage,
        h.damage_h,
        h.healing,
        h.healing_h,
        JSON.stringify(h.monsters),
        JSON.stringify(h.items),
        h.party ? JSON.stringify(h.party) : null,
        h.input ? JSON.stringify(h.input) : null,
        // Server Log analysis is a Premium feature: free accounts keep the hunt without it
        premium && h.server_log ? JSON.stringify(h.server_log) : null,
        h.notes,
        JSON.stringify(h.setup),
        fingerprint,
        h.title,
        h.description,
        h.expenses.length ? JSON.stringify(h.expenses) : null,
        h.extra_expenses,
        h.build ? JSON.stringify(h.build) : null,
        sharedWith,
      ],
    );
    return reply.code(201).send({ id: rows[0].public_id, server_log_saved: premium && !!h.server_log });
  });

  app.get<{ Querystring: { character?: string; scope?: string } }>('/api/hunts/mine', { preHandler: requireUser }, async (req) => {
    const user = req.user!;
    const params: unknown[] = [user.id];
    // scope=shared: hunts friends shared with me (read only)
    if (req.query.scope === 'shared') {
      const { rows } = await db.query(
        `SELECT ${LIST_COLUMNS}, u.display_name AS owner FROM hunt_sessions h JOIN users u ON u.id = h.user_id
          WHERE $1 = ANY(h.shared_with) ORDER BY COALESCE(h.started_at, h.created_at::timestamp) DESC LIMIT 500`,
        params,
      );
      return { hunts: rows.map(numeric), shared: true };
    }
    let where = 'h.user_id = $1';
    if (req.query.character) {
      params.push(req.query.character);
      where += ` AND LOWER(h.character_name) = LOWER($${params.length})`;
    }
    const { rows } = await db.query(
      `SELECT ${LIST_COLUMNS} FROM hunt_sessions h WHERE ${where}
        ORDER BY COALESCE(h.started_at, h.created_at::timestamp) DESC LIMIT 2000`,
      params,
    );
    const premium = isPremium(user);
    return { hunts: rows.map(numeric), limit: premium ? HUNT_LIMIT.premium : HUNT_LIMIT.free, premium };
  });

  app.get<{ Params: { id: string } }>('/api/hunts/:id', { preHandler: optionalUser }, async (req, reply) => {
    if (!/^[A-Za-z0-9_-]{6,16}$/.test(req.params.id)) return reply.code(404).send({ error: 'not_found' });
    const { rows } = await db.query(
      `SELECT h.*, to_char(h.started_at, 'YYYY-MM-DD"T"HH24:MI:SS') AS started_at, to_char(h.ended_at, 'YYYY-MM-DD"T"HH24:MI:SS') AS ended_at, (c.verified_at IS NOT NULL) AS verified FROM hunt_sessions h
         LEFT JOIN characters c ON c.id = h.character_id WHERE h.public_id = $1`,
      [req.params.id],
    );
    const h = rows[0];
    const mine = !!req.user && h?.user_id === req.user.id;
    const shared = !!req.user && !mine && Array.isArray(h?.shared_with) && h.shared_with.includes(req.user.id);
    if (!h || (!mine && !shared && h.visibility !== 'public')) return reply.code(404).send({ error: 'not_found' });
    const { user_id: _u, fingerprint: _f, id: _i, shared_with: sharedWith, ...rest } = h;
    const out = numeric(rest);
    // D-64: hunts saved before the Server Log was rebuilt on save are cleaned on the way out too
    if (out.server_log) out.server_log = sanitizeServerLog(out.server_log);
    if (!mine && h.anonymous) {
      out.character_name = null;
      out.verified = false;
      if (out.party) out.party = { ...out.party, members: out.party.members.map((m: { name: string }, i: number) => ({ ...m, name: `#${i + 1}` })) };
    }
    if (!mine) delete out.notes;
    if (mine && sharedWith?.length) {
      const f = await db.query<{ id: number; display_name: string }>('SELECT id, display_name FROM users WHERE id = ANY($1::int[])', [sharedWith]);
      out.shared_with = f.rows;
    }
    return { hunt: out, mine, shared };
  });

  app.patch<{ Params: { id: string }; Body: Record<string, unknown> }>('/api/hunts/:id', { preHandler: requireUser }, async (req, reply) => {
    const b = req.body ?? {};
    const sets: string[] = [];
    const params: unknown[] = [req.params.id, req.user!.id];
    const set = (col: string, v: unknown) => {
      params.push(v);
      sets.push(`${col} = $${params.length}`);
    };
    if (b.visibility === 'public' || b.visibility === 'private') set('visibility', b.visibility);
    if (typeof b.anonymous === 'boolean') set('anonymous', b.anonymous);
    if ('notes' in b) set('notes', typeof b.notes === 'string' ? b.notes.trim().slice(0, 1000) || null : null);
    if ('level' in b) {
      const lv = Number(b.level);
      set('level', Number.isInteger(lv) && lv > 0 && lv <= 5000 ? lv : null);
    }
    if ('vocation' in b) set('vocation', baseVocation(b.vocation));
    if ('title' in b) set('title', typeof b.title === 'string' ? b.title.trim().slice(0, 120) || null : null);
    if ('description' in b) set('description', typeof b.description === 'string' ? b.description.trim().slice(0, 2000) || null : null);
    if ('expenses' in b) {
      const e = validateExpenses(b.expenses);
      if (!e) return reply.code(400).send({ error: 'invalid_hunt', field: 'expenses' });
      set('expenses', e.rows.length ? JSON.stringify(e.rows) : null);
      set('extra_expenses', e.total);
    }
    if ('build' in b) {
      const build = validateBuild(b.build);
      set('build', build ? JSON.stringify(build) : null);
    }
    if ('shared_with' in b) {
      const ids = friendIds(b.shared_with);
      const ok = ids.length
        ? (await db.query<{ friend_id: number }>('SELECT friend_id FROM friends WHERE user_id = $1 AND friend_id = ANY($2::int[])', [req.user!.id, ids])).rows.map((r) => r.friend_id)
        : [];
      set('shared_with', ok);
    }
    if ('place_slug' in b) {
      const slug = typeof b.place_slug === 'string' && /^[a-z0-9-]{1,160}$/.test(b.place_slug) ? b.place_slug : null;
      const name = slug ? (await db.query<{ name: string }>('SELECT name FROM hunting_zones WHERE slug = $1', [slug])).rows[0]?.name : null;
      set('place_slug', name ? slug : null);
      set('place_name', name ?? (typeof b.place_name === 'string' ? b.place_name.trim().slice(0, 160) || null : null));
    }
    if (!sets.length) return reply.code(400).send({ error: 'nothing_to_update' });
    const r = await db.query(`UPDATE hunt_sessions SET ${sets.join(', ')} WHERE public_id = $1 AND user_id = $2`, params);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    return { ok: true };
  });

  app.delete<{ Params: { id: string } }>('/api/hunts/:id', { preHandler: requireUser }, async (req, reply) => {
    const r = await db.query('DELETE FROM hunt_sessions WHERE public_id = $1 AND user_id = $2', [req.params.id, req.user!.id]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    return { ok: true };
  });

  // ---------------------------------------------------------------------------
  // Public: feed, rankings and place statistics (only sessions made public by their owners)
  // ---------------------------------------------------------------------------

  /** Shared filters for public queries */
  function publicFilters(q: Record<string, string | undefined>, params: unknown[]): string {
    const where = [`h.visibility = 'public'`];
    const voc = baseVocation(q.vocation);
    if (voc) {
      params.push(voc);
      where.push(`h.vocation = $${params.length}`);
    }
    const min = Number(q.min_level);
    if (Number.isInteger(min) && min > 0) {
      params.push(min);
      where.push(`h.level >= $${params.length}`);
    }
    const max = Number(q.max_level);
    if (Number.isInteger(max) && max > 0) {
      params.push(max);
      where.push(`h.level <= $${params.length}`);
    }
    if (q.place && /^[a-z0-9-]{1,160}$/.test(q.place)) {
      params.push(q.place);
      where.push(`h.place_slug = $${params.length}`);
    }
    if (q.kind === 'solo' || q.kind === 'party') {
      params.push(q.kind);
      where.push(`h.kind = $${params.length}`);
    }
    const days = q.period === '7d' ? 7 : q.period === '30d' ? 30 : q.period === '90d' ? 90 : 0;
    if (days) where.push(`h.created_at > now() - interval '${days} days'`);
    return where.join(' AND ');
  }

  app.get<{ Querystring: Record<string, string | undefined> }>('/api/hunts/public', async (req) => {
    const params: unknown[] = [];
    const where = publicFilters(req.query, params);
    const sort = req.query.sort === 'xp_h' ? 'h.xp_h DESC' : req.query.sort === 'balance_h' ? 'h.balance_h DESC' : 'h.created_at DESC';
    const page = Math.max(0, Math.min(50, Number(req.query.page) || 0));
    const { rows } = await db.query(
      `SELECT ${PUBLIC_COLUMNS}, ${PUBLIC_NAME} FROM hunt_sessions h LEFT JOIN characters c ON c.id = h.character_id
        WHERE ${where} ORDER BY ${sort} LIMIT 30 OFFSET ${page * 30}`,
      params,
    );
    return { hunts: rows.map(numeric), page };
  });

  app.get<{ Querystring: Record<string, string | undefined> }>('/api/hunts/ranking', async (req) => {
    const params: unknown[] = [RANKING_MIN_SECONDS];
    const where = publicFilters({ period: '7d', ...req.query }, params) + ' AND h.duration_s >= $1';
    const metric = (['xp_h', 'raw_xp_h', 'balance_h', 'damage_h'] as const).find((m) => m === req.query.metric) ?? 'xp_h';
    // Best session per character, so one player cannot fill the board
    const { rows } = await db.query(
      `SELECT * FROM (
         SELECT DISTINCT ON (COALESCE(LOWER(h.character_name), h.public_id)) ${PUBLIC_COLUMNS}, ${PUBLIC_NAME}
           FROM hunt_sessions h LEFT JOIN characters c ON c.id = h.character_id
          WHERE ${where}
          ORDER BY COALESCE(LOWER(h.character_name), h.public_id), h.${metric} DESC
       ) best ORDER BY ${metric} DESC LIMIT 50`,
      params,
    );
    return { metric, hunts: rows.map(numeric) };
  });

  /**
   * D-61: character performance — every hunt of one of my characters with the creatures killed and the skill
   * advances read from the Server Log (the list endpoint leaves those out to stay small).
   */
  app.get<{ Querystring: { character?: string } }>('/api/hunts/performance', { preHandler: requireUser }, async (req, reply) => {
    const name = typeof req.query.character === 'string' ? req.query.character.trim().slice(0, 40) : '';
    if (!name) return reply.code(400).send({ error: 'character_required' });
    const { rows } = await db.query(
      `SELECT ${LIST_COLUMNS}, h.monsters, h.server_log->'skills' AS skills, h.input FROM hunt_sessions h
        WHERE h.user_id = $1 AND LOWER(h.character_name) = LOWER($2)
        ORDER BY COALESCE(h.started_at, h.created_at::timestamp) DESC LIMIT 2000`,
      [req.user!.id, name],
    );
    // Other characters of mine with hunts, for the comparison picker
    const others = await db.query<{ name: string }>(
      `SELECT DISTINCT character_name AS name FROM hunt_sessions WHERE user_id = $1 AND character_name IS NOT NULL AND LOWER(character_name) <> LOWER($2) ORDER BY 1 LIMIT 50`,
      [req.user!.id, name],
    );
    return { character: rows[0]?.character_name ?? name, hunts: rows.map(numeric), others: others.rows.map((r) => r.name) };
  });

  /**
   * D-72: weapon comparison — my hunts whose saved set has a weapon, with the damage numbers of the Server Log
   * (hits, crits, damage per target, minutes in combat) or, without it, the Hunt Analyser damage per hour.
   */
  app.get('/api/hunts/weapons', { preHandler: requireUser }, async (req) => {
    const { rows } = await db.query(
      `SELECT h.public_id, to_char(h.started_at, 'YYYY-MM-DD"T"HH24:MI:SS') AS started_at, h.created_at, h.place_name, h.place_slug, h.character_name,
              h.duration_s, h.damage, h.damage_h, h.build->'set'->'data'->'slots'->'weapon' AS weapon,
              h.server_log->'dealt' AS dealt,
              (SELECT COUNT(*) FROM jsonb_array_elements(COALESCE(h.server_log->'timeline', '[]'::jsonb)) t WHERE (t->>'dealt')::numeric > 0)::int AS combat_minutes
         FROM hunt_sessions h
        WHERE h.user_id = $1 AND h.build->'set'->'data'->'slots'->'weapon'->>'name' IS NOT NULL
        ORDER BY COALESCE(h.started_at, h.created_at::timestamp) DESC LIMIT 500`,
      [req.user!.id],
    );
    return { hunts: rows.map(numeric) };
  });

  /** D-61: best public sessions of the last 7 days — one per vocation (XP/h) and the top profit/h */
  app.get('/api/hunts/weekly-best', async () => {
    const base = `FROM hunt_sessions h LEFT JOIN characters c ON c.id = h.character_id
      WHERE h.visibility = 'public' AND h.duration_s >= $1 AND h.created_at > now() - interval '7 days'`;
    const xp = await db.query(
      `SELECT DISTINCT ON (h.vocation) ${PUBLIC_COLUMNS}, ${PUBLIC_NAME} ${base} AND h.vocation IS NOT NULL
        ORDER BY h.vocation, h.xp_h DESC`,
      [RANKING_MIN_SECONDS],
    );
    const profit = await db.query(
      `SELECT ${PUBLIC_COLUMNS}, ${PUBLIC_NAME} ${base} ORDER BY h.balance_h DESC LIMIT 3`,
      [RANKING_MIN_SECONDS],
    );
    return { xp: xp.rows.map(numeric).sort((a, b) => b.xp_h - a.xp_h), profit: profit.rows.map(numeric) };
  });

  // ---------------------------------------------------------------------------
  // Weekly goals with streak (D-53)
  // ---------------------------------------------------------------------------

  app.get('/api/hunts/goals', { preHandler: requireUser }, async (req) => {
    const userId = req.user!.id;
    const { rows: goals } = await db.query<{ id: number; character_name: string; metric: GoalMetric; target: string }>(
      'SELECT id, character_name, metric, target FROM hunt_goals WHERE user_id = $1 ORDER BY created_at',
      [userId],
    );
    if (!goals.length) return { goals: [] };
    // Weekly sums per character (and for "all characters" = ''), last ~2 years
    const { rows: weeks } = await db.query<{ character_name: string; week: string; xp: string; profit: string }>(
      `SELECT COALESCE(LOWER(character_name), '') AS character_name,
              to_char(date_trunc('week', COALESCE(started_at, created_at AT TIME ZONE 'UTC')), 'YYYY-MM-DD') AS week,
              SUM(xp)::bigint AS xp, SUM(balance)::bigint AS profit
         FROM hunt_sessions
        WHERE user_id = $1 AND COALESCE(started_at, created_at AT TIME ZONE 'UTC') > now() - interval '730 days'
        GROUP BY 1, 2`,
      [userId],
    );
    return {
      goals: goals.map((g) => {
        const key = g.character_name.toLowerCase();
        const sums = new Map<string, number>();
        for (const w of weeks) {
          if (key && w.character_name !== key) continue;
          sums.set(w.week, (sums.get(w.week) ?? 0) + Number(g.metric === 'xp' ? w.xp : w.profit));
        }
        return {
          id: g.id,
          character_name: g.character_name || null,
          metric: g.metric,
          ...goalProgress([...sums].map(([week, value]) => ({ week, value })), Number(g.target)),
        };
      }),
    };
  });

  app.put<{ Body: { character_name?: unknown; metric?: unknown; target?: unknown } }>(
    '/api/hunts/goals',
    { preHandler: requireUser },
    async (req, reply) => {
      const metric: GoalMetric | null = req.body?.metric === 'xp' ? 'xp' : req.body?.metric === 'profit' ? 'profit' : null;
      const target = Number(req.body?.target);
      if (!metric || !Number.isInteger(target) || target < 1 || target > 1e12) return reply.code(400).send({ error: 'invalid_goal' });
      const name = typeof req.body?.character_name === 'string' ? req.body.character_name.trim().slice(0, 29) : '';
      // 20 goals per account; updating an existing one is always allowed
      const { rows: stat } = await db.query<{ n: number; exists: boolean }>(
        `SELECT COUNT(*)::int AS n, BOOL_OR(LOWER(character_name) = LOWER($2) AND metric = $3) AS exists
           FROM hunt_goals WHERE user_id = $1`,
        [req.user!.id, name, metric],
      );
      if (stat[0].n >= 20 && !stat[0].exists) return reply.code(409).send({ error: 'goal_limit' });
      const { rows } = await db.query<{ id: number }>(
        `INSERT INTO hunt_goals (user_id, character_name, metric, target) VALUES ($1, $2, $3, $4)
         ON CONFLICT (user_id, LOWER(character_name), metric) DO UPDATE SET target = EXCLUDED.target
         RETURNING id`,
        [req.user!.id, name, metric, target],
      );
      return { id: rows[0].id };
    },
  );

  app.delete<{ Params: { id: string } }>('/api/hunts/goals/:id', { preHandler: requireUser }, async (req, reply) => {
    const r = await db.query('DELETE FROM hunt_goals WHERE id = $1 AND user_id = $2', [Number(req.params.id), req.user!.id]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    return { ok: true };
  });

  app.get<{ Querystring: Record<string, string | undefined> }>('/api/hunts/places', async (req) => {
    const params: unknown[] = [RANKING_MIN_SECONDS];
    const where = publicFilters(req.query, params) + ' AND h.duration_s >= $1 AND h.place_slug IS NOT NULL';
    // ?by=vocation splits each place per vocation (the hunting place page shows one line per vocation)
    const byVoc = req.query.by === 'vocation';
    const { rows } = await db.query(
      `SELECT h.place_slug, MAX(h.place_name) AS place_name, ${byVoc ? 'h.vocation,' : ''} COUNT(*)::int AS hunts,
              COUNT(DISTINCT LOWER(h.character_name))::int AS characters,
              ROUND(percentile_cont(0.5) WITHIN GROUP (ORDER BY h.raw_xp_h))::bigint AS raw_xp_h,
              ROUND(percentile_cont(0.5) WITHIN GROUP (ORDER BY h.xp_h))::bigint AS xp_h,
              ROUND(percentile_cont(0.5) WITHIN GROUP (ORDER BY h.balance_h))::bigint AS balance_h,
              MIN(h.level) AS min_level, MAX(h.level) AS max_level
         FROM hunt_sessions h WHERE ${where}
        GROUP BY h.place_slug${byVoc ? ', h.vocation' : ''} HAVING COUNT(*) >= 1
        ORDER BY COUNT(*) DESC LIMIT 100`,
      params,
    );
    return { places: rows.map(numeric) };
  });
}

/** BIGINT columns come back as strings from pg */
const BIG = ['extra_expenses', 'raw_xp', 'xp', 'raw_xp_h', 'xp_h', 'loot', 'supplies', 'balance', 'balance_h', 'damage', 'damage_h', 'healing', 'healing_h'];
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function numeric(row: Record<string, any>): Record<string, any> {
  const out = { ...row };
  for (const k of BIG) if (out[k] != null) out[k] = Number(out[k]);
  return out;
}
