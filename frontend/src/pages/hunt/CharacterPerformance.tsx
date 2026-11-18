/**
 * Character performance (D-61): one character's hunting over time — periods side by side, XP and profit per week,
 * level and skill progression (skills from the Server Log), best hunts, bestiary progress from the saved hunts
 * and the weekly goals.
 */
import { CreatureLink } from '../../components/CreatureLink';
import { useEffect, useMemo, useState } from 'react';
import { useI18n } from '../../i18n';
import { useAuth } from '../../auth';
import { linkHandler } from '../../router';
import { hunts, type HuntRow } from '../../api';
import { BarChart, LineChart, compact } from '../../components/Charts';
import { CreatureImage } from '../../components/CreatureImage';
import { filterHunts, groupHunts, ymd, type ReportFilters } from '../../core/huntReport';
import { bestiaryKills, damageMatrix, findCreature, loadCreatures, sumReceived, BESTIARY, type CreatureStats } from '../../core/creatures';
import type { InputAnalysis } from '../../core/huntLog';
import { InputDamage } from './InputDamage';
import { Kpi } from './HuntView';
import { GoalsBox } from './GoalsBox';
import { fmtClientDate, fmtDuration, gp, huntTime } from './common';

type PerfHunt = HuntRow & { monsters: { name: string; count: number }[]; skills: { skill: string; from: string; to: string }[] | null; input?: InputAnalysis | null };

function periods(now = new Date()): { key: string; f: ReportFilters }[] {
  const d = (days: number) => {
    const x = new Date(now);
    x.setDate(x.getDate() + days);
    return ymd(x);
  };
  const monday = new Date(now);
  monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
  const lastMonday = new Date(monday);
  lastMonday.setDate(lastMonday.getDate() - 7);
  const lastSunday = new Date(monday);
  lastSunday.setDate(lastSunday.getDate() - 1);
  const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
  const lastMonthStart = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 0);
  return [
    { key: 'perf_this_week', f: { period: 'custom', from: ymd(monday), to: d(0) } },
    { key: 'perf_last_week', f: { period: 'custom', from: ymd(lastMonday), to: ymd(lastSunday) } },
    { key: 'perf_this_month', f: { period: 'custom', from: ymd(firstOfMonth), to: d(0) } },
    { key: 'perf_last_month', f: { period: 'custom', from: ymd(lastMonthStart), to: ymd(lastMonthEnd) } },
    { key: 'perf_all', f: { period: 'all' } },
  ];
}

export function CharacterPerformance({ name }: { name: string }) {
  const { t, n, locale } = useI18n();
  const { user } = useAuth();
  const [data, setData] = useState<{ character: string; hunts: PerfHunt[]; others?: string[] } | null>(null);
  const [other, setOther] = useState('');
  const [otherData, setOtherData] = useState<{ character: string; hunts: PerfHunt[] } | null>(null);
  const [error, setError] = useState(false);
  const [metric, setMetric] = useState<'xp' | 'profit'>('xp');
  const [creatures, setCreatures] = useState<Map<string, CreatureStats> | null>(null);

  useEffect(() => {
    if (!user) return;
    setData(null);
    hunts
      .performance(name)
      .then(setData)
      .catch(() => setError(true));
    void loadCreatures().then(setCreatures);
    setOther('');
  }, [user, name]);

  useEffect(() => {
    setOtherData(null);
    if (!other) return;
    hunts
      .performance(other)
      .then(setOtherData)
      .catch(() => null);
  }, [other]);

  const rows = data?.hunts ?? [];
  const table = useMemo(
    () =>
      periods().map((p) => {
        const list = filterHunts(rows, p.f);
        const g = groupHunts(list, 'character')[0];
        return { key: p.key, n: list.length, hours: g?.hours ?? 0, xp: g?.xp ?? 0, profit: g?.profit ?? 0, xp_h: g?.xp_h ?? 0, balance_h: g?.balance_h ?? 0 };
      }),
    [rows],
  );
  const weeks = useMemo(() => groupHunts(rows, 'week').slice(0, 12).reverse(), [rows]);
  const levels = useMemo(
    () =>
      rows
        .filter((r) => r.level)
        .map((r) => ({ t: huntTime(r), y: r.level! }))
        .sort((a, b) => a.t - b.t),
    [rows],
  );
  const skills = useMemo(() => {
    const out: { day: string; id: string; skill: string; from: string; to: string }[] = [];
    for (const r of rows) for (const s of r.skills ?? []) out.push({ day: r.started_at ?? r.created_at, id: r.public_id, ...s });
    return out.sort((a, b) => b.day.localeCompare(a.day));
  }, [rows]);
  const latestSkill = useMemo(() => {
    const m = new Map<string, string>();
    for (const s of skills) if (!m.has(s.skill)) m.set(s.skill, s.to);
    return [...m];
  }, [skills]);
  const bestiary = useMemo(() => {
    if (!creatures) return [];
    const kills = new Map<string, { c: CreatureStats; kills: number }>();
    for (const r of rows)
      for (const m of r.monsters ?? []) {
        const c = findCreature(creatures, m.name);
        if (!c || !c.lvl) continue;
        const cur = kills.get(c.name) ?? { c, kills: 0 };
        cur.kills += m.count;
        kills.set(c.name, cur);
      }
    return [...kills.values()]
      .map((x) => {
        const need = bestiaryKills(x.c) ?? 0;
        return { ...x, need, pct: need ? Math.min(100, (x.kills / need) * 100) : 0 };
      })
      .sort((a, b) => (a.pct >= 100 ? 1 : 0) - (b.pct >= 100 ? 1 : 0) || b.pct - a.pct);
  }, [rows, creatures]);

  // D-62: received damage over every hunt with the Input Analyser (older hunts get the creature × type split here)
  const received = useMemo(() => {
    const inputs = rows.map((r) =>
      r.input && !r.input.by_source_type?.length && creatures && r.input.types.length && r.input.sources.length
        ? (() => {
            const m = damageMatrix(r.input, creatures);
            return { ...r.input, by_source_type: m.rows, estimated: m.estimated };
          })()
        : r.input,
    );
    const sum = sumReceived(inputs);
    if (!sum.hunts) return null;
    const asInput: InputAnalysis = {
      total: sum.total,
      max_dps: 0,
      types: sum.types,
      sources: sum.sources.slice(0, 15).map((x) => ({ name: x.name, value: x.value, pct: x.pct })),
      by_source_type: sum.sources.slice(0, 15).map((x) => ({ name: x.name, creature: x.creature, total: x.value, types: x.types })),
      estimated: inputs.some((i) => i?.estimated),
    };
    return { hunts: sum.hunts, total: sum.total, input: asInput };
  }, [rows, creatures]);

  if (user === undefined) return <p className="muted">{t('loading')}</p>;
  if (!user)
    return (
      <section className="card">
        <p>{t('ha_mine_guest')}</p>
        <a className="button" href="/login" onClick={linkHandler('/login')}>
          {t('login')}
        </a>
      </section>
    );
  if (error) return <p className="error">{t('ha_err_generic')}</p>;
  if (!data) return <p className="muted">{t('loading')}</p>;

  const total = table[table.length - 1];
  const lvNow = levels.length ? levels[levels.length - 1].y : null;
  const lvFirst = levels.length ? levels[0].y : null;
  const bestXp = [...rows].sort((a, b) => b.xp_h - a.xp_h).slice(0, 5);
  const bestProfit = [...rows].sort((a, b) => b.balance_h - a.balance_h).slice(0, 5);
  const done = bestiary.filter((b) => b.pct >= 100);
  const points = done.reduce((s, b) => s + (BESTIARY[b.c.lvl!]?.points ?? 0), 0);

  return (
    <div className="perf">
      <a href="/hunt-analyser/mine" onClick={linkHandler('/hunt-analyser/mine')} className="small">
        ← {t('ha_tab_mine')}
      </a>
      <h2>📊 {t('perf_title', { name: data.character })}</h2>
      {!rows.length ? (
        <p className="card muted">{t('perf_empty')}</p>
      ) : (
        <>
          <div className="kpi-grid">
            <Kpi label={t('ha_hunts')} value={n(total.n)} sub={`${n(Math.round(total.hours * 10) / 10)} h`} />
            <Kpi label={t('ha_total_xp')} value={compact(total.xp, locale)} sub={`${compact(total.xp_h, locale)} XP/h`} />
            <Kpi label={t('ha_total_profit')} value={gp(total.profit, locale)} tone={total.profit >= 0 ? 'pos' : 'neg'} sub={`${gp(total.balance_h, locale)}/h`} />
            {lvNow != null && <Kpi label={t('perf_level')} value={String(lvNow)} sub={lvFirst != null && lvNow > lvFirst ? `+${lvNow - lvFirst} ${t('perf_since_first')}` : undefined} />}
          </div>

          {(data.others?.length ?? 0) > 0 && (
            <section className="card">
              <div className="section-head">
                <h3>⚖️ {t('perf_compare')}</h3>
                <select value={other} onChange={(e) => setOther(e.target.value)} aria-label={t('perf_compare')}>
                  <option value="">{t('perf_compare_pick')}</option>
                  {data.others!.map((o) => (
                    <option key={o} value={o}>
                      {o}
                    </option>
                  ))}
                </select>
              </div>
              {other && !otherData && <p className="muted small">{t('loading')}</p>}
              {otherData && <CompareCharacters a={data} b={otherData} />}
            </section>
          )}

          <section className="card">
            <h3>🗓️ {t('perf_periods')}</h3>
            <div className="table-scroll">
              <table className="compact-table">
                <thead>
                  <tr>
                    <th>{t('ha_period')}</th>
                    <th className="num">{t('ha_hunts')}</th>
                    <th className="num">{t('rep_hours')}</th>
                    <th className="num">XP</th>
                    <th className="num">{t('ha_xp_h')}</th>
                    <th className="num">{t('rep_profit')}</th>
                    <th className="num">{t('ha_balance_h')}</th>
                  </tr>
                </thead>
                <tbody>
                  {table.map((p) => (
                    <tr key={p.key} className={p.n ? '' : 'muted'}>
                      <td>{t(p.key)}</td>
                      <td className="num">{p.n}</td>
                      <td className="num">{n(Math.round(p.hours * 10) / 10)}</td>
                      <td className="num">{p.n ? compact(p.xp, locale) : '—'}</td>
                      <td className="num">{p.n ? compact(p.xp_h, locale) : '—'}</td>
                      <td className={`num ${p.profit < 0 ? 'neg' : 'pos'}`}>{p.n ? gp(p.profit, locale) : '—'}</td>
                      <td className={`num ${p.balance_h < 0 ? 'neg' : 'pos'}`}>{p.n ? gp(p.balance_h, locale) : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <div className="chart-grid">
            <section className="card">
              <div className="section-head">
                <h3>{metric === 'xp' ? t('perf_xp_week') : t('perf_profit_week')}</h3>
                <div className="seg" role="group" aria-label={t('ha_metric')}>
                  <button type="button" className={metric === 'xp' ? 'active' : ''} onClick={() => setMetric('xp')}>
                    XP
                  </button>
                  <button type="button" className={metric === 'profit' ? 'active' : ''} onClick={() => setMetric('profit')}>
                    {t('rep_profit')}
                  </button>
                </div>
              </div>
              {weeks.length >= 2 ? (
                <BarChart
                  bars={weeks.map((w) => ({ key: w.key, value: metric === 'xp' ? w.xp : w.profit, label: fmtClientDate(`${w.key}T00:00:00`, locale).slice(0, 5) }))}
                  formatY={(v) => (metric === 'xp' ? compact(v, locale) : gp(v, locale))}
                  tooltip={(b) => `${t('rep_week_of', { date: fmtClientDate(`${b.key}T00:00:00`, locale).slice(0, 5) })}: ${metric === 'xp' ? compact(b.value, locale) : gp(b.value, locale)}`}
                  ariaLabel={metric === 'xp' ? t('perf_xp_week') : t('perf_profit_week')}
                />
              ) : (
                <p className="muted small">{t('ha_need_two')}</p>
              )}
            </section>
            <section className="card">
              <h3>{t('perf_level_chart')}</h3>
              {levels.length >= 2 ? (
                <LineChart
                  points={levels}
                  formatY={(v) => String(Math.round(v))}
                  formatDate={(ts) => new Date(ts).toLocaleDateString(locale, { timeZone: 'UTC', day: '2-digit', month: '2-digit' })}
                  tooltip={(p) => `${new Date(p.t).toLocaleDateString(locale, { timeZone: 'UTC' })}: level ${p.y}`}
                  ariaLabel={t('perf_level_chart')}
                />
              ) : (
                <p className="muted small">{t('ha_need_levels')}</p>
              )}
            </section>
          </div>

          <div className="chart-grid">
            {(['xp', 'profit'] as const).map((k) => (
              <section key={k} className="card">
                <h3>{k === 'xp' ? `🏅 ${t('perf_best_xp')}` : `💰 ${t('perf_best_profit')}`}</h3>
                <ol className="perf-best">
                  {(k === 'xp' ? bestXp : bestProfit).map((r) => (
                    <li key={r.public_id}>
                      <a href={`/hunt-analyser/h/${r.public_id}`} onClick={linkHandler(`/hunt-analyser/h/${r.public_id}`)}>
                        {r.title ?? r.place_name ?? r.top_monster ?? '—'}
                      </a>
                      <span className="muted small">
                        {' '}
                        · {fmtClientDate(r.started_at ?? r.created_at, locale)} · {fmtDuration(r.duration_s)}
                      </span>
                      <strong className={k === 'profit' && r.balance_h < 0 ? 'neg' : ''}>{k === 'xp' ? `${compact(r.xp_h, locale)} XP/h` : `${gp(r.balance_h, locale)}/h`}</strong>
                    </li>
                  ))}
                </ol>
              </section>
            ))}
          </div>

          <section className="card">
            <h3>📈 {t('perf_skills')}</h3>
            {skills.length ? (
              <>
                <div className="chip-row">
                  {latestSkill.map(([skill, to]) => (
                    <span key={skill} className="chip">
                      <strong>{skill}</strong>&nbsp;{to}
                    </span>
                  ))}
                </div>
                <div className="table-scroll">
                  <table className="compact-table">
                    <thead>
                      <tr>
                        <th>{t('ha_date')}</th>
                        <th>Skill</th>
                        <th>{t('perf_from')}</th>
                        <th>{t('perf_to')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {skills.slice(0, 30).map((s, i) => (
                        <tr key={i}>
                          <td>
                            <a href={`/hunt-analyser/h/${s.id}`} onClick={linkHandler(`/hunt-analyser/h/${s.id}`)}>
                              {fmtClientDate(s.day, locale)}
                            </a>
                          </td>
                          <td>{s.skill}</td>
                          <td>{s.from}</td>
                          <td>
                            <strong>{s.to}</strong>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </>
            ) : (
              <p className="muted small">{t('perf_skills_empty')}</p>
            )}
          </section>

          <section className="card">
            <h3>🩸 {t('perf_received')}</h3>
            {received ? (
              <>
                <p className="muted small">{t('perf_received_note', { hunts: received.hunts, total: n(received.total) })}</p>
                <InputDamage input={received.input} />
              </>
            ) : (
              <p className="muted small">{t('perf_received_empty')}</p>
            )}
          </section>

          <section className="card">
            <h3>📖 {t('perf_bestiary')}</h3>
            <p className="muted small">{t('perf_bestiary_note', { done: done.length, points })}</p>
            {bestiary.length ? (
              <ul className="perf-bestiary">
                {bestiary.slice(0, 18).map((b) => (
                  <li key={b.c.name}>
                    <CreatureImage name={b.c.name} size={32} />
                    <div>
                      <div className="row-between">
                        <strong>
                          <CreatureLink name={b.c.name} />
                        </strong>
                        <span className="small">
                          {n(b.kills)} / {n(b.need)}
                        </span>
                      </div>
                      <div className="perf-progress" role="progressbar" aria-valuenow={Math.round(b.pct)} aria-valuemin={0} aria-valuemax={100} aria-label={b.c.name}>
                        <span style={{ width: `${b.pct}%` }} className={b.pct >= 100 ? 'done' : ''} />
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="muted small">{creatures ? t('perf_bestiary_empty') : t('loading')}</p>
            )}
          </section>

          <GoalsBox characters={[data.character]} />
        </>
      )}
    </div>
  );
}

/** D-62: two characters side by side, last 30 days and all time */
function CompareCharacters({ a, b }: { a: { character: string; hunts: PerfHunt[] }; b: { character: string; hunts: PerfHunt[] } }) {
  const { t, n, locale } = useI18n();
  const [period, setPeriod] = useState<'30' | 'all'>('30');
  const sum = (list: PerfHunt[]) => {
    const rows = filterHunts(list, { period });
    const g = groupHunts(rows, 'character')[0];
    const lv = rows.filter((r) => r.level).sort((x, y) => huntTime(y) - huntTime(x))[0]?.level ?? null;
    const best = rows.reduce<PerfHunt | null>((m, r) => (!m || r.xp_h > m.xp_h ? r : m), null);
    return { n: rows.length, hours: g?.hours ?? 0, xp_h: g?.xp_h ?? 0, raw_xp_h: g?.raw_xp_h ?? 0, balance_h: g?.balance_h ?? 0, xp: g?.xp ?? 0, profit: g?.profit ?? 0, level: lv, best: best?.xp_h ?? 0, place: best ? best.place_name ?? best.top_monster ?? '—' : '—' };
  };
  const A = sum(a.hunts);
  const B = sum(b.hunts);
  const lines: { label: string; a: number | null; b: number | null; fmt: (v: number) => string; money?: boolean }[] = [
    { label: t('ha_hunts'), a: A.n, b: B.n, fmt: (v) => n(v) },
    { label: t('rep_hours'), a: Math.round(A.hours * 10) / 10, b: Math.round(B.hours * 10) / 10, fmt: (v) => n(v) },
    { label: t('ha_xp_h'), a: A.xp_h, b: B.xp_h, fmt: (v) => compact(v, locale) },
    { label: t('ha_raw_xp_h'), a: A.raw_xp_h, b: B.raw_xp_h, fmt: (v) => compact(v, locale) },
    { label: t('ha_balance_h'), a: A.balance_h, b: B.balance_h, fmt: (v) => gp(v, locale), money: true },
    { label: 'XP', a: A.xp, b: B.xp, fmt: (v) => compact(v, locale) },
    { label: t('rep_profit'), a: A.profit, b: B.profit, fmt: (v) => gp(v, locale), money: true },
    { label: t('perf_level'), a: A.level, b: B.level, fmt: (v) => String(v) },
    { label: t('rep_best'), a: A.best, b: B.best, fmt: (v) => compact(v, locale) },
  ];
  return (
    <>
      <div className="seg" role="group" aria-label={t('ha_period')}>
        <button type="button" className={period === '30' ? 'active' : ''} onClick={() => setPeriod('30')}>
          {t('ha_last_days', { n: 30 })}
        </button>
        <button type="button" className={period === 'all' ? 'active' : ''} onClick={() => setPeriod('all')}>
          {t('perf_all')}
        </button>
      </div>
      <div className="table-scroll">
        <table className="compact-table perf-compare">
          <thead>
            <tr>
              <th />
              <th className="num">{a.character}</th>
              <th className="num">{b.character}</th>
            </tr>
          </thead>
          <tbody>
            {lines.map((l) => {
              const win = l.a != null && l.b != null && l.a !== l.b ? (l.a > l.b ? 'a' : 'b') : null;
              return (
                <tr key={l.label}>
                  <td>{l.label}</td>
                  <td className={`num${win === 'a' ? ' win' : ''}`}>{l.a != null ? l.fmt(l.a) : '—'}</td>
                  <td className={`num${win === 'b' ? ' win' : ''}`}>{l.b != null ? l.fmt(l.b) : '—'}</td>
                </tr>
              );
            })}
            <tr>
              <td>{t('perf_best_place')}</td>
              <td className="num small">{A.place}</td>
              <td className="num small">{B.place}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </>
  );
}
