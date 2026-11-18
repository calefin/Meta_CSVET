/**
 * Minute timeline of a hunt (Server Log): the log only has minutes with events, so the chart fills the
 * gaps with zeros (crossing midnight) and draws a short moving average so the line reads as a trend.
 */
export interface MinutePoint {
  t: string;
  xp: number;
  dealt: number;
  taken: number;
}

export interface FilledMinute extends MinutePoint {
  /** minutes since the first point */
  i: number;
}

export const MAX_TIMELINE_MINUTES = 12 * 60;

export function fillMinutes(points: MinutePoint[]): FilledMinute[] {
  const out: FilledMinute[] = [];
  let day = 0;
  let prev = -1;
  let first: number | null = null;
  const byIndex = new Map<number, MinutePoint>();
  for (const p of points) {
    const m = /^(\d{1,2}):(\d{2})$/.exec(p.t);
    if (!m) continue;
    let abs = Number(m[1]) * 60 + Number(m[2]) + day;
    if (prev >= 0 && abs < prev - 60) {
      day += 1440; // crossed midnight
      abs += 1440;
    }
    prev = abs;
    if (first == null) first = abs;
    const i = abs - first;
    if (i < 0 || i > MAX_TIMELINE_MINUTES) continue;
    const cur = byIndex.get(i);
    byIndex.set(i, cur ? { ...cur, xp: cur.xp + p.xp, dealt: cur.dealt + p.dealt, taken: cur.taken + p.taken } : p);
  }
  if (first == null) return out;
  const last = Math.max(...byIndex.keys());
  for (let i = 0; i <= last; i++) {
    const p = byIndex.get(i);
    const abs = (first + i) % 1440;
    const t = `${String(Math.floor(abs / 60)).padStart(2, '0')}:${String(abs % 60).padStart(2, '0')}`;
    out.push({ i, t, xp: p?.xp ?? 0, dealt: p?.dealt ?? 0, taken: p?.taken ?? 0 });
  }
  return out;
}

/** Centered moving average (window in points; 1 = raw) */
export function smooth(values: number[], window: number): number[] {
  if (window <= 1) return values.slice();
  const half = Math.floor(window / 2);
  return values.map((_, i) => {
    let sum = 0;
    let n = 0;
    for (let j = Math.max(0, i - half); j <= Math.min(values.length - 1, i + half); j++) {
      sum += values[j];
      n++;
    }
    return sum / n;
  });
}
