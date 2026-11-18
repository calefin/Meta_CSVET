/** Weapon comparison card (D-72) in "My hunts": loads on demand, compares by place, shows % vs the most used weapon. */
import { useMemo, useState } from 'react';
import { useI18n } from '../../i18n';
import { hunts } from '../../api';
import { compact } from '../../components/Charts';
import { compareWeapons, MIN_HITS, type WeaponHunt } from '../../core/weaponCompare';

export function WeaponCompare() {
  const { t, n, locale } = useI18n();
  const [rows, setRows] = useState<WeaponHunt[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [place, setPlace] = useState('');
  const places = useMemo(() => {
    const m = new Map<string, string>();
    for (const h of rows ?? []) if (h.place_slug || h.place_name) m.set(h.place_slug ?? h.place_name!, h.place_name ?? h.place_slug!);
    return [...m];
  }, [rows]);
  const stats = useMemo(() => (rows ? compareWeapons(rows, place || null) : []), [rows, place]);

  const load = () => {
    setLoading(true);
    hunts
      .weapons()
      .then((r) => setRows(r.hunts))
      .catch(() => setRows([]))
      .finally(() => setLoading(false));
  };

  return (
    <section className="card weapon-compare">
      <div className="section-head">
        <h3>⚔️ {t('wc_title')}</h3>
        {rows && places.length > 1 && (
          <select value={place} onChange={(e) => setPlace(e.target.value)} aria-label={t('ha_place')}>
            <option value="">{t('wc_all_places')}</option>
            {places.map(([k, label]) => (
              <option key={k} value={k}>
                {label}
              </option>
            ))}
          </select>
        )}
      </div>
      <p className="muted small">{t('wc_intro')}</p>
      {!rows ? (
        <button type="button" className="button secondary" onClick={load} disabled={loading}>
          {loading ? t('loading') : t('wc_load')}
        </button>
      ) : stats.length < 2 ? (
        <p className="muted small">{t('wc_need_two')}</p>
      ) : (
        <>
          <div className="table-scroll">
            <table className="compact-table">
              <thead>
                <tr>
                  <th>{t('wc_weapon')}</th>
                  <th className="num">{t('wc_per_min')}</th>
                  <th className="num">{t('wc_vs')}</th>
                  <th className="num">{t('wc_per_hit')}</th>
                  <th className="num">{t('wc_crit')}</th>
                  <th className="num">{t('ha_hunts')}</th>
                  <th className="num">Damage/h</th>
                </tr>
              </thead>
              <tbody>
                {stats.map((w, i) => (
                  <tr key={w.key}>
                    <td>
                      <strong>{w.label}</strong>
                      {i === 0 && <span className="tag">{t('wc_reference')}</span>}
                      {/* Phones: the main numbers next to the name (the table scrolls sideways) */}
                      <div className="small wc-sum">
                        {w.perCombatMin != null ? `${compact(w.perCombatMin, locale)}/min` : `${compact(w.damageH, locale)}/h`}
                        {i > 0 && w.diff != null && <strong className={w.diff > 0 ? 'pos' : 'neg'}> · {`${w.diff > 0 ? '+' : ''}${n(w.diff)}%`}</strong>}
                      </div>
                      {!w.enoughData && w.logHunts > 0 && <div className="muted small">⚠️ {t('wc_few', { n: n(w.hits), min: MIN_HITS })}</div>}
                      {!w.logHunts && <div className="muted small">{t('wc_no_log')}</div>}
                    </td>
                    <td className="num">{w.perCombatMin != null ? compact(w.perCombatMin, locale) : '—'}</td>
                    <td className={`num ${w.diff == null || i === 0 ? '' : w.diff > 0 ? 'pos' : 'neg'}`}>
                      {i === 0 || w.diff == null ? '—' : `${w.diff > 0 ? '+' : ''}${n(w.diff)}%`}
                    </td>
                    <td className="num">{w.perHit != null ? n(w.perHit) : '—'}</td>
                    <td className="num">{w.critRate != null ? `${n(w.critRate)}%${w.critBonus != null ? ` · +${w.critBonus}%` : ''}` : '—'}</td>
                    <td className="num">
                      {w.hunts}
                      {w.logHunts < w.hunts && <span className="muted small"> ({w.logHunts} log)</span>}
                    </td>
                    <td className="num">{compact(w.damageH, locale)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="muted small">{t('wc_note')}</p>
        </>
      )}
    </section>
  );
}
