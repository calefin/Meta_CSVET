/**
 * Server Log summary saved with a hunt (D-64). The site parses the log in the browser and sends the summary;
 * the API used to keep it as it came (size check only). A crafted public hunt could then carry objects where
 * text is expected and break the page of whoever opens it. Now the summary is rebuilt field by field: only the
 * known shape, plain strings and finite numbers, bounded lists.
 */

type Obj = Record<string, unknown>;
const obj = (v: unknown): Obj => (v && typeof v === 'object' && !Array.isArray(v) ? (v as Obj) : {});
const arr = (v: unknown, max: number): Obj[] => (Array.isArray(v) ? v.slice(0, max).map(obj) : []);
const str = (v: unknown, max: number): string | null => (typeof v === 'string' ? v.replace(/[\u0000-\u001f]/g, ' ').slice(0, max) : null);
const num = (v: unknown, max = 1e13): number => {
  const n = typeof v === 'number' ? v : Number(v);
  return Number.isFinite(n) ? Math.max(-max, Math.min(max, n)) : 0;
};
const nameCount = (v: unknown, max: number) => arr(v, max).map((x) => ({ name: str(x.name, 80) ?? '?', count: num(x.count) }));
const shares = (v: unknown, max: number) => arr(v, max).map((x) => ({ name: str(x.name, 80) ?? '?', value: num(x.value), pct: num(x.pct, 100) }));
const trade = (v: unknown) => arr(v, 300).map((x) => ({ name: str(x.name, 80) ?? '?', count: num(x.count), gold: num(x.gold) }));

export function sanitizeServerLog(v: unknown): Obj | null {
  if (!v || typeof v !== 'object' || Array.isArray(v)) return null;
  const s = v as Obj;
  const xp = obj(s.xp);
  const dealt = obj(s.dealt);
  const taken = obj(s.taken);
  const healing = obj(s.healing);
  const boosted = obj(s.boosted);
  const tr = obj(s.trade);
  return {
    saved_at: str(s.saved_at, 40),
    sessions: num(s.sessions, 1000),
    first_time: str(s.first_time, 8),
    last_time: str(s.last_time, 8),
    minutes: num(s.minutes, 100_000),
    vocation: str(s.vocation, 20),
    xp: { total: num(xp.total), kills: num(xp.kills), animus: num(xp.animus) },
    level_ups: arr(s.level_ups, 200).map((x) => ({ from: num(x.from, 10_000), to: num(x.to, 10_000) })),
    skills: arr(s.skills, 200).map((x) => ({ skill: str(x.skill, 40) ?? '?', from: str(x.from, 30) ?? '', to: str(x.to, 30) ?? '' })),
    dealt: { total: num(dealt.total), hits: num(dealt.hits), crits: num(dealt.crits), crit_total: num(dealt.crit_total), by_target: shares(dealt.by_target, 100) },
    taken: { total: num(taken.total), hits: num(taken.hits), by_source: shares(taken.by_source, 100), mana_shield: num(taken.mana_shield) },
    healing: { self: num(healing.self), received: num(healing.received), mana: num(healing.mana) },
    supplies: nameCount(s.supplies, 200),
    loot: arr(s.loot, 300).map((x) => ({ name: str(x.name, 80) ?? '?', corpses: num(x.corpses), empty: num(x.empty), items: nameCount(x.items, 150) })),
    reward_chest: arr(s.reward_chest, 50).map((x) => ({ boss: str(x.boss, 80) ?? '?', items: nameCount(x.items, 150) })),
    trade: { sold: trade(tr.sold), bought: trade(tr.bought) },
    effects: nameCount(s.effects, 100),
    boosted: { creature: str(boosted.creature, 80), boss: str(boosted.boss, 80) },
    timeline: arr(s.timeline, 1500).map((x) => ({ t: str(x.t, 5) ?? '', xp: num(x.xp), dealt: num(x.dealt), taken: num(x.taken) })),
  };
}
