/** My hunts (D-49): history per character, evolution charts, level × XP/h, best places for me, compare. */
import { useEffect, useMemo, useState } from 'react';
import { useI18n } from '../../i18n';
import { useAuth } from '../../auth';
import { linkHandler, navigate } from '../../router';
import { hunts, type HuntRow } from '../../api';
import { analysePaste } from '../../core/huntLog';
import { BarChart, LineChart, ScatterChart, compact } from '../../components/Charts';
import { Kpi } from './HuntView';
import { GoalsBox } from './GoalsBox';
import { WeaponCompare } from './WeaponCompare';
import { averages, clearLegacyHunts, fmtClientDate, fmtDuration, gp, huntTime, legacyHuntLogs, loadLocalHunts, saveLocalHunts, VocationSelect } from './common';
import { csvSeparator, downloadText, filterHunts, groupHunts, groupsCsv, huntsCsv, type GroupBy, type Period, type ReportFilters } from '../../core/huntReport';

type Metric = 'xp_h' | 'raw_xp_h' | 'balance_h';

export function MyHuntsTab() {
  const { t, n, locale } = useI18n();
  const { user } = useAuth();
  const [rows, setRows] = useState<HuntRow[] | null>(null);
  const [limit, setLimit] = useState(0);
  const [premium, setPremium] = useState(false);
  const [f, setF] = useState<ReportFilters>({ period: 'all' });
  const [moreFilters, setMoreFilters] = useState(false);
  const [groupBy, setGroupBy] = useState<GroupBy>('place');
  const char = f.character ?? '';
  const [metric, setMetric] = useState<Metric>('xp_h');
  const [picked, setPicked] = useState<string[]>([]);
  const [importing, setImporting] = useState(false);
  const [error, setError] = useState('');

  const local = loadLocalHunts();
  const legacy = legacyHuntLogs();

  const load = () =>
    hunts
      .mine()
      .then((r) => {
        setRows(r.hunts);
        setLimit(r.limit);
        setPremium(r.premium);
      })
      .catch(() => setError(t('ha_err_generic')));

  useEffect(() => {
    if (user) void load();
  }, [user]); // eslint-disable-line react-hooks/exhaustive-deps

  const charsInData = useMemo(() => [...new Set((rows ?? []).map((r) => r.character_name ?? '—'))], [rows]);

  const filtered = useMemo(() => filterHunts(rows ?? [], f), [rows, f]);
  const extraCount = [f.vocation, f.kind, f.size, f.minLevel, f.maxLevel, f.q].filter(Boolean).length;

  const stats = useMemo(() => {
    if (!filtered.length) return null;
    const avg = averages(filtered)!;
    const best = filtered.reduce((b, r) => (r[metric] > b[metric] ? r : b), filtered[0]);
    return {
      avg,
      xp: filtered.reduce((s, r) => s + r.xp, 0),
      profit: filtered.reduce((s, r) => s + r.balance, 0),
      best,
    };
  }, [filtered, metric]);

  const groups = useMemo(() => groupHunts(filtered, groupBy), [filtered, groupBy]);

  if (!user) {
    return (
      <section className="card">
        <h2>{t('ha_mine_title')}</h2>
        <p>{t('ha_mine_guest')}</p>
        {local.length > 0 && <p className="muted small">{t('ha_local_count', { n: local.length })}</p>}
        <div className="button-row">
          <a className="button" href="/login" onClick={linkHandler('/login')}>
            {t('login')}
          </a>
          <a className="button secondary" href="/register" onClick={linkHandler('/register')}>
            {t('ha_create_account')}
          </a>
        </div>
      </section>
    );
  }

  const importLocal = async () => {
    setImporting(true);
    let ok = 0;
    for (const l of local) {
      try {
        await hunts.save({ ...l.hunt, character_name: l.character || null, place_name: l.place || null, visibility: 'private' });
        ok++;
      } catch {
        /* skip invalid / duplicate */
      }
    }
    for (const l of legacy) {
      const h = analysePaste(l.text).hunt;
      if (!h) continue;
      try {
        await hunts.save({ ...h, character_name: l.character || null, place_name: l.place || null, visibility: 'private' });
        ok++;
      } catch {
        /* skip */
      }
    }
    saveLocalHunts([]);
    clearLegacyHunts();
    setImporting(false);
    setError(t('ha_imported', { n: ok }));
    void load();
  };

  const togglePick = (id: string) =>
    setPicked((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p.slice(-1), id]));

  const toggleVisibility = async (r: HuntRow) => {
    const next = r.visibility === 'public' ? 'private' : 'public';
    await hunts.update(r.public_id, { visibility: next }).catch(() => null);
    setRows((rs) => rs?.map((x) => (x.public_id === r.public_id ? { ...x, visibility: next } : x)) ?? null);
  };

  const remove = async (r: HuntRow) => {
    if (!window.confirm(t('ha_confirm_delete'))) return;
    await hunts.remove(r.public_id).catch(() => null);
    setRows((rs) => rs?.filter((x) => x.public_id !== r.public_id) ?? null);
  };

  const metricLabel = (m: Metric) => t(m === 'xp_h' ? 'ha_xp_h' : m === 'raw_xp_h' ? 'ha_raw_xp_h' : 'ha_balance_h');
  const fmtMetric = (v: number) => (metric === 'balance_h' ? gp(v, locale) : compact(v, locale));
  const chrono = [...filtered].sort((a, b) => huntTime(a) - huntTime(b));
  const withLevel = filtered.filter((r) => r.level);

  return (
    <div className="ha-mine">
      {(local.length > 0 || legacy.length > 0) && (
        <div className="callout">
          <span>{t('ha_import_prompt', { n: local.length + legacy.length })}</span>
          <button type="button" className="button" onClick={importLocal} disabled={importing}>
            {importing ? t('loading') : t('ha_import')}
          </button>
        </div>
      )}
      {error && <p className="muted">{error}</p>}

      <GoalsBox characters={charsInData.filter((c) => c !== '—')} />


      <div className="filter-bar">
        <select value={char} onChange={(e) => setF({ ...f, character: e.target.value })} aria-label={t('nav_characters')}>
          <option value="">{t('ha_all_characters')}</option>
          {charsInData.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </select>
        <select value={f.period} onChange={(e) => setF({ ...f, period: e.target.value as Period })} aria-label={t('ha_period')}>
          <option value="today">{t('rep_today')}</option>
          <option value="week">{t('rep_this_week')}</option>
          <option value="month">{t('rep_this_month')}</option>
          <option value="7">{t('ha_last_days', { n: 7 })}</option>
          <option value="30">{t('ha_last_days', { n: 30 })}</option>
          <option value="90">{t('ha_last_days', { n: 90 })}</option>
          <option value="all">{t('ha_all_time')}</option>
          <option value="custom">{t('rep_custom')}</option>
        </select>
        {f.period === 'custom' && (
          <span className="rep-dates">
            <input type="date" value={f.from ?? ''} onChange={(e) => setF({ ...f, from: e.target.value })} aria-label={t('rep_from')} />
            <input type="date" value={f.to ?? ''} onChange={(e) => setF({ ...f, to: e.target.value })} aria-label={t('rep_to')} />
          </span>
        )}
        <button type="button" className={`button secondary small-btn${extraCount ? ' on' : ''}`} aria-expanded={moreFilters} onClick={() => setMoreFilters((v) => !v)}>
          ⚙️ {t('rep_more_filters')}
          {extraCount ? ` (${extraCount})` : ''}
        </button>
        <div className="seg" role="group" aria-label={t('ha_metric')}>
          {(['xp_h', 'raw_xp_h', 'balance_h'] as Metric[]).map((m) => (
            <button key={m} type="button" className={metric === m ? 'active' : ''} onClick={() => setMetric(m)}>
              {metricLabel(m)}
            </button>
          ))}
        </div>
        <a className="button" href="/hunt-analyser" onClick={linkHandler('/hunt-analyser')}>
          ＋ {t('ha_new')}
        </a>
      </div>
      {moreFilters && (
        <div className="filter-bar rep-more">
          <VocationSelect value={f.vocation ?? ''} onChange={(v) => setF({ ...f, vocation: v })} />
          <select value={f.kind ?? ''} onChange={(e) => setF({ ...f, kind: e.target.value as ReportFilters['kind'] })} aria-label={t('ha_kind')}>
            <option value="">{t('ha_solo_party')}</option>
            <option value="solo">Solo</option>
            <option value="party">Party</option>
          </select>
          <select value={f.size ?? ''} onChange={(e) => setF({ ...f, size: e.target.value as ReportFilters['size'] })} aria-label={t('rep_party_size')}>
            <option value="">{t('rep_party_size')}</option>
            <option value="1">{t('rep_size_1')}</option>
            {['2', '3', '4', '5+'].map((v) => (
              <option key={v} value={v}>
                {t('rep_size_n', { n: v })}
              </option>
            ))}
          </select>
          <input type="number" inputMode="numeric" min={1} className="lvl-input" placeholder={t('ha_min_level')} value={f.minLevel ?? ''} onChange={(e) => setF({ ...f, minLevel: Number(e.target.value) || null })} aria-label={t('ha_min_level')} />
          <input type="number" inputMode="numeric" min={1} className="lvl-input" placeholder={t('ha_max_level')} value={f.maxLevel ?? ''} onChange={(e) => setF({ ...f, maxLevel: Number(e.target.value) || null })} aria-label={t('ha_max_level')} />
          <input type="search" placeholder={t('rep_search')} value={f.q ?? ''} onChange={(e) => setF({ ...f, q: e.target.value })} aria-label={t('rep_search')} />
          {extraCount > 0 && (
            <button type="button" className="button secondary small-btn" onClick={() => setF({ period: f.period, from: f.from, to: f.to, character: f.character })}>
              ✕ {t('ha_clear')}
            </button>
          )}
        </div>
      )}
      {char && char !== '—' && (
        <p className="small">
          <a href={`/hunt-analyser/character/${encodeURIComponent(char)}`} onClick={linkHandler(`/hunt-analyser/character/${encodeURIComponent(char)}`)}>
            📊 {t('perf_open', { name: char })}
          </a>
        </p>
      )}

      {rows === null ? (
        <p className="muted">{t('loading')}</p>
      ) : !rows.length ? (
        <section className="card empty-state">
          <p>{t('ha_mine_empty')}</p>
          <a className="button" href="/hunt-analyser" onClick={linkHandler('/hunt-analyser')}>
            {t('ha_new')}
          </a>
        </section>
      ) : (
        <>
          {stats && (
            <div className="kpi-grid">
              <Kpi label={t('ha_hunts')} value={n(filtered.length)} sub={`${n(Math.round(stats.avg.hours * 10) / 10)} h`} />
              <Kpi label={t('ha_avg_xp_h')} value={compact(stats.avg.xp_h, locale)} sub={`${t('ha_raw')}: ${compact(stats.avg.raw_xp_h, locale)}`} />
              <Kpi label={t('ha_avg_balance_h')} value={gp(stats.avg.balance_h, locale)} tone={stats.avg.balance_h >= 0 ? 'pos' : 'neg'} />
              <Kpi label={t('ha_total_xp')} value={compact(stats.xp, locale)} />
              <Kpi label={t('ha_total_profit')} value={gp(stats.profit, locale)} tone={stats.profit >= 0 ? 'pos' : 'neg'} />
              <Kpi label={t('ha_best')} value={fmtMetric(stats.best[metric])} sub={stats.best.place_name ?? stats.best.top_monster ?? ''} />
            </div>
          )}

          <div className="chart-grid">
            <section className="card">
              <h3>{t('ha_chart_evolution', { metric: metricLabel(metric) })}</h3>
              {chrono.length >= 2 ? (
                metric === 'balance_h' ? (
                  <BarChart
                    bars={chrono.map((r) => ({ key: r.public_id, value: r.balance_h, label: fmtClientDate(r.started_at, locale).slice(0, 5) }))}
                    formatY={(v) => gp(v, locale)}
                    tooltip={(b) => {
                      const r = chrono.find((x) => x.public_id === b.key)!;
                      return `${fmtClientDate(r.started_at, locale)} · ${r.place_name ?? r.top_monster ?? ''}: ${gp(b.value, locale)}/h`;
                    }}
                    ariaLabel={metricLabel(metric)}
                  />
                ) : (
                  <LineChart
                    points={chrono.map((r) => ({ t: huntTime(r), y: r[metric] }))}
                    formatY={(v) => compact(v, locale)}
                    formatDate={(ts) => new Date(ts).toLocaleDateString(locale, { timeZone: 'UTC', day: '2-digit', month: '2-digit' })}
                    tooltip={(p) => {
                      const r = chrono.find((x) => huntTime(x) === p.t);
                      return `${fmtClientDate(r?.started_at ?? null, locale)} · ${r?.place_name ?? r?.top_monster ?? ''}: ${n(p.y)}`;
                    }}
                    ariaLabel={metricLabel(metric)}
                  />
                )
              ) : (
                <p className="muted small">{t('ha_need_two')}</p>
              )}
            </section>

            <section className="card">
              <h3>{t('ha_chart_level', { metric: metricLabel(metric) })}</h3>
              {withLevel.length >= 2 ? (
                <ScatterChart
                  points={withLevel.map((r) => ({ key: r.public_id, x: r.level!, y: r[metric] }))}
                  formatX={(v) => String(Math.round(v))}
                  formatY={fmtMetric}
                  tooltip={(p) => {
                    const r = withLevel.find((x) => x.public_id === p.key)!;
                    return `Lv ${r.level} · ${r.place_name ?? r.top_monster ?? ''}: ${fmtMetric(p.y)}`;
                  }}
                  ariaLabel={t('ha_chart_level', { metric: metricLabel(metric) })}
                />
              ) : (
                <p className="muted small">{t('ha_need_levels')}</p>
              )}
            </section>
          </div>

          {filtered.length > 1 && (
            <section className="card">
              <div className="section-head">
                <h3>📊 {t('rep_title')}</h3>
                <button
                  type="button"
                  className="button secondary small-btn"
                  onClick={() => downloadText(`bagparser-report-${groupBy}.csv`, groupsCsv(groups, groupBy, csvSeparator(locale)))}
                >
                  ⬇️ CSV
                </button>
                <label className="small">
                  {t('rep_group_by')}{' '}
                  <select value={groupBy} onChange={(e) => setGroupBy(e.target.value as GroupBy)}>
                    {(['place', 'character', 'week', 'month', 'size', 'vocation'] as GroupBy[]).map((g) => (
                      <option key={g} value={g}>
                        {t(`rep_by_${g}`)}
                      </option>
                    ))}
                  </select>
                </label>
              </div>
              <div className="table-scroll">
                <table className="compact-table rep-table">
                  <thead>
                    <tr>
                      <th>{t(`rep_by_${groupBy}`)}</th>
                      <th className="num">{t('ha_hunts')}</th>
                      <th className="num">{t('rep_hours')}</th>
                      <th className="num">{t('ha_xp_h')}</th>
                      <th className="num">{t('ha_raw_xp_h')}</th>
                      <th className="num">{t('ha_balance_h')}</th>
                      <th className="num">{t('rep_profit')}</th>
                      <th className="num">{t('rep_best')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {groups.map((g) => {
                      const top = groups.reduce((m, x) => Math.max(m, x[metric]), 0) || 1;
                      return (
                        <tr key={g.key}>
                          <td>
                            {groupBy === 'place' && g.slug ? (
                              <a href={`/hunting/${g.slug}`} onClick={linkHandler(`/hunting/${g.slug}`)}>
                                {g.label}
                              </a>
                            ) : groupBy === 'character' && g.label !== '—' ? (
                              <a href={`/hunt-analyser/character/${encodeURIComponent(g.label)}`} onClick={linkHandler(`/hunt-analyser/character/${encodeURIComponent(g.label)}`)}>
                                {g.label}
                              </a>
                            ) : groupBy === 'size' ? (
                              g.label === '1' ? t('rep_size_1') : t('rep_size_n', { n: g.label })
                            ) : groupBy === 'vocation' && g.label !== '—' ? (
                              t(`voc_${g.label}`)
                            ) : groupBy === 'week' ? (
                              t('rep_week_of', { date: fmtClientDate(`${g.label}T00:00:00`, locale).split(/[ ,]+/)[0] })
                            ) : (
                              g.label
                            )}
                            <span className="rep-bar" style={{ width: `${Math.max(0, Math.round((g[metric] / top) * 100))}%` }} aria-hidden="true" />
                          </td>
                          <td className="num">{g.hunts}</td>
                          <td className="num">{n(Math.round(g.hours * 10) / 10)}</td>
                          <td className="num">{compact(g.xp_h, locale)}</td>
                          <td className="num">{compact(g.raw_xp_h, locale)}</td>
                          <td className={`num ${g.balance_h < 0 ? 'neg' : 'pos'}`}>{gp(g.balance_h, locale)}</td>
                          <td className={`num ${g.profit < 0 ? 'neg' : 'pos'}`}>{gp(g.profit, locale)}</td>
                          <td className="num">
                            <a href={`/hunt-analyser/h/${g.best.id}`} onClick={linkHandler(`/hunt-analyser/h/${g.best.id}`)}>
                              {compact(g.best.xp_h, locale)}
                            </a>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </section>
          )}

          <WeaponCompare />

          <section className="card">
            <div className="section-head">
              <h3>
                {t('ha_history')} <span className="muted small">{t('ha_used_of', { n: rows.length, limit })}</span>
              </h3>
              <button
                type="button"
                className="button secondary small-btn"
                onClick={() => downloadText(`bagparser-hunts-${new Date().toISOString().slice(0, 10)}.csv`, huntsCsv(filtered, csvSeparator(locale), location.origin))}
                title={t('rep_csv_hint')}
              >
                ⬇️ CSV
              </button>
              <button
                type="button"
                className="button"
                disabled={picked.length !== 2}
                onClick={() => navigate(`/hunt-analyser/compare/${picked[0]}/${picked[1]}`)}
              >
                ⚖️ {t('ha_compare_selected', { n: picked.length })}
              </button>
            </div>
            <div className="table-scroll">
              <table className="compact-table hunts-table">
                <thead>
                  <tr>
                    <th aria-label={t('ha_compare')} />
                    <th>{t('ha_date')}</th>
                    <th>{t('ha_place')}</th>
                    <th className="hide-sm">{t('nav_characters')}</th>
                    <th className="num">{t('ha_duration')}</th>
                    <th className="num">{t('ha_xp_h')}</th>
                    <th className="num">{t('ha_balance_h')}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((r) => (
                    <tr key={r.public_id} className={picked.includes(r.public_id) ? 'picked' : ''}>
                      <td>
                        <input
                          type="checkbox"
                          checked={picked.includes(r.public_id)}
                          onChange={() => togglePick(r.public_id)}
                          aria-label={t('ha_compare')}
                        />
                      </td>
                      <td>
                        <a href={`/hunt-analyser/h/${r.public_id}`} onClick={linkHandler(`/hunt-analyser/h/${r.public_id}`)}>
                          {fmtClientDate(r.started_at ?? r.created_at, locale)}
                        </a>
                        {r.kind === 'party' && <span className="tag">party</span>}
                        {r.has_server_log && <span className="tag pro">log</span>}
                      </td>
                      <td>
                        {r.title ?? r.place_name ?? r.top_monster ?? '—'}
                        {r.title && r.place_name && r.title !== r.place_name && <div className="muted small">{r.place_name}</div>}
                        {(r.extra_expenses ?? 0) > 0 && <div className="muted small">💸 {gp(r.extra_expenses ?? 0, locale)}</div>}
                      </td>
                      <td className="hide-sm">
                        {r.character_name ?? '—'}
                        {r.level ? ` (${r.level})` : ''}
                      </td>
                      <td className="num">{fmtDuration(r.duration_s)}</td>
                      <td className="num">{compact(r.xp_h, locale)}</td>
                      <td className={`num ${r.balance_h < 0 ? 'neg' : 'pos'}`}>{gp(r.balance_h, locale)}</td>
                      <td className="row-actions">
                        <button
                          type="button"
                          className="icon-btn"
                          title={r.visibility === 'public' ? t('ha_public') : t('ha_private')}
                          onClick={() => void toggleVisibility(r)}
                        >
                          {r.visibility === 'public' ? '🌐' : '🔒'}
                        </button>
                        <button type="button" className="icon-btn" title={t('delete')} onClick={() => void remove(r)}>
                          🗑️
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {!premium && rows.length >= limit * 0.8 && (
              <p className="muted small">
                {t('ha_limit_near', { limit })}{' '}
                <a href="/pricing" onClick={linkHandler('/pricing')}>
                  ⭐ Pro
                </a>
              </p>
            )}
          </section>
        </>
      )}
      <SharedWithMe />
    </div>
  );
}

/** D-59: hunts that friends shared with me (read only) */
function SharedWithMe() {
  const { t, locale } = useI18n();
  const [rows, setRows] = useState<HuntRow[]>([]);
  useEffect(() => {
    hunts
      .shared()
      .then((r) => setRows(r.hunts))
      .catch(() => null);
  }, []);
  if (!rows.length) return null;
  return (
    <section className="card">
      <h3>🤝 {t('hs_shared_title')}</h3>
      <ul className="plain-list">
        {rows.slice(0, 50).map((r) => (
          <li key={r.public_id}>
            <a href={`/hunt-analyser/h/${r.public_id}`} onClick={linkHandler(`/hunt-analyser/h/${r.public_id}`)}>
              {r.title ?? r.place_name ?? r.top_monster ?? '—'}
            </a>{' '}
            <span className="muted small">
              · {r.owner} · {fmtClientDate(r.started_at ?? r.created_at, locale)} · {compact(r.xp_h, locale)} XP/h · {gp(r.balance_h, locale)}/h
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}
