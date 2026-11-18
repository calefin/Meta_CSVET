/**
 * Received damage (Input Analyser, D-62): element symbol per damage type, creature picture per source, the
 * creature × type table (saved with the hunt, or worked out here for older hunts) and the protection that fits.
 */
import { CreatureLink } from '../../components/CreatureLink';
import { useEffect, useMemo, useState } from 'react';
import { useI18n } from '../../i18n';
import { CreatureImage } from '../../components/CreatureImage';
import type { InputAnalysis } from '../../core/huntLog';
import { damageMatrix, elementIcon, elementKey, loadCreatures, PROTECTION, type CreatureStats } from '../../core/creatures';

export function InputDamage({ input }: { input: InputAnalysis }) {
  const { t, n } = useI18n();
  const [index, setIndex] = useState<Map<string, CreatureStats> | null>(null);
  const saved = input.by_source_type?.length ? input.by_source_type : null;
  useEffect(() => {
    if (!saved && input.sources.length && input.types.length) void loadCreatures().then(setIndex);
  }, [saved, input]);
  const matrix = useMemo(() => {
    if (saved) return { types: input.types.map((x) => x.name), rows: saved, estimated: !!input.estimated };
    return index ? damageMatrix(input, index) : null;
  }, [saved, index, input]);
  const max = Math.max(1, ...input.types.map((x) => x.value), ...input.sources.map((x) => x.value));
  const top = input.types.find((x) => PROTECTION[elementKey(x.name)]);
  const topPhysical = input.types[0] && elementKey(input.types[0].name) === 'physical';

  const bar = (name: string, value: number, pct: number, icon: React.ReactNode) => (
    <li key={name}>
      <span className="dmg-label">
        {icon}
        <span>{name}</span>
      </span>
      <span className="dmg-track" aria-hidden="true">
        <span style={{ width: `${(value / max) * 100}%` }} />
      </span>
      <span className="dmg-value">
        {n(value)} <span className="muted small">({n(pct)}%)</span>
      </span>
    </li>
  );

  return (
    <>
      <div className="hunt-cols">
        <div>
          <h4>{t('ha_damage_types')}</h4>
          <ul className="dmg-bars">
            {input.types.map((x) =>
              bar(
                x.name,
                x.value,
                x.pct,
                <span className="dmg-icon" aria-hidden="true">
                  {elementIcon(x.name)}
                </span>,
              ),
            )}
          </ul>
        </div>
        <div>
          <h4>{t('ha_damage_sources')}</h4>
          <ul className="dmg-bars">
            {input.sources.map((x) => bar(x.name, x.value, x.pct, <CreatureImage name={x.name} size={24} />))}
          </ul>
        </div>
      </div>

      {matrix && matrix.rows.length > 0 && matrix.types.length > 1 && (
        <>
          <h4>
            {t('dmg_by_creature_type')}{' '}
            <span className="muted small">{matrix.estimated ? `· ${t('dmg_estimated')}` : `· ${t('dmg_exact')}`}</span>
          </h4>
          <div className="table-scroll">
            <table className="compact-table dmg-matrix">
              <thead>
                <tr>
                  <th>{t('ha_creature')}</th>
                  {matrix.types.map((ty) => (
                    <th key={ty} className="num" title={ty}>
                      <span aria-hidden="true">{elementIcon(ty)}</span> {ty}
                    </th>
                  ))}
                  <th className="num">{t('pay_total')}</th>
                </tr>
              </thead>
              <tbody>
                {matrix.rows.map((r) => (
                  <tr key={r.name}>
                    <td>
                      <span className="ca-name">
                        <CreatureImage name={r.creature ?? r.name} size={24} />
                        <span>
                          <CreatureLink name={r.creature ?? r.name} resolve />
                        </span>
                      </span>
                    </td>
                    {matrix.types.map((ty) => (
                      <td key={ty} className="num">
                        {r.types[ty] ? n(r.types[ty]) : <span className="muted">—</span>}
                      </td>
                    ))}
                    <td className="num">
                      <strong>{n(r.total)}</strong>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {matrix.estimated && <p className="muted small">{t('dmg_estimated_note')}</p>}
        </>
      )}

      {top && top.pct >= 15 && (
        <p className="small dmg-tip">
          🛡️ {t('dmg_protection', { icon: elementIcon(top.name), type: top.name, pct: n(top.pct), imbue: PROTECTION[elementKey(top.name)].imbue, max: PROTECTION[elementKey(top.name)].pct })}
        </p>
      )}
      {topPhysical && <p className="small muted">{t('dmg_physical_tip')}</p>}
    </>
  );
}
