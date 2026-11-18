/**
 * Creature analysis for one hunt (D-61): HP, XP, weaknesses, bestiary progress, the element that fits the whole
 * hunt best and what the chosen elemental charms did (estimate; exact attack count only with the Server Log).
 */
import { CreatureLink } from '../../components/CreatureLink';
import { useEffect, useMemo, useState } from 'react';
import { useI18n } from '../../i18n';
import { CreatureImage } from '../../components/CreatureImage';
import { compact } from '../../components/Charts';
import type { CharmPick } from '../../core/build';
import type { ServerLogSummary } from '../../core/huntLog';
import { bestElements, charmEstimates, CHARM_HIT_PCT, creatureRows, loadCreatures, type CreatureStats, type Element } from '../../core/creatures';

const EL_ICON: Record<Element, string> = { physical: '⚔️', earth: '🌿', fire: '🔥', energy: '⚡', ice: '❄️', holy: '✨', death: '💀', lifedrain: '🩸', drown: '💧', healing: '💚' };

export function CreatureAnalysis({ monsters, charms, serverLog }: { monsters: { name: string; count: number }[]; charms: CharmPick[]; serverLog?: ServerLogSummary | null }) {
  const { t, n, locale } = useI18n();
  const [index, setIndex] = useState<Map<string, CreatureStats> | null>(null);
  const [all, setAll] = useState(false);
  useEffect(() => {
    void loadCreatures().then(setIndex);
  }, []);
  const rows = useMemo(() => (index ? creatureRows(monsters, index) : []), [index, monsters]);
  const best = useMemo(() => bestElements(rows), [rows]);
  const estimates = useMemo(() => (index ? charmEstimates(charms, monsters, index, serverLog?.dealt) : []), [index, charms, monsters, serverLog]);
  if (!monsters.length) return null;
  if (!index) return <section className="card"><p className="muted">{t('loading')}</p></section>;

  const known = rows.filter((r) => r.stats);
  const totalHp = known.reduce((s, r) => s + r.hpTotal, 0);
  const points = known.reduce((s, r) => s + (r.bestiaryPct === 100 && r.charmPoints ? r.charmPoints : 0), 0);
  const shown = all ? rows : rows.slice(0, 8);
  const elName = (e: Element) => `${EL_ICON[e]} ${t(`el_${e}`)}`;

  return (
    <section className="card creature-analysis">
      <h3>🐉 {t('ca_title')}</h3>
      {best.length > 0 && (
        <div className="ca-summary">
          <div>
            <span className="muted small">{t('ca_best_element')}</span>
            <strong>
              {elName(best[0].element)} <span className={best[0].pct >= 100 ? 'pos' : 'neg'}>{best[0].pct}%</span>
            </strong>
            <span className="muted small">
              {best
                .slice(1, 4)
                .map((b) => `${t(`el_${b.element}`)} ${b.pct}%`)
                .join(' · ')}
            </span>
          </div>
          <div>
            <span className="muted small">{t('ca_hp_killed')}</span>
            <strong>{compact(totalHp, locale)}</strong>
            <span className="muted small">{t('ca_creatures_n', { n: n(rows.reduce((s, r) => s + r.kills, 0)) })}</span>
          </div>
          {points > 0 && (
            <div>
              <span className="muted small">{t('ca_bestiary_done')}</span>
              <strong>+{points} charm points</strong>
            </div>
          )}
        </div>
      )}
      <p className="muted small">{t('ca_note')}</p>

      <div className="table-scroll">
        <table className="compact-table ca-table">
          <thead>
            <tr>
              <th>{t('ha_creature')}</th>
              <th className="num">{t('ha_kills')}</th>
              <th className="num">HP</th>
              <th className="num">XP</th>
              <th>{t('ca_weak')}</th>
              <th>{t('ca_strong')}</th>
              <th>{t('ca_charm')}</th>
              <th className="num">{t('ca_bestiary')}</th>
            </tr>
          </thead>
          <tbody>
            {shown.map((r) => (
              <tr key={r.name}>
                <td>
                  <span className="ca-name">
                    <CreatureImage name={r.name} size={28} />
                    <span>
                      {r.stats ? <CreatureLink name={r.stats.name} /> : r.name}
                      {r.stats?.boss && <span className="tag">boss</span>}
                      {r.stats?.cls && <span className="muted small"> · {r.stats.cls}</span>}
                    </span>
                  </span>
                </td>
                <td className="num">{n(r.kills)}</td>
                <td className="num">{r.stats ? n(r.stats.hp) : '—'}</td>
                <td className="num">{r.stats ? n(r.stats.exp) : '—'}</td>
                <td className="small">{r.weak.length ? r.weak.slice(0, 3).map((w) => `${EL_ICON[w.element]} ${w.pct}%`).join(' ') : '—'}</td>
                <td className="small">{r.strong.length ? r.strong.slice(0, 3).map((w) => `${EL_ICON[w.element]} ${w.pct}%`).join(' ') : '—'}</td>
                <td className="small">{r.bestCharm ? `${r.bestCharm.name} (${r.bestCharm.pct}%)` : '—'}</td>
                <td className="num">
                  {r.bestiaryPct != null ? (
                    <span title={r.stats?.lvl ?? ''}>
                      {r.bestiaryPct}%{r.bestiaryPct >= 100 ? ' ✓' : ''}
                    </span>
                  ) : (
                    '—'
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {rows.length > 8 && (
        <button type="button" className="button secondary small-btn" onClick={() => setAll((v) => !v)}>
          {all ? t('ca_show_less') : t('ca_show_all', { n: rows.length })}
        </button>
      )}

      {estimates.length > 0 && (
        <>
          <h4>✨ {t('ca_charms_title')}</h4>
          <ul className="plain-list small ca-charms">
            {estimates.map((e, i) => (
              <li key={i}>
                <strong>
                  {e.charm} {'I'.repeat(e.tier)}
                </strong>
                {e.creature ? ` → ${e.creature}` : ''}
                {e.kills ? ` (${n(e.kills)} ${t('ha_kills')})` : ''}:{' '}
                {e.perHit != null ? (
                  <>
                    {t('ca_charm_hit', { chance: e.chance, dmg: n(e.perHit), pct: CHARM_HIT_PCT, mod: e.elementPct ?? 100 })}
                    {e.procs != null ? ` ${t('ca_charm_total', { procs: n(e.procs), dmg: compact(e.damage ?? 0, locale) })}` : ` ${t('ca_charm_need_log')}`}
                  </>
                ) : e.note ? (
                  t(`ca_charm_${e.note}`, { v: e.chance })
                ) : !e.creature ? (
                  t('ca_charm_no_creature')
                ) : (
                  t('ca_charm_unknown')
                )}
              </li>
            ))}
          </ul>
        </>
      )}
      <p className="muted small">{t('ca_credit')}</p>
    </section>
  );
}
