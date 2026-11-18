/**
 * Yasir sightings (D-40): weighted, consensus-based status per world and server day.
 *
 * Anyone signed in may report; each report carries a weight:
 *   - verified character on that world (D-28) → 2   (one report is enough to confirm)
 *   - verified character on another world     → 1
 *   - otherwise by account age at report time → 0.75 (≥ 14 days) · 0.5 (≥ 2 days) · 0.25
 * multiplied by the reporter's reputation (agreement with past confirmed days, Laplace-smoothed) and 0 when
 * an admin blocked the reporter. Status of a world/day:
 *   - confirmed:   best city has ≥ 2 weight and more than twice everything against it
 *   - contested:   something against it (other cities or "not there") and not confirmed
 *   - unconfirmed: best city has < 2 weight, nothing against
 *   - absent:      only "not there" reports
 * Only confirmed days feed the public statistics.
 */

/** Cities where Yasir shows up (TibiaWiki); "other" covers anything new */
export const YASIR_CITIES = ['Ankrahmun', 'Carlin', 'Liberty Bay', 'other'] as const;
export type YasirCity = (typeof YASIR_CITIES)[number];
export const isYasirCity = (raw: unknown): raw is YasirCity =>
  typeof raw === 'string' && (YASIR_CITIES as readonly string[]).includes(raw);

export const CONFIRM_WEIGHT = 2;
const DAY_MS = 86_400_000;

export interface YasirReport {
  day: string; // YYYY-MM-DD server day
  world: string;
  userId: number;
  present: boolean;
  city: YasirCity | null;
  reportedAt: Date;
  accountCreatedAt: Date;
  verifiedOnWorld: boolean;
  verifiedAnywhere: boolean;
  blocked: boolean;
}

export type YasirStatus = 'confirmed' | 'contested' | 'unconfirmed' | 'absent' | 'none';

export interface DayOutcome {
  day: string;
  world: string;
  status: YasirStatus;
  city: YasirCity | null;
  support: number;
  against: number;
  reports: number;
}

/** Weight before reputation */
export function baseWeight(r: Pick<YasirReport, 'verifiedOnWorld' | 'verifiedAnywhere' | 'reportedAt' | 'accountCreatedAt' | 'blocked'>): number {
  if (r.blocked) return 0;
  if (r.verifiedOnWorld) return 2;
  if (r.verifiedAnywhere) return 1;
  const ageDays = (r.reportedAt.getTime() - r.accountCreatedAt.getTime()) / DAY_MS;
  return ageDays >= 14 ? 0.75 : ageDays >= 2 ? 0.5 : 0.25;
}

/** Reputation in (0, 1]: (agreed + 1) / (agreed + contradicted + 1) */
export const reputation = (agreed: number, contradicted: number): number => (agreed + 1) / (agreed + contradicted + 1);

const round = (n: number) => Math.round(n * 100) / 100;

/** Status of one world/day from its reports and a weight function */
export function outcomeOf(reports: YasirReport[], weight: (r: YasirReport) => number): DayOutcome {
  const { day, world } = reports[0];
  const byCity = new Map<YasirCity, number>();
  let absent = 0;
  for (const r of reports) {
    const w = weight(r);
    if (r.present && r.city) byCity.set(r.city, (byCity.get(r.city) ?? 0) + w);
    else if (!r.present) absent += w;
  }
  let city: YasirCity | null = null;
  let support = 0;
  for (const [c, w] of byCity) {
    if (w > support) [city, support] = [c, w];
  }
  const presentTotal = [...byCity.values()].reduce((a, b) => a + b, 0);
  const against = presentTotal - support + absent;
  let status: YasirStatus;
  if (support === 0) status = absent > 0 ? 'absent' : 'none';
  else if (support >= CONFIRM_WEIGHT && support > 2 * against) status = 'confirmed';
  else if (against > 0) status = 'contested';
  else status = 'unconfirmed';
  return { day, world, status, city: support > 0 ? city : null, support: round(support), against: round(against), reports: reports.length };
}

const groupKey = (r: YasirReport) => `${r.day}|${r.world}`;

function groupByDayWorld(reports: YasirReport[]): YasirReport[][] {
  const groups = new Map<string, YasirReport[]>();
  for (const r of reports) {
    const k = groupKey(r);
    const g = groups.get(k);
    if (g) g.push(r);
    else groups.set(k, [r]);
  }
  return [...groups.values()];
}

/**
 * Reputation per user from days before `today`, judged against the consensus computed WITHOUT reputation
 * (base weights only), so scores cannot feed on themselves. Only confirmed days count.
 */
export function reputations(reports: YasirReport[], today: string): Map<number, number> {
  const tally = new Map<number, { ok: number; bad: number }>();
  for (const group of groupByDayWorld(reports.filter((r) => r.day < today))) {
    const out = outcomeOf(group, baseWeight);
    if (out.status !== 'confirmed') continue;
    for (const r of group) {
      const t = tally.get(r.userId) ?? { ok: 0, bad: 0 };
      if (r.present && r.city === out.city) t.ok++;
      else t.bad++;
      tally.set(r.userId, t);
    }
  }
  return new Map([...tally].map(([id, t]) => [id, reputation(t.ok, t.bad)]));
}

/** Final outcomes (base weight × reputation) for every world/day in `reports` */
export function outcomes(reports: YasirReport[], today: string): DayOutcome[] {
  const rep = reputations(reports, today);
  const weight = (r: YasirReport) => baseWeight(r) * (rep.get(r.userId) ?? 1);
  return groupByDayWorld(reports)
    .map((g) => outcomeOf(g, weight))
    .sort((a, b) => (a.day === b.day ? a.world.localeCompare(b.world) : b.day.localeCompare(a.day)));
}

export interface YasirStats {
  confirmedDays: number;
  byWorld: { world: string; days: number; lastDay: string }[];
  byCity: Record<string, number>;
  /** 0 = Monday … 6 = Sunday (server day) */
  byWeekday: number[];
  /** Average days between confirmed visits, per world with ≥ 2 visits */
  avgGapDays: { world: string; days: number }[];
}

/** Patterns over confirmed sightings only */
export function stats(list: DayOutcome[]): YasirStats {
  const confirmed = list.filter((o) => o.status === 'confirmed');
  const byWorld = new Map<string, string[]>();
  const byCity: Record<string, number> = {};
  const byWeekday = [0, 0, 0, 0, 0, 0, 0];
  for (const o of confirmed) {
    byWorld.set(o.world, [...(byWorld.get(o.world) ?? []), o.day]);
    if (o.city) byCity[o.city] = (byCity[o.city] ?? 0) + 1;
    byWeekday[(new Date(`${o.day}T12:00:00Z`).getUTCDay() + 6) % 7]++;
  }
  const worlds = [...byWorld].map(([world, days]) => ({ world, days: [...new Set(days)].sort() }));
  return {
    confirmedDays: confirmed.length,
    byWorld: worlds
      .map(({ world, days }) => ({ world, days: days.length, lastDay: days[days.length - 1] }))
      .sort((a, b) => b.days - a.days || b.lastDay.localeCompare(a.lastDay)),
    byCity,
    byWeekday,
    avgGapDays: worlds
      .filter(({ days }) => days.length >= 2)
      .map(({ world, days }) => {
        const span = (Date.parse(days[days.length - 1]) - Date.parse(days[0])) / DAY_MS;
        return { world, days: round(span / (days.length - 1)) };
      })
      .sort((a, b) => a.days - b.days),
  };
}
