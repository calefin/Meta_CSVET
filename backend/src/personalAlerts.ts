/**
 * Instant alerts for Pro accounts (D-76): level up, death, goal reached (from the character tracking) and the end
 * of a boss cooldown (BossLedger tracker, synced from the browser). They go to the owner's private Telegram chat
 * and to the owner's own Discord channels ("my hunts" on) whose `alerts` switch is on.
 *
 *   PUT /api/me/boss-timers   { timers: [{ character, boss, killed_at, cooldown_hours }] }  (replaces the list)
 *   GET /api/me/boss-timers
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import type { AuthGuards } from './accounts.js';
import { isPremium } from './auth.js';
import { tgEscape } from './bulletin.js';
import { postDiscord } from './discord.js';
import type { TelegramBot } from './telegramBot.js';

export type CharEvent =
  | { kind: 'level'; character: string; from: number; to: number }
  | { kind: 'death'; character: string; level: number; reason: string }
  | { kind: 'goal'; character: string; goal: number };

export interface BossDue {
  character: string;
  boss: string;
}

const pt = (lang: string) => lang.startsWith('pt');

/** Alert text; `md` = Discord Markdown instead of Telegram HTML */
export function charEventText(ev: CharEvent, lang: string, siteUrl: string, md = false): string {
  const b = (s: string) => (md ? `**${s}**` : `<b>${tgEscape(s)}</b>`);
  const i = (s: string) => (md ? `*${s}*` : `<i>${tgEscape(s)}</i>`);
  if (ev.kind === 'level') {
    const n = ev.to - ev.from;
    return pt(lang)
      ? `🎉 ${b(ev.character)} subiu ${n > 1 ? `${n} levels: ${ev.from} → ${b(String(ev.to))}` : `para o level ${b(String(ev.to))}`}!`
      : `🎉 ${b(ev.character)} ${n > 1 ? `gained ${n} levels: ${ev.from} → ${b(String(ev.to))}` : `reached level ${b(String(ev.to))}`}!`;
  }
  if (ev.kind === 'death') {
    const reason = ev.reason.replace(/\s+/g, ' ').slice(0, 300);
    return pt(lang) ? `☠️ ${b(ev.character)} morreu no level ${ev.level}: ${i(reason)}` : `☠️ ${b(ev.character)} died at level ${ev.level}: ${i(reason)}`;
  }
  return pt(lang)
    ? `🎯 ${b(ev.character)} chegou à meta: level ${b(String(ev.goal))}! Defina a próxima em ${siteUrl}/characters`
    : `🎯 ${b(ev.character)} reached the goal: level ${b(String(ev.goal))}! Set the next one at ${siteUrl}/characters`;
}

export function bossReadyText(list: BossDue[], lang: string, md = false): string {
  const b = (s: string) => (md ? `**${s}**` : `<b>${tgEscape(s)}</b>`);
  const lines = list.slice(0, 15).map((x) => `• ${b(x.boss)} (${md ? x.character : tgEscape(x.character)})`);
  return `${pt(lang) ? '⏰ Cooldown acabou, já dá para matar de novo:' : '⏰ Cooldown over, you can kill again:'}\n${lines.join('\n')}`;
}

/** Cleans the list sent by the browser (bounded, recent kills only) */
export function cleanTimers(raw: unknown, now = Date.now()): { character: string; boss: string; killed_at: Date; cooldown_hours: number }[] {
  if (!Array.isArray(raw)) return [];
  const out = new Map<string, { character: string; boss: string; killed_at: Date; cooldown_hours: number }>();
  for (const t of raw.slice(0, 300)) {
    if (!t || typeof t !== 'object') continue;
    const o = t as Record<string, unknown>;
    const character = typeof o.character === 'string' ? o.character.trim().slice(0, 40) : '';
    const boss = typeof o.boss === 'string' ? o.boss.trim().slice(0, 80) : '';
    const at = new Date(String(o.killed_at ?? ''));
    const h = Number(o.cooldown_hours ?? 20);
    if (!character || !boss || Number.isNaN(at.getTime())) continue;
    if (at.getTime() > now + 5 * 60_000 || at.getTime() < now - 7 * 86_400_000) continue;
    out.set(`${character.toLowerCase()}|${boss.toLowerCase()}`, { character, boss, killed_at: at, cooldown_hours: Number.isInteger(h) && h >= 1 && h <= 336 ? h : 20 });
  }
  return [...out.values()];
}

export interface PersonalAlerts {
  charEvent(userId: number, ev: CharEvent): void;
}

export function registerPersonalAlerts(
  app: FastifyInstance,
  o: { db: pg.Pool; redis: Redis; guards: AuthGuards; telegram: TelegramBot; siteUrl: string },
): PersonalAlerts {
  const { db, redis, guards, telegram, siteUrl } = o;

  /** The owner's alert destinations, only for Pro accounts */
  async function destinations(userId: number) {
    const u = (await db.query<{ premium_until: Date | null; tier: string; email: string; status: string }>('SELECT premium_until, tier, email, status FROM users WHERE id = $1', [userId])).rows[0];
    if (!u || u.status !== 'active' || !isPremium(u as never)) return [];
    return (
      await db.query<{ id: number; kind: string; chat_id: string | null; webhook: string | null; lang: string }>(
        `SELECT id, kind, chat_id::text AS chat_id, webhook, lang FROM notify_targets
          WHERE user_id = $1 AND alerts AND ((kind = 'telegram' AND chat_type = 'private') OR (kind = 'discord' AND personal))`,
        [userId],
      )
    ).rows;
  }

  async function deliver(userId: number, text: (lang: string, md: boolean) => string): Promise<number> {
    let sent = 0;
    for (const d of await destinations(userId)) {
      const r =
        d.kind === 'telegram' && d.chat_id
          ? await telegram.send(d.chat_id, text(d.lang, false))
          : d.webhook
            ? await postDiscord(d.webhook, { username: 'BagParser', content: text(d.lang, true).slice(0, 1900) })
            : 'error';
      if (r === 'ok') sent++;
    }
    return sent;
  }

  // Boss cooldowns -----------------------------------------------------------

  app.get('/api/me/boss-timers', { preHandler: guards.requireUser }, async (req) => {
    const r = await db.query('SELECT character, boss, killed_at, cooldown_hours, notified_at FROM boss_timers WHERE user_id = $1 ORDER BY killed_at DESC', [req.user!.id]);
    return { timers: r.rows };
  });

  app.put<{ Body: { timers?: unknown } }>('/api/me/boss-timers', { preHandler: guards.requireUser }, async (req, reply) => {
    if (await guards.limited(`ratelimit:bosstimers:${req.user!.id}`, 60, 3600)) return reply.code(429).send({ error: 'rate_limited' });
    const list = cleanTimers(req.body?.timers);
    const client = await db.connect();
    try {
      await client.query('BEGIN');
      // Same kill keeps its "already alerted" mark; a new kill resets it
      const old = await client.query<{ character: string; boss: string; killed_at: Date; notified_at: Date | null }>(
        'SELECT character, boss, killed_at, notified_at FROM boss_timers WHERE user_id = $1',
        [req.user!.id],
      );
      const notified = new Map(old.rows.map((r) => [`${r.character.toLowerCase()}|${r.boss.toLowerCase()}|${r.killed_at.getTime()}`, r.notified_at]));
      await client.query('DELETE FROM boss_timers WHERE user_id = $1', [req.user!.id]);
      for (const t of list) {
        const key = `${t.character.toLowerCase()}|${t.boss.toLowerCase()}|${t.killed_at.getTime()}`;
        // Kills that were already over when synced are not alerted (old data from the browser)
        const already = notified.get(key) ?? (t.killed_at.getTime() + t.cooldown_hours * 3_600_000 <= Date.now() ? new Date() : null);
        await client.query(
          `INSERT INTO boss_timers (user_id, character, boss, killed_at, cooldown_hours, notified_at) VALUES ($1, $2, $3, $4, $5, $6)
           ON CONFLICT (user_id, character, boss) DO NOTHING`,
          [req.user!.id, t.character, t.boss, t.killed_at, t.cooldown_hours, already],
        );
      }
      await client.query('COMMIT');
    } catch (err) {
      await client.query('ROLLBACK').catch(() => null);
      throw err;
    } finally {
      client.release();
    }
    return { ok: true, timers: list.length };
  });

  async function bossTick(): Promise<number> {
    const due = (
      await db.query<{ user_id: number; character: string; boss: string }>(
        `UPDATE boss_timers SET notified_at = now()
          WHERE notified_at IS NULL AND killed_at + make_interval(hours => cooldown_hours) <= now()
            AND killed_at > now() - interval '7 days'
          RETURNING user_id, character, boss`,
      )
    ).rows;
    const byUser = new Map<number, BossDue[]>();
    for (const d of due) byUser.set(d.user_id, [...(byUser.get(d.user_id) ?? []), { character: d.character, boss: d.boss }]);
    let sent = 0;
    for (const [userId, list] of byUser) sent += await deliver(userId, (lang, md) => bossReadyText(list, lang, md)).catch(() => 0);
    return sent;
  }

  const tick = async () => {
    if ((await redis.set('lock:bosstimers', '1', 'EX', 240, 'NX').catch(() => 'OK')) !== 'OK') return;
    try {
      const n = await bossTick();
      if (n) app.log.info({ sent: n }, 'boss cooldown alerts');
    } catch (err) {
      app.log.error({ err: (err as Error).message }, 'boss cooldown alerts failed');
    } finally {
      await redis.del('lock:bosstimers').catch(() => null);
    }
  };
  const timer = setInterval(() => void tick(), 2 * 60_000);
  timer.unref();
  app.addHook('onClose', async () => clearInterval(timer));

  return {
    charEvent(userId, ev) {
      void deliver(userId, (lang, md) => charEventText(ev, lang, siteUrl, md)).catch((err) => app.log.warn({ err: (err as Error).message }, 'character alert failed'));
    },
  };
}
