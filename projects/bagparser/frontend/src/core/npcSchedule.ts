/**
 * FR-CORE-11 / FR-CORE-12: Rashid's weekly route and Yasir toggle.
 * The Tibia "day" starts at server save, 10:00 Europe/Berlin.
 */

const RASHID_BY_WEEKDAY: Record<number, string> = {
  0: 'Carlin', // Sunday
  1: 'Svargrond',
  2: 'Liberty Bay',
  3: 'Port Hope',
  4: 'Ankrahmun',
  5: 'Darashia',
  6: 'Edron',
};

const SERVER_SAVE_HOUR = 10;

interface BerlinParts {
  year: number;
  month: number;
  day: number;
  hour: number;
}

function berlinParts(now: Date): BerlinParts {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/Berlin',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(now);
  const get = (t: string) => parseInt(parts.find((p) => p.type === t)!.value, 10);
  return { year: get('year'), month: get('month'), day: get('day'), hour: get('hour') };
}

/**
 * Calendar date (as a UTC-midnight Date) of the current Tibia server day.
 * Before 10:00 Berlin time it is still "yesterday".
 */
export function tibiaServerDay(now: Date = new Date()): Date {
  const p = berlinParts(now);
  const d = new Date(Date.UTC(p.year, p.month - 1, p.day));
  if (p.hour < SERVER_SAVE_HOUR) d.setUTCDate(d.getUTCDate() - 1);
  return d;
}

export function rashidToday(now: Date = new Date()): { weekday: number; city: string; serverDay: Date } {
  const serverDay = tibiaServerDay(now);
  const weekday = serverDay.getUTCDay();
  return { weekday, city: RASHID_BY_WEEKDAY[weekday], serverDay };
}

export function weekdayName(serverDay: Date, locale: string): string {
  return new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }).format(serverDay);
}

/** Storage key for the Yasir toggle — changes every server save, so the toggle resets daily. */
export function yasirStorageKey(now: Date = new Date()): string {
  return `bagparser_yasir_${tibiaServerDay(now).toISOString().slice(0, 10)}`;
}
