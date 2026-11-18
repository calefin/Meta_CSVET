import { describe, expect, it } from 'vitest';
import { cleanDiscordWebhook, isSendTime, lastWeekRange, maskWebhook, weeklyDiscordMessage } from './weekly.js';

const HOOK = 'https://discord.com/api/webhooks/123456789012345678/AbCdEfGhIjKlMnOpQrStUvWxYz_0123456789-abcdef';

describe('weekly summary (D-71)', () => {
  it('accepts only Discord webhook URLs (the server posts to them)', () => {
    expect(cleanDiscordWebhook(HOOK)).toBe(HOOK);
    expect(cleanDiscordWebhook(HOOK + '?wait=true')).toBe(HOOK);
    expect(cleanDiscordWebhook(HOOK.replace('discord.com', 'discordapp.com'))).not.toBeNull();
    expect(cleanDiscordWebhook('https://evil.com/api/webhooks/123456789012345678/AbCdEfGhIjKlMnOpQrStUv')).toBeNull();
    expect(cleanDiscordWebhook('http://discord.com/api/webhooks/123456789012345678/AbCdEfGhIjKlMnOpQrStUv')).toBeNull();
    expect(cleanDiscordWebhook('https://discord.com.evil.io/api/webhooks/123456789012345678/AbCdEfGhIjKlMnOpQrStUv')).toBeNull();
    expect(cleanDiscordWebhook('https://169.254.169.254/latest')).toBeNull();
    expect(maskWebhook(HOOK)).toBe('discord.com/api/webhooks/1234…/••••');
  });

  it('sends on Monday from 10:00 Brasília, about the week before', () => {
    expect(isSendTime(new Date('2026-11-09T13:30:00Z'))).toBe(true); // Monday 10:30 BRT
    expect(isSendTime(new Date('2026-11-09T12:30:00Z'))).toBe(false); // Monday 09:30 BRT
    expect(isSendTime(new Date('2026-11-10T13:30:00Z'))).toBe(false); // Tuesday
    const r = lastWeekRange(new Date('2026-11-09T13:30:00Z'));
    expect(r.key).toBe('2026-11-02');
    expect(r.start.toISOString()).toBe('2026-11-02T03:00:00.000Z');
    expect(r.end.toISOString()).toBe('2026-11-09T03:00:00.000Z');
  });

  it('builds the Discord message, or nothing for an empty week', () => {
    expect(weeklyDiscordMessage({ name: 'Bob', weekKey: '2026-11-02', hunts: [], chars: [], lang: 'pt', siteUrl: 'https://bagparser.com' })).toBeNull();
    const m = weeklyDiscordMessage({
      name: 'Bob',
      weekKey: '2026-11-02',
      hunts: [
        { character_name: 'Knight A', place_name: 'Cobra Bastion', duration_s: 7200, xp: 20e6, xp_h: 10e6, balance: 2e6, extra_expenses: 5e5 },
        { character_name: 'Knight A', place_name: 'Issavi Sewers', duration_s: 3600, xp: 4e6, xp_h: 4e6, balance: -1e5, extra_expenses: 0 },
      ],
      chars: [{ name: 'Knight A', level_from: 500, level_to: 503, exp_gain: 25e6 }],
      lang: 'pt-BR',
      siteUrl: 'https://bagparser.com',
    })!;
    const f = m.embeds[0].fields;
    expect(f.find((x) => x.name === 'Hunts')?.value).toBe('2 · 3 h');
    expect(f.find((x) => x.name === 'XP')?.value).toBe('24kk');
    expect(f.find((x) => x.name === 'Lucro')?.value).toBe('1,4kk gp');
    expect(f.find((x) => x.name === 'Melhor hunt')?.value).toContain('Cobra Bastion');
    expect(f.find((x) => x.name === 'Knight A')?.value).toBe('25kk XP · level 500 → 503');
  });
});
