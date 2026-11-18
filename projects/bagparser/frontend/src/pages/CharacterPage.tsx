import { useCallback, useEffect, useMemo, useState } from 'react';
import { ApiError, characters, type CharacterDetail } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler, navigate } from '../router';
import { BarChart, compact, LineChart } from '../components/Charts';
import { dailyRates, expForLevel, fractionalLevel, goalReport, suggestions, type Snap } from '../core/progress';
import { errorKey } from './CharactersPage';
import { ShareButtons } from '../components/ShareButtons';
import { WeeklyTasks } from '../components/WeeklyTasks';

const DAY = 86_400_000;

function Progress({ value, label }: { value: number; label: string }) {
  const pct = Math.round(Math.min(1, Math.max(0, value)) * 100);
  return (
    <div className="progress" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100} aria-label={label}>
      <div className="progress-fill" style={{ width: `${pct}%` }} />
    </div>
  );
}

export function CharacterPage({ id }: { id: number }) {
  const { t, n, locale } = useI18n();
  const { user } = useAuth();
  const [data, setData] = useState<CharacterDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [cooldown, setCooldown] = useState(0);
  const [targetLevel, setTargetLevel] = useState('');
  const [targetDate, setTargetDate] = useState('');
  const [goalMsg, setGoalMsg] = useState<string | null>(null);
  const [editGoal, setEditGoal] = useState(false);
  const [range, setRange] = useState<30 | 90 | 365 | 0>(90);
  const [renameOpen, setRenameOpen] = useState(false);
  const [newName, setNewName] = useState('');
  const [renameMsg, setRenameMsg] = useState<string | null>(null);

  const msgFor = useCallback(
    (err: unknown) => {
      const key = errorKey(err);
      return t(key) === key ? t('api_error') : t(key);
    },
    [t],
  );

  const load = useCallback(() => {
    characters
      .get(id)
      .then((d) => {
        setData(d);
        setCooldown(d.refresh_available_in);
      })
      .catch((err) => setError(err instanceof ApiError && err.status === 404 ? t('char_err_not_found') : t('api_error')));
  }, [id, t]);

  useEffect(() => {
    if (user === null) navigate('/login');
    if (user) load();
  }, [user, load]);

  // Count down the refresh cooldown
  useEffect(() => {
    if (cooldown <= 0) return;
    const timer = setInterval(() => setCooldown((c) => Math.max(0, c - 1)), 1000);
    return () => clearInterval(timer);
  }, [cooldown > 0]); // eslint-disable-line react-hooks/exhaustive-deps

  const snaps: Snap[] = useMemo(
    () => (data?.snapshots ?? []).map((s) => ({ t: new Date(s.taken_at).getTime(), level: s.level, exp: s.experience })),
    [data],
  );
  const report = useMemo(() => (data?.goal ? goalReport(data.goal, snaps) : null), [data, snaps]);
  const tips = useMemo(() => (data ? suggestions(report, snaps, data.deaths) : []), [report, snaps, data]);

  if (error) return <p className="alert">{error}</p>;
  if (!data) return <p className="muted">{t('loading')}</p>;

  const c = data.character;
  const last = snaps[snaps.length - 1];
  const exact = data.snapshots[data.snapshots.length - 1]?.exp_exact;
  const date = (ms: number) => new Date(ms).toLocaleDateString(locale);
  const shortDate = (ms: number) => new Date(ms).toLocaleDateString(locale, { day: '2-digit', month: '2-digit' });
  const dateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short' }) : '—';

  const refresh = async () => {
    setBusy(true);
    setError(null);
    try {
      await characters.refresh(id);
      load();
    } catch (err) {
      if (err instanceof ApiError && err.code === 'refresh_cooldown') setCooldown(Number(err.data.retry_after) || 60);
      else if (err instanceof ApiError && err.code === 'traded_reverify') navigate('/characters');
      else if (err instanceof ApiError && err.code === 'character_renamed') setRenameOpen(true);
      else setError(msgFor(err));
    } finally {
      setBusy(false);
    }
  };

  const saveGoal = async (e: React.FormEvent) => {
    e.preventDefault();
    setGoalMsg(null);
    try {
      await characters.setGoal(id, Number(targetLevel), targetDate || null);
      setEditGoal(false);
      load();
    } catch (err) {
      setGoalMsg(msgFor(err));
    }
  };

  const rename = async (e: React.FormEvent) => {
    e.preventDefault();
    setRenameMsg(null);
    try {
      await characters.rename(id, newName);
      setRenameOpen(false);
      setNewName('');
      load();
    } catch (err) {
      setRenameMsg(msgFor(err));
    }
  };

  const removeCharacter = async () => {
    if (!window.confirm(t('char_remove_confirm', { name: c.name }))) return;
    await characters.remove(id).catch(() => undefined);
    navigate('/characters');
  };

  // Chart data
  const since = range ? Date.now() - range * DAY : 0;
  const shown = snaps.filter((s) => s.t >= since);
  const linePoints = shown.map((s) => ({ t: s.t, y: fractionalLevel(s.level, s.exp), exp: s.exp }));
  const projection =
    report && !report.achieved && report.etaDate && last && report.etaDays! <= 3 * 365
      ? { t: report.etaDate, y: data.goal!.target_level }
      : undefined;
  const bars = dailyRates(snaps)
    .filter((b) => b.t >= since)
    .map((b) => ({
      key: b.day,
      value: b.xp,
      label: shortDate(b.t),
      days: b.days,
    }));

  const cooldownText = cooldown > 0 ? `${Math.floor(cooldown / 60)}:${String(cooldown % 60).padStart(2, '0')}` : '';

  return (
    <div className="page char-page">
      <a className="back-link" href="/characters" onClick={linkHandler('/characters')}>
        ← {t('chars_title')}
      </a>

      <section className="card char-header">
        <div>
          <h1>{c.name}</h1>
          <div className="muted">
            {c.vocation} · {c.world}
            {c.residence ? ` · ${c.residence}` : ''}
          </div>
          <div className="muted small">
            {c.guild ? `${c.guild} · ` : ''}
            {c.account_status ?? ''}
            {c.last_login ? ` · ${t('last_login', { date: dateTime(c.last_login) })}` : ''}
          </div>
        </div>
        <div className="char-actions">
          <button type="button" onClick={refresh} disabled={busy || cooldown > 0}>
            {busy ? t('loading') : cooldown > 0 ? t('refresh_in', { time: cooldownText }) : t('refresh_char')}
          </button>
          <span className="muted small">{t('updated_at', { date: dateTime(c.last_refresh_at) })}</span>
        </div>
      </section>

      {renameOpen && (
        <form className="card rename-card" onSubmit={rename}>
          <h2>{t('rename_title')}</h2>
          <p className="muted small">{t('rename_text', { name: c.name })}</p>
          <div className="inline-form">
            <input value={newName} onChange={(e) => setNewName(e.target.value)} placeholder={t('character_name')} required minLength={2} maxLength={29} />
            <button type="submit">{t('save')}</button>
          </div>
          {renameMsg && <p className="alert">{renameMsg}</p>}
        </form>
      )}

      {last && (
        <div className="metrics">
          <div className="metric">
            <span className="muted small">{t('level')}</span>
            <span className="metric-value">{fractionalLevel(last.level, last.exp).toFixed(exact ? 2 : 0)}</span>
          </div>
          <div className="metric">
            <span className="muted small">{t('experience')}</span>
            <span className="metric-value">{n(last.exp)}</span>
            {!exact && <span className="muted small">{t('exp_estimated')}</span>}
          </div>
          <div className="metric">
            <span className="muted small">{t('xp_day_7')}</span>
            <span className="metric-value">{report?.pace7 != null ? n(Math.round(report.pace7)) : '—'}</span>
          </div>
          <div className="metric">
            <span className="muted small">{t('xp_day_30')}</span>
            <span className="metric-value">{report?.pace30 != null ? n(Math.round(report.pace30)) : '—'}</span>
          </div>
          <div className="metric">
            <span className="muted small">Achievement points</span>
            <span className="metric-value">{n(c.achievement_points ?? 0)}</span>
          </div>
        </div>
      )}

      {/* Goal */}
      <section className="card">
        <div className="dest-head">
          <h2>{t('goal')}</h2>
          {data.goal && !editGoal && (
            <button
              type="button"
              className="secondary"
              onClick={() => {
                setTargetLevel(String(data.goal!.target_level));
                setTargetDate(data.goal!.target_date ?? '');
                setEditGoal(true);
              }}
            >
              {t('edit')}
            </button>
          )}
        </div>

        {data.goal && report && !editGoal && (
          <>
            <p className="goal-line">
              <strong>{t('goal_short', { level: data.goal.target_level })}</strong>
              {data.goal.target_date && <> · {t('goal_until', { date: date(new Date(`${data.goal.target_date}T12:00:00`).getTime()) })}</>}
              {report.achieved && <span className="tag premium">{t('goal_achieved')}</span>}
            </p>
            <Progress value={report.progress} label={t('goal')} />
            <p className="muted small">
              {t('goal_progress', {
                pct: Math.round(report.progress * 100),
                from: data.goal.start_level,
                remaining: n(report.remaining),
                levels: Math.max(0, data.goal.target_level - (last?.level ?? 0)),
              })}
            </p>

            {!report.achieved && (
              <div className="goal-grid">
                <div>
                  <span className="muted small">{t('eta')}</span>
                  <strong>
                    {report.etaDays != null
                      ? t('eta_value', { days: n(Math.ceil(report.etaDays)), date: date(report.etaDate!) })
                      : t('eta_unknown')}
                  </strong>
                </div>
                {report.requiredPerDay != null && (
                  <div>
                    <span className="muted small">{t('required_per_day')}</span>
                    <strong className={report.onTrack === false ? 'neg' : report.onTrack ? 'pos' : ''}>
                      {Number.isFinite(report.requiredPerDay) ? `${n(Math.round(report.requiredPerDay))} XP` : '—'}
                    </strong>
                  </div>
                )}
                {report.week.target != null && (
                  <div>
                    <span className="muted small">{t('week_goal')}</span>
                    <strong>
                      {compact(report.week.gained, locale)} / {compact(report.week.target, locale)}
                    </strong>
                    <Progress value={report.week.gained / report.week.target} label={t('week_goal')} />
                  </div>
                )}
                {report.month.target != null && (
                  <div>
                    <span className="muted small">{t('month_goal')}</span>
                    <strong>
                      {compact(report.month.gained, locale)} / {compact(report.month.target, locale)}
                    </strong>
                    <Progress value={report.month.gained / report.month.target} label={t('month_goal')} />
                  </div>
                )}
              </div>
            )}
            {!report.achieved && !data.goal.target_date && <p className="muted small">{t('goal_no_date_note')}</p>}
          </>
        )}

        {(!data.goal || editGoal) && (
          <form className="goal-form" onSubmit={saveGoal}>
            {!data.goal && <p className="muted small">{t('goal_intro')}</p>}
            <div className="goal-inputs">
              <label className="field">
                <span>{t('target_level')}</span>
                <input
                  type="number"
                  inputMode="numeric"
                  min={(last?.level ?? 1) + 1}
                  max={5000}
                  value={targetLevel}
                  onChange={(e) => setTargetLevel(e.target.value)}
                  placeholder={String((last?.level ?? 100) + 50)}
                  required
                />
              </label>
              <label className="field">
                <span>
                  {t('target_date')} ({t('optional')})
                </span>
                <input type="date" value={targetDate} onChange={(e) => setTargetDate(e.target.value)} />
              </label>
            </div>
            {targetLevel && last && Number(targetLevel) > last.level && (
              <p className="muted small">
                {t('goal_preview', {
                  levels: Number(targetLevel) - last.level,
                  xp: n(expForLevel(Number(targetLevel)) - last.exp),
                })}
              </p>
            )}
            {goalMsg && <p className="alert">{goalMsg}</p>}
            <div className="button-row">
              <button type="submit" disabled={!last}>
                {t('save')}
              </button>
              {editGoal && (
                <button type="button" className="secondary" onClick={() => setEditGoal(false)}>
                  {t('cancel')}
                </button>
              )}
              {editGoal && data.goal && (
                <button
                  type="button"
                  className="secondary danger"
                  onClick={() =>
                    characters.clearGoal(id).then(() => {
                      setEditGoal(false);
                      load();
                    })
                  }
                >
                  {t('remove_goal')}
                </button>
              )}
            </div>
          </form>
        )}
      </section>

      <WeeklyTasks characterId={id} />

      {tips.length > 0 && (
        <section className="card">
          <h2>{t('suggestions')}</h2>
          <ul className="plain-list bullets">
            {tips.map((s) => (
              <li key={s.key}>
                {t(s.key, {
                  ...s.vars,
                  ...(s.vars?.extra != null ? { extra: n(Number(s.vars.extra)) } : {}),
                  ...(s.vars?.loss != null ? { loss: n(Number(s.vars.loss)) } : {}),
                })}
                {s.key === 'sug_tips' && (
                  <>
                    {' '}
                    <a href="/spawnrank" onClick={linkHandler('/spawnrank')}>
                      SpawnRank
                    </a>
                  </>
                )}
              </li>
            ))}
          </ul>
        </section>
      )}

      {/* History */}
      <section className="card">
        <div className="dest-head">
          <h2>{t('history')}</h2>
          <div className="chips-row" role="group" aria-label={t('period')}>
            {([30, 90, 365, 0] as const).map((r) => (
              <button key={r} type="button" className={range === r ? 'chip active' : 'chip'} onClick={() => setRange(r)}>
                {r ? t('days_n', { n: r }) : t('all_time')}
              </button>
            ))}
          </div>
        </div>
        {snaps.length < 2 && <p className="muted small">{t('history_empty')}</p>}

        {linePoints.length > 0 && (
          <>
            <h3 className="small-heading">{t('chart_level')}</h3>
            <LineChart
              points={linePoints}
              formatY={(v) => n(Math.round(v * 10) / 10)}
              formatDate={shortDate}
              ariaLabel={t('chart_level')}
              goal={data.goal ? { y: data.goal.target_level, label: t('goal_short', { level: data.goal.target_level }) } : undefined}
              projection={projection}
              tooltip={(p) => {
                const s = linePoints.find((l) => l.t === p.t)!;
                return (
                  <>
                    <strong>{dateTime(new Date(p.t).toISOString())}</strong>
                    <div>{t('level_n', { level: p.y.toFixed(2) })}</div>
                    <div>{n(s.exp)} XP</div>
                  </>
                );
              }}
            />
            {projection && <p className="muted small chart-note">{t('projection_note')}</p>}
          </>
        )}

        {bars.length > 0 && (
          <>
            <h3 className="small-heading">{t('chart_xp_day')}</h3>
            <BarChart
              bars={bars}
              formatY={(v) => compact(v, locale)}
              ariaLabel={t('chart_xp_day')}
              target={report?.planPerDay && !report.achieved ? { y: report.planPerDay, label: t('needed_per_day') } : undefined}
              tooltip={(b) => {
                const bar = bars.find((x) => x.key === b.key)!;
                return (
                  <>
                    <strong>{b.label}</strong>
                    <div>{n(Math.round(b.value))} XP/{t('day')}</div>
                    {bar.days > 1 && <div className="muted">{t('avg_over_days', { n: bar.days })}</div>}
                  </>
                );
              }}
            />
          </>
        )}

        {snaps.length > 0 && (
          <details className="table-view">
            <summary>{t('show_table')}</summary>
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>{t('date')}</th>
                    <th className="num">{t('level')}</th>
                    <th className="num">{t('experience')}</th>
                    <th>{t('world')}</th>
                  </tr>
                </thead>
                <tbody>
                  {[...data.snapshots].reverse().map((s) => (
                    <tr key={s.taken_at}>
                      <td>{dateTime(s.taken_at)}</td>
                      <td className="num">{s.level}</td>
                      <td className="num">
                        {n(s.experience)}
                        {!s.exp_exact && '*'}
                      </td>
                      <td>{s.world ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="muted small">* {t('exp_estimated')}</p>
          </details>
        )}
      </section>

      {data.changes.length > 0 && (
        <section className="card">
          <h2>{t('char_changes')}</h2>
          <ul className="plain-list small">
            {data.changes.map((ch) => (
              <li key={ch.detected_at + ch.field}>
                <span className="muted">{dateTime(ch.detected_at)}</span> ·{' '}
                {t(ch.field === 'name' ? 'change_name' : 'change_world', { from: ch.old_value ?? '?', to: ch.new_value })}
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="card">
        <h2>{t('deaths')}</h2>
        {data.deaths.length === 0 ? (
          <p className="muted small">{t('no_deaths')}</p>
        ) : (
          <ul className="plain-list small deaths">
            {data.deaths.map((d) => (
              <li key={d.time}>
                <span className="muted">{dateTime(d.time)}</span> · {d.reason}
              </li>
            ))}
          </ul>
        )}
        <p className="muted small">{t('deaths_note')}</p>
      </section>

      <section className="card">
        <ShareButtons
          text={
            data.goal
              ? t('share_char_message', { name: c.name, level: c.level ?? '?', target: data.goal.target_level })
              : t('share_char_message_nogoal', { name: c.name, level: c.level ?? '?' })
          }
        />
      </section>

      <div className="button-row">
        <a className="button secondary" href={`https://www.tibia.com/community/?subtopic=characters&name=${encodeURIComponent(c.name)}`} target="_blank" rel="noopener noreferrer">
          {t('open_on_tibia')}
        </a>
        {!renameOpen && (
          <button type="button" className="secondary" onClick={() => setRenameOpen(true)}>
            {t('rename_title')}
          </button>
        )}
        <button type="button" className="secondary danger" onClick={removeCharacter}>
          {t('stop_tracking')}
        </button>
      </div>
    </div>
  );
}
