/**
 * Server-day facts for the daily bulletin (D-75). Same rules as the site's "Today in Tibia" cards
 * (frontend/src/core/daily.ts and npcSchedule.ts; TibiaWiki, CC BY-SA): the Tibia day starts at the
 * server save, 10:00 Europe/Berlin.
 */

const RASHID_BY_WEEKDAY = ['Carlin', 'Svargrond', 'Liberty Bay', 'Port Hope', 'Ankrahmun', 'Darashia', 'Edron'];

export const DREAM_BOSSES = ['Maxxenius', 'Alptramun', 'Izcandar the Banished', 'Plagueroot', 'Malofur Mangrinder'] as const;

const DROME_EPOCH = Date.UTC(2021, 6, 14, 8, 0, 0); // 2021-07-14 10:00 CEST
const DROME_PERIOD = 14 * 86_400_000;

/** Berlin offset from UTC in hours (1 in winter, 2 in summer). */
export function berlinOffsetHours(now = Date.now()): number {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/Berlin',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(new Date(now));
  const get = (t: string) => parseInt(parts.find((p) => p.type === t)!.value, 10);
  const asUtc = Date.UTC(get('year'), get('month') - 1, get('day'), get('hour'), get('minute'));
  return Math.round((asUtc - Math.floor(now / 60_000) * 60_000) / 3_600_000);
}

/** Server save instant (10:00 Berlin) on a calendar day "YYYY-MM-DD". */
export function serverSaveAt(day: string): Date {
  const [y, m, d] = day.split('-').map(Number);
  const noon = Date.UTC(y, m - 1, d, 12);
  return new Date(Date.UTC(y, m - 1, d, 10) - berlinOffsetHours(noon) * 3_600_000);
}

export function addDays(day: string, n: number): string {
  const d = new Date(`${day}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

/** [start, end) of a server day */
export function serverDayRange(day: string): { start: Date; end: Date } {
  return { start: serverSaveAt(day), end: serverSaveAt(addDays(day, 1)) };
}

export function rashidCity(day: string): string {
  return RASHID_BY_WEEKDAY[new Date(`${day}T00:00:00Z`).getUTCDay()];
}

/** Dream Scar boss for a world offset (0 = most worlds). Same formula as TibiaWiki's template. */
export function dreamScarBoss(worldOffset = 0, now = Date.now()): string {
  const t = now / 1000 + berlinOffsetHours(now) * 3600 - 10 * 3600;
  const day = Math.ceil(t / 86400);
  return DREAM_BOSSES[(((day + 4 + worldOffset) % 5) + 5) % 5];
}

/** When the current Tibiadrome rotation ends (server save of every other Wednesday). */
export function dromeEndsAt(now = Date.now()): number {
  const n = Math.floor((now - DROME_EPOCH) / DROME_PERIOD);
  const at = (k: number) => serverSaveAt(new Date(DROME_EPOCH + k * DROME_PERIOD).toISOString().slice(0, 10)).getTime();
  let end = at(n + 1);
  if (now >= end) end = at(n + 2);
  else if (now < at(n)) end = at(n);
  return end;
}

/** Calendar date and hour in a time zone (for "send at 8 h local time"). */
export function localClock(tz: string, now = new Date()): { date: string; hour: number } {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: tz,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(now);
  const get = (t: string) => parts.find((p) => p.type === t)!.value;
  return { date: `${get('year')}-${get('month')}-${get('day')}`, hour: parseInt(get('hour'), 10) };
}

export function validTimeZone(tz: unknown): tz is string {
  if (typeof tz !== 'string' || tz.length > 40 || !/^[A-Za-z_]+(?:\/[A-Za-z0-9_+-]+){0,2}$/.test(tz)) return false;
  try {
    new Intl.DateTimeFormat('en', { timeZone: tz });
    return true;
  } catch {
    return false;
  }
}

/** Default zone for a language when the user did not choose one (bot commands, groups). */
export const defaultTz = (lang: string): string => (lang.startsWith('pt') ? 'America/Sao_Paulo' : lang.startsWith('pl') ? 'Europe/Warsaw' : 'Europe/Berlin');
