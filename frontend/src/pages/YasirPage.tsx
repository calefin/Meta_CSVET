/** Public Yasir sightings: today per world, history and patterns (D-40). */
import { useEffect, useState } from 'react';
import { ApiError, daily, YASIR_CITIES, type YasirCity, type YasirOutcome, type YasirPublic, type YasirStatus } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

/** World the visitor picked (shared with the home page) */
export const WORLD_KEY = 'bagparser_world';
export function readSavedWorld(): string {
  try {
    return localStorage.getItem(WORLD_KEY) ?? '';
  } catch {
    return '';
  }
}
export function saveWorld(w: string): void {
  try {
    localStorage.setItem(WORLD_KEY, w);
  } catch {
    /* private mode */
  }
}

/**
 * Report form (D-61): the world comes first and is always shown, so a sighting never goes to a world the
 * visitor did not mean (before, the home card used the saved world without asking).
 */
function YasirReport({ worlds, onReported }: { worlds: string[]; onReported: () => void }) {
  const { t } = useI18n();
  const { user } = useAuth();
  const [world, setWorld] = useState(readSavedWorld);
  const [picking, setPicking] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const valid = !!world && worlds.includes(world);

  const vote = async (present: boolean, city?: YasirCity) => {
    if (!valid) return;
    setBusy(true);
    setMsg(null);
    try {
      await daily.reportYasir(world, present, city);
      setMsg({ ok: true, text: t('yasir_thanks_world', { world }) });
      setPicking(false);
      onReported();
    } catch (err) {
      setMsg({ ok: false, text: err instanceof ApiError && err.status === 401 ? t('yasir_login') : t('api_error') });
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="card yasir-report">
      <h2>⛵ {t('yasir_report_title')}</h2>
      {!user ? (
        <p>
          <a href="/login" onClick={linkHandler('/login')}>
            {t('yasir_login')}
          </a>
        </p>
      ) : (
        <>
          <label className="field">
            <span>1. {t('yasir_step_world')}</span>
            <select
              value={valid ? world : ''}
              onChange={(e) => {
                setWorld(e.target.value);
                saveWorld(e.target.value);
                setMsg(null);
                setPicking(false);
              }}
              required
            >
              <option value="">{t('pick_world')}</option>
              {worlds.map((w) => (
                <option key={w} value={w}>
                  {w}
                </option>
              ))}
            </select>
          </label>
          <div className={`yasir-step${valid ? '' : ' disabled'}`}>
            <span>2. {valid ? t('yasir_step_seen', { world }) : t('yasir_step_need_world')}</span>
            {picking ? (
              <div className="button-row">
                <span className="muted small">{t('yasir_where')}</span>
                {YASIR_CITIES.map((c) => (
                  <button key={c} type="button" className="button secondary small-btn" disabled={busy} onClick={() => void vote(true, c)}>
                    {yasirCityName(t, c)}
                  </button>
                ))}
                <button type="button" className="icon-btn" aria-label={t('cancel')} onClick={() => setPicking(false)}>
                  ✕
                </button>
              </div>
            ) : (
              <div className="button-row">
                <button type="button" className="button secondary small-btn" disabled={!valid || busy} onClick={() => setPicking(true)}>
                  {t('yasir_vote_yes')}
                </button>
                <button type="button" className="button secondary small-btn" disabled={!valid || busy} onClick={() => void vote(false)}>
                  {t('yasir_vote_no')}
                </button>
              </div>
            )}
          </div>
          {msg && <p className={msg.ok ? 'small pos' : 'small error'}>{msg.text}</p>}
        </>
      )}
    </section>
  );
}

type T = (key: string, vars?: Record<string, string | number>) => string;

export const yasirCityName = (t: T, city: YasirCity | null): string =>
  city === 'other' ? t('yasir_city_other') : (city ?? '—');

/** One-line status for the home card */
export function yasirStatusText(t: T, status: YasirStatus, city: YasirCity | null): string {
  const c = yasirCityName(t, city);
  if (status === 'confirmed') return t('yasir_confirmed_in', { city: c });
  if (status === 'unconfirmed') return t('yasir_reported_in', { city: c });
  if (status === 'contested') return t('yasir_contested');
  if (status === 'absent') return t('yasir_absent');
  return t('yasir_no_reports');
}

function StatusTag({ status }: { status: YasirStatus }) {
  const { t } = useI18n();
  if (status === 'none') return null;
  return <span className={`tag yasir-${status}`}>{t(`yasir_status_${status}`)}</span>;
}

/** Single-series bar list: label · bar · count (count in text, so the value never relies on the bar) */
function BarList({ rows }: { rows: { label: string; value: number }[] }) {
  const max = Math.max(1, ...rows.map((r) => r.value));
  return (
    <ul className="bar-list">
      {rows.map((r) => (
        <li key={r.label} title={`${r.label}: ${r.value}`}>
          <span className="bar-label">{r.label}</span>
          <span className="bar-track" aria-hidden="true">
            {r.value > 0 && <span className="bar-fill" style={{ width: `${(r.value / max) * 100}%` }} />}
          </span>
          <span className="bar-value">{r.value}</span>
        </li>
      ))}
    </ul>
  );
}

function OutcomeTable({ rows, withDay }: { rows: YasirOutcome[]; withDay?: boolean }) {
  const { t, locale } = useI18n();
  const fmtDay = (d: string) =>
    new Date(`${d}T12:00:00Z`).toLocaleDateString(locale, { weekday: 'short', day: '2-digit', month: '2-digit', timeZone: 'UTC' });
  return (
    <div className="table-wrap">
      <table className="yasir-table">
        <thead>
          <tr>
            {withDay && <th>{t('yasir_col_day')}</th>}
            <th>{t('yasir_col_world')}</th>
            <th>{t('yasir_col_city')}</th>
            <th>{t('yasir_col_status')}</th>
            <th className="num">{t('yasir_col_reports')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((o) => (
            <tr key={`${o.day}|${o.world}`}>
              {withDay && <td>{fmtDay(o.day)}</td>}
              <td>{o.world}</td>
              <td>{yasirCityName(t, o.city)}</td>
              <td>
                <StatusTag status={o.status} />
              </td>
              <td className="num">{o.reports}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function YasirPage() {
  const { t, locale } = useI18n();
  const [data, setData] = useState<YasirPublic | null>(null);
  const [error, setError] = useState(false);
  const [worlds, setWorlds] = useState<string[]>([]);

  const load = () =>
    daily
      .yasirPublic(30)
      .then(setData)
      .catch(() => setError(true));
  useEffect(() => {
    void load();
    daily
      .worlds()
      .then(setWorlds)
      .catch(() => null);
  }, []);

  // Monday-first weekday names in the user's language (2026-11-02 was a Monday)
  const weekdays = Array.from({ length: 7 }, (_, i) =>
    new Date(Date.UTC(2026, 10, 2 + i, 12)).toLocaleDateString(locale, { weekday: 'short', timeZone: 'UTC' }),
  );
  const s = data?.stats;
  const gap = new Map(s?.avgGapDays.map((g) => [g.world, g.days]));
  const fmtDay = (d: string) => new Date(`${d}T12:00:00Z`).toLocaleDateString(locale, { day: '2-digit', month: '2-digit', timeZone: 'UTC' });

  return (
    <div className="page yasir-page">
      <h1>{t('yasir_title')}</h1>
      <p className="muted">{t('yasir_intro')}</p>
      <YasirReport worlds={worlds} onReported={() => void load()} />

      {error && <p className="alert">{t('api_error')}</p>}
      {!data && !error && <p className="muted">{t('loading')}</p>}

      {data && (
        <>
          <section className="card">
            <h2>{t('yasir_today')}</h2>
            {data.today.length ? (
              <OutcomeTable rows={data.today} />
            ) : (
              <p className="muted">{t('yasir_none_today')}</p>
            )}
          </section>

          <section className="card">
            <h2>{t('yasir_patterns')}</h2>
            <p className="muted small">{t('yasir_patterns_note', { days: data.window_days, n: s!.confirmedDays })}</p>
            {s!.confirmedDays === 0 ? (
              <p className="muted">{t('yasir_no_data')}</p>
            ) : (
              <div className="yasir-patterns">
                <div>
                  <h3>{t('yasir_by_city')}</h3>
                  <BarList
                    rows={(['Ankrahmun', 'Carlin', 'Liberty Bay', 'other'] as YasirCity[])
                      .map((c) => ({ label: yasirCityName(t, c), value: s!.byCity[c] ?? 0 }))
                      .filter((r, i) => i < 3 || r.value > 0)}
                  />
                </div>
                <div>
                  <h3>{t('yasir_by_weekday')}</h3>
                  <BarList rows={weekdays.map((label, i) => ({ label, value: s!.byWeekday[i] }))} />
                </div>
                <div className="yasir-by-world">
                  <h3>{t('yasir_by_world')}</h3>
                  <div className="table-wrap">
                    <table className="yasir-table">
                      <thead>
                        <tr>
                          <th>{t('yasir_col_world')}</th>
                          <th className="num">{t('yasir_col_days')}</th>
                          <th>{t('yasir_col_last')}</th>
                          <th className="num">{t('yasir_col_gap')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {s!.byWorld.slice(0, 20).map((w) => (
                          <tr key={w.world}>
                            <td>{w.world}</td>
                            <td className="num">{w.days}</td>
                            <td>{fmtDay(w.lastDay)}</td>
                            <td className="num">{gap.has(w.world) ? t('yasir_gap_days', { n: gap.get(w.world)! }) : '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}
          </section>

          {data.history.length > 0 && (
            <section className="card">
              <h2>{t('yasir_history')}</h2>
              <OutcomeTable rows={data.history} withDay />
            </section>
          )}

          <section className="card">
            <h2>{t('yasir_how_title')}</h2>
            <p className="small">{t('yasir_how')}</p>
          </section>
        </>
      )}
    </div>
  );
}
