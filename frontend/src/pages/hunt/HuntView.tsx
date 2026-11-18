/** One hunt session, as numbers, insights and charts (D-49). Used by the analyser preview and the detail page. */
import { useMemo, useState } from 'react';
import { useI18n } from '../../i18n';
import { linkHandler } from '../../router';
import { defaultLookupDb, findItem } from '../../core/items';
import { computeLootSplit } from '../../core/party';
import { balancesWithExpenses, expensesTotal, expenseValue, type Expense } from '../../core/build';
import { huntInsights, npcLootValue, type HuntData, type InputAnalysis, type ServerLogSummary } from '../../core/huntLog';
import { ItemSprite, useSprites } from '../../components/ItemSprite';
import { SeriesChart, ShareBars, compact } from '../../components/Charts';
import { fillMinutes, smooth } from '../../core/timeline';
import { CopyText } from '../../components/CopyText';
import { fmtDuration, gp } from './common';
import { InputDamage } from './InputDamage';

type Avg = { xp_h: number; balance_h: number; count: number } | null;

export function Kpi({ label, value, sub, tone }: { label: string; value: string; sub?: string; tone?: 'pos' | 'neg' }) {
  return (
    <div className="kpi">
      <span className="kpi-label">{label}</span>
      <strong className={tone ?? ''}>{value}</strong>
      {sub && <span className="muted small">{sub}</span>}
    </div>
  );
}

export function HuntView({
  hunt,
  input,
  serverLog,
  premium,
  avg,
  expenses,
}: {
  hunt: HuntData;
  input?: InputAnalysis | null;
  serverLog?: ServerLogSummary | null;
  premium: boolean;
  avg?: Avg;
  /** D-59: extra expenses come off the final profit and the party split */
  expenses?: Expense[] | null;
}) {
  const { t, n, locale } = useI18n();
  const sprites = useSprites();
  const insights = useMemo(() => huntInsights(hunt, avg ?? null, input ?? null), [hunt, avg, input]);
  const npc = useMemo(() => npcLootValue(hunt.items, defaultLookupDb), [hunt.items]);
  const hours = Math.max(1, hunt.duration_s) / 3600;
  const kills = hunt.monsters.reduce((s, m) => s + m.count, 0);
  const extra = expensesTotal(expenses ?? []);
  const final = hunt.balance - extra;

  const lootRows = useMemo(
    () =>
      hunt.items
        .map((it) => {
          const info = /^(gold|platinum|crystal) coins?$/i.test(it.name) ? null : findItem(it.name, defaultLookupDb);
          const unit = /^gold coins?$/i.test(it.name) ? 1 : /^platinum coins?$/i.test(it.name) ? 100 : /^crystal coins?$/i.test(it.name) ? 10000 : info?.npc_price ?? 0;
          return { ...it, unit, total: unit * it.count, known: unit > 0 || !!info };
        })
        .sort((a, b) => b.total - a.total),
    [hunt.items],
  );

  return (
    <div className="hunt-view">
      <div className="kpi-grid">
        <Kpi label={t('ha_xp_h')} value={n(hunt.xp_h)} sub={`${t('ha_raw')}: ${n(hunt.raw_xp_h)}`} />
        <Kpi
          label={t('ha_balance_h')}
          value={gp(hunt.balance_h, locale)}
          sub={`${t('ha_balance')}: ${gp(hunt.balance, locale)}`}
          tone={hunt.balance_h >= 0 ? 'pos' : 'neg'}
        />
        {extra > 0 && (
          <Kpi
            label={t('hs_final_profit')}
            value={gp(final, locale)}
            sub={`${t('hs_expenses')}: ${gp(extra, locale)} · ${gp(Math.round(final / hours), locale)}/h`}
            tone={final >= 0 ? 'pos' : 'neg'}
          />
        )}
        <Kpi label={t('ha_xp_total')} value={n(hunt.xp)} sub={`${t('ha_raw')}: ${n(hunt.raw_xp)}`} />
        <Kpi label={t('ha_loot')} value={gp(hunt.loot, locale)} sub={`${t('ha_supplies')}: ${gp(hunt.supplies, locale)}`} />
        <Kpi label={t('ha_damage_h')} value={compact(hunt.damage_h, locale)} sub={`${t('ha_healing_h')}: ${compact(hunt.healing_h, locale)}`} />
        <Kpi label={t('ha_duration')} value={fmtDuration(hunt.duration_s)} sub={`${n(kills)} ${t('ha_kills')} · ${n(Math.round(kills / hours))}/h`} />
      </div>

      {insights.length > 0 && (
        <ul className="insights">
          {insights.map((i) => (
            <li key={i.key} className={`insight ${i.tone}`}>
              {t(i.key, Object.fromEntries(Object.entries(i.vars ?? {}).map(([k, v]) => [k, typeof v === 'number' && k === 'gp' ? gp(v, locale) : typeof v === 'number' ? n(v) : v])))}
            </li>
          ))}
        </ul>
      )}

      <div className="hunt-cols">
        <section className="card">
          <h3>
            {t('ha_monsters')} <span className="muted small">({n(kills)})</span>
          </h3>
          {hunt.monsters.length ? (
            <table className="compact-table">
              <thead>
                <tr>
                  <th>{t('ha_creature')}</th>
                  <th className="num">{t('ha_count')}</th>
                  <th className="num">/h</th>
                </tr>
              </thead>
              <tbody>
                {hunt.monsters.map((m) => (
                  <tr key={m.name}>
                    <td>{m.name}</td>
                    <td className="num">{n(m.count)}</td>
                    <td className="num">{n(Math.round(m.count / hours))}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p className="muted small">{t('ha_none')}</p>
          )}
        </section>

        <section className="card">
          <h3>
            {t('ha_loot_items')} <span className="muted small">({lootRows.length})</span>
          </h3>
          {lootRows.length ? (
            <>
              <table className="compact-table">
                <thead>
                  <tr>
                    <th>{t('ha_item')}</th>
                    <th className="num">{t('ha_count')}</th>
                    <th className="num">NPC</th>
                  </tr>
                </thead>
                <tbody>
                  {lootRows.slice(0, 40).map((it) => (
                    <tr key={it.name}>
                      <td>
                        <span className="item-cell">
                          <ItemSprite name={it.name} sprites={sprites} size={24} />
                          {it.name}
                        </span>
                      </td>
                      <td className="num">{n(it.count)}</td>
                      <td className="num">{it.known ? gp(it.total, locale) : '?'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <p className="muted small">
                {t('ha_npc_value', { value: gp(npc.value, locale), client: gp(hunt.loot, locale) })}{' '}
                <a href="/parser" onClick={linkHandler('/parser')}>
                  {t('ha_where_to_sell')} →
                </a>
              </p>
            </>
          ) : (
            <p className="muted small">{t('ha_none')}</p>
          )}
        </section>
      </div>

      {input && (input.total > 0 || input.types.length > 0) && (
        <section className="card">
          <h3>{t('ha_input_title')}</h3>
          <div className="kpi-grid small">
            <Kpi label={t('ha_received')} value={n(input.total)} sub={`${n(Math.round(input.total / hours))}/h`} />
            <Kpi label="Max-DPS" value={n(input.max_dps)} />
          </div>
          <InputDamage input={input} />
        </section>
      )}

      {(expenses?.length ?? 0) > 0 && (
        <section className="card">
          <h3>
            💸 {t('hs_expenses')} <span className="muted small">· {gp(extra, locale)}</span>
          </h3>
          <ul className="plain-list small">
            {expenses!.map((e, i) => (
              <li key={i}>
                <strong>{gp(expenseValue(e), locale)}</strong>
                {e.tc ? ` · ${n(e.tc)} TC × ${n(e.tc_price)}` : ''}
                {e.gold ? ` · ${gp(e.gold, locale)} gold` : ''}
                {e.reason ? ` · ${e.reason}` : ''}
                {e.payer ? <span className="muted"> · {t('hs_paid_by', { name: e.payer })}</span> : null}
              </li>
            ))}
          </ul>
        </section>
      )}

      {hunt.party && hunt.party.members.length > 0 && <PartyBox party={hunt.party} expenses={expenses ?? []} />}

      {serverLog && <ServerLogView log={serverLog} premium={premium} />}
    </div>
  );
}

function PartyBox({ party, expenses }: { party: NonNullable<HuntData['party']>; expenses: Expense[] }) {
  const { t, locale } = useI18n();
  const split = useMemo(() => computeLootSplit(balancesWithExpenses(party.members, expenses)), [party.members, expenses]);
  const totalDamage = party.members.reduce((s, m) => s + m.damage, 0) || 1;
  const totalHeal = party.members.reduce((s, m) => s + m.healing, 0) || 1;
  return (
    <section className="card">
      <h3>
        {t('ha_party')} {party.loot_type && <span className="muted small">· {t('ha_loot_type')}: {party.loot_type}</span>}
      </h3>
      <div className="table-scroll">
        <table className="compact-table">
          <thead>
            <tr>
              <th>{t('ha_player')}</th>
              <th className="num">{t('ha_loot')}</th>
              <th className="num">{t('ha_supplies')}</th>
              <th className="num">{t('ha_balance')}</th>
              <th className="num">{t('ha_damage_share')}</th>
              <th className="num">{t('ha_healing_share')}</th>
            </tr>
          </thead>
          <tbody>
            {party.members.map((m) => (
              <tr key={m.name}>
                <td>
                  {m.name} {m.isLeader && <span className="tag">{t('ha_leader')}</span>}
                </td>
                <td className="num">{gp(m.loot, locale)}</td>
                <td className="num">{gp(m.supplies, locale)}</td>
                <td className={`num ${m.balance < 0 ? 'neg' : 'pos'}`}>{gp(m.balance, locale)}</td>
                <td className="num">{Math.round((m.damage / totalDamage) * 100)}%</td>
                <td className="num">{Math.round((m.healing / totalHeal) * 100)}%</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <h4>
        {t('ha_split')} · {t('ha_share_each', { gp: gp(split.sharePerPlayer, locale) })}
      </h4>
      {expenses.length > 0 && <p className="muted small">{t('hs_split_with_expenses')}</p>}
      {split.transfers.length ? (
        <ul className="transfer-list">
          {split.transfers.map((tr) => (
            <li key={`${tr.from}-${tr.to}`}>
              <span>
                <strong>{tr.from}</strong> → <strong>{tr.to}</strong>: {gp(tr.amount, locale)}
              </span>
              <CopyText text={`transfer ${tr.amount} to ${tr.to}`} />
            </li>
          ))}
        </ul>
      ) : (
        <p className="muted small">{t('ha_no_transfers')}</p>
      )}
    </section>
  );
}

/** Server Log analysis (Premium): what the analysers do not show */
export function ServerLogView({ log, premium }: { log: ServerLogSummary; premium: boolean }) {
  const { t, n, locale } = useI18n();
  const hours = Math.max(1, log.minutes) / 60;
  const potions = log.supplies.reduce((s, x) => s + x.count, 0);
  const critRate = log.dealt.hits ? Math.round((log.dealt.crits / log.dealt.hits) * 1000) / 10 : 0;
  const killXp = log.xp.kills ? Math.round((log.xp.total - log.xp.animus) / log.xp.kills) : 0;
  const bought = log.trade.bought.reduce((s, x) => s + x.gold, 0);
  const sold = log.trade.sold.reduce((s, x) => s + x.gold, 0);

  return (
    <section className={`card server-log${premium ? '' : ' locked'}`}>
      <h3>
        📜 {t('ha_serverlog_title')} <span className="tag pro">Pro</span>
      </h3>
      <p className="muted small">
        {t('ha_serverlog_span', { from: log.first_time ?? '?', to: log.last_time ?? '?', sessions: log.sessions })}
        {log.vocation && ` · ${log.vocation}`}
        {log.boosted.creature && ` · ${t('boosted_creature')}: ${log.boosted.creature}`}
      </p>
      <div className="kpi-grid small">
        <Kpi label={t('ha_xp_from_kills')} value={n(log.xp.total)} sub={`${n(Math.round(log.xp.total / hours))}/h`} />
        <Kpi label={t('ha_avg_xp_kill')} value={n(killXp)} sub={`${n(log.xp.kills)} ${t('ha_kills')}`} />
        <Kpi label={t('ha_dealt')} value={compact(log.dealt.total, locale)} sub={`${t('ha_crit_rate')}: ${critRate}%`} />
        <Kpi label={t('ha_taken')} value={compact(log.taken.total, locale)} sub={`${n(log.taken.hits)} ${t('ha_hits')}`} />
        <Kpi label={t('ha_potions')} value={n(potions)} sub={`${n(Math.round(potions / hours))}/h`} />
        <Kpi label={t('ha_healing')} value={compact(log.healing.self + log.healing.received, locale)} sub={`${t('ha_mana')}: ${compact(log.healing.mana, locale)}`} />
      </div>

      {premium ? (
        <>
          {log.timeline.length > 2 && <MinuteTimeline timeline={log.timeline} />}
          <div className="hunt-cols">
            <div>
              <h4>{t('ha_taken_by_source')}</h4>
              <ShareBars rows={log.taken.by_source} format={(v) => n(v)} />
            </div>
            <div>
              <h4>{t('ha_dealt_by_target')}</h4>
              <ShareBars rows={log.dealt.by_target} format={(v) => compact(v, locale)} />
            </div>
          </div>

          {log.loot.length > 0 && (
            <>
              <h4>{t('ha_drops_per_creature')}</h4>
              <div className="table-scroll">
                <table className="compact-table">
                  <thead>
                    <tr>
                      <th>{t('ha_creature')}</th>
                      <th className="num">{t('ha_corpses')}</th>
                      <th className="num">{t('ha_empty')}</th>
                      <th>{t('ha_top_drops')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {log.loot.slice(0, 20).map((c) => (
                      <tr key={c.name}>
                        <td>{c.name}</td>
                        <td className="num">{n(c.corpses)}</td>
                        <td className="num">{Math.round((c.empty / Math.max(1, c.corpses)) * 100)}%</td>
                        <td className="small">
                          {c.items
                            .filter((i) => !/coins?$/.test(i.name))
                            .slice(0, 4)
                            .map((i) => `${i.name} ${Math.round((i.count / c.corpses) * 100)}%`)
                            .join(' · ') || '—'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="muted small">{t('ha_drop_note')}</p>
            </>
          )}

          <div className="hunt-cols">
            {log.supplies.length > 0 && (
              <div>
                <h4>{t('ha_supplies_used')}</h4>
                <ShareBars rows={log.supplies.map((s) => ({ name: s.name, value: s.count }))} format={(v) => n(v)} />
              </div>
            )}
            {(log.skills.length > 0 || log.level_ups.length > 0) && (
              <div>
                <h4>{t('ha_progress')}</h4>
                <ul className="plain-list small">
                  {log.level_ups.map((l) => (
                    <li key={`${l.from}-${l.to}`}>
                      ⬆️ Level {l.from} → {l.to}
                    </li>
                  ))}
                  {log.skills.map((s) => (
                    <li key={s.skill}>
                      {s.skill}: {s.from} → {s.to}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          {log.reward_chest.length > 0 && (
            <>
              <h4>{t('ha_reward_chest')}</h4>
              <ul className="plain-list small">
                {log.reward_chest.map((r, i) => (
                  <li key={i}>
                    👑 <strong>{r.boss}</strong>: {r.items.map((x) => `${x.count}x ${x.name}`).join(', ')}
                  </li>
                ))}
              </ul>
            </>
          )}

          {(bought > 0 || sold > 0) && (
            <p className="small">
              🛒 {t('ha_trade', { bought: gp(bought, locale), sold: gp(sold, locale) })}
            </p>
          )}
          {log.effects.length > 0 && (
            <p className="small muted">✨ {log.effects.map((e) => `${e.name} ×${n(e.count)}`).join(' · ')}</p>
          )}
        </>
      ) : (
        <div className="locked-cta">
          <p>{t('ha_serverlog_locked')}</p>
          <a className="button" href="/pricing" onClick={linkHandler('/pricing')}>
            ⭐ {t('ha_go_pro')}
          </a>
        </div>
      )}
    </section>
  );
}

/** XP and damage per minute (Server Log): smooth lines, gaps filled with zeros, raw minute in the tooltip */
function MinuteTimeline({ timeline }: { timeline: { t: string; xp: number; dealt: number; taken: number }[] }) {
  const { t, locale } = useI18n();
  const [view, setView] = useState<'damage' | 'xp'>('damage');
  const pts = fillMinutes(timeline);
  if (pts.length < 3) return null;
  const win = pts.length > 90 ? 5 : pts.length > 20 ? 3 : 1;
  const nf = new Intl.NumberFormat(locale);
  const raw = { xp: pts.map((p) => p.xp), dealt: pts.map((p) => p.dealt), taken: pts.map((p) => p.taken) };
  const series =
    view === 'xp'
      ? [{ key: 'xp', name: 'XP', values: smooth(raw.xp, win), className: 'series-xp' }]
      : [
          { key: 'dealt', name: t('ha_dealt'), values: smooth(raw.dealt, win), className: 'series-dealt' },
          { key: 'taken', name: t('ha_taken'), values: smooth(raw.taken, win), className: 'series-taken' },
        ];
  return (
    <>
      <div className="timeline-switch">
        <h4>{view === 'xp' ? t('ha_xp_per_minute') : t('ha_damage_per_minute')}</h4>
        <div className="chips-row" role="group" aria-label={t('ha_timeline')}>
          <button type="button" className={view === 'damage' ? 'chip active' : 'chip'} onClick={() => setView('damage')}>
            {t('ha_timeline_damage')}
          </button>
          <button type="button" className={view === 'xp' ? 'chip active' : 'chip'} onClick={() => setView('xp')}>
            XP
          </button>
        </div>
      </div>
      <SeriesChart
        labels={pts.map((p) => p.t)}
        series={series}
        raw={raw}
        formatY={(v) => compact(v, locale)}
        tooltipValue={(_k, v) => nf.format(Math.round(v))}
        ariaLabel={view === 'xp' ? t('ha_xp_per_minute') : t('ha_damage_per_minute')}
      />
      {win > 1 && <p className="muted small chart-note">{t('ha_timeline_smooth', { n: win })}</p>}
    </>
  );
}
