/**
 * Where bulletins and summaries go (D-71, D-75) and the jobs that send them.
 *
 *   GET    /api/notify                       { telegram: {enabled, bot, chat, groups}, discord: [...] }
 *   POST   /api/notify/telegram/link         { url } one-time t.me link that connects this account
 *   POST   /api/notify/discord               { url, label?, lang?, tz? } adds a Discord channel (webhook)
 *   PATCH  /api/notify/targets/:id           { daily?, weekly?, personal?, world?, hour?, tz?, title? }
 *   DELETE /api/notify/targets/:id
 *   POST   /api/notify/targets/:id/test      sends today's bulletin there now
 *   POST   /api/admin/weekly/run             weekly summary now (admin, for tests)
 *
 * Jobs inside the API: every 5 minutes the daily bulletin goes to each target whose local hour has come (once per
 * local day); on Monday after 10:00 (Brasília) last week's summary goes to the targets with `weekly`.
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import type { AuthGuards } from './accounts.js';
import { bulletinDiscord, bulletinTelegram, weeklyTelegram, type BulletinData } from './bulletin.js';
import type { BulletinSource } from './bulletinSource.js';
import { TARGET_COLS, type NotifyTarget, type SendResult, type TelegramBot } from './telegramBot.js';
import { tgEscape, tt } from './telegram.js';
import { isPremium } from './auth.js';
import type { GuildRankingService } from './guildRanking.js';
import { previousMonth, retroStats, retroTelegram } from './retro.js';
import { postDiscord } from './discord.js';
import { addDays, defaultTz, localClock, validTimeZone } from './tibiaDay.js';
import { cleanDiscordWebhook, isSendTime, lastWeekRange, maskWebhook, weeklyDiscordMessage, type WeekChar, type WeekHunt } from './weekly.js';

const MAX_DISCORD = 5;

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

/** What the account page shows of a target (never the full webhook) */
function view(t: NotifyTarget) {
  return {
    id: t.id,
    kind: t.kind,
    chat_type: t.chat_type,
    title: t.title,
    webhook: t.webhook ? maskWebhook(t.webhook) : null,
    world: t.world,
    lang: t.lang,
    tz: t.tz,
    hour: t.hour,
    daily: t.daily,
    personal: t.personal,
    weekly: t.weekly,
    alerts: t.alerts,
    guild: t.guild,
  };
}

export function registerNotifyRoutes(
  app: FastifyInstance,
  {
    db,
    redis,
    guards,
    siteUrl,
    source,
    telegram,
    guilds,
  }: { db: pg.Pool; redis: Redis; guards: AuthGuards; siteUrl: string; source: BulletinSource; telegram: TelegramBot; guilds: GuildRankingService },
): { announceYasir: (world: string, city: string | null) => Promise<number> } {
  const { requireUser, limited } = guards;

  async function ownTarget(userId: number, idParam: string): Promise<NotifyTarget | null> {
    const id = Number(idParam);
    if (!Number.isInteger(id) || id <= 0) return null;
    const r = await db.query<NotifyTarget>(`SELECT ${TARGET_COLS} FROM notify_targets WHERE id = $1 AND user_id = $2`, [id, userId]);
    return r.rows[0] ?? null;
  }

  async function ownerPremium(userId: number | null): Promise<boolean> {
    if (!userId) return false;
    const u = (await db.query<{ premium_until: Date | null; tier: string; email: string }>('SELECT premium_until, tier, email FROM users WHERE id = $1', [userId])).rows[0];
    return !!u && isPremium(u as never);
  }

  app.get('/api/notify', { preHandler: requireUser }, async (req, reply) => {
    reply.header('Cache-Control', 'no-store');
    const rows = (await db.query<NotifyTarget>(`SELECT ${TARGET_COLS} FROM notify_targets WHERE user_id = $1 ORDER BY id`, [req.user!.id])).rows;
    const chat = rows.find((t) => t.kind === 'telegram' && t.chat_type === 'private');
    return {
      telegram: {
        enabled: telegram.enabled && !!telegram.username(),
        bot: telegram.username(),
        chat: chat ? view(chat) : null,
        groups: rows.filter((t) => t.kind === 'telegram' && t.chat_type !== 'private').map(view),
      },
      discord: rows.filter((t) => t.kind === 'discord').map(view),
    };
  });

  app.post('/api/notify/telegram/link', { preHandler: requireUser }, async (req, reply) => {
    if (await limited(`ratelimit:tglink:${req.user!.id}`, 10, 600)) return reply.code(429).send({ error: 'rate_limited' });
    const url = await telegram.linkUrl(req.user!.id);
    if (!url) return reply.code(503).send({ error: 'telegram_off' });
    return { url };
  });

  app.post<{ Body: { url?: unknown; label?: unknown; lang?: unknown; tz?: unknown } }>('/api/notify/discord', { preHandler: requireUser }, async (req, reply) => {
    const url = cleanDiscordWebhook(req.body?.url);
    if (!url) return reply.code(400).send({ error: 'invalid_webhook' });
    const u = req.user!;
    const n = (await db.query<{ n: number }>(`SELECT COUNT(*)::int AS n FROM notify_targets WHERE user_id = $1 AND kind = 'discord'`, [u.id])).rows[0].n;
    if (n >= MAX_DISCORD) return reply.code(409).send({ error: 'too_many_channels' });
    const lang = typeof req.body?.lang === 'string' && /^[a-z]{2}(-[A-Z]{2})?$/.test(req.body.lang) ? req.body.lang.slice(0, 5) : 'pt';
    const tz = validTimeZone(req.body?.tz) ? req.body.tz : defaultTz(lang);
    const label = typeof req.body?.label === 'string' ? req.body.label.trim().slice(0, 60) || null : null;
    // The first channel of an account is "mine" (my hunts + weekly summary); later ones are usually a guild's
    const first = n === 0;
    const r = await db.query<NotifyTarget>(
      `INSERT INTO notify_targets (user_id, kind, webhook, title, lang, tz, hour, daily, personal, weekly, last_daily)
       VALUES ($1, 'discord', $2, $3, $4, $5, 8, TRUE, $6, $6, $7)
       ON CONFLICT DO NOTHING RETURNING ${TARGET_COLS}`,
      [u.id, url, label, lang, tz, first, localClock(tz).date],
    );
    if (!r.rows[0]) return reply.code(409).send({ error: 'already_added' });
    const t = r.rows[0];
    const pt = lang.startsWith('pt');
    const hello = await postDiscord(url, {
      username: 'BagParser',
      content: pt
        ? `✅ Canal ligado ao BagParser de ${u.display_name}. O boletim do Tibia chega todo dia às ${t.hour}h${first ? ' e o resumo da semana toda segunda' : ''}.`
        : `✅ Channel linked to ${u.display_name}'s BagParser. The Tibia bulletin arrives every day at ${t.hour}:00${first ? ' and the weekly summary every Monday' : ''}.`,
    });
    if (hello === 'gone') {
      await db.query('DELETE FROM notify_targets WHERE id = $1', [t.id]);
      return reply.code(400).send({ error: 'webhook_gone' });
    }
    return reply.code(201).send(view(t));
  });

  app.patch<{ Params: { id: string }; Body: Record<string, unknown> }>('/api/notify/targets/:id', { preHandler: requireUser }, async (req, reply) => {
    const t = await ownTarget(req.user!.id, req.params.id);
    if (!t) return reply.code(404).send({ error: 'not_found' });
    const b = req.body ?? {};
    const set: Record<string, unknown> = {};
    for (const k of ['daily', 'weekly', 'personal', 'alerts'] as const) if (typeof b[k] === 'boolean') set[k] = b[k];
    // D-76: guild XP ranking (Pro)
    if (b.guild === null || b.guild === '') set.guild = null;
    else if (typeof b.guild === 'string') {
      if (!(await ownerPremium(req.user!.id))) return reply.code(403).send({ error: 'pro_required' });
      const g = await guilds.lookup(b.guild);
      if (!g) return reply.code(400).send({ error: 'invalid_guild' });
      set.guild = g.name;
      if (!t.world && b.world === undefined) set.world = g.world;
    }
    // Hunts only go to a private Telegram chat or a Discord channel of the owner; never to a group
    if (t.kind === 'telegram' && t.chat_type !== 'private') {
      delete set.personal;
      delete set.weekly;
    }
    if (b.world === null || b.world === '') set.world = null;
    else if (typeof b.world === 'string') {
      const w = await source.world(b.world);
      if (!w) return reply.code(400).send({ error: 'invalid_world' });
      set.world = w;
    }
    if (b.hour !== undefined) {
      const h = Number(b.hour);
      if (!Number.isInteger(h) || h < 0 || h > 23) return reply.code(400).send({ error: 'invalid_hour' });
      set.hour = h;
    }
    if (b.tz !== undefined) {
      if (!validTimeZone(b.tz)) return reply.code(400).send({ error: 'invalid_tz' });
      set.tz = b.tz;
    }
    if (typeof b.title === 'string' && t.kind === 'discord') set.title = b.title.trim().slice(0, 60) || null;
    if (set.daily === true && !t.daily) set.last_daily = localClock((set.tz as string) ?? t.tz).date; // starts tomorrow (the test button sends now)
    const keys = Object.keys(set);
    if (!keys.length) return reply.code(400).send({ error: 'nothing_to_change' });
    const r = await db.query<NotifyTarget>(
      `UPDATE notify_targets SET ${keys.map((k, i) => `${k} = $${i + 2}`).join(', ')}, failures = 0 WHERE id = $1 RETURNING ${TARGET_COLS}`,
      [t.id, ...keys.map((k) => set[k])],
    );
    return view(r.rows[0]);
  });

  app.delete<{ Params: { id: string } }>('/api/notify/targets/:id', { preHandler: requireUser }, async (req, reply) => {
    const t = await ownTarget(req.user!.id, req.params.id);
    if (!t) return reply.code(404).send({ error: 'not_found' });
    if (t.kind === 'telegram' && t.chat_type === 'private') {
      // Unlink the account; the chat keeps the general bulletin if it had it on
      await db.query('UPDATE notify_targets SET user_id = NULL, personal = FALSE, weekly = FALSE WHERE id = $1', [t.id]);
      if (t.chat_id) void telegram.send(t.chat_id, tt(t.lang, 'unlinked'));
    } else if (t.kind === 'telegram') {
      // A group: just stop being its owner here (the group admins turn it off there with /boletim off)
      await db.query('UPDATE notify_targets SET user_id = NULL WHERE id = $1', [t.id]);
    } else await db.query('DELETE FROM notify_targets WHERE id = $1', [t.id]);
    return { ok: true };
  });

  app.post<{ Params: { id: string } }>('/api/notify/targets/:id/test', { preHandler: requireUser }, async (req, reply) => {
    const t = await ownTarget(req.user!.id, req.params.id);
    if (!t) return reply.code(404).send({ error: 'not_found' });
    if (await limited(`ratelimit:notifytest:${req.user!.id}`, 5, 600)) return reply.code(429).send({ error: 'rate_limited' });
    const data = await source.general(t.world);
    const res = await deliver(t, data, true);
    if (res === 'gone') return reply.code(400).send({ error: t.kind === 'discord' ? 'webhook_gone' : 'telegram_blocked' });
    if (res === 'error') return reply.code(502).send({ error: 'send_error' });
    return { ok: true };
  });

  // ---------------------------------------------------------------------------
  // Daily bulletin
  // ---------------------------------------------------------------------------

  // Guild rankings are computed once per run and guild
  const guildRuns = new Map<string, { at: number; value: Promise<Awaited<ReturnType<GuildRankingService['ranking']>>> }>();
  function guildCache(name: string) {
    const hit = guildRuns.get(name.toLowerCase());
    if (hit && Date.now() - hit.at < 10 * 60_000) return hit.value;
    const value = guilds.ranking(name).catch(() => null);
    guildRuns.set(name.toLowerCase(), { at: Date.now(), value });
    return value;
  }

  /** D-76: on the 1st, after the bulletin, last month's retrospective (private chat / my channels) */
  async function sendRetro(t: NotifyTarget, localDate: string): Promise<void> {
    if (!t.personal || !t.user_id || !localDate.endsWith('-01')) return;
    const month = previousMonth(localDate.slice(0, 7));
    if (t.last_retro === month) return;
    await db.query('UPDATE notify_targets SET last_retro = $2 WHERE id = $1', [t.id, month]);
    const r = await retroStats(db, t.user_id, month);
    if (t.kind === 'telegram' && t.chat_id) {
      const msg = retroTelegram(r, t.lang, siteUrl);
      if (msg) await telegram.send(t.chat_id, msg);
    } else if (t.kind === 'discord' && t.webhook) {
      const msg = retroTelegram(r, t.lang, siteUrl);
      if (msg) {
        const md = msg.replace(/<b>(.*?)<\/b>/g, '**$1**').replace(/<a href="([^"]+)">(.*?)<\/a>/g, '[$2]($1)').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
        await postDiscord(t.webhook, { username: 'BagParser', content: md.slice(0, 1900) });
      }
    }
  }

  /** Sends the bulletin to one target (adds the owner's yesterday when it is "personal") */
  async function deliver(t: NotifyTarget, general: BulletinData, test = false): Promise<SendResult> {
    const data: BulletinData = { ...general };
    if (t.personal && t.user_id) data.personal = await source.personal(t.user_id, addDays(general.day, -1), general.boosted.creature).catch(() => null);
    if (t.guild && (await ownerPremium(t.user_id))) data.guild = await guildCache(t.guild);
    if (t.kind === 'telegram' && t.chat_id) {
      if (!telegram.enabled) return 'error';
      const hint = test ? tt(t.lang, 'test_hint') : t.chat_type === 'private' ? tt(t.lang, 'bulletin_hint') : undefined;
      return telegram.send(t.chat_id, bulletinTelegram(data, t.lang, siteUrl, hint));
    }
    if (t.kind === 'discord' && t.webhook) return postDiscord(t.webhook, bulletinDiscord(data, t.lang, siteUrl));
    return 'error';
  }

  /** After a send: gone chats/channels stop, repeated errors give up after a week */
  async function afterSend(t: NotifyTarget, res: SendResult): Promise<void> {
    if (res === 'gone') {
      if (t.kind === 'telegram' && t.chat_type === 'private') await db.query('UPDATE notify_targets SET daily = FALSE, weekly = FALSE WHERE id = $1', [t.id]);
      else await db.query('DELETE FROM notify_targets WHERE id = $1', [t.id]);
    } else if (res === 'error') await db.query('UPDATE notify_targets SET failures = failures + 1, daily = (failures + 1 < 7) AND daily WHERE id = $1', [t.id]);
    else if (t.failures) await db.query('UPDATE notify_targets SET failures = 0 WHERE id = $1', [t.id]);
  }

  async function runDaily(now = new Date()): Promise<{ sent: number; failed: number }> {
    const due = (
      await db.query<NotifyTarget>(`SELECT ${TARGET_COLS} FROM notify_targets WHERE daily AND (kind = 'discord' OR $1::boolean) ORDER BY id`, [telegram.enabled])
    ).rows.filter((t) => {
      const c = localClock(t.tz, now);
      return c.hour >= t.hour && t.last_daily !== c.date;
    });
    if (!due.length) return { sent: 0, failed: 0 };
    const cache = new Map<string, Promise<BulletinData>>();
    const general = (world: string | null) => {
      const k = world ?? '';
      if (!cache.has(k)) cache.set(k, source.general(world, now));
      return cache.get(k)!;
    };
    let sent = 0;
    let failed = 0;
    for (const t of due) {
      // Marked first: a crash in the middle never sends twice
      await db.query('UPDATE notify_targets SET last_daily = $2 WHERE id = $1', [t.id, localClock(t.tz, now).date]);
      const res = await deliver(t, await general(t.world)).catch((): SendResult => 'error');
      await afterSend(t, res);
      if (res === 'ok') await sendRetro(t, localClock(t.tz, now).date).catch(() => null);
      if (res === 'ok') sent++;
      else failed++;
      await sleep(t.kind === 'discord' ? 400 : 60);
    }
    return { sent, failed };
  }

  // ---------------------------------------------------------------------------
  // Weekly summary (D-71): Discord and Telegram targets with `weekly`
  // ---------------------------------------------------------------------------

  async function sendWeek(now = new Date()): Promise<{ sent: number; skipped: number; gone: number }> {
    const { start, end, key } = lastWeekRange(now);
    const targets = (
      await db.query<NotifyTarget & { display_name: string }>(
        `SELECT t.*, u.display_name
           FROM (SELECT ${TARGET_COLS} FROM notify_targets WHERE weekly AND personal) t JOIN users u ON u.id = t.user_id
          WHERE u.status = 'active' ORDER BY t.user_id, t.id`,
      )
    ).rows;
    let sent = 0;
    let skipped = 0;
    let gone = 0;
    const perUser = new Map<number, { hunts: WeekHunt[]; chars: WeekChar[] }>();
    for (const t of targets) {
      const uid = t.user_id!;
      if (!perUser.has(uid)) {
        const [hunts, chars] = await Promise.all([
          db.query<WeekHunt>(
            `SELECT character_name, place_name, duration_s, xp::float8 AS xp, xp_h::float8 AS xp_h, balance::float8 AS balance, extra_expenses::float8 AS extra_expenses
               FROM hunt_sessions WHERE user_id = $1 AND created_at >= $2 AND created_at < $3`,
            [uid, start, end],
          ),
          db.query<WeekChar>(
            `SELECT c.name, MIN(s.level)::int AS level_from, MAX(s.level)::int AS level_to, (MAX(s.experience) - MIN(s.experience))::float8 AS exp_gain
               FROM characters c JOIN character_snapshots s ON s.character_id = c.id
              WHERE c.user_id = $1 AND s.taken_at >= $2 AND s.taken_at < $3 GROUP BY c.name`,
            [uid, start, end],
          ),
        ]);
        perUser.set(uid, { hunts: hunts.rows, chars: chars.rows });
      }
      const { hunts, chars } = perUser.get(uid)!;
      let res: SendResult;
      if (t.kind === 'discord' && t.webhook) {
        const msg = weeklyDiscordMessage({ name: t.display_name, weekKey: key, hunts, chars, lang: t.lang, siteUrl });
        if (!msg) {
          skipped++;
          continue;
        }
        res = await postDiscord(t.webhook, msg);
        await sleep(400);
      } else if (t.kind === 'telegram' && t.chat_id && telegram.enabled) {
        const msg = weeklyTelegram({ name: t.display_name, weekKey: key, hunts, chars, lang: t.lang, siteUrl });
        if (!msg) {
          skipped++;
          continue;
        }
        res = await telegram.send(t.chat_id, msg);
        await sleep(60);
      } else continue;
      if (res === 'gone') gone++;
      else if (res === 'ok') sent++;
      await afterSend(t, res);
    }
    return { sent, skipped, gone };
  }

  // ---------------------------------------------------------------------------
  // Timer
  // ---------------------------------------------------------------------------

  const tick = async () => {
    if ((await redis.set('notify:tick:lock', '1', 'EX', 240, 'NX').catch(() => 'OK')) !== 'OK') return;
    try {
      await guilds.daily().catch((err) => app.log.warn({ err: (err as Error).message }, 'guild snapshots failed'));
      const d = await runDaily();
      if (d.sent || d.failed) app.log.info(d, 'daily bulletin');
      if (isSendTime()) {
        const { key } = lastWeekRange();
        if ((await redis.set(`weekly:sent:${key}`, '1', 'EX', 8 * 86_400, 'NX')) === 'OK') {
          const w = await sendWeek();
          app.log.info({ week: key, ...w }, 'weekly summary');
        }
      }
    } catch (err) {
      app.log.error({ err: (err as Error).message }, 'notify job failed');
    } finally {
      await redis.del('notify:tick:lock').catch(() => null);
    }
  };
  const first = setTimeout(() => void tick(), 30_000);
  first.unref();
  const timer = setInterval(() => void tick(), 5 * 60_000);
  timer.unref();
  app.addHook('onClose', async () => {
    clearTimeout(first);
    clearInterval(timer);
  });

  // Admin: run them now (for testing after deploy)
  app.post('/api/admin/weekly/run', { preHandler: guards.requireAdmin }, async () => sendWeek());
  app.post('/api/admin/daily/run', { preHandler: guards.requireAdmin }, async () => runDaily());

  return {
    /** D-76: Yasir confirmed on a world → every destination following that world (daily bulletin on) */
    async announceYasir(world, city) {
      const targets = (await db.query<NotifyTarget>(`SELECT ${TARGET_COLS} FROM notify_targets WHERE daily AND world = $1`, [world])).rows;
      let sent = 0;
      for (const t of targets) {
        const pt = t.lang.startsWith('pt');
        const where = city && city !== 'other' ? city : pt ? 'outra cidade' : 'another city';
        const text = pt ? `🏺 Yasir confirmado hoje em ${world}: ${where}.` : `🏺 Yasir confirmed today on ${world}: ${where}.`;
        const res =
          t.kind === 'telegram' && t.chat_id
            ? telegram.enabled
              ? await telegram.send(t.chat_id, `<b>${tgEscape(text)}</b>`)
              : 'error'
            : t.webhook
              ? await postDiscord(t.webhook, { username: 'BagParser', content: `**${text}**` })
              : 'error';
        if (res === 'ok') sent++;
        await sleep(t.kind === 'discord' ? 400 : 60);
      }
      return sent;
    },
  };
}
