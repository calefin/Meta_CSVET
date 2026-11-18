/**
 * Payment distributions (D-60): who pays whom after several party hunts.
 *
 *   GET    /api/payouts/hunts?days=30   party hunts I can settle (mine + shared with me), with members and expenses
 *   GET    /api/payouts                 my distributions + the ones friends shared with me
 *   POST   /api/payouts                 save { name, hunt_ids, aliases, round_to, players, transfers, total, shared_with? }
 *   PATCH  /api/payouts/:id             owner: { name?, paid?, shared_with? } · friend it is shared with: { paid } only
 *   DELETE /api/payouts/:id             owner only
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';
import { friendIds } from './huntBuild.js';
import { PAYOUT_LIMIT, validatePaidFlags, validatePayout } from './payouts.js';

const COLUMNS = `p.id, p.name, p.hunt_ids, p.aliases, p.round_to, p.players, p.transfers, p.total, p.created_at, p.updated_at, p.settled_at`;

export function registerPayoutRoutes(app: FastifyInstance, { db, guards }: { db: pg.Pool; guards: AuthGuards }): void {
  const { requireUser, limited } = guards;

  /** Friend ids among `ids` (sharing only goes to people I added) */
  async function myFriends(userId: number, v: unknown): Promise<number[]> {
    const ids = friendIds(v);
    if (!ids.length) return [];
    const r = await db.query<{ friend_id: number }>('SELECT friend_id FROM friends WHERE user_id = $1 AND friend_id = ANY($2::int[])', [userId, ids]);
    return r.rows.map((x) => x.friend_id);
  }

  app.get<{ Querystring: { days?: string } }>('/api/payouts/hunts', { preHandler: requireUser }, async (req) => {
    const userId = req.user!.id;
    const days = Number(req.query.days ?? 30);
    const params: unknown[] = [userId];
    let since = '';
    if (Number.isInteger(days) && days > 0 && days <= 3650) {
      params.push(days);
      since = ` AND COALESCE(h.started_at, h.created_at::timestamp) >= now()::timestamp - make_interval(days => $2)`;
    }
    const { rows } = await db.query(
      `SELECT h.public_id AS id, h.title, h.place_name, h.character_name, h.anonymous, (h.user_id = $1) AS mine,
              CASE WHEN h.user_id = $1 THEN NULL ELSE u.display_name END AS owner,
              to_char(h.started_at, 'YYYY-MM-DD"T"HH24:MI:SS') AS started_at, h.duration_s, h.balance, h.extra_expenses,
              h.party, h.expenses,
              ARRAY(SELECT p.id FROM hunt_payouts p WHERE p.user_id = $1 AND h.public_id = ANY(p.hunt_ids)) AS payout_ids
         FROM hunt_sessions h JOIN users u ON u.id = h.user_id
        WHERE (h.user_id = $1 OR $1 = ANY(h.shared_with)) AND h.kind = 'party' AND h.party IS NOT NULL${since}
        ORDER BY COALESCE(h.started_at, h.created_at::timestamp) DESC LIMIT 300`,
      params,
    );
    const hunts = rows.map((h) => {
      const members = (h.party?.members ?? []) as { name: string; balance: number; isLeader?: boolean }[];
      // Same rule as the hunt page: a friend's anonymous hunt does not show the names
      const hide = !h.mine && h.anonymous;
      return {
        id: h.id,
        title: h.title,
        place_name: h.place_name,
        character_name: hide ? null : h.character_name,
        owner: h.owner,
        started_at: h.started_at,
        duration_s: h.duration_s,
        balance: Number(h.balance),
        extra_expenses: Number(h.extra_expenses),
        members: members.map((m, i) => ({ name: hide ? `#${i + 1}` : m.name, balance: Number(m.balance) || 0, isLeader: !!m.isLeader })),
        expenses: h.expenses ?? [],
        anonymous: hide,
        payout_ids: h.payout_ids,
      };
    });
    return { hunts };
  });

  app.get('/api/payouts', { preHandler: requireUser }, async (req) => {
    const userId = req.user!.id;
    const mine = await db.query(`SELECT ${COLUMNS}, p.shared_with FROM hunt_payouts p WHERE p.user_id = $1 ORDER BY p.created_at DESC LIMIT ${PAYOUT_LIMIT}`, [
      userId,
    ]);
    const shared = await db.query(
      `SELECT ${COLUMNS}, u.display_name AS owner FROM hunt_payouts p JOIN users u ON u.id = p.user_id
        WHERE $1 = ANY(p.shared_with) ORDER BY p.created_at DESC LIMIT 100`,
      [userId],
    );
    const ids = [...new Set(mine.rows.flatMap((r) => r.shared_with as number[]))];
    const names = ids.length ? (await db.query<{ id: number; display_name: string }>('SELECT id, display_name FROM users WHERE id = ANY($1::int[])', [ids])).rows : [];
    const out = (r: Record<string, unknown>, isMine: boolean) => ({
      ...r,
      total: Number(r.total),
      mine: isMine,
      shared_with: isMine ? names.filter((n) => (r.shared_with as number[]).includes(n.id)) : undefined,
    });
    return { payouts: [...mine.rows.map((r) => out(r, true)), ...shared.rows.map((r) => out(r, false))] };
  });

  app.post<{ Body: Record<string, unknown> }>('/api/payouts', { preHandler: requireUser }, async (req, reply) => {
    const userId = req.user!.id;
    if (await limited(`ratelimit:payout:${userId}`, 60, 3600)) return reply.code(429).send({ error: 'rate_limited' });
    const p = validatePayout(req.body);
    if (!p) return reply.code(400).send({ error: 'invalid_payout' });
    const n = (await db.query<{ n: number }>('SELECT COUNT(*)::int AS n FROM hunt_payouts WHERE user_id = $1', [userId])).rows[0].n;
    if (n >= PAYOUT_LIMIT) return reply.code(409).send({ error: 'payout_limit', limit: PAYOUT_LIMIT });
    // Only hunts I can see: mine or shared with me
    const ok = await db.query<{ public_id: string }>(
      `SELECT public_id FROM hunt_sessions WHERE public_id = ANY($2::varchar[]) AND (user_id = $1 OR $1 = ANY(shared_with))`,
      [userId, p.hunt_ids],
    );
    const huntIds = p.hunt_ids.filter((id) => ok.rows.some((r) => r.public_id === id));
    if (!huntIds.length) return reply.code(400).send({ error: 'invalid_payout', field: 'hunt_ids' });
    const shared = await myFriends(userId, req.body?.shared_with);
    const settled = p.transfers.length > 0 && p.transfers.every((t) => t.paid);
    const { rows } = await db.query<{ id: number }>(
      `INSERT INTO hunt_payouts (user_id, name, hunt_ids, aliases, round_to, players, transfers, total, shared_with, settled_at)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, ${settled ? 'now()' : 'NULL'}) RETURNING id`,
      [userId, p.name, huntIds, JSON.stringify(p.aliases), p.round_to, JSON.stringify(p.players), JSON.stringify(p.transfers), p.total, shared],
    );
    return reply.code(201).send({ id: rows[0].id, hunt_ids: huntIds });
  });

  app.patch<{ Params: { id: string }; Body: Record<string, unknown> }>('/api/payouts/:id', { preHandler: requireUser }, async (req, reply) => {
    const userId = req.user!.id;
    const id = Number(req.params.id);
    if (!Number.isInteger(id) || id <= 0) return reply.code(404).send({ error: 'not_found' });
    const { rows } = await db.query<{ user_id: number; transfers: { paid: boolean }[]; shared_with: number[] }>(
      'SELECT user_id, transfers, shared_with FROM hunt_payouts WHERE id = $1',
      [id],
    );
    const row = rows[0];
    const owner = row?.user_id === userId;
    if (!row || (!owner && !row.shared_with.includes(userId))) return reply.code(404).send({ error: 'not_found' });
    const b = req.body ?? {};
    const sets: string[] = [];
    const params: unknown[] = [id];
    const set = (col: string, v: unknown) => {
      params.push(v);
      sets.push(`${col} = $${params.length}`);
    };
    if ('paid' in b) {
      const flags = validatePaidFlags(b.paid, row.transfers.length);
      if (!flags) return reply.code(400).send({ error: 'invalid_payout', field: 'paid' });
      set('transfers', JSON.stringify(row.transfers.map((t, i) => ({ ...t, paid: flags[i] }))));
      sets.push(flags.length && flags.every(Boolean) ? 'settled_at = COALESCE(settled_at, now())' : 'settled_at = NULL');
    }
    if (owner && 'name' in b) {
      const name = typeof b.name === 'string' ? b.name.trim().slice(0, 120) : '';
      if (!name) return reply.code(400).send({ error: 'invalid_payout', field: 'name' });
      set('name', name);
    }
    if (owner && 'shared_with' in b) set('shared_with', await myFriends(userId, b.shared_with));
    if (!sets.length) return reply.code(400).send({ error: 'nothing_to_update' });
    await db.query(`UPDATE hunt_payouts SET ${sets.join(', ')}, updated_at = now() WHERE id = $1`, params);
    return { ok: true };
  });

  app.delete<{ Params: { id: string } }>('/api/payouts/:id', { preHandler: requireUser }, async (req, reply) => {
    const id = Number(req.params.id);
    if (!Number.isInteger(id) || id <= 0) return reply.code(404).send({ error: 'not_found' });
    const r = await db.query('DELETE FROM hunt_payouts WHERE id = $1 AND user_id = $2', [id, req.user!.id]);
    if (!r.rowCount) return reply.code(404).send({ error: 'not_found' });
    return { ok: true };
  });
}
