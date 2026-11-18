/**
 * Character tracking helpers (D-28): ownership code, experience formula, highscore lookup.
 * Pure functions, unit tested in characters.test.ts.
 */
import { randomInt } from 'node:crypto';

/** Official Tibia formula: total experience needed to reach `level`. */
export function expForLevel(level: number): number {
  const l = Math.max(1, Math.floor(level));
  return Math.round((50 / 3) * (l ** 3 - 6 * l ** 2 + 17 * l - 12));
}

/** Tibia character names: letters, spaces, ' and -, 2–29 chars. Returns the canonical spacing or null. */
export function normalizeCharName(raw: unknown): string | null {
  if (typeof raw !== 'string') return null;
  const name = raw.trim().replace(/\s+/g, ' ');
  if (name.length < 2 || name.length > 29 || !/^[A-Za-z][A-Za-z '-]*$/.test(name)) return null;
  return name;
}

// No 0/O/1/I to avoid typos when copying by hand
const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

/** Code the player puts in the character comment on tibia.com, e.g. "BP-7KQ2M9XA". */
export function newVerificationCode(): string {
  let s = '';
  for (let i = 0; i < 8; i++) s += CODE_ALPHABET[randomInt(CODE_ALPHABET.length)];
  return `BP-${s}`;
}

/** The comment must contain the code (case-insensitive, ignoring spaces and dashes/punctuation). */
export function commentHasCode(comment: string | null | undefined, code: string): boolean {
  if (!comment) return false;
  const cleanComment = comment.toUpperCase().replace(/[^A-Z0-9]/g, '');
  const cleanCode = code.toUpperCase().replace(/[^A-Z0-9]/g, '');
  return cleanComment.includes(cleanCode);
}

export interface HighscoreEntry {
  name: string;
  level: number;
  value: number;
}

export interface HighscorePage {
  entries: HighscoreEntry[];
  totalPages: number;
}

/**
 * Finds a character's exact experience in the world's experience highscores (top 1000, 20 pages,
 * sorted by experience). Binary search by level: ~5 page loads. Returns null when not listed.
 */
export async function findExperience(
  name: string,
  level: number,
  loadPage: (page: number) => Promise<HighscorePage>,
): Promise<number | null> {
  const target = name.toLowerCase();
  const first = await loadPage(1);
  const match = (p: HighscorePage) => p.entries.find((e) => e.name.toLowerCase() === target);
  const hit = match(first);
  if (hit) return hit.value;
  if (!first.entries.length) return null;

  let lo = 2;
  let hi = first.totalPages;
  const seen = new Set<number>([1]);
  while (lo <= hi) {
    const mid = Math.floor((lo + hi) / 2);
    seen.add(mid);
    const page = await loadPage(mid);
    const found = match(page);
    if (found) return found.value;
    if (!page.entries.length) {
      hi = mid - 1;
      continue;
    }
    const top = page.entries[0].level;
    const bottom = page.entries[page.entries.length - 1].level;
    if (level > top) hi = mid - 1;
    else if (level < bottom) lo = mid + 1;
    else {
      // Same level spans page borders: check the neighbours once
      for (const p of [mid - 1, mid + 1]) {
        if (p < 1 || p > first.totalPages || seen.has(p)) continue;
        seen.add(p);
        const near = match(await loadPage(p));
        if (near) return near.value;
      }
      return null;
    }
  }
  return null;
}

/** Normalised view of a TibiaData v4 /character response. */
export interface CharacterInfo {
  name: string;
  level: number;
  vocation: string;
  world: string;
  residence: string | null;
  sex: string | null;
  achievement_points: number;
  account_status: string | null;
  guild: string | null;
  last_login: string | null;
  comment: string;
  traded: boolean;
  former_names: string[];
  former_worlds: string[];
  deaths: { time: string; level: number; reason: string }[];
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function parseCharacter(json: any): CharacterInfo | null {
  const c = json?.character?.character;
  if (!c?.name || typeof c.level !== 'number') return null;
  const deaths = Array.isArray(json.character.deaths) ? json.character.deaths : [];
  return {
    name: String(c.name),
    level: c.level,
    vocation: String(c.vocation ?? 'None'),
    world: String(c.world ?? ''),
    residence: c.residence ?? null,
    sex: c.sex ?? null,
    achievement_points: Number(c.achievement_points ?? 0),
    account_status: c.account_status ?? null,
    guild: c.guild?.name ?? null,
    last_login: c.last_login ?? null,
    comment: String(c.comment ?? ''),
    traded: !!c.traded,
    former_names: Array.isArray(c.former_names) ? c.former_names.map(String) : [],
    former_worlds: Array.isArray(c.former_worlds) ? c.former_worlds.map(String) : [],
    deaths: deaths
      .filter((d: { time?: string }) => d?.time)
      .map((d: { time: string; level: number; reason: string }) => ({
        time: d.time,
        level: Number(d.level ?? 0),
        reason: String(d.reason ?? '').slice(0, 500),
      })),
  };
}
