import { describe, expect, it } from 'vitest';
import { filterHunts, groupHunts, periodRange, weekStart, type ReportRow } from './huntReport';

const row = (id: string, day: string, extra: Partial<ReportRow> = {}): ReportRow => ({
  public_id: id,
  started_at: `${day}T10:00:00`,
  created_at: `${day}T13:00:00Z`,
  duration_s: 3600,
  xp: 1_000_000,
  raw_xp: 500_000,
  xp_h: 1_000_000,
  raw_xp_h: 500_000,
  balance: 100_000,
  balance_h: 100_000,
  kind: 'solo',
  vocation: 'knight',
  level: 300,
  character_name: 'Knight A',
  place_name: 'Issavi Sewers',
  place_slug: 'issavi-sewers',
  top_monster: 'Rotworm',
  ...extra,
});

const now = new Date(2026, 10, 7, 12); // Saturday 2026-11-07

describe('hunt report', () => {
  it('computes period shortcuts', () => {
    expect(weekStart('2026-11-07')).toBe('2026-11-02');
    expect(periodRange({ period: 'today' }, now)).toEqual({ from: '2026-11-07', to: '2026-11-07' });
    expect(periodRange({ period: 'week' }, now).from).toBe('2026-11-02');
    expect(periodRange({ period: 'month' }, now).from).toBe('2026-11-01');
    expect(periodRange({ period: '7' }, now).from).toBe('2026-11-01');
    expect(periodRange({ period: 'custom', from: '2026-10-01', to: '2026-10-31' }, now)).toEqual({ from: '2026-10-01', to: '2026-10-31' });
  });

  it('filters by period, party size, level and text', () => {
    const rows = [
      row('a', '2026-11-07'),
      row('b', '2026-11-03', { kind: 'party', party_size: 4, level: 500 }),
      row('c', '2026-10-20', { kind: 'party', party_size: 5, place_name: 'Cobra Bastion', place_slug: 'cobra-bastion' }),
    ];
    expect(filterHunts(rows, { period: 'week' }, now).map((r) => r.public_id)).toEqual(['a', 'b']);
    expect(filterHunts(rows, { period: 'all', size: '4' }, now).map((r) => r.public_id)).toEqual(['b']);
    expect(filterHunts(rows, { period: 'all', size: '5+' }, now).map((r) => r.public_id)).toEqual(['c']);
    expect(filterHunts(rows, { period: 'all', size: '1' }, now).map((r) => r.public_id)).toEqual(['a']);
    expect(filterHunts(rows, { period: 'all', minLevel: 400 }, now).map((r) => r.public_id)).toEqual(['b']);
    expect(filterHunts(rows, { period: 'all', q: 'cobra' }, now).map((r) => r.public_id)).toEqual(['c']);
  });

  it('groups with time-weighted averages', () => {
    const rows = [
      row('a', '2026-11-07', { duration_s: 3 * 3600, xp_h: 2_000_000 }),
      row('b', '2026-11-03', { duration_s: 3600, xp_h: 1_000_000 }),
      row('c', '2026-10-20', { place_name: 'Cobra Bastion', place_slug: 'cobra-bastion', xp_h: 4_000_000, extra_expenses: 50_000 }),
    ];
    const byPlace = groupHunts(rows, 'place');
    expect(byPlace[0].label).toBe('Cobra Bastion');
    expect(byPlace[0].profit).toBe(50_000);
    const issavi = byPlace.find((g) => g.slug === 'issavi-sewers')!;
    expect(issavi.xp_h).toBe(1_750_000); // (3 h × 2kk + 1 h × 1kk) / 4 h
    expect(issavi.hours).toBe(4);
    expect(issavi.best.id).toBe('a');
    expect(groupHunts(rows, 'week').map((g) => g.label)).toEqual(['2026-11-02', '2026-10-19']);
    expect(groupHunts(rows, 'month').map((g) => g.label)).toEqual(['2026-11', '2026-10']);
  });
});

describe('CSV export (D-62)', () => {
  it('quotes cells with separators, quotes and line breaks', async () => {
    const { toCsv, csvSeparator } = await import('./huntReport');
    expect(csvSeparator('pt-BR')).toBe(';');
    expect(csvSeparator('en')).toBe(',');
    const csv = toCsv([['a', 'b;c', 'say "hi"', 'x\ny', 3, null]], ';');
    expect(csv).toBe('\uFEFFa;"b;c";"say ""hi""";"x\ny";3;\r\n');
  });
  it('exports hunts with one row each', async () => {
    const { huntsCsv } = await import('./huntReport');
    const csv = huntsCsv([row('a', '2026-11-07', { title: 'Wardragons; north' })], ';', 'https://bagparser.com');
    const lines = csv.trim().split('\r\n');
    expect(lines).toHaveLength(2);
    expect(lines[1]).toContain('"Wardragons; north"');
    expect(lines[1]).toContain('https://bagparser.com/hunt-analyser/h/a');
  });
});

describe('CSV formula injection (D-64)', () => {
  it('neutralises cells that a spreadsheet would run as a formula', async () => {
    const { toCsv } = await import('./huntReport');
    expect(toCsv([['=HYPERLINK("http://x")', '+1+1', '@SUM(A1)', '-9,90', -5, 'ok']], ';')).toBe('\uFEFF"\'=HYPERLINK(""http://x"")";\'+1+1;\'@SUM(A1);-9,90;-5;ok\r\n');
  });
});
