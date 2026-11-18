/**
 * Daily bulletin (D-75): one content, two renderings — Telegram (HTML) and Discord (embed with Markdown).
 * Pure: the data is gathered by bulletinSource.ts.
 */
import { expForLevel } from './characters.js';
import type { GuildRanking } from './guildRanking.js';
import { fmtNum, type WeekChar, type WeekHunt } from './weekly.js';

export interface KillStat {
  race: string;
  killed: number;
}

/** One character's day (D-75): needs snapshots, i.e. daily tracking (Pro) */
export interface CharDay {
  name: string;
  level_from: number;
  level_to: number;
  exp_gain: number;
  exp_now: number;
  /** Average XP per day over the last ~7 days (null with too little history) */
  avg_day: number | null;
  deaths: number;
  goal_level: number | null;
  goal_date: string | null;
}

export interface NewsItem {
  date: string;
  title: string;
  url: string;
  ticker: boolean;
}

export interface BulletinData {
  /** Current server day (YYYY-MM-DD) */
  day: string;
  now: number;
  world: string | null;
  boosted: { creature: string | null; boss: string | null };
  rashid: string;
  dreamScar: string;
  yasir: { status: string; city: string | null; reports: number } | null;
  dromeEndsAt: number;
  /** Kill statistics of the previous server day on the world */
  kills: { bosses: string[]; top: KillStat[] } | null;
  news: NewsItem[];
  /** The owner's hunts and characters on the previous server day; soulMissing = characters without today's boosted soul core */
  personal: { hunts: WeekHunt[]; chars: CharDay[]; soulMissing?: string[] } | null;
  /** D-76: XP ranking of the followed guild (owner Pro) */
  guild?: GuildRanking | null;
  /** D-76: supporter thanked in today's footer */
  supporter?: string | null;
}

const VOC: Record<string, string> = { knight: '🛡️', paladin: '🏹', sorcerer: '🔥', druid: '🍃', monk: '🥋' };
export const vocationIcon = (v: string | null | undefined): string => {
  const k = Object.keys(VOC).find((x) => (v ?? '').toLowerCase().includes(x));
  return k ? VOC[k] : '⚪';
};

/** Guild ranking lines (yesterday) */
export function guildLines(g: GuildRanking, lang: string, s: Style): string[] {
  const pt = isPt(lang);
  if (g.first) return [`🏆 ${s.b(s.esc(g.guild))}: ${pt ? 'o ranking de XP começa amanhã (hoje guardamos o ponto de partida).' : 'the XP ranking starts tomorrow (today we stored the starting point).'}`];
  const out = [`🏆 ${s.b(pt ? `Top XP da guild ${s.esc(g.guild)} (ontem)` : `Top XP of ${s.esc(g.guild)} (yesterday)`)}`];
  const medal = ['🥇', '🥈', '🥉'];
  g.top.forEach((r, i) => {
    const lv = r.to > r.from ? `Lv ${r.from}→${r.to}` : `Lv ${r.to}`;
    out.push(`${medal[i] ?? `${i + 1}.`} ${vocationIcon(r.vocation)} ${s.b(s.esc(r.name))} (${lv})${r.gain != null ? ` +${fmtNum(r.gain, lang)}` : ''}`);
  });
  if (!g.top.length) out.push(pt ? 'Ninguém da guild ganhou XP ontem.' : 'Nobody in the guild gained XP yesterday.');
  if (g.fell.length)
    out.push(
      `☠️ ${pt ? 'Perderam XP' : 'Lost XP'}: ${g.fell
        .map((r) => `${s.esc(r.name)} (${r.to < r.from ? `Lv ${r.from}→${r.to}` : `${fmtNum(r.gain ?? 0, lang)}`})`)
        .join(', ')}`,
    );
  return out;
}

interface Style {
  b: (s: string) => string;
  a: (text: string, url: string) => string;
  esc: (s: string) => string;
}

export const tgEscape = (s: string): string => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const mdEscape = (s: string): string => s.replace(/([*_~`|\\[\]])/g, '\\$1');

const TG: Style = { b: (s) => `<b>${s}</b>`, a: (t, u) => `<a href="${tgEscape(u)}">${t}</a>`, esc: tgEscape };
const MD: Style = { b: (s) => `**${s}**`, a: (t, u) => `[${t}](${u})`, esc: mdEscape };

const isPt = (lang: string) => lang.startsWith('pt');

/** "sábado, 07/11" / "Saturday, Nov 7" */
export function dayLabel(day: string, lang: string): string {
  const d = new Date(`${day}T12:00:00Z`);
  const pt = isPt(lang);
  const wd = new Intl.DateTimeFormat(pt ? 'pt-BR' : 'en-US', { weekday: 'long', timeZone: 'UTC' }).format(d);
  const date = new Intl.DateTimeFormat(pt ? 'pt-BR' : 'en-US', pt ? { day: '2-digit', month: '2-digit', timeZone: 'UTC' } : { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(d);
  return `${wd}, ${date}`;
}

const shortDate = (day: string, lang: string) =>
  new Intl.DateTimeFormat(isPt(lang) ? 'pt-BR' : 'en-US', isPt(lang) ? { day: '2-digit', month: '2-digit', timeZone: 'UTC' } : { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(
    new Date(`${day.slice(0, 10)}T12:00:00Z`),
  );

function duration(ms: number, lang: string): string {
  const m = Math.max(0, Math.floor(ms / 60_000));
  const d = Math.floor(m / 1440);
  const h = Math.floor((m % 1440) / 60);
  if (d) return `${d}d ${h}h`;
  return isPt(lang) ? `${h}h ${m % 60}min` : `${h}h ${m % 60}m`;
}

/** Lines about the owner's hunts (shared by the daily bulletin and the weekly summary) */
export function huntLines(hunts: WeekHunt[], lang: string, s: Style): string[] {
  const pt = isPt(lang);
  const out: string[] = [];
  if (hunts.length) {
    const hours = hunts.reduce((x, h) => x + h.duration_s, 0) / 3600;
    const xp = hunts.reduce((x, h) => x + h.xp, 0);
    const profit = hunts.reduce((x, h) => x + h.balance - (h.extra_expenses ?? 0), 0);
    const hoursTxt = hours.toLocaleString(pt ? 'pt-BR' : 'en-US', { maximumFractionDigits: 1 });
    out.push(`${hunts.length} hunt${hunts.length > 1 ? 's' : ''} · ${hoursTxt} h · ${fmtNum(xp, lang)} XP · ${pt ? 'lucro' : 'profit'} ${fmtNum(profit, lang)} gp`);
    const best = [...hunts].sort((a, b) => b.xp_h - a.xp_h)[0];
    if (best && hunts.length > 1)
      out.push(`${pt ? 'Melhor' : 'Best'}: ${s.esc(best.place_name ?? '—')}${best.character_name ? ` (${s.esc(best.character_name)})` : ''} · ${fmtNum(best.xp_h, lang)} XP/h`);
  }
  return out;
}

/** Weekly summary: XP and levels of the week */
export function weekCharLines(chars: WeekChar[], lang: string, s: Style): string[] {
  return chars
    .filter((x) => x.exp_gain > 0)
    .slice(0, 5)
    .map((c) => `${s.b(s.esc(c.name))}: +${fmtNum(c.exp_gain, lang)} XP${c.level_to > c.level_from ? ` · level ${c.level_from} → ${c.level_to}` : ''}`);
}

/** Daily evolution of each tracked character: level, XP of the day, 7-day pace, deaths and the goal forecast */
export function charDayLines(chars: CharDay[], lang: string, s: Style, today: string): string[] {
  const pt = isPt(lang);
  const out: string[] = [];
  const shown = chars.filter((c) => c.exp_gain > 0 || c.deaths > 0 || c.goal_level).slice(0, 5);
  for (const c of shown) {
    const up = c.level_to - c.level_from;
    const parts = [`level ${c.level_to}${up > 0 ? ` (+${up})` : up < 0 ? ` (${up})` : ''}`];
    parts.push(c.exp_gain > 0 ? `+${fmtNum(c.exp_gain, lang)} XP` : pt ? 'sem XP' : 'no XP');
    if (c.avg_day && c.avg_day > 0) parts.push(`${pt ? 'média 7d' : '7d avg'} ${fmtNum(c.avg_day, lang)}/${pt ? 'dia' : 'day'}`);
    out.push(`${s.b(s.esc(c.name))}: ${parts.join(' · ')}`);
    if (c.deaths > 0) out.push(`   ☠️ ${c.deaths} ${pt ? (c.deaths > 1 ? 'mortes' : 'morte') : c.deaths > 1 ? 'deaths' : 'death'}`);
    if (c.goal_level && c.goal_level > c.level_to) {
      const left = Math.max(0, expForLevel(c.goal_level) - c.exp_now);
      let line = `   🎯 ${pt ? 'meta' : 'goal'} level ${c.goal_level}: ${pt ? 'faltam' : ''} ${fmtNum(left, lang)} XP`.replace('  ', ' ');
      if (c.avg_day && c.avg_day > 0) {
        const days = Math.ceil(left / c.avg_day);
        line += ` · ~${days} ${pt ? 'dias no ritmo atual' : 'days at this pace'}`;
        if (c.goal_date) {
          const daysLeft = Math.round((Date.parse(`${c.goal_date}T00:00:00Z`) - Date.parse(`${today}T00:00:00Z`)) / 86_400_000);
          if (daysLeft >= 0) line += days <= daysLeft ? (pt ? ' ✅ dentro do prazo' : ' ✅ on time') : pt ? ` ⚠️ prazo em ${daysLeft} dias` : ` ⚠️ deadline in ${daysLeft} days`;
        }
      }
      out.push(line);
    } else if (c.goal_level && c.goal_level <= c.level_to) out.push(`   🎯 ${pt ? 'meta' : 'goal'} level ${c.goal_level} ${pt ? 'alcançada! 🎉' : 'reached! 🎉'}`);
  }
  return out;
}

const YASIR_TEXT: Record<string, { pt: string; en: string }> = {
  confirmed: { pt: 'está em {city} hoje ✅', en: 'is in {city} today ✅' },
  unconfirmed: { pt: 'relatado em {city} (ainda sem confirmação)', en: 'reported in {city} (not confirmed yet)' },
  contested: { pt: 'relatos divergentes', en: 'conflicting reports' },
  absent: { pt: 'relatos dizem que não veio', en: 'reports say he did not come' },
  none: { pt: 'sem relatos ainda (viu? avise no site)', en: 'no reports yet (seen him? tell the site)' },
};

/** "está em Carlin hoje ✅" (bot /yasir) */
export function yasirStatusText(y: { status: string; city: string | null }, lang: string): string {
  const pt = isPt(lang);
  const t = YASIR_TEXT[y.status] ?? YASIR_TEXT.none;
  const city = y.city && y.city !== 'other' ? y.city : pt ? 'outra cidade' : 'another city';
  return (pt ? t.pt : t.en).replace('{city}', `<b>${tgEscape(city)}</b>`);
}

/** Title + body lines; the renderers only differ in markup */
function bulletinParts(d: BulletinData, lang: string, siteUrl: string, s: Style): { title: string; sections: string[][]; footer: string } {
  const pt = isPt(lang);
  const title = `${pt ? 'Tibia hoje' : 'Tibia today'} · ${dayLabel(d.day, lang)}${d.world ? ` · ${d.world}` : ''}`;
  const today: string[] = [];
  if (d.boosted.creature) today.push(`⭐ ${pt ? 'Criatura boosted' : 'Boosted creature'}: ${s.b(s.esc(d.boosted.creature))}`);
  if (d.boosted.boss) today.push(`👑 ${pt ? 'Boss boosted' : 'Boosted boss'}: ${s.b(s.esc(d.boosted.boss))}`);
  today.push(`🧳 Rashid: ${s.b(d.rashid)}`);
  today.push(`💤 Dream Scar: ${s.b(d.dreamScar)}`);
  if (d.yasir) {
    const t = YASIR_TEXT[d.yasir.status] ?? YASIR_TEXT.none;
    const city = d.yasir.city && d.yasir.city !== 'other' ? d.yasir.city : pt ? 'outra cidade' : 'another city';
    today.push(`🏺 Yasir: ${(pt ? t.pt : t.en).replace('{city}', s.b(city))}`);
  }
  const dromeLeft = d.dromeEndsAt - d.now;
  if (dromeLeft > 0 && dromeLeft <= 3 * 86_400_000) today.push(`🏟️ Tibiadrome: ${pt ? 'a rotação acaba em' : 'rotation ends in'} ${duration(dromeLeft, lang)}`);

  const sections: string[][] = [today];
  if (d.kills && (d.kills.bosses.length || d.kills.top.length)) {
    const k: string[] = [`☠️ ${s.b(pt ? `Ontem em ${d.world}` : `Yesterday on ${d.world}`)}`];
    if (d.kills.bosses.length) k.push(`${pt ? 'Bosses raros mortos' : 'Rare bosses killed'}: ${d.kills.bosses.slice(0, 12).map(s.esc).join(', ')}${d.kills.bosses.length > 12 ? '…' : ''}`);
    if (d.kills.top.length)
      k.push(
        `${pt ? 'Mais caçadas' : 'Most hunted'}: ${d.kills.top
          .slice(0, 5)
          .map((x) => `${s.esc(x.race)} (${x.killed.toLocaleString(pt ? 'pt-BR' : 'en-US')})`)
          .join(', ')}`,
      );
    sections.push(k);
  }
  if (d.guild) sections.push(guildLines(d.guild, lang, s));
  if (d.personal) {
    const evo = charDayLines(d.personal.chars, lang, s, d.day);
    if (evo.length) sections.push([`📈 ${s.b(pt ? 'Evolução dos seus personagens (ontem)' : 'Your characters (yesterday)')}`, ...evo]);
    if (d.personal.soulMissing?.length && d.boosted.creature)
      sections.push([
        `🧿 ${pt ? `O boosted de hoje (${s.b(s.esc(d.boosted.creature))}) ainda falta no soul core de` : `Today's boosted (${s.b(s.esc(d.boosted.creature))}) is still missing from the soul cores of`}: ${d.personal.soulMissing.slice(0, 6).map(s.esc).join(', ')}`,
      ]);
    const hunts = huntLines(d.personal.hunts, lang, s);
    if (hunts.length) sections.push([`⚔️ ${s.b(pt ? 'Suas hunts ontem' : 'Your hunts yesterday')}`, ...hunts]);
  }
  if (d.news.length) {
    sections.push([
      `📰 ${s.b(pt ? 'Notícias do Tibia' : 'Tibia news')}`,
      ...d.news.slice(0, 3).map((n) => `• ${s.a(s.esc(n.title.length > 110 ? `${n.title.slice(0, 107)}…` : n.title), n.url)} (${shortDate(n.date, lang)})`),
    ]);
  }
  const thanks = d.supporter ? `💚 ${pt ? 'Apoio de hoje' : "Today's supporter"}: ${s.b(s.esc(d.supporter))}\n` : '';
  const footer = `${thanks}${s.a('bagparser.com', siteUrl)} · ${pt ? 'dados: TibiaData, TibiaWiki e relatos dos jogadores' : 'data: TibiaData, TibiaWiki and player reports'}`;
  return { title, sections, footer };
}

/** Telegram message (parse_mode HTML) */
export function bulletinTelegram(d: BulletinData, lang: string, siteUrl: string, hint?: string): string {
  const p = bulletinParts(d, lang, siteUrl, TG);
  const body = p.sections.map((x) => x.join('\n')).join('\n\n');
  return `📅 <b>${tgEscape(p.title)}</b>\n\n${body}\n\n${p.footer}${hint ? `\n<i>${tgEscape(hint)}</i>` : ''}`.slice(0, 4000);
}

/** Discord webhook body */
export function bulletinDiscord(d: BulletinData, lang: string, siteUrl: string) {
  const p = bulletinParts(d, lang, siteUrl, MD);
  return {
    username: 'BagParser',
    embeds: [
      {
        title: `📅 ${p.title}`,
        description: `${p.sections.map((x) => x.join('\n')).join('\n\n')}\n\n${p.footer}`.slice(0, 4000),
        color: 0x14634a,
        url: siteUrl,
      },
    ],
  };
}

/** Weekly summary for Telegram (the Discord one is weeklyDiscordMessage) */
export function weeklyTelegram(o: { name: string; weekKey: string; hunts: WeekHunt[]; chars: WeekChar[]; lang: string; siteUrl: string }): string | null {
  const lines = [...huntLines(o.hunts, o.lang, TG), ...weekCharLines(o.chars, o.lang, TG)];
  if (!lines.length) return null;
  const pt = isPt(o.lang);
  return `📅 <b>${pt ? 'Sua semana no Tibia' : 'Your Tibia week'} (${shortDate(o.weekKey, o.lang)})</b>\n${pt ? 'Resumo de' : 'Summary for'} ${tgEscape(o.name)}\n\n${lines.join('\n')}\n\n${TG.a(
    pt ? 'Ver minhas hunts' : 'See my hunts',
    `${o.siteUrl}/hunt-analyser/mine`,
  )}`;
}

/**
 * Kill statistics of one world → rare bosses killed yesterday (Bosstiary "Nemesis": the ones that spawn on the map)
 * + most killed creatures (any boss left out)
 */
export function summarizeKills(
  entries: { race: string; last_day_killed: number }[],
  isBoss: (name: string) => boolean,
  isRare: (name: string) => boolean,
): { bosses: string[]; top: KillStat[] } {
  const killed = entries.filter((e) => e.last_day_killed > 0 && e.race && !/^players?$/i.test(e.race));
  const bosses = killed.filter((e) => isRare(e.race)).map((e) => e.race);
  const top = killed
    .filter((e) => !isBoss(e.race))
    .sort((a, b) => b.last_day_killed - a.last_day_killed)
    .slice(0, 5)
    .map((e) => ({ race: e.race, killed: e.last_day_killed }));
  return { bosses: [...new Set(bosses)].sort((a, b) => a.localeCompare(b)), top };
}
