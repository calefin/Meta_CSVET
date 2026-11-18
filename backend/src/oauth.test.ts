import { createHash } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import { buildAuthUrl, displayNameFrom, parseProfile, pkcePair, providersFromEnv, safeNext } from './oauth.js';

describe('oauth helpers (D-39)', () => {
  it('enables only providers with both id and secret', () => {
    const p = providersFromEnv({ GOOGLE_CLIENT_ID: 'gid', GOOGLE_CLIENT_SECRET: 'gs', DISCORD_CLIENT_ID: 'did' });
    expect([...p.keys()]).toEqual(['google']);
    expect(p.get('google')?.clientId).toBe('gid');
  });

  it('builds a PKCE S256 authorization URL', () => {
    const cfg = providersFromEnv({ DISCORD_CLIENT_ID: 'did', DISCORD_CLIENT_SECRET: 'ds' }).get('discord')!;
    const { verifier, challenge } = pkcePair();
    expect(challenge).toBe(createHash('sha256').update(verifier).digest('base64url'));
    const url = new URL(buildAuthUrl(cfg, { state: 'st', challenge, redirectUri: 'https://x.test/cb' }));
    expect(url.origin + url.pathname).toBe('https://discord.com/oauth2/authorize');
    expect(Object.fromEntries(url.searchParams)).toMatchObject({
      response_type: 'code',
      client_id: 'did',
      redirect_uri: 'https://x.test/cb',
      scope: 'identify email',
      state: 'st',
      code_challenge_method: 'S256',
    });
  });

  it('accepts only same-site paths as next', () => {
    expect(safeNext('/characters?x=1')).toBe('/characters?x=1');
    for (const bad of ['//evil.com', '/\\evil.com', 'https://evil.com', '/api/auth/me', '/a b', '', 42, undefined]) {
      expect(safeNext(bad, '/')).toBe('/');
    }
  });

  it('parses Google and Discord profiles', () => {
    expect(parseProfile('google', { sub: '123', email: 'A@B.com', email_verified: true, name: 'Ana' })).toEqual({
      subject: '123',
      email: 'a@b.com',
      emailVerified: true,
      name: 'Ana',
    });
    expect(parseProfile('discord', { id: '80351110224678912', email: 'x@y.io', verified: false, username: 'nelly' }))
      .toEqual({ subject: '80351110224678912', email: 'x@y.io', emailVerified: false, name: 'nelly' });
    expect(parseProfile('discord', { id: 'abc' })).toBeNull();
    expect(parseProfile('google', { email: 'a@b.com' })).toBeNull();
  });

  it('derives a valid display name', () => {
    expect(displayNameFrom({ name: 'Zé 🐉 Knight', email: null })).toBe('Zé Knight');
    expect(displayNameFrom({ name: '🐉', email: 'druid.master@x.com' })).toBe('druid.master');
    expect(displayNameFrom({ name: null, email: null })).toBe('Player');
  });
});

describe('oauthDecision (D-65)', () => {
  it('creates, links, or refuses depending on the e-mail and the existing account', async () => {
    const { oauthDecision } = await import('./oauth.js');
    expect(oauthDecision({ email: null, emailVerified: false }, null)).toBe('email_unverified');
    expect(oauthDecision({ email: 'a@x.com', emailVerified: false }, null)).toBe('email_unverified');
    expect(oauthDecision({ email: 'a@x.com', emailVerified: true }, null)).toBe('create');
    // password account that confirmed its e-mail: same person → link
    expect(oauthDecision({ email: 'a@x.com', emailVerified: true }, { id: 1, emailVerified: true })).toBe('link_existing');
    // unconfirmed account with that e-mail: could be someone else's pre-registration → refuse
    expect(oauthDecision({ email: 'a@x.com', emailVerified: true }, { id: 1, emailVerified: false })).toBe('email_exists');
  });
});
