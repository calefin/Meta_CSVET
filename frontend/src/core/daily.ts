/**
 * "Today in Tibia" maths (D-30): Tibiadrome rotation end and the Dream Scar boss of the day.
 * Rules from TibiaWiki (CC BY-SA): the Drome rotates at the server save of every other Wednesday
 * since 14 July 2021 10:00 CEST; the Dream Scar boss advances one step every server save and
 * some worlds are shifted by extra (emergency) saves, so each world has an offset.
 */

const DROME_EPOCH = Date.UTC(2021, 6, 14, 8, 0, 0); // 2021-07-14 10:00 CEST
const DROME_PERIOD = 14 * 86_400_000;

/** 10:00 Europe/Berlin (server save) on the calendar day of `ms` (UTC date). */
function serverSaveOn(ms: number): number {
  const d = new Date(ms);
  const noonUtc = Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate(), 12);
  return Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate(), 10) - berlinOffsetHours(noonUtc) * 3_600_000;
}

/** Current Tibiadrome rotation: starts/ends at the server save (10:00 Berlin, DST aware) of every other Wednesday. */
export function dromeRotation(now = Date.now()): { number: number; endsAt: number; startedAt: number } {
  let n = Math.floor((now - DROME_EPOCH) / DROME_PERIOD);
  let startedAt = serverSaveOn(DROME_EPOCH + n * DROME_PERIOD);
  let endsAt = serverSaveOn(DROME_EPOCH + (n + 1) * DROME_PERIOD);
  // Near a DST change the fixed 14-day step can be one hour off: snap to the real server save
  if (now >= endsAt) {
    n += 1;
    startedAt = endsAt;
    endsAt = serverSaveOn(DROME_EPOCH + (n + 1) * DROME_PERIOD);
  } else if (now < startedAt) {
    n -= 1;
    endsAt = startedAt;
    startedAt = serverSaveOn(DROME_EPOCH + n * DROME_PERIOD);
  }
  return { number: n + 1, startedAt, endsAt };
}

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

export const DREAM_BOSSES = ['Maxxenius', 'Alptramun', 'Izcandar the Banished', 'Plagueroot', 'Malofur Mangrinder'] as const;
export type DreamBoss = (typeof DREAM_BOSSES)[number];

/**
 * Animated sprites served by us (frontend/public/bosses, © CipSoft GmbH, file pages on TibiaWiki).
 * Hotlinking the wiki CDN returned a grey placeholder (bare URL) and these bosses have no image on
 * static.tibia.com, so the files are bundled with the site and cached by the service worker.
 */
export const DREAM_BOSS_IMAGES: Record<DreamBoss, string> = {
  'Maxxenius': '/bosses/maxxenius.webp',
  'Alptramun': '/bosses/alptramun.webp',
  'Izcandar the Banished': '/bosses/izcandar-the-banished.webp',
  'Plagueroot': '/bosses/plagueroot.webp',
  'Malofur Mangrinder': '/bosses/malofur-mangrinder.webp',
};

/** Dream Scar boss for a world offset (0 = "most worlds"). Same formula as TibiaWiki's template. */
export function dreamScarBoss(worldOffset = 0, now = Date.now()): DreamBoss {
  const t = now / 1000 + berlinOffsetHours(now) * 3600 - 10 * 3600;
  const day = Math.ceil(t / 86400);
  return DREAM_BOSSES[(((day + 4 + worldOffset) % 5) + 5) % 5];
}

/** "3d 4h" / "5h 12min" countdown */
export function countdownParts(ms: number): { d: number; h: number; m: number } {
  const total = Math.max(0, Math.floor(ms / 60_000));
  return { d: Math.floor(total / 1440), h: Math.floor((total % 1440) / 60), m: total % 60 };
}
