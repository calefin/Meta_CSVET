/** Shared helpers for the Hunt Analyser pages (D-49). */
import type { HuntData } from '../../core/huntLog';
import type { HuntRow, Vocation } from '../../api';
import { useI18n } from '../../i18n';

export const VOCATIONS: Vocation[] = ['knight', 'paladin', 'sorcerer', 'druid', 'monk'];
export const VOC_ICON: Record<Vocation, string> = { knight: '🗡️', paladin: '🏹', sorcerer: '🔥', druid: '❄️', monk: '👊' };

/** "1h 05min" */
export function fmtDuration(seconds: number): string {
  const m = Math.round(seconds / 60);
  const h = Math.floor(m / 60);
  return h ? `${h}h ${String(m % 60).padStart(2, '0')}min` : `${m}min`;
}

/** Client time "2026-11-05T17:36:27" → "05/11 17:36" in the user's locale (no time zone shift) */
export function fmtClientDate(v: string | null, locale: string, withYear = false): string {
  if (!v) return '—';
  const m = v.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
  if (!m) return new Date(v).toLocaleString(locale, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]));
  return d.toLocaleString(locale, {
    timeZone: 'UTC',
    day: '2-digit',
    month: '2-digit',
    ...(withYear ? { year: 'numeric' } : {}),
    hour: '2-digit',
    minute: '2-digit',
  });
}

/** Timestamp for charts: client time read as UTC (keeps the order and the clock the player saw) */
export const huntTime = (h: Pick<HuntRow, 'started_at' | 'created_at'>): number =>
  h.started_at ? Date.parse(h.started_at + 'Z') : Date.parse(h.created_at);

/** Signed gold with k/kk like players write it: 1.2kk, -350k */
export function gp(v: number, locale: string): string {
  const a = Math.abs(v);
  const sign = v < 0 ? '-' : '';
  const f = (x: number) => x.toLocaleString(locale, { maximumFractionDigits: x < 10 ? 2 : 1 });
  if (a >= 1_000_000_000) return `${sign}${f(a / 1_000_000_000)}kkk`;
  if (a >= 1_000_000) return `${sign}${f(a / 1_000_000)}kk`;
  if (a >= 10_000) return `${sign}${f(a / 1000)}k`;
  return v.toLocaleString(locale);
}

export function Delta({ a, b, money = false, invert = false }: { a: number; b: number; money?: boolean; invert?: boolean }) {
  const { n, locale } = useI18n();
  if (!a && !b) return null;
  const d = b - a;
  if (!d) return <span className="delta">=</span>;
  const good = invert ? d < 0 : d > 0;
  const pct = a ? Math.round((d / Math.abs(a)) * 100) : null;
  return (
    <span className={`delta ${good ? 'pos' : 'neg'}`}>
      {d > 0 ? '▲' : '▼'} {money ? gp(Math.abs(d), locale) : n(Math.abs(d))}
      {pct != null && Math.abs(pct) < 10000 && ` (${pct > 0 ? '+' : ''}${pct}%)`}
    </span>
  );
}

// ---------------------------------------------------------------------------
// Guests: hunts kept in this browser until they sign in (and import them)
// ---------------------------------------------------------------------------

export interface LocalHunt {
  id: string;
  savedAt: string;
  character: string;
  place: string;
  hunt: HuntData;
}

const LOCAL_KEY = 'bagparser_hunts_v2';
const LEGACY_KEY = 'bagparser_hunt_tracker_sessions';

export function loadLocalHunts(): LocalHunt[] {
  try {
    return JSON.parse(localStorage.getItem(LOCAL_KEY) ?? '[]') as LocalHunt[];
  } catch {
    return [];
  }
}

export function saveLocalHunts(list: LocalHunt[]): void {
  try {
    localStorage.setItem(LOCAL_KEY, JSON.stringify(list.slice(0, 200)));
  } catch {
    /* quota */
  }
}

/** Sessions saved by the first tracker version (raw log text) — re-parsed on import */
export function legacyHuntLogs(): { character: string; place: string; text: string }[] {
  try {
    const raw = JSON.parse(localStorage.getItem(LEGACY_KEY) ?? '[]') as { characterName?: string; huntingSpot?: string; logText?: string }[];
    return raw.filter((r) => r.logText).map((r) => ({ character: r.characterName ?? '', place: r.huntingSpot ?? '', text: r.logText! }));
  } catch {
    return [];
  }
}

export function clearLegacyHunts(): void {
  try {
    localStorage.removeItem(LEGACY_KEY);
  } catch {
    /* ignore */
  }
}

/** Averages used by insights and the "vs your average" line */
export function averages(rows: Pick<HuntRow, 'xp_h' | 'balance_h' | 'raw_xp_h' | 'duration_s'>[]) {
  if (!rows.length) return null;
  // Weighted by time: a 3 h hunt counts more than a 10 min test
  const w = rows.reduce((s, r) => s + r.duration_s, 0) || 1;
  const avg = (k: 'xp_h' | 'balance_h' | 'raw_xp_h') => Math.round(rows.reduce((s, r) => s + r[k] * r.duration_s, 0) / w);
  return { xp_h: avg('xp_h'), raw_xp_h: avg('raw_xp_h'), balance_h: avg('balance_h'), count: rows.length, hours: w / 3600 };
}

export function VocationSelect({ value, onChange, allLabel }: { value: string; onChange: (v: string) => void; allLabel?: string }) {
  const { t } = useI18n();
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)} aria-label={t('vocation')}>
      <option value="">{allLabel ?? t('ha_all_vocations')}</option>
      {VOCATIONS.map((v) => (
        <option key={v} value={v}>
          {VOC_ICON[v]} {t(`voc_${v}`)}
        </option>
      ))}
    </select>
  );
}
