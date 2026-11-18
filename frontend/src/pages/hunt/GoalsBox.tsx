/** Weekly goals with streak (D-53): XP or profit per week, per character or for all of them. */
import { useEffect, useState } from 'react';
import { ApiError, hunts, type HuntGoal } from '../../api';
import { useI18n } from '../../i18n';
import { compact } from '../../components/Charts';
import { gp } from './common';

/** "5kk" / "500k" / "5000000" → number */
export function parseAmount(v: string): number | null {
  const m = v.trim().toLowerCase().replace(',', '.').match(/^(\d+(?:\.\d+)?)\s*(kkk|kk|k|m|b)?$/);
  if (!m) return null;
  const mult = { k: 1e3, kk: 1e6, m: 1e6, kkk: 1e9, b: 1e9 }[m[2] as 'k'] ?? 1;
  const n = Math.round(parseFloat(m[1]) * mult);
  return n > 0 ? n : null;
}

export function GoalsBox({ characters, onChange }: { characters: string[]; onChange?: () => void }) {
  const { t, locale } = useI18n();
  const [goals, setGoals] = useState<HuntGoal[] | null>(null);
  const [open, setOpen] = useState(false);
  const [metric, setMetric] = useState<'xp' | 'profit'>('xp');
  const [amount, setAmount] = useState('');
  const [char, setChar] = useState('');
  const [msg, setMsg] = useState('');

  const load = () =>
    hunts
      .goals()
      .then((r) => setGoals(r.goals))
      .catch(() => setGoals([]));
  useEffect(() => {
    void load();
  }, []);

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    const target = parseAmount(amount);
    if (!target) {
      setMsg(t('goal_err_amount'));
      return;
    }
    try {
      await hunts.setGoal(metric, target, char || null);
      setAmount('');
      setOpen(false);
      setMsg('');
      void load();
      onChange?.();
    } catch (err) {
      setMsg(err instanceof ApiError && err.code === 'goal_limit' ? t('goal_err_limit') : t('ha_err_generic'));
    }
  };

  const fmt = (g: HuntGoal, v: number) => (g.metric === 'xp' ? compact(v, locale) : gp(v, locale));

  return (
    <section className="card goals-box">
      <div className="section-head">
        <h3>🎯 {t('goal_title')}</h3>
        <button type="button" className="button secondary" onClick={() => setOpen((o) => !o)}>
          {open ? t('cancel') : `＋ ${t('goal_new')}`}
        </button>
      </div>
      {open && (
        <form className="inline-form goal-form" onSubmit={save}>
          <select value={metric} onChange={(e) => setMetric(e.target.value as 'xp' | 'profit')} aria-label={t('ha_metric')}>
            <option value="xp">{t('goal_metric_xp')}</option>
            <option value="profit">{t('goal_metric_profit')}</option>
          </select>
          <input value={amount} onChange={(e) => setAmount(e.target.value)} placeholder={metric === 'xp' ? '20kk' : '5kk'} aria-label={t('goal_amount')} />
          <select value={char} onChange={(e) => setChar(e.target.value)} aria-label={t('nav_characters')}>
            <option value="">{t('ha_all_characters')}</option>
            {characters.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </select>
          <button type="submit">{t('goal_save')}</button>
        </form>
      )}
      {msg && <p className="error small">{msg}</p>}
      {goals === null ? null : !goals.length ? (
        <p className="muted small">{t('goal_empty')}</p>
      ) : (
        <ul className="goal-list">
          {goals.map((g) => (
            <li key={g.id} className={g.reached ? 'reached' : ''}>
              <div className="goal-head">
                <strong>
                  {g.metric === 'xp' ? t('goal_metric_xp') : t('goal_metric_profit')}: {fmt(g, g.target)}
                  <span className="muted small"> · {g.character_name ?? t('ha_all_characters')}</span>
                </strong>
                <span className={`streak${g.streak ? ' on' : ''}`} title={t('goal_best', { n: g.best_streak })}>
                  🔥 {t('goal_streak', { n: g.streak })}
                </span>
              </div>
              <div className="goal-bar" role="progressbar" aria-valuenow={g.pct} aria-valuemin={0} aria-valuemax={100}>
                <span style={{ width: `${Math.max(2, g.pct)}%` }} />
              </div>
              <div className="goal-foot muted small">
                <span>
                  {t('goal_this_week')}: {fmt(g, g.current)} ({g.pct}%){g.reached ? ` · ✓ ${t('goal_reached')}` : ''}
                </span>
                <span className="goal-weeks" aria-label={t('goal_last_weeks')}>
                  {g.history.map((w) => (
                    <i key={w.week} className={w.reached ? 'ok' : w.value > 0 ? 'part' : ''} title={`${w.week}: ${fmt(g, w.value)}`} />
                  ))}
                </span>
                <button
                  type="button"
                  className="icon-btn"
                  title={t('delete')}
                  onClick={() => {
                    if (window.confirm(t('goal_confirm_delete'))) void hunts.removeGoal(g.id).then(load);
                  }}
                >
                  🗑️
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
