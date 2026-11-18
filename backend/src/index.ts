import Fastify from 'fastify';
import pg from 'pg';
import { Redis } from 'ioredis';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { parseBossLogLine, payloadHash, playerFingerprint, seedBosses } from './bossLog.js';
import { nameCandidates } from './normalize.js';
import { SPAWNRANK_SQL, spawnScore } from './spawnrank.js';
import { getBoosted, getCreatureLibrary, raceKey, sameName } from './tibiadata.js';
import { migrate } from './migrate.js';
import { mergeDuplicateCreatures } from './creatureNames.js';
import { libraryIndex, libraryLookup, wikiCreatureImages } from './creatureImages.js';
import { bootstrapAdmins, bootstrapPremiumUsers, registerAccountRoutes } from './accounts.js';
import { providersFromEnv } from './oauth.js';
import { registerYasirRoutes } from './yasirRoutes.js';
import { loadSoulCores, registerSoulCoreRoutes, soulCoreIndex } from './soulcoreRoutes.js';
import { registerPersonalAlerts, type CharEvent, type PersonalAlerts } from './personalAlerts.js';
import { createGuildRanking } from './guildRanking.js';
import { registerRetroRoutes } from './retro.js';
import type { DonationPaid } from './pixRoutes.js';
import { loadHuntingPlaces, registerHuntingPlaceRoutes, seedHuntingPlaces } from './huntingPlaces.js';
import { loadCreatureSheets, registerCreatureInfoRoutes } from './creatureInfo.js';
import { registerCharacterRoutes } from './characterRoutes.js';
import { registerDailyRoutes } from './dailyRoutes.js';
import { registerWeeklyRoutes } from './weeklyRoutes.js';
import { registerHuntRoutes } from './huntRoutes.js';
import { registerHuntExtraRoutes } from './huntExtraRoutes.js';
import { registerPayoutRoutes } from './payoutRoutes.js';
import { registerPixRoutes } from './pixRoutes.js';
import { registerNotifyRoutes } from './notifyRoutes.js';
import { registerTelegram, type TelegramBot } from './telegramBot.js';
import { createBulletinSource } from './bulletinSource.js';
import { registerAdminRoutes } from './adminRoutes.js';
import { createMailer } from './mailer.js';
import { createAlerts, type Alerts } from './alerts.js';
import { adsConfig, adsForBrowser, adsTxt } from './ads.js';
import { recordError } from './errorLog.js';
import { registerPromoRoutes } from './promoRoutes.js';
import { registerBillingRoutes } from './billingRoutes.js';

const PORT = Number(process.env.PORT ?? 3000);
// Version from package.json (CHANGELOG.md)
const APP_VERSION = (JSON.parse(await readFile(new URL('../package.json', import.meta.url), 'utf8')) as { version: string }).version;
const DATABASE_URL = process.env.DATABASE_URL ?? 'postgres://bagparser_user:secure_db_pass@localhost:5432/bagparser_db';
const REDIS_URL = process.env.REDIS_URL ?? 'redis://localhost:6379';
const FINGERPRINT_SALT = process.env.FINGERPRINT_SALT ?? 'dev_salt';
const ITEMS_JSON = process.env.ITEMS_JSON ?? path.resolve(process.cwd(), '../shared/items.json');

const RARE_VALUE_THRESHOLD = 10_000; // FR-BL-07
const MAX_LINES_PER_SUBMIT = 20;
const SUBMITS_PER_MINUTE = 10;
// Number of reverse proxies in front of the API (host Nginx + web container Nginx).
// Trusting the whole X-Forwarded-For chain would let clients spoof their IP and bypass
// the BossLedger cooldown / rate limit (D-17).
const TRUST_PROXY_HOPS = Number(process.env.TRUST_PROXY_HOPS ?? 2);
// Accounts (D-24): e-mails that always get the admin role; session cookie Secure flag (auto = follow HTTPS)
const ADMIN_EMAILS = (process.env.ADMIN_EMAILS ?? '')
  .split(',')
  .map((e) => e.trim().toLowerCase())
  .filter(Boolean);
const COOKIE_SECURE = process.env.COOKIE_SECURE === 'true' ? true : process.env.COOKIE_SECURE === 'false' ? false : 'auto';

const db = new pg.Pool({ connectionString: DATABASE_URL, max: 10 });
const redis = new Redis(REDIS_URL, { maxRetriesPerRequest: 2, lazyConnect: false });
redis.on('error', (err) => app.log.warn({ err: err.message }, 'redis error'));

// Trust exactly the first N proxies in front of the API (NPM → web Nginx → API); same meaning as the old numeric
// option (proxy-addr hop count), written as a function for Fastify 5's types. Clients cannot fake their IP past them.
// D-70: admin alerts (created after the mailer below; the error hook only runs on requests)
let alerts: Alerts | null = null;

const app = Fastify({ logger: true, trustProxy: (_addr: string, hop: number) => hop < TRUST_PROXY_HOPS, bodyLimit: 64 * 1024 });

// D-62: API 5xx go to the admin error log (grouped; path without query string, no IP)
app.addHook('onError', async (req, _reply, err) => {
  const status = (err as { statusCode?: number }).statusCode ?? 500;
  if (status < 500) return;
  const message = `${req.method} ${req.routeOptions?.url ?? req.url.split('?')[0]}: ${err.message}`.slice(0, 500);
  void recordError(db, {
    kind: 'server',
    message,
    stack: err.stack?.slice(0, 4000) ?? null,
    path: req.url.split('?')[0].slice(0, 300),
    user_agent: null,
    version: APP_VERSION,
    user_id: null,
  })
    .then((r) => {
      // D-70: e-mail the admins about new API errors (or resolved ones that came back)
      if (r === 'new' || r === 'reopened') void alerts?.notify('error', `Erro na API: ${message.slice(0, 90)}`, [message, r === 'reopened' ? 'Este erro tinha sido marcado como resolvido e voltou.' : 'Primeira vez que acontece.', `Versão ${APP_VERSION}`]);
    })
    .catch(() => undefined);
});

// ---------------------------------------------------------------------------
// Startup tasks
// ---------------------------------------------------------------------------

/** DATA-01: upsert shared/items.json into `items` */
async function seedItems(): Promise<void> {
  const raw = JSON.parse(await readFile(ITEMS_JSON, 'utf8')) as {
    items: {
      name: string;
      npc_price: number;
      vendor: string;
      weight: number;
      imbuement?: boolean;
      task_product?: boolean;
      market_alert?: boolean;
    }[];
  };
  const client = await db.connect();
  try {
    await client.query('BEGIN');
    for (const it of raw.items) {
      await client.query(
        `INSERT INTO items (name, slug, npc_price, npc_vendor_category, weight_oz, is_imbuement,
                            is_task_creature_product, market_priority_alert)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
         ON CONFLICT (name) DO UPDATE SET
           slug = EXCLUDED.slug, npc_price = EXCLUDED.npc_price,
           npc_vendor_category = EXCLUDED.npc_vendor_category, weight_oz = EXCLUDED.weight_oz,
           is_imbuement = EXCLUDED.is_imbuement, is_task_creature_product = EXCLUDED.is_task_creature_product,
           market_priority_alert = EXCLUDED.market_priority_alert`,
        [
          it.name.toLowerCase(),
          it.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
          it.npc_price,
          it.vendor,
          it.weight,
          !!it.imbuement,
          !!it.task_product,
          !!it.market_alert,
        ],
      );
    }
    await client.query('COMMIT');
    app.log.info(`seeded ${raw.items.length} items`);
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  } finally {
    client.release();
  }
}

/** FR-TD-03: fill creatures.race / image_url from the TibiaData library */
async function syncCreatureImages(): Promise<void> {
  const library = await getCreatureLibrary(redis);
  const index = libraryIndex(library);
  const { rows } = await db.query<{ id: number; name: string; race: string | null; image_url: string | null }>(
    'SELECT id, name, race, image_url FROM creatures',
  );
  // A race can belong to one row only (unique index): a second spelling gets the image but not the race
  const taken = new Map(rows.filter((r) => r.race).map((r) => [r.race!, r.id]));
  let linked = 0;
  for (const row of rows) {
    const hit = libraryLookup(index, row.name);
    if (!hit) continue;
    const owner = taken.get(hit.race);
    const canTakeRace = owner == null || owner === row.id;
    if (row.image_url === hit.image_url && (!canTakeRace || row.race === hit.race)) continue;
    try {
      if (canTakeRace) {
        await db.query('UPDATE creatures SET race = $1, image_url = $2 WHERE id = $3', [hit.race, hit.image_url, row.id]);
        taken.set(hit.race, row.id);
      } else {
        await db.query('UPDATE creatures SET image_url = $2 WHERE id = $1', [row.id, hit.image_url]);
      }
      row.image_url = hit.image_url;
      linked++;
    } catch (err) {
      app.log.warn({ err: (err as Error).message, creature: row.name }, 'tibiadata: creature not linked');
    }
  }
  // D-57: bosses, quest and newer creatures are not in the official library → TibiaWiki sprite
  const missing = rows.filter((r) => !r.image_url).map((r) => r.name);
  let fromWiki = 0;
  if (missing.length) {
    try {
      const found = await wikiCreatureImages(missing, 'BagParser/0.5 (+https://bagparser.com)');
      for (const [name, url] of found) {
        const r = await db.query('UPDATE creatures SET image_url = $2 WHERE name = $1 AND image_url IS NULL', [name, url]);
        fromWiki += r.rowCount ?? 0;
      }
    } catch (err) {
      app.log.warn({ err: (err as Error).message }, 'wiki creature images failed');
    }
  }
  const left = (await db.query<{ n: number }>('SELECT COUNT(*)::int AS n FROM creatures WHERE image_url IS NULL')).rows[0].n;
  app.log.info(`creature images: ${linked} official updated, ${fromWiki} from TibiaWiki, ${left} without image (of ${rows.length})`);
}

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

// D-66: public runtime settings for the site (ads only for now; nothing secret here)
const ADS = adsConfig(process.env);
app.get('/api/config', async (_req, reply) => {
  reply.header('Cache-Control', 'public, max-age=300');
  return { ads: adsForBrowser(ADS), adsense_client: ADS?.client ?? null };
});
app.get('/api/ads.txt', async (_req, reply) => {
  const txt = adsTxt(ADS);
  if (!txt) return reply.code(404).type('text/plain').send('');
  return reply.type('text/plain; charset=utf-8').header('Cache-Control', 'public, max-age=3600').send(txt);
});

app.get('/api/health', async () => {
  await db.query('SELECT 1');
  return { ok: true, redis: redis.status, version: APP_VERSION };
});

app.get('/api/items', async () => {
  const { rows } = await db.query(
    `SELECT name, npc_price, npc_vendor_category, weight_oz::float AS weight_oz, is_imbuement,
            is_task_creature_product, market_priority_alert
       FROM items ORDER BY name`,
  );
  return rows;
});

app.get('/api/boosted', async () => getBoosted(redis));

app.get('/api/creatures', async () => {
  const [{ rows }, boosted] = await Promise.all([
    db.query(
      `SELECT c.id, c.name, c.grizzly_task_points, c.is_weekly_bounty, c.image_url
         FROM creatures c
        WHERE EXISTS (SELECT 1 FROM spawn_densities sd WHERE sd.creature_id = c.id)
        ORDER BY c.name`,
    ),
    getBoosted(redis).catch(() => null),
  ]);
  return rows.map((r) => ({ ...r, is_boosted: sameName(r.name, boosted?.creature?.race) }));
});

app.get<{ Querystring: { creature_id?: string } }>('/api/spawnrank', async (req, reply) => {
  const creatureId = Number(req.query.creature_id);
  if (!Number.isInteger(creatureId) || creatureId <= 0) {
    return reply.code(400).send({ error: 'creature_id required' });
  }
  const { rows } = await db.query(SPAWNRANK_SQL, [creatureId]);
  return rows
    .map((r) => ({
      zone_id: r.id,
      slug: r.slug,
      zone: r.name,
      exp_stars: r.exp_stars,
      loot_stars: r.loot_stars,
      access_quests: r.access_quests ?? [],
      levels: { knights: r.lvl_knights, paladins: r.lvl_paladins, mages: r.lvl_mages },
      city: r.city,
      min_level: r.recommended_min_level,
      vocation: r.recommended_vocation,
      access_quest: r.access_quest,
      note: r.spawn_distribution_note,
      components: {
        density: r.density_tier,
        synergy: Math.min(5, r.synergy),
        profitability: r.profitability_factor,
        access_friction: r.access_friction,
      },
      score: spawnScore({
        density: r.density_tier,
        synergy: r.synergy,
        profitability: r.profitability_factor,
        accessFriction: r.access_friction,
      }),
    }))
    .sort((a, b) => b.score - a.score);
});

app.get('/api/bosses', async () => {
  const [{ rows }, boosted] = await Promise.all([
    db.query(
      `SELECT b.id, b.name, b.cooldown_hours,
              (SELECT COUNT(*) FROM boss_drop_logs l WHERE l.boss_id = b.id)::int AS kills
         FROM bosses b ORDER BY b.name`,
    ),
    getBoosted(redis).catch(() => null),
  ]);
  return rows.map((r) => ({ ...r, is_boosted: sameName(r.name, boosted?.boss?.name) }));
});

const statsKey = (bossId: number) => `bossledger:stats:${bossId}`;

app.get<{ Params: { id: string } }>('/api/bosses/:id/stats', async (req, reply) => {
  const bossId = Number(req.params.id);
  if (!Number.isInteger(bossId)) return reply.code(400).send({ error: 'invalid id' });

  try {
    const hit = await redis.get(statsKey(bossId));
    if (hit) return JSON.parse(hit);
  } catch {
    /* cache miss path */
  }

  const boss = await db.query('SELECT id, name, cooldown_hours FROM bosses WHERE id = $1', [bossId]);
  if (!boss.rowCount) return reply.code(404).send({ error: 'boss not found' });

  const kills = (await db.query('SELECT COUNT(*)::int AS n FROM boss_drop_logs WHERE boss_id = $1', [bossId])).rows[0].n;
  const { rows } = await db.query(
    `SELECT i.name, s.total_recorded_drops::int AS drops, i.npc_price, i.market_priority_alert
       FROM boss_drop_aggregated_stats s JOIN items i ON i.id = s.item_id
      WHERE s.boss_id = $1 ORDER BY s.total_recorded_drops DESC, i.name`,
    [bossId],
  );
  const result = {
    boss: boss.rows[0],
    kills,
    items: rows.map((r) => ({ ...r, drop_rate: kills ? r.drops / kills : 0 })),
  };
  try {
    await redis.set(statsKey(bossId), JSON.stringify(result), 'EX', 300);
  } catch {
    /* ignore */
  }
  return result;
});

type SubmitResult =
  | { line: string; status: 'recorded'; boss: string; items: number }
  | { line: string; status: 'invalid_format' | 'unknown_boss' | 'duplicate' | 'rate_limited' };

app.post<{ Body: { text?: string } }>('/api/bosses/drops', async (req, reply) => {
  const text = typeof req.body?.text === 'string' ? req.body.text : '';
  const rawLines = text
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter(Boolean);
  if (!rawLines.length) return reply.code(400).send({ error: 'text required' });

  // If pasting a full Server Log with chat/spells/damage, extract only the loot lines
  const isMultiLine = rawLines.length > 1;
  const lines = isMultiLine
    ? rawLines.filter((l) => /loot of/i.test(l)).slice(0, MAX_LINES_PER_SUBMIT)
    : rawLines.slice(0, 1);

  if (!lines.length) return reply.code(400).send({ error: 'no_loot_lines_found' });

  // Coarse per-IP throttle on top of the per-boss cooldown
  const ipKey = `ratelimit:submit:${req.ip}`;
  const count = await redis.incr(ipKey);
  if (count === 1) await redis.expire(ipKey, 60);
  if (count > SUBMITS_PER_MINUTE) return reply.code(429).send({ error: 'too many requests' });

  const fingerprint = playerFingerprint(req.ip, String(req.headers['user-agent'] ?? ''), FINGERPRINT_SALT);
  const now = new Date();
  const results: SubmitResult[] = [];

  for (const line of lines) {
    const parsed = parseBossLogLine(line);
    if (!parsed) {
      // In single-line mode report invalid format; in multi-line skip non-matching
      if (!isMultiLine) results.push({ line, status: 'invalid_format' });
      continue;
    }
    let boss = await db.query<{ id: number; name: string; cooldown_hours: number }>(
      'SELECT id, name, cooldown_hours FROM bosses WHERE LOWER(name) = LOWER($1)',
      [parsed.bossName],
    );
    if (!boss.rowCount) {
      // Auto-register any valid boss from the log if not yet in database
      const cleanBoss = parsed.bossName.trim();
      if (cleanBoss.length >= 2 && cleanBoss.length <= 100) {
        boss = await db.query<{ id: number; name: string; cooldown_hours: number }>(
          `INSERT INTO bosses (name, cooldown_hours) VALUES ($1, 20)
           ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name RETURNING id, name, cooldown_hours`,
          [cleanBoss],
        );
      }
    }
    if (!boss.rowCount) {
      results.push({ line, status: 'unknown_boss' });
      continue;
    }
    const { id: bossId, cooldown_hours } = boss.rows[0];

    // FR-BL-04: one entry per boss per fingerprint per cooldown
    const cooldownKey = `bossledger:cooldown:${bossId}:${fingerprint}`;
    const allowed = await redis.set(cooldownKey, '1', 'EX', (cooldown_hours ?? 20) * 3600, 'NX');
    if (!allowed) {
      results.push({ line, status: 'rate_limited' });
      continue;
    }

    // Resolve drops against the catalog
    const resolved: { itemId: number; count: number; value: number; alert: boolean }[] = [];
    for (const drop of parsed.drops) {
      const { rows } = await db.query<{ id: number; npc_price: number; market_priority_alert: boolean }>(
        'SELECT id, npc_price, market_priority_alert FROM items WHERE LOWER(name) = ANY($1::text[]) LIMIT 1',
        [nameCandidates(drop.name)],
      );
      if (rows[0]) {
        resolved.push({
          itemId: rows[0].id,
          count: drop.count,
          value: rows[0].npc_price * drop.count,
          alert: rows[0].market_priority_alert || rows[0].npc_price >= RARE_VALUE_THRESHOLD,
        });
      }
    }

    const client = await db.connect();
    try {
      await client.query('BEGIN');
      const ins = await client.query(
        `INSERT INTO boss_drop_logs (boss_id, player_identifier_hash, payload_hash, drop_timestamp,
                                     raw_loot_line, total_npc_value, has_rare_item)
         VALUES ($1, $2, $3, $4, $5, $6, $7)
         ON CONFLICT (payload_hash) DO NOTHING RETURNING id`,
        [
          bossId,
          fingerprint,
          payloadHash(parsed, now),
          now,
          line.slice(0, 2000),
          resolved.reduce((s, r) => s + r.value, 0),
          resolved.some((r) => r.alert),
        ],
      );
      if (!ins.rowCount) {
        await client.query('ROLLBACK');
        await redis.del(cooldownKey); // a duplicate should not burn the player's cooldown
        results.push({ line, status: 'duplicate' });
        continue;
      }
      // Drop rate = kills containing the item / kills, so count each item once per kill
      const uniqueItemIds = [...new Set(resolved.map((r) => r.itemId))];
      for (const itemId of uniqueItemIds) {
        await client.query(
          `INSERT INTO boss_drop_aggregated_stats (boss_id, item_id, total_recorded_drops)
           VALUES ($1, $2, 1)
           ON CONFLICT (boss_id, item_id) DO UPDATE
             SET total_recorded_drops = boss_drop_aggregated_stats.total_recorded_drops + 1`,
          [bossId, itemId],
        );
      }
      await client.query('COMMIT');
      await redis.del(statsKey(bossId)).catch(() => 0);
      results.push({ line, status: 'recorded', boss: boss.rows[0].name, items: uniqueItemIds.length });
    } catch (err) {
      await client.query('ROLLBACK');
      await redis.del(cooldownKey).catch(() => 0);
      throw err;
    } finally {
      client.release();
    }
  }

  return { results };
});

// D-75: created further down (the account routes only call it when a ticket arrives)
let telegramBot: TelegramBot | null = null;
// D-76: filled once the bot exists (character tracking, Yasir and Pix are registered before it)
let personalAlerts: PersonalAlerts | null = null;
const yasirHooks: { onConfirmed?: (world: string, city: string | null) => void } = {};

// D-65: transactional e-mail (SMTP_* in the .env of the server)
const mailer = createMailer(process.env, app.log);
app.log.info({ mail: mailer.info.mode, host: mailer.info.host }, 'mailer');
{
  const pub = (process.env.PUBLIC_URL ?? '').trim().replace(/\/+$/, '');
  alerts = createAlerts({ mailer, store: redis, emails: ADMIN_EMAILS, siteUrl: pub && !/localhost|127\.0\.0\.1/.test(pub) ? pub : 'https://bagparser.com', log: app.log });
}

const guards = registerAccountRoutes(app, {
  mailer,
  db,
  redis,
  adminEmails: ADMIN_EMAILS,
  cookieSecure: COOKIE_SECURE,
  fingerprintSalt: FINGERPRINT_SALT,
  oauthProviders: providersFromEnv(),
  publicUrl: (process.env.PUBLIC_URL ?? '').trim().replace(/\/+$/, ''),
  onTicket: (t) => telegramBot?.notifyTicket(t),
});
registerCharacterRoutes(app, { db, redis, guards, events: { onChar: (userId: number, ev: CharEvent) => personalAlerts?.charEvent(userId, ev) } });
registerHuntRoutes(app, { db, guards });
registerHuntExtraRoutes(app, { db, guards });
registerPayoutRoutes(app, { db, guards });
registerAdminRoutes(app, { db, guards, mailer, ads: ADS, alerts });
registerPromoRoutes(app, { db, guards });
const billing = registerBillingRoutes(app, { db, guards, alerts });
// D-69: Pix via Mercado Pago (Pro days; card subscriptions stay on Stripe)
const pixDonations = registerPixRoutes(app, {
  db,
  guards,
  publicUrl: (process.env.PUBLIC_URL ?? '').trim(),
  alerts,
  hooks: { onDonationPaid: (d: DonationPaid) => telegramBot?.donationPaid(d) },
});
// D-55: deleting an account cancels its Stripe subscription first
guards.onUserDelete(billing.cancelForUser);
registerDailyRoutes(app, { db, redis, guards });
const yasirLookup = registerYasirRoutes(app, { db, guards, redis, hooks: yasirHooks });
{
  // D-71 + D-75: daily bulletin and weekly summary on Telegram and Discord; support over Telegram
  const pub = (process.env.PUBLIC_URL ?? '').trim().replace(/\/+$/, '');
  const siteUrl = pub && !/localhost|127\.0\.0\.1/.test(pub) ? pub : 'https://bagparser.com';
  const bossNames = new Set<string>();
  const rareBosses = new Set<string>();
  try {
    const raw = JSON.parse(await readFile(path.join(path.dirname(ITEMS_JSON), 'creatures.json'), 'utf8')) as { creatures: { name: string; boss: boolean; bc?: string | null }[] };
    for (const c of raw.creatures) {
      if (c.boss) bossNames.add(c.name.toLowerCase());
      if (c.boss && c.bc === 'Nemesis') rareBosses.add(c.name.toLowerCase());
    }
  } catch {
    /* optional: without it bosses are listed among the most killed creatures */
  }
  const soulCore = soulCoreIndex(await loadSoulCores(path.join(path.dirname(ITEMS_JSON), 'soulcores.json')));
  const source = createBulletinSource({
    db,
    redis,
    yasir: yasirLookup,
    isBoss: (n) => bossNames.has(n.toLowerCase()),
    isRareBoss: (n) => rareBosses.has(n.toLowerCase()),
    soulCore: (n) => soulCore(n),
  });
  const guilds = createGuildRanking({ db, redis });
  telegramBot = registerTelegram(app, { db, redis, guards, source, siteUrl, publicUrl: pub, mailer, env: process.env, yasir: yasirLookup, guilds, pix: pixDonations });
  const notify = registerNotifyRoutes(app, { db, redis, guards, siteUrl, source, telegram: telegramBot, guilds });
  yasirHooks.onConfirmed = (world, city) => void notify.announceYasir(world, city).catch((err) => app.log.warn({ err: (err as Error).message }, 'yasir announce failed'));
  personalAlerts = registerPersonalAlerts(app, { db, redis, guards, telegram: telegramBot, siteUrl });
  registerRetroRoutes(app, { db, guards });
}
registerHuntingPlaceRoutes(app, { db });
registerCreatureInfoRoutes(app, { sheets: await loadCreatureSheets(path.dirname(ITEMS_JSON)) });
const soulCatalog = await loadSoulCores(path.join(path.dirname(ITEMS_JSON), 'soulcores.json'));
registerSoulCoreRoutes(app, { db, guards, catalog: soulCatalog });
// Item name → TibiaWiki page title (shared/item-wiki.json, generated with the catalog)
const wikiTitles = new Map<string, string>();
try {
  const raw = JSON.parse(await readFile(path.join(path.dirname(ITEMS_JSON), 'item-wiki.json'), 'utf8')) as {
    titles: Record<string, string | { title: string }>;
  };
  for (const [name, v] of Object.entries(raw.titles)) wikiTitles.set(name, typeof v === 'string' ? v : v.title);
} catch {
  /* optional: titles are guessed from the item name */
}
registerWeeklyRoutes(app, { db, redis, guards, wikiTitles });

// ---------------------------------------------------------------------------

async function main() {
  await migrate(db);
  const promoted = await bootstrapAdmins(db, ADMIN_EMAILS);
  if (promoted) app.log.info(`promoted ${promoted} user(s) from ADMIN_EMAILS to admin`);
  const premiumPromoted = await bootstrapPremiumUsers(db);
  if (premiumPromoted) app.log.info(`seeded ${premiumPromoted} premium user(s)`);
  await seedItems();
  // D-80: the boss list comes from the wiki, not from a handful of names in the code
  try {
    const addedBosses = await seedBosses(db, path.join(path.dirname(ITEMS_JSON), 'creatures.json'));
    if (addedBosses) app.log.info(`bosses: ${addedBosses} added from the wiki`);
  } catch (err) {
    app.log.warn({ err: (err as Error).message }, 'boss seed failed');
  }
  {
    const shared = path.dirname(ITEMS_JSON);
    const { places, hash } = await loadHuntingPlaces(
      path.join(shared, 'hunting-places.json'),
      path.join(shared, 'hunting-places.overrides.json'),
    );
    const n = await seedHuntingPlaces(db, places, hash);
    app.log.info(n ? `hunting places: seeded ${n}` : 'hunting places: unchanged');
  }
  // Retention (Privacy Policy §6): expired sessions/reset links, admin log older than 2 years
  const prune = () =>
    db
      .query(
        `DELETE FROM sessions WHERE expires_at < now();
         DELETE FROM password_resets WHERE expires_at < now() - interval '7 days';
         DELETE FROM admin_audit WHERE created_at < now() - interval '2 years';`,
      )
      .catch((err) => app.log.warn({ err: err.message }, 'prune failed'));
  void prune();
  setInterval(prune, 24 * 3600_000).unref();
  // D-57: soul core creatures (824) join the creatures table so they get a sprite too
  await db
    .query('INSERT INTO creatures (name) SELECT unnest($1::text[]) ON CONFLICT (name) DO NOTHING', [soulCatalog.map((c) => c.creature)])
    .catch((err) => app.log.warn({ err: (err as Error).message }, 'soul core creatures insert failed'));
  // D-52: old imports left the same creature under two spellings; merge them, then link images
  try {
    const merged = await mergeDuplicateCreatures(db);
    if (merged) app.log.info(`creatures: merged ${merged} duplicate row(s)`);
  } catch (err) {
    app.log.warn({ err: (err as Error).message }, 'creature merge failed');
  }
  syncCreatureImages().catch((err) => app.log.warn({ err: err.message }, 'tibiadata creature sync failed'));
  await app.listen({ host: '0.0.0.0', port: PORT });
}

const shutdown = async () => {
  await app.close();
  await db.end();
  redis.disconnect();
  process.exit(0);
};
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

main().catch((err) => {
  app.log.error(err);
  process.exit(1);
});
