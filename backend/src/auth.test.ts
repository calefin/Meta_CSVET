import { describe, expect, it } from 'vitest';
import {
  DUMMY_HASH,
  hashPassword,
  isPremium,
  normalizeCharacter,
  normalizeDisplayName,
  normalizeEmail,
  parseCookies,
  sessionCookie,
  tokenHash,
  verifyPassword,
} from './auth.js';

describe('password hashing', () => {
  it('verifies the right password and rejects others', async () => {
    const hash = await hashPassword('correct horse');
    expect(hash.startsWith('scrypt$16384$8$1$')).toBe(true);
    expect(await verifyPassword('correct horse', hash)).toBe(true);
    expect(await verifyPassword('wrong horse', hash)).toBe(false);
    expect(await hashPassword('correct horse')).not.toBe(hash); // random salt
  });
  it('dummy hash never matches and malformed hashes are rejected', async () => {
    expect(await verifyPassword('', DUMMY_HASH)).toBe(false);
    expect(await verifyPassword('x', 'bcrypt$abc')).toBe(false);
  });
});

describe('cookies and tokens', () => {
  it('parses cookies, keeping the first value', () => {
    expect(parseCookies('a=1; bp_session=abc%3D; a=2')).toEqual({ a: '1', bp_session: 'abc=' });
    expect(parseCookies(undefined)).toEqual({});
  });
  it('builds a scoped HttpOnly cookie', () => {
    const c = sessionCookie('bp_session', 'tok', { maxAgeSeconds: 60, secure: true });
    expect(c).toBe('bp_session=tok; Path=/api; Max-Age=60; HttpOnly; SameSite=Lax; Secure');
    expect(sessionCookie('x', '', { maxAgeSeconds: 0, secure: false })).not.toContain('Secure');
  });
  it('hashes tokens to hex sha256', () => {
    expect(tokenHash('abc')).toMatch(/^[0-9a-f]{64}$/);
  });
});

describe('validation', () => {
  it('normalises e-mails', () => {
    expect(normalizeEmail('  Foo@Example.COM ')).toBe('foo@example.com');
    expect(normalizeEmail('foo@bar')).toBeNull();
    expect(normalizeEmail(42)).toBeNull();
  });
  it('validates display names', () => {
    expect(normalizeDisplayName('  Knight   Do Sul ')).toBe('Knight Do Sul');
    expect(normalizeDisplayName('a')).toBeNull();
    expect(normalizeDisplayName('<script>')).toBeNull();
  });
  it('validates Tibia character names', () => {
    expect(normalizeCharacter("Bubble O'Neil")).toBe("Bubble O'Neil");
    expect(normalizeCharacter('')).toBeNull();
    expect(normalizeCharacter('Name123')).toBeUndefined();
  });
  it('premium only while premium_until is in the future', () => {
    const now = new Date('2026-11-04T12:00:00Z');
    expect(isPremium({ premium_until: null }, now)).toBe(false);
    expect(isPremium({ premium_until: new Date('2026-12-01') }, now)).toBe(true);
    expect(isPremium({ premium_until: new Date('2026-11-01') }, now)).toBe(false);
  });
});
