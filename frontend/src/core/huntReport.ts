/**
 * Hunt report (D-61): filters (period shortcuts, vocation, solo/party, party size, level, text) and grouping
 * (place, character, week, month, party size, vocation) over my saved hunts. Dates use the client time the
 * analyser printed ("YYYY-MM-DDTHH:MM:SS", no time zone), compared as text so nothing shifts between zones.
 */

export interface ReportRow {
  public_id: string;
  started_at: string | null;
  created_at: string;
  duration_s: number;
  xp: number;
  raw_xp: number;
  xp_h: number;
  raw_xp_h: number;
  balance: number;
  balance_h: number;
  extra_expenses?: number;
  kind: 'solo' | 'party';
  party_size?: number;
  vocation: string | null;
  level: number | null;
  character_name: string | null;
  place_name: string | null;
  place_slug: string | null;
  title?: string | null;
  top_monster: string | null;
}

export type Period = 'today' | 'week' | 'month' | '7' | '30' | '90' | 'all' | 'custom';
export type GroupBy = 'place' | 'character' | 'week' | 'month' | 'size' | 'vocation';

export interface ReportFilters {
  period: Period;
  from?: string;
  to?: string;
  character?: string;
  vocation?: string;
  kind?: '' | 'solo' | 'party';
  size?: '' | '1' | '2' | '3' | '4' | '5+';
  minLevel?: number | null;
  maxLevel?: number | null;
  q?: string;
}

const pad = (n: number) => String(n).padStart(2, '0');
export const ymd = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const dayOf = (r: Pick<ReportRow, 'started_at' | 'created_at'>) => (r.started_at ?? r.created_at).slice(0, 10);

/** Monday of the week of a YYYY-MM-DD date */
export function weekStart(day: string): string {
  const [y, m, d] = day.split('-').map(Number);
  const dt = new Date(y, m - 1, d);
  dt.setDate(dt.getDate() - ((dt.getDay() + 6) % 7));
  return ymd(dt);
}

/** First day (inclusive) and last day (inclusive) for a period, null = open */
export function periodRange(f: Pick<ReportFilters, 'period' | 'from' | 'to'>, now = new Date()): { from: string | null; to: string | null } {
  const today = ymd(now);
  const back = (days: number) => {
    const d = new Date(now);
    d.setDate(d.getDate() - days + 1);
    return ymd(d);
  };
  switch (f.period) {
    case 'today':
      return { from: today, to: today };
    case 'week':
      return { from: weekStart(today), to: null };
    case 'month':
      return { from: `${today.slice(0, 7)}-01`, to: null };
    case '7':
    case '30':
    case '90':
      return { from: back(Number(f.period)), to: null };
    case 'custom':
      return { from: f.from || null, to: f.to || null };
    default:
      return { from: null, to: null };
  }
}

export const partySize = (r: Pick<ReportRow, 'kind' | 'party_size'>) => (r.kind === 'party' ? Math.max(2, r.party_size ?? 2) : 1);

export function filterHunts<T extends ReportRow>(rows: T[], f: ReportFilters, now = new Date()): T[] {
  const { from, to } = periodRange(f, now);
  const q = (f.q ?? '').trim().toLowerCase();
  return rows.filter((r) => {
    const day = dayOf(r);
    if (from && day < from) return false;
    if (to && day > to) return false;
    if (f.character && (r.character_name ?? '—') !== f.character) return false;
    if (f.vocation && r.vocation !== f.vocation) return false;
    if (f.kind && r.kind !== f.kind) return false;
    if (f.size) {
      const s = partySize(r);
      if (f.size === '5+' ? s < 5 : s !== Number(f.size)) return false;
    }
    if (f.minLevel && (r.level ?? 0) < f.minLevel) return false;
    if (f.maxLevel && (r.level ?? Infinity) > f.maxLevel) return false;
    if (q && ![r.title, r.place_name, r.top_monster, r.character_name].some((v) => v?.toLowerCase().includes(q))) return false;
    return true;
  });
}

export interface ReportGroup {
  key: string;
  label: string;
  slug: string | null;
  hunts: number;
  hours: number;
  xp: number;
  profit: number;
  /** time-weighted averages */
  xp_h: number;
  raw_xp_h: number;
  balance_h: number;
  best: { id: string; xp_h: number; balance_h: number };
}

function groupKey(r: ReportRow, by: GroupBy): { key: string; label: string; slug: string | null } {
  switch (by) {
    case 'place': {
      const label = r.place_name ?? r.top_monster ?? '—';
      return { key: (r.place_slug ?? label).toLowerCase(), label, slug: r.place_slug };
    }
    case 'character':
      return { key: (r.character_name ?? '—').toLowerCase(), label: r.character_name ?? '—', slug: null };
    case 'week': {
      const w = weekStart(dayOf(r));
      return { key: w, label: w, slug: null };
    }
    case 'month': {
      const m = dayOf(r).slice(0, 7);
      return { key: m, label: m, slug: null };
    }
    case 'size': {
      const s = partySize(r);
      return { key: String(s >= 5 ? 5 : s), label: s >= 5 ? '5+' : String(s), slug: null };
    }
    case 'vocation':
      return { key: r.vocation ?? '—', label: r.vocation ?? '—', slug: null };
  }
}

export function groupHunts(rows: ReportRow[], by: GroupBy): ReportGroup[] {
  const map = new Map<string, { label: string; slug: string | null; list: ReportRow[] }>();
  for (const r of rows) {
    const g = groupKey(r, by);
    const cur = map.get(g.key) ?? { label: g.label, slug: g.slug, list: [] };
    cur.list.push(r);
    map.set(g.key, cur);
  }
  const out: ReportGroup[] = [...map].map(([key, g]) => {
    const secs = g.list.reduce((s, r) => s + r.duration_s, 0) || 1;
    const w = (k: 'xp_h' | 'raw_xp_h' | 'balance_h') => Math.round(g.list.reduce((s, r) => s + r[k] * r.duration_s, 0) / secs);
    const best = g.list.reduce((b, r) => (r.xp_h > b.xp_h ? r : b), g.list[0]);
    return {
      key,
      label: g.label,
      slug: g.slug,
      hunts: g.list.length,
      hours: secs / 3600,
      xp: g.list.reduce((s, r) => s + r.xp, 0),
      profit: g.list.reduce((s, r) => s + r.balance - (r.extra_expenses ?? 0), 0),
      xp_h: w('xp_h'),
      raw_xp_h: w('raw_xp_h'),
      balance_h: w('balance_h'),
      best: { id: best.public_id, xp_h: best.xp_h, balance_h: best.balance_h },
    };
  });
  // Time groups in time order (newest first); the others by XP/h
  return by === 'week' || by === 'month' || by === 'size' ? out.sort((a, b) => (by === 'size' ? a.key.localeCompare(b.key) : b.key.localeCompare(a.key))) : out.sort((a, b) => b.xp_h - a.xp_h);
}

// ---------------------------------------------------------------------------
// CSV export (D-62): ";" and a BOM for spreadsheets in languages with decimal comma (pt, es, pl), "," for en
// ---------------------------------------------------------------------------

export const csvSeparator = (locale: string) => (/^(pt|es|pl|de|fr|it)/.test(locale) ? ';' : ',');

export function toCsv(rows: (string | number | null | undefined)[][], sep = ','): string {
  const cell = (v: string | number | null | undefined) => {
    if (v == null) return '';
    let s = String(v);
    // D-64: a text like "=HYPERLINK(...)" would run as a formula in Excel / Sheets
    if (typeof v === 'string' && /^[=+\-@\t\r]/.test(s) && !/^-?\d+([.,]\d+)?$/.test(s)) s = `'${s}`;
    return /["\n\r]/.test(s) || s.includes(sep) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  return '\uFEFF' + rows.map((r) => r.map(cell).join(sep)).join('\r\n') + '\r\n';
}

export function huntsCsv(rows: ReportRow[], sep: string, origin = ''): string {
  return toCsv(
    [
      ['date', 'title', 'place', 'character', 'level', 'vocation', 'kind', 'party_size', 'minutes', 'xp', 'raw_xp', 'xp_h', 'raw_xp_h', 'balance', 'balance_h', 'extra_expenses', 'link'],
      ...rows.map((r) => [
        (r.started_at ?? r.created_at).slice(0, 16).replace('T', ' '),
        r.title ?? '',
        r.place_name ?? r.top_monster ?? '',
        r.character_name ?? '',
        r.level,
        r.vocation ?? '',
        r.kind,
        partySize(r),
        Math.round(r.duration_s / 60),
        r.xp,
        r.raw_xp,
        r.xp_h,
        r.raw_xp_h,
        r.balance,
        r.balance_h,
        r.extra_expenses ?? 0,
        `${origin}/hunt-analyser/h/${r.public_id}`,
      ]),
    ],
    sep,
  );
}

export function groupsCsv(groups: ReportGroup[], by: GroupBy, sep: string): string {
  return toCsv(
    [
      [by, 'hunts', 'hours', 'xp_h', 'raw_xp_h', 'balance_h', 'profit', 'xp', 'best_xp_h'],
      ...groups.map((g) => [g.label, g.hunts, Math.round(g.hours * 10) / 10, g.xp_h, g.raw_xp_h, g.balance_h, g.profit, g.xp, g.best.xp_h]),
    ],
    sep,
  );
}

/** Starts a file download in the browser */
export function downloadText(name: string, text: string, type = 'text/csv;charset=utf-8'): void {
  const url = URL.createObjectURL(new Blob([text], { type }));
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
