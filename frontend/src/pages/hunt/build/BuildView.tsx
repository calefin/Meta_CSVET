/** Read-only view of the build used in a hunt (D-59): set + stats per source, charms per creature, preys, WoD. */
import { useMemo } from 'react';
import { useI18n } from '../../../i18n';
import { SLOTS, charmCreatureStats, charmValue, computeSetStats, type HuntBuild } from '../../../core/build';
import { ItemSprite, useSprites } from '../../../components/ItemSprite';
import { CopyText } from '../../../components/CopyText';
import { StatsPanel, titleCase } from './StatsPanel';
import { SLOT_ICON, useEquipment } from './SetEditor';

export function BuildView({ build, monsters }: { build: HuntBuild; monsters: { name: string; count: number }[] }) {
  const { t, n } = useI18n();
  const eq = useEquipment();
  const sprites = useSprites();
  const stats = useMemo(() => (eq ? computeSetStats(build.set?.data ?? null, eq.index, { consumables: build.consumables }) : null), [eq, build]);
  const kills = (c: string | null) => (c ? monsters.find((m) => m.name.toLowerCase() === c)?.count ?? 0 : 0);
  const perCreature = useMemo(() => charmCreatureStats(build.charms), [build.charms]);
  const base = (stat: string) => stats?.stats.find((s) => s.key === stat)?.total ?? 0;

  return (
    <section className="card build-view">
      <h3>🧙 {t('hs_build_title')}</h3>
      <div className="build-grid">
        {build.set && (
          <div>
            <h4>
              🛡️ {build.set.name} <span className="muted small">· {t('hs_set')}</span>
            </h4>
            <ul className="set-items">
              {SLOTS.filter((s) => build.set!.data.slots[s]).map((s) => {
                const it = build.set!.data.slots[s]!;
                return (
                  <li key={s}>
                    <span className="muted" aria-hidden="true">
                      {SLOT_ICON[s]}
                    </span>
                    <ItemSprite name={it.name} sprites={sprites} size={24} />
                    <span>
                      {it.name}
                      {it.tier ? <span className="tag">T{it.tier}</span> : null}
                      {it.imbues?.length ? (
                        <span className="muted small"> · {it.imbues.map((i) => `${titleCase(i.type)} ${['I', 'II', 'III'][i.tier - 1]}`).join(', ')}</span>
                      ) : null}
                    </span>
                  </li>
                );
              })}
            </ul>
            {stats && <StatsPanel stats={stats} compact />}
          </div>
        )}

        <div>
          {build.charms.length > 0 && (
            <>
              <h4>✨ Charms</h4>
              <table className="compact-table">
                <thead>
                  <tr>
                    <th>Charm</th>
                    <th className="num">%</th>
                    <th>{t('ha_creature')}</th>
                    <th className="num">{t('ha_kills')}</th>
                  </tr>
                </thead>
                <tbody>
                  {build.charms.map((c) => (
                    <tr key={c.name}>
                      <td>
                        {c.name} <span className="muted small">{'I'.repeat(c.tier)}</span>
                      </td>
                      <td className="num">{charmValue(c)}%</td>
                      <td>{c.creature ? titleCase(c.creature) : '—'}</td>
                      <td className="num">{c.creature ? n(kills(c.creature)) : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {perCreature.some((p) => p.creature && p.lines.some((l) => l.stat === 'critical hit chance' || l.stat === 'critical extra damage')) && (
                <ul className="plain-list small">
                  {perCreature
                    .filter((p) => p.creature)
                    .map((p) => {
                      const crit = p.lines.filter((l) => l.stat === 'critical hit chance' || l.stat === 'critical extra damage');
                      if (!crit.length) return null;
                      return (
                        <li key={p.creature}>
                          {t('hs_vs_creature', { creature: titleCase(p.creature) })}:{' '}
                          {crit.map((l) => `${titleCase(l.stat)} ${base(l.stat) + l.value}% (${l.from} +${l.value}%)`).join(' · ')}
                        </li>
                      );
                    })}
                </ul>
              )}
            </>
          )}

          {build.preys.length > 0 && (
            <>
              <h4>🎯 Preys</h4>
              <ul className="plain-list small">
                {build.preys.map((p, i) => (
                  <li key={i}>
                    <strong>{t(`hs_prey_${p.type}`)}</strong> ★{p.grade} · {p.creature ? titleCase(p.creature) : '—'}
                    {p.creature && kills(p.creature) ? <span className="muted"> · {n(kills(p.creature))} {t('ha_kills')}</span> : null}
                  </li>
                ))}
              </ul>
            </>
          )}

          {build.stances.length > 0 && (
            <p className="small">
              🧘 <strong>{t('hs_stances')}:</strong> {build.stances.join(', ')}
            </p>
          )}
          {build.consumables.length > 0 && (
            <p className="small">
              🧪 <strong>{t('hs_consumables')}:</strong> {build.consumables.join(', ')}
            </p>
          )}

          {build.wod && (
            <>
              <h4>
                ☸️ {build.wod.name} <span className="muted small">· Wheel of Destiny</span>
              </h4>
              {build.wod.data.code && (
                <p className="small wod-code">
                  <code>{build.wod.data.code.length > 28 ? `${build.wod.data.code.slice(0, 28)}…` : build.wod.data.code}</code>{' '}
                  <CopyText text={build.wod.data.code} />
                </p>
              )}
              {Object.keys(build.wod.data.revelations).length > 0 && (
                <ul className="plain-list small">
                  {Object.entries(build.wod.data.revelations).map(([p, s]) => (
                    <li key={p}>
                      {p}: {t('hs_stage')} {s}
                    </li>
                  ))}
                </ul>
              )}
              {Object.keys(build.wod.data.points).length > 0 && (
                <p className="small muted">
                  {Object.entries(build.wod.data.points)
                    .map(([d, v]) => `${t(`hs_wod_${d}`)} ${v}`)
                    .join(' · ')}
                </p>
              )}
              {build.wod.data.gems && <p className="small">💎 {build.wod.data.gems}</p>}
              {build.wod.data.notes && <p className="small muted">{build.wod.data.notes}</p>}
            </>
          )}
        </div>
      </div>
    </section>
  );
}
