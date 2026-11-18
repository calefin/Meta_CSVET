/**
 * Weekly summary (D-71) posted every Monday to the Discord channel the user chose (channel webhook: no bot to
 * install). D-75: the same summary also goes to Telegram (bulletin.ts). Pure parts here (tested).
 */

export const DISCORD_WEBHOOK_RE = /^https:\/\/(?:(?:ptb|canary)\.)?discord(?:app)?\.com\/api\/webhooks\/(\d{5,25})\/([\w-]{20,120})$/;

/** Only Discord's own webhook URLs (the server posts to it: nothing else may be reachable this way) */
export function cleanDiscordWebhook(v: unknown): string | null {
  if (typeof v !== 'string') return null;
  const s = v.trim().replace(/\?.*$/, '');
  return DISCORD_WEBHOOK_RE.test(s) ? s : null;
}

export const maskWebhook = (url: string): string => {
  const m = url.match(DISCORD_WEBHOOK_RE);
  return m ? `discord.com/api/webhooks/${m[1].slice(0, 4)}…/••••` : '••••';
};

/** Monday 00:00 (Brasília) of the week before "now" → [start, end) in UTC */
export function lastWeekRange(now = new Date()): { start: Date; end: Date; key: string } {
  const brt = new Date(now.getTime() - 3 * 3_600_000);
  const dow = (brt.getUTCDay() + 6) % 7; // 0 = Monday
  const thisMonday = Date.UTC(brt.getUTCFullYear(), brt.getUTCMonth(), brt.getUTCDate() - dow) + 3 * 3_600_000;
  const start = new Date(thisMonday - 7 * 86_400_000);
  return { start, end: new Date(thisMonday), key: new Date(thisMonday - 7 * 86_400_000 - 3 * 3_600_000).toISOString().slice(0, 10) };
}

/** It is Monday after 10:00 Brasília (the job checks often and sends once per week) */
export function isSendTime(now = new Date()): boolean {
  const brt = new Date(now.getTime() - 3 * 3_600_000);
  return brt.getUTCDay() === 1 && brt.getUTCHours() >= 10;
}

export interface WeekHunt {
  character_name: string | null;
  place_name: string | null;
  duration_s: number;
  xp: number;
  xp_h: number;
  balance: number;
  extra_expenses: number;
}
export interface WeekChar {
  name: string;
  level_from: number;
  level_to: number;
  exp_gain: number;
}

export const fmtNum = (n: number, lang: string): string => {
  const a = Math.abs(n);
  const sign = n < 0 ? '-' : '';
  const loc = lang.startsWith('pt') ? 'pt-BR' : 'en-US';
  const f = (x: number) => x.toLocaleString(loc, { maximumFractionDigits: 1 });
  if (a >= 1e9) return `${sign}${f(a / 1e9)}kkk`;
  if (a >= 1e6) return `${sign}${f(a / 1e6)}kk`;
  if (a >= 1e4) return `${sign}${f(a / 1e3)}k`;
  return `${sign}${Math.round(a).toLocaleString(loc)}`;
};

/** Discord message (embed) for one account; null when there is nothing to tell */
export function weeklyDiscordMessage(o: { name: string; weekKey: string; hunts: WeekHunt[]; chars: WeekChar[]; lang: string; siteUrl: string }) {
  const pt = o.lang.startsWith('pt');
  if (!o.hunts.length && !o.chars.some((c) => c.exp_gain > 0)) return null;
  const hours = o.hunts.reduce((s, h) => s + h.duration_s, 0) / 3600;
  const xp = o.hunts.reduce((s, h) => s + h.xp, 0);
  const profit = o.hunts.reduce((s, h) => s + h.balance - (h.extra_expenses ?? 0), 0);
  const best = [...o.hunts].sort((a, b) => b.xp_h - a.xp_h)[0];
  const fields: { name: string; value: string; inline?: boolean }[] = [];
  if (o.hunts.length) {
    fields.push(
      { name: pt ? 'Hunts' : 'Hunts', value: `${o.hunts.length} · ${hours.toLocaleString(pt ? 'pt-BR' : 'en-US', { maximumFractionDigits: 1 })} h`, inline: true },
      { name: 'XP', value: fmtNum(xp, o.lang), inline: true },
      { name: pt ? 'Lucro' : 'Profit', value: `${fmtNum(profit, o.lang)} gp`, inline: true },
    );
    if (best) fields.push({ name: pt ? 'Melhor hunt' : 'Best hunt', value: `${best.place_name ?? '—'}${best.character_name ? ` (${best.character_name})` : ''}: ${fmtNum(best.xp_h, o.lang)} XP/h` });
  }
  for (const c of o.chars.filter((x) => x.exp_gain > 0).slice(0, 5)) {
    fields.push({
      name: c.name,
      value: `${fmtNum(c.exp_gain, o.lang)} XP${c.level_to > c.level_from ? ` · ${pt ? 'level' : 'level'} ${c.level_from} → ${c.level_to}` : ''}`,
      inline: true,
    });
  }
  return {
    username: 'BagParser',
    embeds: [
      {
        title: pt ? `📅 Sua semana no Tibia (${o.weekKey})` : `📅 Your Tibia week (${o.weekKey})`,
        description: pt ? `Resumo de ${o.name}` : `Summary for ${o.name}`,
        color: 0x14634a,
        fields,
        url: `${o.siteUrl}/hunt-analyser/mine`,
        footer: { text: pt ? 'BagParser · desligue em Minha conta' : 'BagParser · turn off in your account' },
      },
    ],
  };
}
