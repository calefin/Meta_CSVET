/**
 * Character progress maths (D-28): XP pace, goal ETA, weekly/monthly targets and suggestions.
 * Pure functions; snapshots come from /api/characters/:id.
 */

const DAY = 86_400_000;

/** Official Tibia formula: total experience at the start of `level`. */
export function expForLevel(level: number): number {
  const l = Math.max(1, Math.floor(level));
  return Math.round((50 / 3) * (l ** 3 - 6 * l ** 2 + 17 * l - 12));
}

/** Level with the fraction of progress into the next level (e.g. 351.42). */
export function fractionalLevel(level: number, exp: number): number {
  const lo = expForLevel(level);
  const hi = expForLevel(level + 1);
  if (exp <= lo || hi <= lo) return level;
  return level + Math.min(0.999, (exp - lo) / (hi - lo));
}

/** Experience lost on death without any blessing or promotion reduction (level ≥ 24). */
export function deathLossNoBless(level: number): number {
  if (level < 24) return Math.round(expForLevel(level) * 0.1);
  return Math.round(((level + 50) / 100) * 50 * (level ** 2 - 5 * level + 8));
}

export interface Snap {
  t: number; // ms
  level: number;
  exp: number;
}

export interface Goal {
  target_level: number;
  target_date: string | null; // YYYY-MM-DD
  start_level: number;
  start_exp: number;
  created_at: string;
  achieved_at: string | null;
}

/** Exp of the last snapshot at or before `t`, else the first snapshot after it. */
function baselineAt(snaps: Snap[], t: number): Snap | null {
  let base: Snap | null = null;
  for (const s of snaps) {
    if (s.t <= t) base = s;
    else break;
  }
  return base ?? snaps[0] ?? null;
}

/** Average XP/day over the last `days` (null when the history is shorter than 12 h). */
export function paceOver(snaps: Snap[], days: number, now = Date.now()): number | null {
  if (snaps.length < 2) return null;
  const last = snaps[snaps.length - 1];
  const base = baselineAt(snaps, Math.min(now, last.t) - days * DAY);
  if (!base || base === last) return null;
  const span = (last.t - base.t) / DAY;
  if (span < 0.5) return null;
  return Math.max(0, (last.exp - base.exp) / span);
}

/** XP gained since `t` (0 when there is no snapshot after it). */
export function gainedSince(snaps: Snap[], t: number): number {
  if (!snaps.length) return 0;
  const last = snaps[snaps.length - 1];
  const base = baselineAt(snaps, t);
  if (!base || last.t < t) return 0;
  return Math.max(0, last.exp - base.exp);
}

/** XP per day between consecutive days with snapshots (bar chart). */
export function dailyRates(snaps: Snap[]): { day: string; t: number; xp: number; days: number }[] {
  const lastOfDay = new Map<string, Snap>();
  for (const s of snaps) lastOfDay.set(localDay(s.t), s);
  const days = [...lastOfDay.entries()];
  const out: { day: string; t: number; xp: number; days: number }[] = [];
  for (let i = 1; i < days.length; i++) {
    const [day, s] = days[i];
    const prev = days[i - 1][1];
    const span = Math.max(1, Math.round((startOfDay(s.t) - startOfDay(prev.t)) / DAY));
    out.push({ day, t: s.t, xp: Math.max(0, (s.exp - prev.exp) / span), days: span });
  }
  return out;
}

export function localDay(t: number): string {
  const d = new Date(t);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function startOfDay(t: number): number {
  const d = new Date(t);
  d.setHours(0, 0, 0, 0);
  return d.getTime();
}

/** Monday 00:00 local time of the week containing `t`. */
export function startOfWeek(t: number): number {
  const d = new Date(startOfDay(t));
  const dow = (d.getDay() + 6) % 7; // Monday = 0
  d.setDate(d.getDate() - dow);
  return d.getTime();
}

export function startOfMonth(t: number): number {
  const d = new Date(startOfDay(t));
  d.setDate(1);
  return d.getTime();
}

function daysInMonth(t: number): number {
  const d = new Date(t);
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
}

export interface Suggestion {
  key: string;
  vars?: Record<string, string | number>;
}

export interface GoalReport {
  targetExp: number;
  currentExp: number;
  remaining: number;
  /** 0..1 since the goal was set */
  progress: number;
  pace7: number | null;
  pace30: number | null;
  /** pace used for the ETA: 7 days, else 30 days, else whole history */
  pace: number | null;
  etaDays: number | null;
  etaDate: number | null;
  daysLeft: number | null;
  requiredPerDay: number | null;
  onTrack: boolean | null;
  /** Plan per day used for the weekly/monthly targets */
  planPerDay: number | null;
  week: { gained: number; target: number | null };
  month: { gained: number; target: number | null };
  achieved: boolean;
}

export function goalReport(goal: Goal, snaps: Snap[], now = Date.now()): GoalReport | null {
  const last = snaps[snaps.length - 1];
  if (!last) return null;
  const targetExp = expForLevel(goal.target_level);
  const remaining = Math.max(0, targetExp - last.exp);
  const total = Math.max(1, targetExp - goal.start_exp);
  const progress = Math.min(1, Math.max(0, (last.exp - goal.start_exp) / total));

  const pace7 = paceOver(snaps, 7, now);
  const pace30 = paceOver(snaps, 30, now);
  const pace = pace7 ?? pace30 ?? paceOver(snaps, 36500, now);
  const etaDays = pace && pace > 0 ? remaining / pace : null;

  let daysLeft: number | null = null;
  let requiredPerDay: number | null = null;
  if (goal.target_date) {
    const end = new Date(`${goal.target_date}T23:59:59`).getTime();
    daysLeft = Math.max(0, (end - now) / DAY);
    requiredPerDay = daysLeft > 0 ? remaining / daysLeft : remaining > 0 ? Infinity : 0;
  }
  const achieved = remaining === 0 || !!goal.achieved_at;
  const onTrack =
    achieved ? true : requiredPerDay == null || pace == null ? null : pace >= requiredPerDay;
  const planPerDay = requiredPerDay != null && Number.isFinite(requiredPerDay) ? requiredPerDay : pace30 ?? pace;

  return {
    targetExp,
    currentExp: last.exp,
    remaining,
    progress: achieved ? 1 : progress,
    pace7,
    pace30,
    pace,
    etaDays,
    etaDate: etaDays != null ? now + etaDays * DAY : null,
    daysLeft,
    requiredPerDay,
    onTrack,
    planPerDay,
    week: { gained: gainedSince(snaps, startOfWeek(now)), target: planPerDay != null ? planPerDay * 7 : null },
    month: {
      gained: gainedSince(snaps, startOfMonth(now)),
      target: planPerDay != null ? planPerDay * daysInMonth(now) : null,
    },
    achieved,
  };
}

/** Rule-based tips shown under the goal (i18n keys with variables). */
export function suggestions(
  report: GoalReport | null,
  snaps: Snap[],
  deaths: { time: string; level: number }[],
  now = Date.now(),
): Suggestion[] {
  const out: Suggestion[] = [];
  const last = snaps[snaps.length - 1];
  if (!last) return out;

  if (now - last.t > 3 * DAY) out.push({ key: 'sug_refresh', vars: { days: Math.floor((now - last.t) / DAY) } });
  if (report && !report.achieved && report.pace == null) out.push({ key: 'sug_need_data' });

  if (report && !report.achieved && report.requiredPerDay != null && report.pace != null) {
    if (!Number.isFinite(report.requiredPerDay) || report.pace < report.requiredPerDay) {
      const extra = Number.isFinite(report.requiredPerDay) ? report.requiredPerDay - report.pace : null;
      out.push({ key: 'sug_behind', vars: { extra: extra != null ? Math.round(extra) : 0 } });
      out.push({ key: 'sug_tips' });
    } else {
      out.push({ key: 'sug_ahead' });
    }
  }

  if (report && report.pace7 != null && report.pace30 != null && report.pace30 > 0 && report.pace7 < report.pace30 * 0.75) {
    out.push({ key: 'sug_slowdown', vars: { pct: Math.round((1 - report.pace7 / report.pace30) * 100) } });
  }

  const recent = deaths.filter((d) => now - new Date(d.time).getTime() <= 30 * DAY);
  if (recent.length) {
    const loss = recent.reduce((s, d) => s + deathLossNoBless(d.level), 0);
    out.push({ key: 'sug_deaths', vars: { count: recent.length, loss } });
  }
  return out;
}
