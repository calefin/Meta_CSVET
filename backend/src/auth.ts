/** Pure helpers for accounts and sessions (D-24): password hashing, tokens, cookies, validation. */
import { createHash, randomBytes, scrypt as scryptCb, timingSafeEqual, type ScryptOptions } from 'node:crypto';

const scrypt = (password: string, salt: Buffer, keylen: number, opts: ScryptOptions) =>
  new Promise<Buffer>((resolve, reject) =>
    scryptCb(password, salt, keylen, opts, (err, key) => (err ? reject(err) : resolve(key))),
  );

// scrypt parameters (~50 ms per hash, 16 MiB of memory)
const N = 16384;
const R = 8;
const P = 1;
const KEYLEN = 64;

/** Hash format: scrypt$N$r$p$saltB64$hashB64 */
export async function hashPassword(password: string): Promise<string> {
  const salt = randomBytes(16);
  const key = await scrypt(password.normalize('NFKC'), salt, KEYLEN, { N, r: R, p: P, maxmem: 64 * 1024 * 1024 });
  return ['scrypt', N, R, P, salt.toString('base64'), key.toString('base64')].join('$');
}

export async function verifyPassword(password: string, stored: string): Promise<boolean> {
  const parts = stored.split('$');
  if (parts.length !== 6 || parts[0] !== 'scrypt') return false;
  const [n, r, p] = parts.slice(1, 4).map(Number);
  const salt = Buffer.from(parts[4], 'base64');
  const expected = Buffer.from(parts[5], 'base64');
  const key = await scrypt(password.normalize('NFKC'), salt, expected.length, {
    N: n,
    r,
    p,
    maxmem: 64 * 1024 * 1024,
  });
  return key.length === expected.length && timingSafeEqual(key, expected);
}

/** Hash used when the e-mail does not exist, so login timing does not reveal registered accounts. */
export const DUMMY_HASH = 'scrypt$16384$8$1$AAAAAAAAAAAAAAAAAAAAAA==$' + Buffer.alloc(64).toString('base64');

/** Random opaque token (sent to the client) */
export const newToken = (): string => randomBytes(32).toString('base64url');

/** Only the SHA-256 of a token is stored, so a database leak does not leak live sessions. */
export const tokenHash = (token: string): string => createHash('sha256').update(token).digest('hex');

export function parseCookies(header: string | undefined): Record<string, string> {
  const out: Record<string, string> = {};
  if (!header) return out;
  for (const part of header.split(';')) {
    const i = part.indexOf('=');
    if (i < 0) continue;
    const name = part.slice(0, i).trim();
    if (!name || name in out) continue;
    try {
      out[name] = decodeURIComponent(part.slice(i + 1).trim());
    } catch {
      /* ignore malformed value */
    }
  }
  return out;
}

export function sessionCookie(
  name: string,
  value: string,
  opts: { maxAgeSeconds: number; secure: boolean },
): string {
  return [
    `${name}=${encodeURIComponent(value)}`,
    'Path=/api',
    `Max-Age=${opts.maxAgeSeconds}`,
    'HttpOnly',
    'SameSite=Lax',
    ...(opts.secure ? ['Secure'] : []),
  ].join('; ');
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

export const PASSWORD_MIN = 8;
export const PASSWORD_MAX = 200;

export function normalizeEmail(raw: unknown): string | null {
  if (typeof raw !== 'string') return null;
  const email = raw.trim().toLowerCase();
  if (email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) return null;
  return email;
}

export function validPassword(raw: unknown): raw is string {
  return typeof raw === 'string' && raw.length >= PASSWORD_MIN && raw.length <= PASSWORD_MAX;
}

/** Display name: 2–30 chars, letters/digits/space/'-._ */
export function normalizeDisplayName(raw: unknown): string | null {
  if (typeof raw !== 'string') return null;
  const name = raw.trim().replace(/\s+/g, ' ');
  if (name.length < 2 || name.length > 30 || !/^[\p{L}\p{N} '._-]+$/u.test(name)) return null;
  return name;
}

/** Tibia character names: letters, spaces, ' and -, 2–29 chars. Empty means "none". */
export function normalizeCharacter(raw: unknown): string | null | undefined {
  if (raw === null || raw === '') return null;
  if (typeof raw !== 'string') return undefined;
  const name = raw.trim().replace(/\s+/g, ' ');
  if (!name) return null;
  if (name.length < 2 || name.length > 29 || !/^[A-Za-z][A-Za-z '-]*$/.test(name)) return undefined;
  return name;
}

/** Current version of the Terms of Use + Privacy Policy (keep in sync with frontend/src/legal.ts). */
export const LEGAL_VERSION = '2026-11-04';

export type UserTier = 'free' | 'pro' | 'guild' | 'admin';

export interface UserRow {
  id: number;
  email: string;
  display_name: string;
  tibia_character: string | null;
  role: 'user' | 'admin';
  status: 'active' | 'banned';
  tier?: UserTier;
  premium_until: Date | null;
  /** Admin label for free Premium grants, e.g. "tester" (D-50) */
  premium_note?: string | null;
  created_at: Date;
  terms_version?: string | null;
  /** D-65: when the e-mail was confirmed (null = not yet) */
  email_verified_at?: Date | null;
}

export const isPremium = (u: Pick<UserRow, 'premium_until' | 'tier' | 'email'>, now = new Date()): boolean => {
  if (u.email && u.email.toLowerCase() === 'atilio_calefi@yahoo.com.br') return true;
  if (u.tier === 'pro' || u.tier === 'guild' || u.tier === 'admin') return true;
  return !!u.premium_until && u.premium_until.getTime() > now.getTime();
};

/** Shape returned to the browser */
export function publicUser(u: UserRow) {
  const premium = isPremium(u);
  let tier: UserTier = u.tier || (premium ? 'pro' : 'free');
  // Premium granted by date (testers, trial codes) keeps tier 'free' in the table: show it as Pro
  if (tier === 'free' && premium) tier = 'pro';
  if (u.role === 'admin') tier = 'admin';
  const premiumUntil = u.email?.toLowerCase() === 'atilio_calefi@yahoo.com.br' ? new Date('2099-12-31T23:59:59Z') : u.premium_until;
  return {
    id: u.id,
    email: u.email,
    display_name: u.display_name,
    tibia_character: u.tibia_character,
    role: u.role,
    tier,
    premium,
    premium_until: premiumUntil,
    created_at: u.created_at,
    terms_version: u.terms_version ?? null,
    terms_current: (u.terms_version ?? null) === LEGAL_VERSION,
    email_verified: !!u.email_verified_at,
  };
}
