import { describe, expect, it } from 'vitest';
import { bulletinTelegram, guildLines, vocationIcon, type BulletinData } from './bulletin.js';
import { rankGuild } from './guildRanking.js';
import { bossReadyText, charEventText, cleanTimers } from './personalAlerts.js';
import { monthRange, previousMonth, retroTelegram } from './retro.js';
import { parseCommand, parseYasir } from './telegram.js';

const TG = { b: (s: string) => `<b>${s}</b>`, a: (t: string) => t, esc: (s: string) => s };

describe('guild ranking (D-76)', () => {
  const y = [
    { name: 'Armlock', level: 516, experience: 3_000_000_000, vocation: 'Elite Knight' },
    { name: 'Mongil', level: 641, experience: 4_300_000_000, vocation: 'Exalted Monk' },
    { name: 'Low', level: 300, experience: null, vocation: 'Druid' },
    { name: 'Dead', level: 351, experience: 900_000_000, vocation: 'Druid' },
  ];
  const t = [
    { name: 'Armlock', level: 518, experience: 3_029_950_000, vocation: 'Elite Knight' },
    { name: 'Mongil', level: 642, experience: 4_325_390_000, vocation: 'Exalted Monk' },
    { name: 'Low', level: 301, experience: null, vocation: 'Druid' },
    { name: 'Dead', level: 350, experience: 898_580_000, vocation: 'Druid' },
    { name: 'Newbie', level: 100, experience: null, vocation: 'Paladin' },
  ];

  it('orders by XP gained, levels only when XP is unknown, separates who lost XP', () => {
    const r = rankGuild('Furia', t, y);
    expect(r.first).toBe(false);
    expect(r.top.map((x) => x.name)).toEqual(['Armlock', 'Mongil', 'Low']);
    expect(r.top[0].gain).toBe(29_950_000);
    expect(r.top[2].gain).toBeNull();
    expect(r.fell.map((x) => x.name)).toEqual(['Dead']);
  });

  it('first day: nothing to compare yet', () => {
    expect(rankGuild('Furia', t, []).first).toBe(true);
    expect(guildLines(rankGuild('Furia', t, []), 'pt', TG)[0]).toContain('começa amanhã');
  });

  it('renders medals, vocation icons and the losers', () => {
    const lines = guildLines(rankGuild('Furia', t, y), 'pt', TG);
    expect(lines[0]).toContain('Top XP da guild Furia (ontem)');
    expect(lines[1]).toBe('🥇 🛡️ <b>Armlock</b> (Lv 516→518) +30kk');
    expect(lines[2]).toContain('🥈 🥋 <b>Mongil</b>');
    expect(lines.at(-1)).toContain('Perderam XP: Dead (Lv 351→350)');
    expect(vocationIcon('Royal Paladin')).toBe('🏹');
    expect(vocationIcon(null)).toBe('⚪');
  });
});

describe('instant alerts (D-76)', () => {
  it('level, death and goal texts (Telegram HTML and Discord Markdown)', () => {
    expect(charEventText({ kind: 'level', character: 'Knight <A>', from: 503, to: 504 }, 'pt', 'https://bagparser.com')).toBe('🎉 <b>Knight &lt;A&gt;</b> subiu para o level <b>504</b>!');
    expect(charEventText({ kind: 'level', character: 'A', from: 500, to: 503 }, 'en', 'x')).toContain('gained 3 levels: 500 → <b>503</b>');
    expect(charEventText({ kind: 'death', character: 'A', level: 350, reason: 'Killed at Level 350 by a dragon lord.' }, 'pt', 'x', true)).toBe(
      '☠️ **A** morreu no level 350: *Killed at Level 350 by a dragon lord.*',
    );
    expect(charEventText({ kind: 'goal', character: 'A', goal: 550 }, 'pt', 'https://bagparser.com')).toContain('https://bagparser.com/characters');
    expect(bossReadyText([{ character: 'A', boss: 'Grand Master Oberon' }], 'pt')).toContain('• <b>Grand Master Oberon</b> (A)');
  });

  it('boss timers from the browser: recent, valid and one per character/boss', () => {
    const now = Date.parse('2026-11-07T12:00:00Z');
    const list = cleanTimers(
      [
        { character: 'A', boss: 'Oberon', killed_at: '2026-11-07T10:00:00Z', cooldown_hours: 20 },
        { character: 'A', boss: 'oberon', killed_at: '2026-11-07T11:00:00Z' },
        { character: 'A', boss: 'Old', killed_at: '2026-10-01T10:00:00Z' },
        { character: 'A', boss: 'Future', killed_at: '2026-11-08T10:00:00Z' },
        { character: '', boss: 'X', killed_at: '2026-11-07T10:00:00Z' },
        { character: 'B', boss: 'Y', killed_at: 'nope' },
        { character: 'B', boss: 'Z', killed_at: '2026-11-07T10:00:00Z', cooldown_hours: 9999 },
        'junk',
      ],
      now,
    );
    expect(list.map((x) => `${x.character}/${x.boss}`)).toEqual(['A/oberon', 'B/Z']);
    expect(list[1].cooldown_hours).toBe(20);
    expect(cleanTimers('x')).toEqual([]);
  });
});

describe('bot commands (D-76)', () => {
  it('/yasir reports', () => {
    expect(parseYasir(['Liberty', 'Bay', 'Antica'])).toEqual({ present: true, city: 'Liberty Bay', rest: ['Antica'] });
    expect(parseYasir(['carlin'])).toEqual({ present: true, city: 'Carlin', rest: [] });
    expect(parseYasir(['não'])).toEqual({ present: false, city: null, rest: [] });
    expect(parseYasir(['outra', 'Belobra'])?.city).toBe('other');
    expect(parseYasir(['Antica'])).toBeNull();
    expect(parseYasir([])).toBeNull();
  });

  it('new commands and aliases', () => {
    expect(parseCommand('/guilda Furia Rising', 'b')?.cmd).toBe('guild');
    expect(parseCommand('/donate', 'b')?.cmd).toBe('apoiar');
    expect(parseCommand('/alerts off', 'b')?.cmd).toBe('avisos');
    expect(parseCommand('/paysupport', 'b')?.cmd).toBe('paysupport');
  });
});

describe('monthly retrospective (D-76)', () => {
  it('months', () => {
    expect(previousMonth('2026-01')).toBe('2025-12');
    expect(previousMonth('2026-11')).toBe('2026-10');
    const r = monthRange('2026-12');
    expect(r.start.toISOString()).toBe('2026-12-01T03:00:00.000Z');
    expect(r.end.toISOString()).toBe('2027-01-01T03:00:00.000Z');
  });

  it('message, nothing for an empty month', () => {
    const empty = { month: '2026-10', hunts: 0, hours: 0, xp: 0, profit: 0, best: null, favorite: null, chars: [], deaths: 0 };
    expect(retroTelegram(empty, 'pt', 'https://bagparser.com')).toBeNull();
    const m = retroTelegram(
      {
        ...empty,
        hunts: 12,
        hours: 30.5,
        xp: 420e6,
        profit: 35e6,
        best: { place: 'Cobra Bastion', character: 'A', xp_h: 12e6 },
        favorite: { place: 'Issavi Sewers', hunts: 5 },
        chars: [{ name: 'A', level_from: 500, level_to: 512, exp_gain: 400e6 }],
        deaths: 2,
      },
      'pt',
      'https://bagparser.com',
    )!;
    expect(m).toContain('Seu mês no Tibia: outubro de 2026');
    expect(m).toContain('12 hunts · 30,5 h · 420kk XP');
    expect(m).toContain('Spawn favorito: Issavi Sewers (5×)');
    expect(m).toContain('level 500 → 512');
    expect(m).toContain('2 mortes');
    expect(m).toContain('https://bagparser.com/retro?month=2026-10');
  });
});

describe('bulletin extras (D-76)', () => {
  const base: BulletinData = {
    day: '2026-11-07',
    now: Date.parse('2026-11-07T10:00:00Z'),
    world: null,
    boosted: { creature: 'Oozing Corpus', boss: null },
    rashid: 'Edron',
    dreamScar: 'Plagueroot',
    yasir: null,
    dromeEndsAt: 0,
    kills: null,
    news: [],
    personal: { hunts: [], chars: [], soulMissing: ['Knight A', 'Druid B'] },
    supporter: 'Fulano <3',
  };
  it('soul core of the boosted creature and the supporter of the day', () => {
    const html = bulletinTelegram(base, 'pt', 'https://bagparser.com');
    expect(html).toContain('O boosted de hoje (<b>Oozing Corpus</b>) ainda falta no soul core de: Knight A, Druid B');
    expect(html).toContain('💚 Apoio de hoje: <b>Fulano &lt;3</b>');
  });
});
