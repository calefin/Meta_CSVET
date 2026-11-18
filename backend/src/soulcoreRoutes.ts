/**
 * Soul core tracker per character (D-41). Catalog: shared/soulcores.json (scripts/import-soulcores.mjs).
 *
 * GET  /api/soulcores                         → catalog (public)
 * GET  /api/characters/:id/soulcores          → creatures whose soul core this character already has (owner only)
 * PUT  /api/characters/:id/soulcores          → { creature, owned } toggle one
 * POST /api/characters/:id/soulcores/bulk     → { creatures: string[], owned } mark many (paste a list, "mark all")
 */
import { readFile } from 'node:fs/promises';
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';

export interface SoulCore {
  creature: string;
  itemId: number;
  bestiaryClass: string | null;
  difficulty: string | null;
}

export async function loadSoulCores(file: string): Promise<SoulCore[]> {
  try {
    const raw = JSON.parse(await readFile(file, 'utf8')) as { soulcores?: SoulCore[] };
    return raw.soulcores ?? [];
  } catch {
    return [];
  }
}

/** Case-insensitive lookup; also accepts "Dragon Soul Core" */
export function soulCoreIndex(list: SoulCore[]): (raw: unknown) => string | null {
  const byKey = new Map(list.map((s) => [s.creature.toLowerCase(), s.creature]));
  return (raw) => {
    if (typeof raw !== 'string') return null;
    const key = raw.trim().replace(/\s+/g, ' ').replace(/ soul core$/i, '').toLowerCase();
    return byKey.get(key) ?? null;
  };
}

export function registerSoulCoreRoutes(app: FastifyInstance, opts: { db: pg.Pool; guards: AuthGuards; catalog: SoulCore[] }) {
  const { db, guards, catalog } = opts;
  const resolve = soulCoreIndex(catalog);

  async function ownsCharacter(userId: number, idParam: string): Promise<number | null> {
    const id = Number(idParam);
    if (!Number.isInteger(id) || id <= 0) return null;
    const { rows } = await db.query<{ id: number }>('SELECT id FROM characters WHERE id = $1 AND user_id = $2', [id, userId]);
    return rows[0]?.id ?? null;
  }

  // D-57: each soul core carries its creature sprite (official CipSoft library or TibiaWiki), cached 1 h
  let images: { at: number; map: Map<string, string> } | null = null;
  async function imageMap(): Promise<Map<string, string>> {
    if (images && Date.now() - images.at < 3600_000) return images.map;
    const { rows } = await db.query<{ name: string; image_url: string }>('SELECT name, image_url FROM creatures WHERE image_url IS NOT NULL');
    images = { at: Date.now(), map: new Map(rows.map((r) => [r.name.toLowerCase(), r.image_url])) };
    return images.map;
  }

  app.get('/api/soulcores', async (_req, reply) => {
    reply.header('Cache-Control', 'public, max-age=3600');
    const map = await imageMap().catch(() => new Map<string, string>());
    return {
      total: catalog.length,
      soulcores: catalog.map((c) => ({ ...c, image_url: map.get(c.creature.toLowerCase()) ?? null })),
    };
  });

  app.get<{ Params: { id: string } }>('/api/characters/:id/soulcores', { preHandler: guards.requireUser }, async (req, reply) => {
    const id = await ownsCharacter(req.user!.id, req.params.id);
    if (!id) return reply.code(404).send({ error: 'not_found' });
    const { rows } = await db.query<{ creature: string; added_at: Date }>(
      'SELECT creature, added_at FROM character_soulcores WHERE character_id = $1 ORDER BY added_at DESC',
      [id],
    );
    return { total: catalog.length, owned: rows.map((r) => r.creature), last_added: rows[0]?.added_at ?? null };
  });

  app.put<{ Params: { id: string }; Body: { creature?: unknown; owned?: unknown } }>(
    '/api/characters/:id/soulcores',
    { preHandler: guards.requireUser },
    async (req, reply) => {
      const id = await ownsCharacter(req.user!.id, req.params.id);
      if (!id) return reply.code(404).send({ error: 'not_found' });
      const creature = resolve(req.body?.creature);
      if (!creature) return reply.code(400).send({ error: 'unknown_soulcore' });
      if (typeof req.body?.owned !== 'boolean') return reply.code(400).send({ error: 'invalid' });
      if (await guards.limited(`ratelimit:soulcore:${req.user!.id}`, 600, 3600)) return reply.code(429).send({ error: 'rate_limited' });
      if (req.body.owned) {
        await db.query(
          'INSERT INTO character_soulcores (character_id, creature) VALUES ($1, $2) ON CONFLICT DO NOTHING',
          [id, creature],
        );
      } else {
        await db.query('DELETE FROM character_soulcores WHERE character_id = $1 AND creature = $2', [id, creature]);
      }
      return { ok: true, creature, owned: req.body.owned };
    },
  );

  app.post<{ Params: { id: string }; Body: { creatures?: unknown; owned?: unknown } }>(
    '/api/characters/:id/soulcores/bulk',
    { preHandler: guards.requireUser },
    async (req, reply) => {
      const id = await ownsCharacter(req.user!.id, req.params.id);
      if (!id) return reply.code(404).send({ error: 'not_found' });
      if (!Array.isArray(req.body?.creatures) || req.body.creatures.length > 2000 || typeof req.body?.owned !== 'boolean') {
        return reply.code(400).send({ error: 'invalid' });
      }
      if (await guards.limited(`ratelimit:soulcore-bulk:${req.user!.id}`, 30, 3600)) return reply.code(429).send({ error: 'rate_limited' });
      const known: string[] = [];
      const unknown: string[] = [];
      for (const raw of req.body.creatures) {
        const c = resolve(raw);
        if (c) known.push(c);
        else if (typeof raw === 'string' && raw.trim()) unknown.push(raw.trim().slice(0, 60));
      }
      const unique = [...new Set(known)];
      if (req.body.owned) {
        await db.query(
          `INSERT INTO character_soulcores (character_id, creature)
           SELECT $1, unnest($2::text[]) ON CONFLICT DO NOTHING`,
          [id, unique],
        );
      } else {
        await db.query('DELETE FROM character_soulcores WHERE character_id = $1 AND creature = ANY($2::text[])', [id, unique]);
      }
      return { ok: true, changed: unique.length, unknown: unknown.slice(0, 50) };
    },
  );
}
