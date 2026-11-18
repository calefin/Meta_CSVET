/**
 * Weekly hunt goals with a streak (D-53): "earn 5kk XP a week with this character" / "make 10kk profit a week".
 * Weeks start on Monday (ISO). The current week counts for the streak only once it is reached; a missed past
 * week resets it. Values come from the saved hunt sessions (client time of the hunt).
 */
export type GoalMetric = 'xp' | 'profit';

export interface WeekValue {
  /** Monday of the week, "YYYY-MM-DD" */
  week: string;
  value: number;
}

/** Monday (UTC date) of the ISO week that contains `d` */
export function weekStart(d: Date): string {
  const day = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()));
  const dow = (day.getUTCDay() + 6) % 7; // 0 = Monday
  day.setUTCDate(day.getUTCDate() - dow);
  return day.toISOString().slice(0, 10);
}

const addWeeks = (week: string, n: number) => {
  const d = new Date(`${week}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + 7 * n);
  return d.toISOString().slice(0, 10);
};

export interface GoalProgress {
  current_week: string;
  current: number;
  target: number;
  pct: number;
  reached: boolean;
  /** Consecutive weeks reached, ending with the current one (if reached) or the last one */
  streak: number;
  best_streak: number;
  /** Last 12 weeks, oldest first, for the bar chart */
  history: { week: string; value: number; reached: boolean }[];
}

export function goalProgress(weeks: WeekValue[], target: number, now = new Date()): GoalProgress {
  const byWeek = new Map(weeks.map((w) => [w.week, w.value]));
  const current = weekStart(now);
  const value = (w: string) => byWeek.get(w) ?? 0;
  const reached = (w: string) => target > 0 && value(w) >= target;

  let streak = reached(current) ? 1 : 0;
  for (let w = addWeeks(current, -1); reached(w); w = addWeeks(w, -1)) streak++;

  // Best streak over everything we have
  let best = 0;
  let run = 0;
  const first = weeks.length ? weeks.map((w) => w.week).sort()[0] : current;
  for (let w = first; w <= current; w = addWeeks(w, 1)) {
    run = reached(w) ? run + 1 : 0;
    best = Math.max(best, run);
  }

  const history = Array.from({ length: 12 }, (_, i) => {
    const w = addWeeks(current, i - 11);
    return { week: w, value: value(w), reached: reached(w) };
  });
  const cur = value(current);
  return {
    current_week: current,
    current: cur,
    target,
    pct: target > 0 ? Math.min(100, Math.round((cur / target) * 100)) : 0,
    reached: reached(current),
    streak,
    best_streak: Math.max(best, streak),
    history,
  };
}
