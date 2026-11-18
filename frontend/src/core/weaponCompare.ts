/**
 * Weapon comparison (D-72): which weapon does more damage, from my own hunts. With the Server Log: damage per
 * minute in combat (minutes where I hit something), per hit, crit rate and crit size. Without it: the Hunt
 * Analyser damage per hour (includes walking and waiting, so it is only a rough guide).
 */
export interface WeaponHunt {
  public_id: string;
  place_name: string | null;
  place_slug: string | null;
  character_name: string | null;
  duration_s: number;
  damage: number;
  damage_h: number;
  weapon: { name: string; tier?: number; imbues?: { type: string; tier: number }[] } | null;
  dealt: { total: number; hits: number; crits: number; crit_total: number; by_target?: { name: string; value: number; pct: number }[] } | null;
  combat_minutes: number;
}

export interface WeaponStats {
  key: string;
  label: string;
  hunts: number;
  logHunts: number;
  hits: number;
  /** damage per minute in combat (Server Log), null without logs */
  perCombatMin: number | null;
  perHit: number | null;
  critRate: number | null;
  /** how much bigger a critical hit is than a normal one, in % */
  critBonus: number | null;
  /** Hunt Analyser damage per hour, time-weighted (all hunts) */
  damageH: number;
  enoughData: boolean;
  /** % against the reference weapon (the most used one) */
  diff: number | null;
}

export const MIN_HITS = 300;

export function weaponKey(w: WeaponHunt['weapon']): string {
  if (!w) return '';
  const imb = [...(w.imbues ?? [])].map((i) => `${i.type}${i.tier}`).sort().join('+');
  return `${w.name.toLowerCase()}|${w.tier ?? 0}|${imb}`;
}

export function weaponLabel(w: NonNullable<WeaponHunt['weapon']>): string {
  const imb = (w.imbues ?? []).map((i) => `${i.type} ${['I', 'II', 'III'][i.tier - 1] ?? ''}`.trim()).join(', ');
  return `${w.name}${w.tier ? ` T${w.tier}` : ''}${imb ? ` (${imb})` : ''}`;
}

export function compareWeapons(hunts: WeaponHunt[], place?: string | null): WeaponStats[] {
  const groups = new Map<string, WeaponHunt[]>();
  for (const h of hunts) {
    if (!h.weapon?.name) continue;
    if (place && (h.place_slug ?? h.place_name) !== place) continue;
    const k = weaponKey(h.weapon);
    groups.set(k, [...(groups.get(k) ?? []), h]);
  }
  const out: WeaponStats[] = [...groups].map(([key, list]) => {
    const logs = list.filter((h) => h.dealt && h.dealt.hits > 0 && h.combat_minutes > 0);
    const total = logs.reduce((s, h) => s + h.dealt!.total, 0);
    const hits = logs.reduce((s, h) => s + h.dealt!.hits, 0);
    const crits = logs.reduce((s, h) => s + h.dealt!.crits, 0);
    const critTotal = logs.reduce((s, h) => s + h.dealt!.crit_total, 0);
    const minutes = logs.reduce((s, h) => s + h.combat_minutes, 0);
    const normalAvg = hits - crits > 0 ? (total - critTotal) / (hits - crits) : 0;
    const critAvg = crits > 0 ? critTotal / crits : 0;
    const secs = list.reduce((s, h) => s + h.duration_s, 0) || 1;
    return {
      key,
      label: weaponLabel(list[0].weapon!),
      hunts: list.length,
      logHunts: logs.length,
      hits,
      perCombatMin: minutes ? Math.round(total / minutes) : null,
      perHit: hits ? Math.round(total / hits) : null,
      critRate: hits ? Math.round((crits / hits) * 1000) / 10 : null,
      critBonus: normalAvg && critAvg ? Math.round((critAvg / normalAvg - 1) * 100) : null,
      damageH: Math.round(list.reduce((s, h) => s + h.damage_h * h.duration_s, 0) / secs),
      enoughData: hits >= MIN_HITS,
      diff: null,
    };
  });
  out.sort((a, b) => b.hunts - a.hunts || a.label.localeCompare(b.label));
  const ref = out[0];
  if (ref) {
    for (const w of out) {
      const a = w.perCombatMin ?? null;
      const r = ref.perCombatMin ?? null;
      if (a != null && r) w.diff = Math.round((a / r - 1) * 1000) / 10;
      else if (ref.damageH) w.diff = Math.round((w.damageH / ref.damageH - 1) * 1000) / 10;
    }
  }
  return out;
}
