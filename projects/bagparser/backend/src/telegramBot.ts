/**
 * Telegram bot (D-75): @bagparser_bot.
 *
 *   POST /api/telegram/webhook          updates from Telegram (secret header checked)
 *   GET  /api/telegram/info             { enabled, username } (public: support page, account page)
 *   GET  /api/admin/tickets/:id/replies conversation of a ticket
 *   POST /api/admin/tickets/:id/reply   { text } → sent to the person on Telegram
 *
 * Production receives updates by webhook (PUBLIC_URL with https); locally TELEGRAM_POLLING=1 reads them with
 * getUpdates — use a separate test bot for that, since polling removes the webhook of the bot.
 * What the bot does: links a Telegram chat to a BagParser account (one-time code from /account), daily bulletin
 * in private chats, groups and channels, the owner's hunts, and support: messages become tickets in /admin and
 * the admin answers from the panel or by replying to the notice on Telegram.
 */
import { randomBytes, timingSafeEqual } from 'node:crypto';
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { Redis } from 'ioredis';
import type { AuthGuards } from './accounts.js';
import { bulletinTelegram, yasirStatusText } from './bulletin.js';
import { isPremium } from './auth.js';
import type { GuildRankingService } from './guildRanking.js';
import type { DonationPaid, PixDonations } from './pixRoutes.js';
import type { YasirLookup } from './yasirRoutes.js';
import type { BulletinSource } from './bulletinSource.js';
import { ticketNoticeEmail } from './emails.js';
import type { Mailer } from './mailer.js';
import {
  attachmentLabel,
  DONATE_PIX_CENTS,
  DONATE_STARS,
  parseYasir,
  botLang,
  chatGone,
  COMMAND_MENU,
  createTelegramApi,
  displayName,
  hourLabel,
  onOff,
  parseCommand,
  parseHour,
  tgEscape,
  tt,
  webhookSecret,
  type TgCall,
  type TgChat,
  type TgMessage,
  type TgResult,
  type TgUpdate,
  type TgUser,
} from './telegram.js';
import { addDays, defaultTz, localClock } from './tibiaDay.js';
import { fmtNum } from './weekly.js';

export interface NotifyTarget {
  id: number;
  user_id: number | null;
  kind: 'telegram' | 'discord';
  chat_id: string | null;
  chat_type: string | null;
  title: string | null;
  webhook: string | null;
  world: string | null;
  lang: string;
  tz: string;
  hour: number;
  daily: boolean;
  personal: boolean;
  weekly: boolean;
  last_daily: string | null;
  failures: number;
  alerts: boolean;
  guild: string | null;
  last_retro: string | null;
}

export interface TicketNotice {
  id: number;
  name: string;
  email: string | null;
  category: string;
  subject: string;
  message: string;
  userId: number | null;
}

export type SendResult = 'ok' | 'gone' | 'error';

export interface TelegramBot {
  enabled: boolean;
  username(): string | null;
  /** One-time link to connect the account (null when the bot is off) */
  linkUrl(userId: number): Promise<string | null>;
  send(chatId: number | string, html: string, extra?: Record<string, unknown>): Promise<SendResult>;
  /** New ticket from the site: tell the admins on Telegram */
  notifyTicket(t: TicketNotice): void;
  /** D-76: a Pix donation was paid (thanks the person on Telegram) */
  donationPaid(d: DonationPaid): void;
}

export const TARGET_COLS = `id, user_id, kind, chat_id::text AS chat_id, chat_type, title, webhook, world, lang, tz, hour, daily, personal, weekly,
  last_daily::text AS last_daily, failures, alerts, guild, last_retro`;

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

export function registerTelegram(
  app: FastifyInstance,
  o: {
    db: pg.Pool;
    redis: Redis;
    guards: AuthGuards;
    source: BulletinSource;
    siteUrl: string;
    publicUrl: string;
    mailer?: Mailer;
    env: NodeJS.ProcessEnv;
    yasir?: YasirLookup;
    guilds?: GuildRankingService;
    pix?: PixDonations;
  },
): TelegramBot {
  const { db, redis, guards, source, siteUrl } = o;
  const token = o.env.TELEGRAM_BOT_TOKEN?.trim() ?? '';
  const enabled = /^\d{5,15}:[\w-]{30,60}$/.test(token);
  const api: TgCall = enabled ? createTelegramApi(token, o.env.TELEGRAM_API_BASE?.trim() || undefined) : async () => ({ ok: false, description: 'disabled' });
  const secret = enabled ? webhookSecret(token, o.env.TELEGRAM_WEBHOOK_SECRET?.trim()) : '';
  let botUsername: string | null = o.env.TELEGRAM_BOT_USERNAME?.trim().replace(/^@/, '') || null;
  let botId: number | null = null;
  const site = siteUrl.replace(/^https?:\/\//, '');

  // -------------------------------------------------------------------------
  // Sending
  // -------------------------------------------------------------------------

  async function send(chatId: number | string, html: string, extra: Record<string, unknown> = {}): Promise<SendResult> {
    const params = { chat_id: chatId, text: html, parse_mode: 'HTML', link_preview_options: { is_disabled: true }, ...extra };
    let r = await api('sendMessage', params);
    if (!r.ok && r.error_code === 429) {
      await sleep(Math.min(30, r.parameters?.retry_after ?? 3) * 1000);
      r = await api('sendMessage', params);
    }
    if (!r.ok && r.parameters?.migrate_to_chat_id) {
      // A group became a supergroup: the chat id changed
      await db.query(`UPDATE notify_targets SET chat_id = $2 WHERE kind = 'telegram' AND chat_id = $1`, [chatId, r.parameters.migrate_to_chat_id]).catch(() => null);
      r = await api('sendMessage', { ...params, chat_id: r.parameters.migrate_to_chat_id });
    }
    if (r.ok) return 'ok';
    if (chatGone(r)) return 'gone';
    app.log.warn({ code: r.error_code, desc: r.description }, 'telegram send failed');
    return 'error';
  }

  /** Sends and returns the new message id (admin notices are mapped to tickets by it) */
  async function sendId(chatId: number | string, html: string, extra: Record<string, unknown> = {}): Promise<number | null> {
    const r = (await api('sendMessage', { chat_id: chatId, text: html, parse_mode: 'HTML', link_preview_options: { is_disabled: true }, ...extra })) as TgResult<{
      message_id: number;
    }>;
    return r.ok ? (r.result?.message_id ?? null) : null;
  }

  // -------------------------------------------------------------------------
  // Targets
  // -------------------------------------------------------------------------

  async function targetOf(chatId: number): Promise<NotifyTarget | null> {
    const r = await db.query<NotifyTarget>(`SELECT ${TARGET_COLS} FROM notify_targets WHERE kind = 'telegram' AND chat_id = $1`, [chatId]);
    return r.rows[0] ?? null;
  }

  const chatTitle = (c: TgChat, from?: TgUser) => (c.type === 'private' ? (from?.username ? `@${from.username}` : displayName(from)) : (c.title ?? c.username ?? 'Telegram')).slice(0, 120);

  /** Creates the chat's target (or updates it) with the given fields */
  async function upsertTarget(chat: TgChat, from: TgUser | undefined, fields: Partial<NotifyTarget>): Promise<NotifyTarget> {
    const lang = fields.lang ?? botLang(from?.language_code);
    const tz = fields.tz ?? defaultTz(lang);
    const today = localClock(tz).date;
    const r = await db.query<NotifyTarget>(
      `INSERT INTO notify_targets (user_id, kind, chat_id, chat_type, title, world, lang, tz, hour, daily, personal, weekly, last_daily)
       VALUES ($1, 'telegram', $2, $3, $4, $5, $6, $7, COALESCE($8, 8), COALESCE($9, FALSE), COALESCE($10, FALSE), COALESCE($11, FALSE), $12)
       ON CONFLICT (chat_id) WHERE kind = 'telegram' DO UPDATE SET
         user_id = COALESCE(EXCLUDED.user_id, notify_targets.user_id),
         chat_type = EXCLUDED.chat_type, title = EXCLUDED.title,
         world = CASE WHEN $13 THEN EXCLUDED.world ELSE notify_targets.world END,
         hour = COALESCE($8, notify_targets.hour),
         daily = COALESCE($9, notify_targets.daily), personal = COALESCE($10, notify_targets.personal),
         weekly = COALESCE($11, notify_targets.weekly), failures = 0,
         last_daily = CASE WHEN $9 THEN $12::date ELSE notify_targets.last_daily END
       RETURNING ${TARGET_COLS}`,
      [
        fields.user_id ?? null,
        chat.id,
        chat.type,
        chatTitle(chat, from),
        fields.world ?? null,
        lang,
        tz,
        fields.hour ?? null,
        fields.daily ?? null,
        fields.personal ?? null,
        fields.weekly ?? null,
        today,
        fields.world !== undefined,
      ],
    );
    return r.rows[0];
  }

  async function setTarget(id: number, fields: Record<string, unknown>): Promise<void> {
    const keys = Object.keys(fields);
    if (!keys.length) return;
    await db.query(`UPDATE notify_targets SET ${keys.map((k, i) => `${k} = $${i + 2}`).join(', ')} WHERE id = $1`, [id, ...keys.map((k) => fields[k])]);
  }

  // -------------------------------------------------------------------------
  // Bulletin and hunts
  // -------------------------------------------------------------------------

  async function premium(userId: number | null | undefined): Promise<boolean> {
    if (!userId) return false;
    const u = (await db.query<{ premium_until: Date | null; tier: string; email: string; status: string }>('SELECT premium_until, tier, email, status FROM users WHERE id = $1', [userId])).rows[0];
    return !!u && u.status === 'active' && isPremium(u as never);
  }

  type BulletinTarget = { world: string | null; lang: string; personal: boolean; user_id: number | null; guild?: string | null };

  async function bulletinFor(t: BulletinTarget, hint?: string): Promise<string> {
    const data = await source.general(t.world);
    if (t.personal && t.user_id) data.personal = await source.personal(t.user_id, addDays(data.day, -1), data.boosted.creature).catch(() => null);
    if (t.guild && o.guilds && (await premium(t.user_id))) data.guild = await o.guilds.ranking(t.guild).catch(() => null);
    return bulletinTelegram(data, t.lang, siteUrl, hint);
  }

  async function huntsText(userId: number, lang: string): Promise<string> {
    const pt = lang.startsWith('pt');
    const r = await db.query<{ character_name: string | null; place_name: string | null; duration_s: number; xp_h: number; balance_h: number; at: Date }>(
      `SELECT character_name, place_name, duration_s, xp_h::float8 AS xp_h, balance_h::float8 AS balance_h, COALESCE(started_at, created_at) AS at
         FROM hunt_sessions WHERE user_id = $1 AND created_at > now() - interval '7 days'
        ORDER BY COALESCE(started_at, created_at) DESC LIMIT 6`,
      [userId],
    );
    if (!r.rows.length) return tt(lang, 'hunts_none', { site });
    const lines = r.rows.map((h) => {
      const when = new Intl.DateTimeFormat(pt ? 'pt-BR' : 'en-US', { day: '2-digit', month: '2-digit', timeZone: 'America/Sao_Paulo' }).format(new Date(h.at));
      const dur = `${Math.floor(h.duration_s / 3600)}h${String(Math.floor((h.duration_s % 3600) / 60)).padStart(2, '0')}`;
      return `• ${when} · <b>${tgEscape(h.place_name ?? '—')}</b>${h.character_name ? ` (${tgEscape(h.character_name)})` : ''}\n   ${dur} · ${fmtNum(h.xp_h, lang)} XP/h · ${fmtNum(h.balance_h, lang)} gp/h`;
    });
    return `${tt(lang, 'hunts_title')}\n\n${lines.join('\n')}\n\n<a href="${siteUrl}/hunt-analyser/mine">${pt ? 'Ver todas' : 'See all'}</a>`;
  }

  function configText(t: NotifyTarget | null, lang: string, accountName: string | null, isPrivate: boolean): string {
    const onoff = (b: boolean) => tt(lang, b ? 'on' : 'off');
    return tt(lang, 'config', {
      daily: onoff(!!t?.daily),
      hour: hourLabel(t?.hour ?? 8, t?.tz ?? defaultTz(lang), lang),
      world: t?.world ?? tt(lang, 'none'),
      weekly: isPrivate ? tt(lang, 'weekly_line', { weekly: onoff(!!t?.weekly) }) : '',
      account: isPrivate ? tt(lang, 'account_line', { account: accountName ? tgEscape(accountName) : tt(lang, 'no_account', { site }) }) : '',
      weeklyHelp: isPrivate ? tt(lang, 'weekly_help') : '',
    });
  }

  // -------------------------------------------------------------------------
  // Support
  // -------------------------------------------------------------------------

  async function adminChats(): Promise<string[]> {
    const r = await db.query<{ chat_id: string }>(
      `SELECT t.chat_id::text AS chat_id FROM notify_targets t JOIN users u ON u.id = t.user_id
        WHERE t.kind = 'telegram' AND t.chat_type = 'private' AND u.role = 'admin' AND u.status = 'active'`,
    );
    return r.rows.map((x) => x.chat_id);
  }

  /** Tells every admin with Telegram; replying to that notice answers the ticket */
  async function noticeAdmins(ticketId: number, header: string, body: string, copyFrom?: { chat: number; message: number }): Promise<void> {
    for (const chat of await adminChats()) {
      const id = await sendId(chat, `🎫 <b>Ticket #${ticketId}</b> · ${header}\n\n${tgEscape(body.slice(0, 3000))}\n\n<i>Responda esta mensagem para responder à pessoa.</i>`);
      if (id) await redis.set(`tg:adm:${chat}:${id}`, String(ticketId), 'EX', 60 * 86400).catch(() => null);
      if (copyFrom) {
        const c = (await api('copyMessage', { chat_id: chat, from_chat_id: copyFrom.chat, message_id: copyFrom.message })) as TgResult<{ message_id: number }>;
        if (c.ok && c.result) await redis.set(`tg:adm:${chat}:${c.result.message_id}`, String(ticketId), 'EX', 60 * 86400).catch(() => null);
      }
    }
  }

  async function activeTicket(chatId: number): Promise<number | null> {
    const r = await db.query<{ id: number }>(
      `SELECT id FROM support_messages WHERE telegram_chat_id = $1 AND status <> 'closed' AND updated_at > now() - interval '7 days'
        ORDER BY id DESC LIMIT 1`,
      [chatId],
    );
    return r.rows[0]?.id ?? null;
  }

  async function createTicket(m: TgMessage, text: string, lang: string): Promise<number> {
    const t = await targetOf(m.chat.id);
    const user = t?.user_id ? (await db.query<{ email: string; display_name: string }>('SELECT email, display_name FROM users WHERE id = $1', [t.user_id])).rows[0] : undefined;
    const name = displayName(m.from);
    const subject = text.replace(/\s+/g, ' ').slice(0, 80) || 'Telegram';
    const r = await db.query<{ id: number }>(
      `INSERT INTO support_messages (user_id, name, email, category, subject, message, client_info, telegram_chat_id)
       VALUES ($1, $2, $3, 'telegram', $4, $5, $6, $7) RETURNING id`,
      [
        t?.user_id ?? null,
        name.slice(0, 100),
        user?.email ?? null,
        subject,
        text.slice(0, 10000),
        JSON.stringify({ source: 'telegram', username: m.from?.username ?? null, lang: m.from?.language_code ?? null }),
        m.chat.id,
      ],
    );
    const id = r.rows[0].id;
    const header = `Telegram · ${tgEscape(name)}${m.from?.username ? ` (@${tgEscape(m.from.username)})` : ''}${user ? ` · conta ${tgEscape(user.email)}` : ''}`;
    void noticeAdmins(id, header, text, attachmentLabel(m, lang) ? { chat: m.chat.id, message: m.message_id } : undefined).catch(() => null);
    if (o.mailer?.enabled) {
      const mail = ticketNoticeEmail({ id, name, email: user?.email ?? `Telegram${m.from?.username ? ` @${m.from.username}` : ''}`, category: 'telegram', subject, message: text.slice(0, 4000), adminUrl: `${siteUrl}/admin/tickets`, support: o.mailer.support });
      void o.mailer.send(o.mailer.support, mail).catch(() => null);
    }
    return id;
  }

  async function appendToTicket(id: number, m: TgMessage, text: string, lang: string): Promise<void> {
    await db.query(`INSERT INTO support_replies (ticket_id, from_admin, body, via) VALUES ($1, FALSE, $2, 'telegram')`, [id, text.slice(0, 10000)]);
    await db.query(`UPDATE support_messages SET status = 'pending', updated_at = now() WHERE id = $1`, [id]);
    void noticeAdmins(id, `resposta de ${tgEscape(displayName(m.from))}`, text, attachmentLabel(m, lang) ? { chat: m.chat.id, message: m.message_id } : undefined).catch(() => null);
  }

  /** Text of a message for a ticket (caption + what was attached) */
  const ticketText = (m: TgMessage, lang: string) => [m.text ?? m.caption ?? '', attachmentLabel(m, lang) ?? ''].filter(Boolean).join(' ').trim();

  /** Admin answer → the person's Telegram. Used by the panel and by replies on Telegram. */
  async function deliverReply(ticketId: number, text: string, via: 'admin' | 'telegram'): Promise<'ok' | 'no_telegram' | 'gone' | 'error' | 'not_found'> {
    const r = await db.query<{ telegram_chat_id: string | null; user_id: number | null; lang: string | null }>(
      `SELECT s.telegram_chat_id::text AS telegram_chat_id, s.user_id, s.client_info->>'lang' AS lang FROM support_messages s WHERE s.id = $1`,
      [ticketId],
    );
    const tk = r.rows[0];
    if (!tk) return 'not_found';
    let chat = tk.telegram_chat_id;
    let lang = botLang(tk.lang);
    if (!chat && tk.user_id) {
      const t = await db.query<{ chat_id: string; lang: string }>(
        `SELECT chat_id::text AS chat_id, lang FROM notify_targets WHERE user_id = $1 AND kind = 'telegram' AND chat_type = 'private' LIMIT 1`,
        [tk.user_id],
      );
      chat = t.rows[0]?.chat_id ?? null;
      if (t.rows[0]) lang = botLang(t.rows[0].lang);
    }
    if (!chat) return 'no_telegram';
    const res = await send(chat, tt(lang, 'support_reply', { id: ticketId, text: tgEscape(text) }));
    if (res !== 'ok') return res;
    await db.query(`INSERT INTO support_replies (ticket_id, from_admin, body, via) VALUES ($1, TRUE, $2, $3)`, [ticketId, text.slice(0, 10000), via]);
    await db.query(`UPDATE support_messages SET status = 'answered', updated_at = now(), telegram_chat_id = COALESCE(telegram_chat_id, $2) WHERE id = $1`, [ticketId, chat]);
    return 'ok';
  }

  // -------------------------------------------------------------------------
  // Updates
  // -------------------------------------------------------------------------

  async function isChatAdmin(m: TgMessage): Promise<boolean> {
    if (m.chat.type === 'channel') return true; // only admins post in a channel
    if (m.sender_chat?.id === m.chat.id) return true; // anonymous group admin
    if (!m.from) return false;
    const key = `tg:isadmin:${m.chat.id}:${m.from.id}`;
    const hit = await redis.get(key).catch(() => null);
    if (hit) return hit === '1';
    const r = (await api('getChatMember', { chat_id: m.chat.id, user_id: m.from.id })) as TgResult<{ status: string }>;
    const yes = r.ok && (r.result?.status === 'creator' || r.result?.status === 'administrator');
    await redis.set(key, yes ? '1' : '0', 'EX', 300).catch(() => null);
    return yes;
  }

  async function sendBulletinNow(chatId: number, t: BulletinTarget, hint?: string) {
    await send(chatId, await bulletinFor(t, hint));
  }

  /** Commands shared by private chats, groups and channels that change the bulletin */
  async function settingsCommand(m: TgMessage, cmd: string, args: string[], lang: string, t: NotifyTarget | null, isPrivate: boolean): Promise<boolean> {
    const chat = m.chat;
    if (cmd === 'boletim' && args.length) {
      const on = onOff(args[0]);
      if (on === null) return false;
      if (!on) {
        if (t) await setTarget(t.id, { daily: false });
        await send(chat.id, tt(lang, 'daily_off'));
        return true;
      }
      let world: string | undefined;
      if (args[1]) {
        const w = await source.world(args[1]);
        if (!w) {
          await send(chat.id, tt(lang, 'world_unknown', { world: tgEscape(args[1].slice(0, 30)) }));
          return true;
        }
        world = w;
      }
      const linkedUser = !isPrivate && m.from ? ((await targetOf(m.from.id))?.user_id ?? null) : null;
      const nt = await upsertTarget(chat, m.from, { daily: true, ...(world ? { world } : {}), ...(t ? { lang: t.lang, tz: t.tz } : {}), ...(linkedUser ? { user_id: linkedUser } : {}) });
      await send(chat.id, tt(lang, 'daily_on', { hour: hourLabel(nt.hour, nt.tz, lang), world: nt.world ? ` · ${nt.world}` : '' }));
      await sendBulletinNow(chat.id, nt);
      return true;
    }
    if (cmd === 'mundo') {
      const arg = args.join(' ');
      if (!arg) {
        await send(chat.id, tt(lang, 'world_unknown', { world: '' }));
        return true;
      }
      if (['nenhum', 'none', '-'].includes(arg.toLowerCase())) {
        if (t) await setTarget(t.id, { world: null });
        await send(chat.id, tt(lang, 'world_cleared'));
        return true;
      }
      const w = await source.world(arg);
      if (!w) {
        await send(chat.id, tt(lang, 'world_unknown', { world: tgEscape(arg.slice(0, 30)) }));
        return true;
      }
      await upsertTarget(chat, m.from, { world: w, ...(t ? { lang: t.lang, tz: t.tz } : {}) });
      await send(chat.id, tt(lang, 'world_set', { world: w }));
      return true;
    }
    if (cmd === 'hora') {
      const h = parseHour(args[0]);
      if (h === null) {
        await send(chat.id, tt(lang, 'hour_invalid'));
        return true;
      }
      const nt = await upsertTarget(chat, m.from, { hour: h, ...(t ? { lang: t.lang, tz: t.tz } : {}) });
      await send(chat.id, tt(lang, 'hour_set', { hour: hourLabel(h, nt.tz, lang) }));
      return true;
    }
    if (cmd === 'parar') {
      if (t) await setTarget(t.id, { daily: false, weekly: false });
      await send(chat.id, tt(lang, 'stopped'));
      return true;
    }
    return false;
  }

  // -------------------------------------------------------------------------
  // D-76: Yasir, guild ranking, alerts, donations
  // -------------------------------------------------------------------------

  const brl = (cents: number, lang: string) => (cents / 100).toLocaleString(lang.startsWith('pt') ? 'pt-BR' : 'en-US', { style: 'currency', currency: 'BRL' });

  async function noticeAdminsText(html: string): Promise<void> {
    for (const chat of await adminChats()) await send(chat, html);
  }

  /** Thanks + "show my name in the bulletin?" */
  async function thank(chatId: number | string, donationId: number, amount: string, lang: string, name: string): Promise<void> {
    await send(chatId, tt(lang, 'donate_thanks', { amount: tgEscape(amount) }), {
      reply_markup: {
        inline_keyboard: [
          [
            { text: tt(lang, 'donate_public_yes', { name: name.slice(0, 30) }), callback_data: `don:pub:${donationId}:y` },
            { text: tt(lang, 'donate_public_no'), callback_data: `don:pub:${donationId}:n` },
          ],
        ],
      },
    });
  }

  async function donateMenu(chatId: number, lang: string, linked: boolean): Promise<void> {
    const rows: { text: string; callback_data: string }[][] = [DONATE_STARS.map((n) => ({ text: `⭐ ${n}`, callback_data: `don:s:${n}` }))];
    if (o.pix?.enabled && linked) rows.push(DONATE_PIX_CENTS.map((c) => ({ text: `Pix ${brl(c, lang)}`, callback_data: `don:p:${c}` })));
    const extra = o.pix?.enabled && !linked ? `\n\n${tt(lang, 'donate_pix_account', { site })}` : '';
    await send(chatId, tt(lang, 'donate') + extra, { reply_markup: { inline_keyboard: rows } });
  }

  /** /yasir, /guild, /avisos, /apoiar, /paysupport (private chats; /yasir and /guild also in groups) */
  async function extraCommand(m: TgMessage, cmd: string, args: string[], lang: string, t: NotifyTarget | null, isPrivate: boolean): Promise<boolean> {
    const chat = m.chat;
    const person = m.from && m.from.id !== chat.id ? await targetOf(m.from.id) : isPrivate ? t : null;
    if (cmd === 'yasir') {
      if (!o.yasir) return false;
      const r = parseYasir(args);
      if (!r) {
        const world = args.length ? await source.world(args.join(' ')) : (t?.world ?? person?.world ?? null);
        if (!world) {
          await send(chat.id, tt(lang, 'yasir_need_world'));
          return true;
        }
        const y = await o.yasir.today(world);
        await send(chat.id, tt(lang, 'yasir_status', { world, status: yasirStatusText(y, lang) }));
        return true;
      }
      const userId = person?.user_id ?? null;
      if (!userId) {
        await send(chat.id, tt(lang, 'needs_account', { site }));
        return true;
      }
      const world = r.rest.length ? await source.world(r.rest.join(' ')) : (t?.world ?? person?.world ?? null);
      if (!world) {
        await send(chat.id, tt(lang, 'yasir_need_world'));
        return true;
      }
      const res = await o.yasir.report(userId, world, r.present, r.city);
      if (res === 'rate_limited') await send(chat.id, tt(lang, 'yasir_rate'));
      else if (res !== 'ok') await send(chat.id, tt(lang, 'yasir_need_world'));
      else {
        const what = r.present ? (r.city === 'other' ? (lang.startsWith('pt') ? 'outra cidade' : 'another city') : r.city!) : lang.startsWith('pt') ? 'não veio' : 'not there';
        await send(chat.id, tt(lang, 'yasir_ok', { world, what }));
      }
      return true;
    }
    if (cmd === 'guild') {
      if (!o.guilds) return false;
      if (!isPrivate && !(await isChatAdmin(m))) {
        await send(chat.id, tt(lang, 'admins_only'));
        return true;
      }
      const arg = args.join(' ').trim();
      if (['off', 'nenhuma', 'none', '-'].includes(arg.toLowerCase())) {
        if (t) await setTarget(t.id, { guild: null });
        await send(chat.id, tt(lang, 'guild_off'));
        return true;
      }
      const owner = (isPrivate ? t?.user_id : person?.user_id) ?? t?.user_id ?? null;
      if (!(await premium(owner))) {
        await send(chat.id, tt(lang, 'guild_pro', { site }));
        return true;
      }
      const g = arg ? await o.guilds.lookup(arg) : null;
      if (!g) {
        await send(chat.id, tt(lang, 'guild_unknown', { guild: tgEscape(arg.slice(0, 40)) }));
        return true;
      }
      const nt = await upsertTarget(chat, m.from, { ...(t?.world ? {} : { world: g.world }), ...(t ? { lang: t.lang, tz: t.tz } : {}) });
      await setTarget(nt.id, { guild: g.name, user_id: owner });
      void o.guilds.snapshot(g.name).catch(() => false);
      await send(chat.id, tt(lang, 'guild_set', { guild: tgEscape(g.name), world: g.world }));
      return true;
    }
    if (cmd === 'avisos' && isPrivate) {
      if (!t?.user_id) {
        await send(chat.id, tt(lang, 'needs_account', { site }));
        return true;
      }
      const on = onOff(args[0]) ?? !t.alerts;
      await setTarget(t.id, { alerts: on });
      await send(chat.id, tt(lang, on ? 'alerts_on' : 'alerts_off'));
      return true;
    }
    if (cmd === 'apoiar') {
      if (!isPrivate) {
        await send(chat.id, `💚 https://t.me/${botUsername}?start=apoiar`);
        return true;
      }
      await donateMenu(chat.id, lang, !!t?.user_id);
      return true;
    }
    if (cmd === 'paysupport') {
      if (!isPrivate) return false;
      await redis.set(`tg:supmode:${chat.id}`, '1', 'EX', 1800).catch(() => null);
      await send(chat.id, tt(lang, 'paysupport'));
      return true;
    }
    return false;
  }

  /** Telegram Stars: the invoice was paid */
  async function starsPaid(m: TgMessage, lang: string): Promise<void> {
    const pay = m.successful_payment!;
    const id = Number(/^don:(\d{1,10})$/.exec(pay.invoice_payload)?.[1] ?? 0);
    const r = await db.query<{ id: number; amount: number }>(
      `UPDATE donations SET status = 'paid', paid_at = now(), charge_id = $2 WHERE id = $1 AND status = 'pending' RETURNING id, amount`,
      [id, pay.telegram_payment_charge_id.slice(0, 120)],
    );
    if (!r.rows[0]) return;
    const t = await targetOf(m.chat.id);
    const name = t?.user_id ? ((await db.query<{ display_name: string }>('SELECT display_name FROM users WHERE id = $1', [t.user_id])).rows[0]?.display_name ?? displayName(m.from)) : displayName(m.from);
    await thank(m.chat.id, id, `${r.rows[0].amount} ⭐`, lang, name);
    void noticeAdminsText(`💚 Doação: <b>${r.rows[0].amount} ⭐</b> de ${tgEscape(displayName(m.from))}${m.from?.username ? ` (@${tgEscape(m.from.username)})` : ''}`).catch(() => null);
  }

  async function handlePrivate(m: TgMessage): Promise<void> {
    const chat = m.chat;
    const t = await targetOf(chat.id);
    const lang = t?.lang ?? botLang(m.from?.language_code);
    if (m.successful_payment) return starsPaid(m, lang);
    if (await guards.limited(`ratelimit:tg:${chat.id}`, 30, 60)) return;
    const c = parseCommand(m.text, botUsername);

    // Admin answering a ticket notice
    if (!c && m.reply_to_message) {
      const ticket = await redis.get(`tg:adm:${chat.id}:${m.reply_to_message.message_id}`).catch(() => null);
      if (ticket) {
        const text = (m.text ?? '').trim();
        if (!text) {
          await send(chat.id, 'Por enquanto só respostas em texto.');
          return;
        }
        const res = await deliverReply(Number(ticket), text, 'telegram');
        const email = res === 'no_telegram' ? (await db.query<{ email: string | null }>('SELECT email FROM support_messages WHERE id = $1', [Number(ticket)])).rows[0]?.email : null;
        await send(
          chat.id,
          res === 'ok'
            ? `✅ Enviado (ticket #${ticket}).`
            : res === 'no_telegram'
              ? `Esse ticket não tem Telegram: responda por e-mail${email ? ` (${tgEscape(email)})` : ''}.`
              : res === 'gone'
                ? 'A pessoa bloqueou o bot: não deu para entregar.'
                : 'Não consegui enviar agora. Tente pelo painel.',
        );
        return;
      }
    }

    if (c) {
      const { cmd, args } = c;
      if (cmd === 'start') {
        const code = args[0] ?? '';
        if (code === 'apoiar') {
          await donateMenu(chat.id, lang, !!t?.user_id);
          return;
        }
        if (code === 'suporte' || code === 'support') {
          await redis.set(`tg:supmode:${chat.id}`, '1', 'EX', 1800).catch(() => null);
          await send(chat.id, tt(lang, 'support_prompt'));
          return;
        }
        if (/^[A-Za-z0-9_-]{16,64}$/.test(code)) {
          const userId = await redis.getdel(`tg:link:${code}`).catch(() => null);
          const user = userId
            ? (await db.query<{ id: number; display_name: string; notify_lang: string | null }>(`SELECT id, display_name, notify_lang FROM users WHERE id = $1 AND status = 'active'`, [Number(userId)])).rows[0]
            : undefined;
          if (!user) {
            await send(chat.id, tt(lang, 'link_invalid', { site }));
            return;
          }
          const ulang = user.notify_lang ? botLang(user.notify_lang) : lang;
          // One Telegram per account: an older chat of this account is unlinked
          await db.query(`DELETE FROM notify_targets WHERE user_id = $1 AND kind = 'telegram' AND chat_type = 'private' AND chat_id <> $2`, [user.id, chat.id]);
          const nt = await upsertTarget(chat, m.from, { user_id: user.id, lang: t?.lang ?? ulang, tz: t?.tz, daily: true, personal: true, weekly: true });
          await db.query('UPDATE notify_targets SET user_id = $2, personal = TRUE WHERE id = $1', [nt.id, user.id]);
          await redis.set(`tg:linked:${user.id}`, '1', 'EX', 600).catch(() => null);
          await send(chat.id, tt(nt.lang, 'linked', { name: tgEscape(user.display_name), hour: hourLabel(nt.hour, nt.tz, nt.lang) }));
          return;
        }
        await send(chat.id, tt(lang, 'welcome', { site }));
        return;
      }
      if (cmd === 'boletim' && !args.length) {
        await sendBulletinNow(chat.id, t ?? { world: null, lang, personal: false, user_id: null }, tt(lang, 'bulletin_hint'));
        return;
      }
      if (cmd === 'semanal') {
        const on = onOff(args[0]);
        if (!t?.user_id) {
          await send(chat.id, tt(lang, 'needs_account', { site }));
          return;
        }
        if (on === null) {
          await send(chat.id, configText(t, lang, null, true));
          return;
        }
        await setTarget(t.id, { weekly: on });
        await send(chat.id, tt(lang, on ? 'weekly_on' : 'weekly_off'));
        return;
      }
      if (await settingsCommand(m, cmd, args, lang, t, true)) return;
      if (await extraCommand(m, cmd, args, lang, t, true)) return;
      if (cmd === 'config') {
        const name = t?.user_id ? ((await db.query<{ display_name: string }>('SELECT display_name FROM users WHERE id = $1', [t.user_id])).rows[0]?.display_name ?? null) : null;
        await send(chat.id, configText(t, lang, name, true));
        return;
      }
      if (cmd === 'hunts') {
        await send(chat.id, t?.user_id ? await huntsText(t.user_id, lang) : tt(lang, 'needs_account', { site }));
        return;
      }
      if (cmd === 'suporte') {
        if (c.rest) {
          await supportMessage(m, c.rest, lang, true);
          return;
        }
        await redis.set(`tg:supmode:${chat.id}`, '1', 'EX', 1800).catch(() => null);
        await send(chat.id, tt(lang, 'support_prompt'));
        return;
      }
      if (cmd === 'ajuda' || cmd === 'boletim') {
        await send(chat.id, tt(lang, 'help_private'));
        return;
      }
      await send(chat.id, tt(lang, 'unknown'));
      return;
    }

    // Plain message: support
    const text = ticketText(m, lang);
    if (!text) return;
    const direct = (await redis.get(`tg:supmode:${chat.id}`).catch(() => null)) === '1';
    await supportMessage(m, text, lang, direct);
  }

  /** Goes to the open ticket of this chat; otherwise opens one (asking first unless the person chose /suporte) */
  async function supportMessage(m: TgMessage, text: string, lang: string, direct: boolean): Promise<void> {
    if (await guards.limited(`ratelimit:tgsupport:${m.chat.id}`, 20, 3600)) {
      await send(m.chat.id, tt(lang, 'rate_limited'));
      return;
    }
    const open = await activeTicket(m.chat.id);
    if (open) {
      await appendToTicket(open, m, text, lang);
      await send(m.chat.id, tt(lang, 'support_added', { id: open }));
      return;
    }
    if (direct) {
      const id = await createTicket(m, text, lang);
      await send(m.chat.id, tt(lang, 'support_sent', { id }));
      return;
    }
    await redis.set(`tg:pending:${m.chat.id}`, JSON.stringify({ text, message: m.message_id, attach: !!attachmentLabel(m, lang) }), 'EX', 900).catch(() => null);
    await send(m.chat.id, tt(lang, 'support_ask'), {
      reply_parameters: { message_id: m.message_id, allow_sending_without_reply: true },
      reply_markup: {
        inline_keyboard: [[{ text: tt(lang, 'support_send'), callback_data: 'sup:send' }, { text: tt(lang, 'support_cancel'), callback_data: 'sup:cancel' }]],
      },
    });
  }

  async function handleCallback(q: NonNullable<TgUpdate['callback_query']>): Promise<void> {
    const chat = q.message?.chat;
    await api('answerCallbackQuery', { callback_query_id: q.id });
    if (!chat || chat.type !== 'private') return;
    const t = await targetOf(chat.id);
    const lang = t?.lang ?? botLang(q.from.language_code);
    const edit = (text: string) => api('editMessageText', { chat_id: chat.id, message_id: q.message!.message_id, text, parse_mode: 'HTML' });
    if (q.data?.startsWith('don:')) {
      const [, kind, a, b] = q.data.split(':');
      if (kind === 's' && (DONATE_STARS as readonly number[]).includes(Number(a))) {
        const d = await db.query<{ id: number }>(
          `INSERT INTO donations (user_id, chat_id, provider, amount, currency) VALUES ($1, $2, 'stars', $3, 'XTR') RETURNING id`,
          [t?.user_id ?? null, chat.id, Number(a)],
        );
        await api('sendInvoice', {
          chat_id: chat.id,
          title: tt(lang, 'donate_title'),
          description: tt(lang, 'donate_desc'),
          payload: `don:${d.rows[0].id}`,
          currency: 'XTR',
          prices: [{ label: tt(lang, 'donate_title'), amount: Number(a) }],
        });
        return;
      }
      if (kind === 'p' && (DONATE_PIX_CENTS as readonly number[]).includes(Number(a))) {
        if (!t?.user_id || !o.pix?.enabled) {
          await send(chat.id, tt(lang, 'donate_pix_account', { site }));
          return;
        }
        if (await guards.limited(`ratelimit:pixdon:${t.user_id}`, 6, 3600)) {
          await send(chat.id, tt(lang, 'rate_limited'));
          return;
        }
        try {
          const email = (await db.query<{ email: string }>('SELECT email FROM users WHERE id = $1', [t.user_id])).rows[0].email;
          const pix = await o.pix.createDonation({ userId: t.user_id, email, cents: Number(a), chatId: chat.id });
          const link = pix.ticket_url ? `\n\n<a href="${tgEscape(pix.ticket_url)}">${lang.startsWith('pt') ? 'Abrir no Mercado Pago' : 'Open in Mercado Pago'}</a>` : '';
          await send(chat.id, tt(lang, 'donate_pix', { amount: brl(Number(a), lang), code: tgEscape(pix.qr_code), link }));
        } catch (err) {
          app.log.error({ err: (err as Error).message }, 'pix donation failed');
          await send(chat.id, tt(lang, 'donate_pix_error'));
        }
        return;
      }
      if (kind === 'pub') {
        const id = Number(a);
        if (b === 'y') {
          const name = t?.user_id ? ((await db.query<{ display_name: string }>('SELECT display_name FROM users WHERE id = $1', [t.user_id])).rows[0]?.display_name ?? displayName(q.from)) : displayName(q.from);
          await db.query(`UPDATE donations SET public_name = $3 WHERE id = $1 AND chat_id = $2 AND status = 'paid'`, [id, chat.id, name.slice(0, 40)]);
          await edit(tt(lang, 'donate_public_done'));
        } else {
          await db.query(`UPDATE donations SET public_name = NULL WHERE id = $1 AND chat_id = $2`, [id, chat.id]);
          await edit(tt(lang, 'donate_anon_done'));
        }
        return;
      }
      return;
    }
    if (q.data === 'sup:cancel') {
      await redis.del(`tg:pending:${chat.id}`).catch(() => null);
      await edit(tt(lang, 'support_cancelled'));
      return;
    }
    if (q.data === 'sup:send') {
      const raw = await redis.getdel(`tg:pending:${chat.id}`).catch(() => null);
      if (!raw) {
        await edit(tt(lang, 'support_expired'));
        return;
      }
      const p = JSON.parse(raw) as { text: string; message: number; attach: boolean };
      const fake: TgMessage = { message_id: p.message, chat, from: q.from, ...(p.attach ? { photo: [] } : {}) };
      const id = await createTicket(fake, p.text, lang);
      await redis.set(`tg:supmode:${chat.id}`, '1', 'EX', 1800).catch(() => null);
      await edit(tt(lang, 'support_sent', { id }));
    }
  }

  async function handleGroup(m: TgMessage): Promise<void> {
    const c = parseCommand(m.text ?? m.caption, botUsername);
    if (!c) return;
    const chat = m.chat;
    const t = await targetOf(chat.id);
    const lang = t?.lang ?? botLang(m.from?.language_code);
    if (await guards.limited(`ratelimit:tg:${chat.id}`, 20, 60)) return;
    const { cmd, args } = c;
    if (cmd === 'boletim' && !args.length) {
      if (await guards.limited(`ratelimit:tgbulletin:${chat.id}`, 2, 120)) return;
      await sendBulletinNow(chat.id, t ?? { world: null, lang, personal: false, user_id: null });
      return;
    }
    if (['boletim', 'mundo', 'hora', 'parar'].includes(cmd)) {
      if (!(await isChatAdmin(m))) {
        await send(chat.id, tt(lang, 'admins_only'));
        return;
      }
      if (await settingsCommand(m, cmd, args, lang, t, false)) return;
      await send(chat.id, tt(lang, 'help_group'));
      return;
    }
    if (['yasir', 'guild', 'apoiar'].includes(cmd) && (await extraCommand(m, cmd, args, lang, t, false))) return;
    if (cmd === 'config') {
      await send(chat.id, t ? configText(t, lang, null, false) : tt(lang, 'not_subscribed'));
      return;
    }
    if (cmd === 'suporte') {
      await send(chat.id, tt(lang, 'support_group', { link: `https://t.me/${botUsername}?start=suporte` }));
      return;
    }
    if (cmd === 'start' || cmd === 'ajuda') await send(chat.id, tt(lang, 'help_group'));
  }

  async function handleUpdate(u: TgUpdate): Promise<void> {
    if (u.my_chat_member) {
      const { chat, from, new_chat_member: now, old_chat_member: before } = u.my_chat_member;
      const inside = (s: string) => ['member', 'administrator', 'creator', 'restricted'].includes(s);
      if (chat.type === 'private') {
        // The person blocked the bot: stop sending (the link stays)
        if (now.status === 'kicked') await db.query(`UPDATE notify_targets SET daily = FALSE, weekly = FALSE WHERE kind = 'telegram' AND chat_id = $1`, [chat.id]);
        return;
      }
      if (!inside(now.status)) {
        await db.query(`DELETE FROM notify_targets WHERE kind = 'telegram' AND chat_id = $1`, [chat.id]);
        return;
      }
      if (!inside(before.status) && chat.type !== 'channel') {
        await send(chat.id, tt(botLang(from.language_code), 'group_welcome', { title: tgEscape(chat.title ?? '') }));
      }
      return;
    }
    if (u.pre_checkout_query) {
      const q = u.pre_checkout_query;
      const id = Number(/^don:(\d{1,10})$/.exec(q.invoice_payload)?.[1] ?? 0);
      const d = (await db.query<{ amount: number; status: string; currency: string }>('SELECT amount, status, currency FROM donations WHERE id = $1', [id])).rows[0];
      const ok = !!d && d.status === 'pending' && d.currency === q.currency && d.amount === q.total_amount;
      await api('answerPreCheckoutQuery', ok ? { pre_checkout_query_id: q.id, ok: true } : { pre_checkout_query_id: q.id, ok: false, error_message: 'Este pedido expirou. Mande /apoiar de novo.' });
      return;
    }
    if (u.callback_query) return handleCallback(u.callback_query);
    const m = u.message ?? u.channel_post;
    if (!m) return;
    if (m.migrate_to_chat_id) {
      await db.query(`UPDATE notify_targets SET chat_id = $2, chat_type = 'supergroup' WHERE kind = 'telegram' AND chat_id = $1`, [m.chat.id, m.migrate_to_chat_id]);
      return;
    }
    if (m.from?.is_bot && m.from.id === botId) return;
    if (m.chat.type === 'private') return handlePrivate(m);
    return handleGroup(m);
  }

  async function processUpdate(u: TgUpdate): Promise<void> {
    if (typeof u?.update_id !== 'number') return;
    if ((await redis.set(`tg:update:${u.update_id}`, '1', 'EX', 86400, 'NX').catch(() => 'OK')) !== 'OK') return;
    await handleUpdate(u).catch((err) => app.log.error({ err: (err as Error).message }, 'telegram update failed'));
  }

  // -------------------------------------------------------------------------
  // Routes
  // -------------------------------------------------------------------------

  app.post('/api/telegram/webhook', async (req, reply) => {
    if (!enabled) return reply.code(404).send({ error: 'not_found' });
    const got = Buffer.from(String(req.headers['x-telegram-bot-api-secret-token'] ?? ''));
    const want = Buffer.from(secret);
    if (got.length !== want.length || !timingSafeEqual(got, want)) return reply.code(401).send({ error: 'unauthorized' });
    await processUpdate(req.body as TgUpdate);
    return { ok: true };
  });

  app.get('/api/telegram/info', async () => ({ enabled: enabled && !!botUsername, username: botUsername }));

  app.get<{ Params: { id: string } }>('/api/admin/tickets/:id/replies', { preHandler: guards.requireAdmin }, async (req) => {
    const r = await db.query('SELECT id, from_admin, body, via, created_at FROM support_replies WHERE ticket_id = $1 ORDER BY created_at', [Number(req.params.id) || 0]);
    return { replies: r.rows };
  });

  app.post<{ Params: { id: string }; Body: { text?: unknown } }>('/api/admin/tickets/:id/reply', { preHandler: guards.requireAdmin }, async (req, reply) => {
    if (!enabled) return reply.code(503).send({ error: 'telegram_off' });
    const text = typeof req.body?.text === 'string' ? req.body.text.trim() : '';
    if (text.length < 2 || text.length > 3500) return reply.code(400).send({ error: 'invalid_text' });
    const res = await deliverReply(Number(req.params.id) || 0, text, 'admin');
    if (res === 'ok') return { ok: true };
    if (res === 'not_found') return reply.code(404).send({ error: 'not_found' });
    if (res === 'no_telegram') return reply.code(409).send({ error: 'no_telegram' });
    if (res === 'gone') return reply.code(410).send({ error: 'telegram_blocked' });
    return reply.code(502).send({ error: 'telegram_error' });
  });

  // -------------------------------------------------------------------------
  // Start: identity, commands, webhook (production) or polling (local test bot)
  // -------------------------------------------------------------------------

  let polling = false;
  async function start(): Promise<void> {
    const me = (await api('getMe')) as TgResult<{ id: number; username: string }>;
    if (!me.ok || !me.result) {
      app.log.error({ desc: me.description }, 'telegram: getMe failed (check TELEGRAM_BOT_TOKEN)');
      return;
    }
    botUsername = me.result.username;
    botId = me.result.id;
    await api('setMyCommands', { commands: COMMAND_MENU.pt });
    await api('setMyCommands', { commands: COMMAND_MENU.en, language_code: 'en' });
    await api('setMyShortDescription', { short_description: 'Daily Tibia bulletin, your hunt summaries and BagParser support.', language_code: 'en' });

    const pub = o.publicUrl.replace(/\/+$/, '');
    if (o.env.TELEGRAM_POLLING === '1') {
      await api('deleteWebhook', {});
      polling = true;
      app.log.info({ bot: botUsername }, 'telegram: polling (local test bot)');
      void pollLoop();
      return;
    }
    if (/^https:\/\//.test(pub) && !/localhost|127\.0\.0\.1/.test(pub)) {
      const r = await api('setWebhook', {
        url: `${pub}/api/telegram/webhook`,
        secret_token: secret,
        allowed_updates: ['message', 'channel_post', 'callback_query', 'my_chat_member', 'pre_checkout_query'],
        max_connections: 10,
      });
      app.log.info({ bot: botUsername, ok: r.ok, desc: r.ok ? undefined : r.description }, 'telegram: webhook');
    } else {
      app.log.info({ bot: botUsername }, 'telegram: no https PUBLIC_URL and TELEGRAM_POLLING unset: not receiving updates');
    }
  }

  async function pollLoop(): Promise<void> {
    let offset = 0;
    while (polling) {
      const r = (await api('getUpdates', { offset, timeout: 25, allowed_updates: ['message', 'channel_post', 'callback_query', 'my_chat_member', 'pre_checkout_query'] }, 35_000)) as TgResult<TgUpdate[]>;
      if (!r.ok) {
        await sleep(5000);
        continue;
      }
      for (const u of r.result ?? []) {
        offset = u.update_id + 1;
        await processUpdate(u);
      }
    }
  }

  if (enabled) {
    setTimeout(() => void start().catch((err) => app.log.error({ err: (err as Error).message }, 'telegram start failed')), 2000).unref();
    app.addHook('onClose', async () => {
      polling = false;
    });
  } else app.log.info('telegram: off (TELEGRAM_BOT_TOKEN empty)');

  return {
    enabled,
    username: () => botUsername,
    async linkUrl(userId) {
      if (!enabled || !botUsername) return null;
      const code = randomBytes(18).toString('base64url');
      await redis.set(`tg:link:${code}`, String(userId), 'EX', 900);
      return `https://t.me/${botUsername}?start=${code}`;
    },
    send,
    donationPaid(d) {
      if (!enabled) return;
      void (async () => {
        const amount = d.currency === 'XTR' ? `${d.amount} ⭐` : brl(d.amount, 'pt');
        if (d.chatId) {
          const t = await targetOf(Number(d.chatId));
          const name = d.userId ? ((await db.query<{ display_name: string }>('SELECT display_name FROM users WHERE id = $1', [d.userId])).rows[0]?.display_name ?? 'BagParser') : 'BagParser';
          await thank(d.chatId, d.id, amount, t?.lang ?? 'pt', name);
        }
        await noticeAdminsText(`💚 Doação Pix: <b>${tgEscape(amount)}</b>${d.userId ? ` (conta #${d.userId})` : ''}`);
      })().catch(() => null);
    },
    notifyTicket(t) {
      if (!enabled) return;
      const header = `site · ${tgEscape(t.name)}${t.email ? ` &lt;${tgEscape(t.email)}&gt;` : ''} · ${tgEscape(t.category)}`;
      void noticeAdmins(t.id, header, `${t.subject}\n\n${t.message}`).catch(() => null);
    },
  };
}

/** Today's date in the target's zone, used to send once per day */
export const targetToday = (t: { tz: string }, now = new Date()) => localClock(t.tz, now).date;
