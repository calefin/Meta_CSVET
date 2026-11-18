import { describe, expect, it } from 'vitest';
import { baseVocation, huntFingerprint, newPublicId, validateHunt } from './hunts.js';

const base = {
  kind: 'solo',
  started_at: '2026-11-05T17:36:27',
  ended_at: '2026-11-05T17:41:23',
  duration_s: 296,
  raw_xp: 19001,
  xp: 28498,
  raw_xp_h: 231216,
  xp_h: 346782,
  loot: 3735,
  supplies: 3792,
  balance: -57,
  balance_h: -693,
  damage: 24774,
  damage_h: 339337,
  healing: 1665,
  healing_h: 22806,
  monsters: [{ name: 'Broken Shaper', count: 7 }],
  items: [{ name: 'gold coin', count: 775 }],
};

describe('validateHunt', () => {
  it('accepts a parsed session and normalises it', () => {
    const h = validateHunt({ ...base, vocation: 'Elite Knight', visibility: 'public', setup: { prey: 'XP boost', bad_key: 1, 'x y': 'no' } });
    expect('error' in h).toBe(false);
    if ('error' in h) return;
    expect(h.vocation).toBe('knight');
    expect(h.visibility).toBe('public');
    expect(h.monsters[0].name).toBe('broken shaper');
    expect(h.setup).toEqual({ prey: 'XP boost' });
  });

  it('rejects broken input', () => {
    expect(validateHunt({ ...base, kind: 'x' })).toEqual({ error: 'invalid_hunt', field: 'kind' });
    expect(validateHunt({ ...base, duration_s: 5 })).toEqual({ error: 'invalid_hunt', field: 'duration_s' });
    expect(validateHunt({ ...base, xp: -1 })).toEqual({ error: 'invalid_hunt', field: 'xp' });
    expect(validateHunt({ ...base, items: [{ name: '', count: 1 }] })).toEqual({ error: 'invalid_hunt', field: 'lists' });
    expect(validateHunt({ ...base, xp: 0, loot: 0, supplies: 0, damage: 0 })).toEqual({ error: 'empty_hunt' });
    expect(validateHunt({ ...base, server_log: { big: 'x'.repeat(300_000) } })).toEqual({ error: 'invalid_hunt', field: 'server_log' });
  });

  it('drops a bad date instead of failing', () => {
    const h = validateHunt({ ...base, started_at: 'yesterday' });
    expect('error' in h ? null : h.started_at).toBeNull();
  });
});

describe('helpers', () => {
  it('maps vocations', () => {
    expect(baseVocation('Royal Paladin')).toBe('paladin');
    expect(baseVocation('Exalted Monk')).toBe('monk');
    expect(baseVocation('None')).toBeNull();
  });

  it('gives the same fingerprint for the same session', () => {
    const a = huntFingerprint(1, { ...base, character_name: 'Calefi' } as never);
    expect(huntFingerprint(1, { ...base, character_name: 'calefi', duration_s: 307 } as never)).toBe(a);
    expect(huntFingerprint(2, { ...base, character_name: 'Calefi' } as never)).not.toBe(a);
  });

  it('makes url-safe ids', () => {
    expect(newPublicId()).toMatch(/^[A-Za-z0-9_-]{10}$/);
  });
});
