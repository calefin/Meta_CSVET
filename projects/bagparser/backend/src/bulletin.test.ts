import { describe, expect, it } from 'vitest';
import { bulletinDiscord, bulletinTelegram, charDayLines, summarizeKills, weeklyTelegram, type BulletinData } from './bulletin.js';
import { expForLevel } from './characters.js';
import { dromeEndsAt, localClock, rashidCity, serverDayRange, serverSaveAt, validTimeZone } from './tibiaDay.js';

const TG = { b: (s: string) => `<b>${s}</b>`, a: (t: string) => t, esc: (s: string) => s };

const base = (over: Partial<BulletinData> = {}): BulletinData => ({
  day: '2026-11-07',
  now: Date.parse('2026-11-07T10:00:00Z'),
  world: 'Antica',
  boosted: { creature: 'Oozing Corpus', boss: 'The Dread Maiden' },
  rashid: 'Edron',
  dreamScar: 'Plagueroot',
  yasir: { status: 'none', city: null, reports: 0 },
  dromeEndsAt: Date.parse('2026-11-09T09:00:00Z'),
  kills: { bosses: ['Dreadmaw', 'Groam'], top: [{ race: 'breach broods', killed: 12871 }] },
  news: [{ date: '2026-11-05', title: 'Fixes & <Changes>', url: 'https://www.tibia.com/news/?id=1', ticker: false }],
  personal: null,
  ...over,
});

describe('server day (D-75)', () => {
  it('server save is 10:00 Berlin (winter/summer) and Rashid follows the server day', () => {
    expect(serverSaveAt('2026-11-07').toISOString()).toBe('2026-11-07T09:00:00.000Z');
    expect(serverSaveAt('2026-07-07').toISOString()).toBe('2026-07-07T08:00:00.000Z');
    const r = serverDayRange('2026-11-06');
    expect(r.end.getTime() - r.start.getTime()).toBe(86_400_000);
    expect(rashidCity('2026-11-07')).toBe('Edron'); // Saturday
    expect(rashidCity('2026-11-08')).toBe('Carlin'); // Sunday
  });

  it('Tibiadrome ends at a server save in the future, at most 14 days away', () => {
    const now = Date.parse('2026-11-07T12:00:00Z');
    const end = dromeEndsAt(now);
    expect(end).toBeGreaterThan(now);
    expect(end - now).toBeLessThanOrEqual(14 * 86_400_000);
    expect(new Date(end).getUTCHours()).toBe(9);
  });

  it('local clock and time zone validation', () => {
    expect(localClock('America/Sao_Paulo', new Date('2026-11-07T10:30:00Z'))).toEqual({ date: '2026-11-07', hour: 7 });
    expect(localClock('Asia/Tokyo', new Date('2026-11-07T20:30:00Z'))).toEqual({ date: '2026-11-08', hour: 5 });
    expect(validTimeZone('Europe/Warsaw')).toBe(true);
    expect(validTimeZone('Mars/Olympus')).toBe(false);
    expect(validTimeZone('../../etc')).toBe(false);
  });
});

describe('daily bulletin (D-75)', () => {
  it('Telegram: escaped HTML with every section', () => {
    const html = bulletinTelegram(base(), 'pt', 'https://bagparser.com', '/config para mudar');
    expect(html).toContain('<b>Tibia hoje · sábado, 07/11 · Antica</b>');
    expect(html).toContain('Criatura boosted: <b>Oozing Corpus</b>');
    expect(html).toContain('Rashid: <b>Edron</b>');
    expect(html).toContain('Yasir: sem relatos');
    expect(html).toContain('Tibiadrome');
    expect(html).toContain('Bosses raros mortos: Dreadmaw, Groam');
    expect(html).toContain('breach broods (12.871)');
    expect(html).toContain('Fixes &amp; &lt;Changes&gt;');
    expect(html).not.toContain('<Changes>');
    expect(html.length).toBeLessThan(4096);
  });

  it('without a world: no Yasir or kill statistics; English', () => {
    const html = bulletinTelegram(base({ world: null, yasir: null, kills: null }), 'en', 'https://bagparser.com');
    expect(html).toContain('Tibia today · Saturday, Nov 7</b>');
    expect(html).not.toContain('Yasir');
    expect(html).not.toContain('Yesterday on');
  });

  it('Discord: embed with Markdown', () => {
    const body = bulletinDiscord(base(), 'pt', 'https://bagparser.com');
    expect(body.embeds[0].title).toContain('Tibia hoje');
    expect(body.embeds[0].description).toContain('**Oozing Corpus**');
    expect(body.embeds[0].description).toContain('](https://www.tibia.com/news/?id=1)');
  });

  it('kill statistics: bosses apart, top creatures by kills', () => {
    const r = summarizeKills(
      [
        { race: 'dragons', last_day_killed: 10 },
        { race: 'Groam', last_day_killed: 1 },
        { race: 'Abyssador', last_day_killed: 40 },
        { race: 'breach broods', last_day_killed: 99 },
        { race: 'players', last_day_killed: 5 },
        { race: 'rats', last_day_killed: 0 },
      ],
      (n) => n === 'Groam' || n === 'Abyssador',
      (n) => n === 'Groam',
    );
    expect(r.bosses).toEqual(['Groam']);
    expect(r.top.map((x) => x.race)).toEqual(['breach broods', 'dragons']);
  });
});

describe('character evolution (D-75)', () => {
  const char = {
    name: 'Knight A',
    level_from: 500,
    level_to: 503,
    exp_gain: 25e6,
    exp_now: expForLevel(503) + 1000,
    avg_day: 20e6,
    deaths: 1,
    goal_level: 520,
    goal_date: '2027-01-30',
  };

  it('level, XP of the day, 7-day pace, deaths and goal forecast', () => {
    const lines = charDayLines([char], 'pt', TG, '2026-11-07');
    expect(lines[0]).toBe('<b>Knight A</b>: level 503 (+3) · +25kk XP · média 7d 20kk/dia');
    expect(lines[1]).toContain('1 morte');
    const left = expForLevel(520) - char.exp_now;
    expect(lines[2]).toContain(`meta level 520: faltam`);
    expect(lines[2]).toContain(`~${Math.ceil(left / 20e6)} dias no ritmo atual`);
    expect(lines[2]).toContain('dentro do prazo');
  });

  it('warns when the pace misses the deadline; celebrates a reached goal; skips idle characters', () => {
    const late = charDayLines([{ ...char, goal_date: '2026-11-10' }], 'pt', TG, '2026-11-07');
    expect(late[2]).toContain('⚠️ prazo em 3 dias');
    const done = charDayLines([{ ...char, goal_level: 503 }], 'en', TG, '2026-11-07');
    expect(done.at(-1)).toContain('goal level 503 reached');
    expect(charDayLines([{ ...char, exp_gain: 0, deaths: 0, goal_level: null }], 'pt', TG, '2026-11-07')).toEqual([]);
  });

  it('goes into the bulletin before the hunts', () => {
    const html = bulletinTelegram(
      base({
        personal: {
          hunts: [{ character_name: 'Knight A', place_name: 'Cobra Bastion', duration_s: 7200, xp: 20e6, xp_h: 10e6, balance: 2e6, extra_expenses: 0 }],
          chars: [char],
        },
      }),
      'pt',
      'https://bagparser.com',
    );
    expect(html.indexOf('Evolução dos seus personagens')).toBeGreaterThan(0);
    expect(html.indexOf('Evolução')).toBeLessThan(html.indexOf('Suas hunts ontem'));
    expect(html).toContain('1 hunt · 2 h · 20kk XP');
  });

  it('weekly summary on Telegram, nothing for an empty week', () => {
    expect(weeklyTelegram({ name: 'Bob', weekKey: '2026-11-02', hunts: [], chars: [], lang: 'pt', siteUrl: 'https://bagparser.com' })).toBeNull();
    const w = weeklyTelegram({
      name: 'Bob <x>',
      weekKey: '2026-11-02',
      hunts: [{ character_name: 'A', place_name: 'B', duration_s: 3600, xp: 1e6, xp_h: 1e6, balance: 0, extra_expenses: 0 }],
      chars: [{ name: 'A', level_from: 100, level_to: 101, exp_gain: 2e6 }],
      lang: 'pt',
      siteUrl: 'https://bagparser.com',
    })!;
    expect(w).toContain('Sua semana no Tibia (02/11)');
    expect(w).toContain('Bob &lt;x&gt;');
    expect(w).toContain('<b>A</b>: +2kk XP · level 100 → 101');
  });
});
