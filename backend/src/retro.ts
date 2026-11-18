/**
 * Monthly retrospective (D-76): the month of an account in numbers, for the /retro page (shareable card) and the
 * message the bot sends on the 1st.
 *
 *   GET /api/me/retro?month=YYYY-MM   (default: last month)
 */
import type { FastifyInstance } from 'fastify';
import type pg from 'pg';
import type { AuthGuards } from './accounts.js';
import { tgEscape } from './bulletin.js';
import { fmtNum } from './weekly.js';

export interface RetroStats {
  month: string;
  hunts: number;
  hours: number;
  xp: number;
  profit: number;
  best: { place: string; character: string | null; xp_h: number } | null;
  favorite: { place: string; hunts: number } | null;
  chars: { name: string; level_from: number; level_to: number; exp_gain: number }[];
  deaths: number;
}

const MONTH_RE = /^(\d{4})-(0[1-9]|1[0-2])$/;

/** "2026-11" → previous month "2026-10" */
export function previousMonth(month: string): string {
  const [y, m] = month.split('-').map(Number);
  return m === 1 ? `${y - 1}-12` : `${y}-${String(m - 1).padStart(2, '0')}`;
}

/** Month bounds in Brasília (UTC−3, the site's default zone) */
export function monthRange(month: string): { start: Date; end: Date } {
  const [y, m] = month.split('-').map(Number);
  return { start: new Date(Date.UTC(y, m - 1, 1, 3)), end: new Date(Date.UTC(m === 12 ? y + 1 : y, m === 12 ? 0 : m, 1, 3)) };
}

export const monthName = (month: string, lang: string): string =>
  new Intl.DateTimeFormat(lang.startsWith('pt') ? 'pt-BR' : 'en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month}-15T12:00:00Z`));

export async function retroStats(db: pg.Pool, userId: number, month: string): Promise<RetroStats> {
  const { start, end } = monthRange(month);
  const [h, best, fav, chars, deaths] = await Promise.all([
    db.query<{ n: number; secs: number; xp: number; profit: number }>(
      `SELECT COUNT(*)::int AS n, COALESCE(SUM(duration_s), 0)::float8 AS secs, COALESCE(SUM(xp), 0)::float8 AS xp,
              COALESCE(SUM(balance - COALESCE(extra_expenses, 0)), 0)::float8 AS profit
         FROM hunt_sessions WHERE user_id = $1 AND COALESCE(started_at, created_at) >= $2 AND COALESCE(started_at, created_at) < $3`,
      [userId, start, end],
    ),
    db.query<{ place: string; character: string | null; xp_h: number }>(
      `SELECT COALESCE(place_name, '—') AS place, character_name AS character, xp_h::float8 AS xp_h
         FROM hunt_sessions WHERE user_id = $1 AND COALESCE(started_at, created_at) >= $2 AND COALESCE(started_at, created_at) < $3
          AND duration_s >= 1800 ORDER BY xp_h DESC LIMIT 1`,
      [userId, start, end],
    ),
    db.query<{ place: string; n: number }>(
      `SELECT place_name AS place, COUNT(*)::int AS n FROM hunt_sessions
        WHERE user_id = $1 AND place_name IS NOT NULL AND COALESCE(started_at, created_at) >= $2 AND COALESCE(started_at, created_at) < $3
        GROUP BY place_name ORDER BY n DESC, place_name LIMIT 1`,
      [userId, start, end],
    ),
    db.query<{ name: string; level_from: number; level_to: number; exp_gain: number }>(
      `SELECT c.name, MIN(s.level)::int AS level_from, MAX(s.level)::int AS level_to, (MAX(s.experience) - MIN(s.experience))::float8 AS exp_gain
         FROM characters c JOIN character_snapshots s ON s.character_id = c.id
        WHERE c.user_id = $1 AND s.taken_at >= $2 AND s.taken_at < $3 GROUP BY c.name
        ORDER BY exp_gain DESC LIMIT 5`,
      [userId, start, end],
    ),
    db.query<{ n: number }>(
      `SELECT COUNT(*)::int AS n FROM character_deaths d JOIN characters c ON c.id = d.character_id WHERE c.user_id = $1 AND d.time >= $2 AND d.time < $3`,
      [userId, start, end],
    ),
  ]);
  return {
    month,
    hunts: h.rows[0].n,
    hours: h.rows[0].secs / 3600,
    xp: h.rows[0].xp,
    profit: h.rows[0].profit,
    best: best.rows[0] ?? null,
    favorite: fav.rows[0] ? { place: fav.rows[0].place, hunts: fav.rows[0].n } : null,
    chars: chars.rows.filter((c) => c.exp_gain > 0 || c.level_to > c.level_from),
    deaths: deaths.rows[0].n,
  };
}

export const retroIsEmpty = (r: RetroStats): boolean => !r.hunts && !r.chars.length;

/** Telegram message for the 1st of the month */
export function retroTelegram(r: RetroStats, lang: string, siteUrl: string): string | null {
  if (retroIsEmpty(r)) return null;
  const pt = lang.startsWith('pt');
  const lines: string[] = [`📊 <b>${pt ? 'Seu mês no Tibia' : 'Your month in Tibia'}: ${tgEscape(monthName(r.month, lang))}</b>`, ''];
  if (r.hunts) {
    lines.push(
      `⚔️ ${r.hunts} hunts · ${r.hours.toLocaleString(pt ? 'pt-BR' : 'en-US', { maximumFractionDigits: 1 })} h · ${fmtNum(r.xp, lang)} XP · ${pt ? 'lucro' : 'profit'} ${fmtNum(r.profit, lang)} gp`,
    );
    if (r.best) lines.push(`🚀 ${pt ? 'Melhor hunt' : 'Best hunt'}: ${tgEscape(r.best.place)} · ${fmtNum(r.best.xp_h, lang)} XP/h`);
    if (r.favorite && r.favorite.hunts > 1) lines.push(`❤️ ${pt ? 'Spawn favorito' : 'Favorite spawn'}: ${tgEscape(r.favorite.place)} (${r.favorite.hunts}×)`);
  }
  for (const c of r.chars) lines.push(`📈 <b>${tgEscape(c.name)}</b>: ${c.level_to > c.level_from ? `level ${c.level_from} → ${c.level_to} · ` : ''}+${fmtNum(c.exp_gain, lang)} XP`);
  if (r.deaths) lines.push(`☠️ ${r.deaths} ${pt ? (r.deaths > 1 ? 'mortes' : 'morte') : r.deaths > 1 ? 'deaths' : 'death'}`);
  lines.push('', `<a href="${siteUrl}/retro?month=${r.month}">${pt ? 'Ver o card para compartilhar' : 'See the card to share'}</a>`);
  return lines.join('\n');
}

export function registerRetroRoutes(app: FastifyInstance, { db, guards }: { db: pg.Pool; guards: AuthGuards }): void {
  app.get<{ Querystring: { month?: string } }>('/api/me/retro', { preHandler: guards.requireUser }, async (req, reply) => {
    const now = new Date(Date.now() - 3 * 3_600_000);
    const current = `${now.getUTCFullYear()}-${String(now.getUTCMonth() + 1).padStart(2, '0')}`;
    const month = MONTH_RE.test(req.query.month ?? '') ? req.query.month! : previousMonth(current);
    if (month > current) return reply.code(400).send({ error: 'invalid_month' });
    reply.header('Cache-Control', 'no-store');
    const r = await retroStats(db, req.user!.id, month);
    return { ...r, name: req.user!.display_name };
  });
}
