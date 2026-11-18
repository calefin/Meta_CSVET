/**
 * Community numbers on a hunting place page (D-53): median XP/h, raw XP/h and profit/h of the public hunts
 * saved for this place, one line per vocation. Our own data, from players who made their hunts public.
 */
import { useEffect, useState } from 'react';
import { hunts, type HuntPlaceStat, type Vocation } from '../api';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { compact } from './Charts';

const VOC_ICON: Record<Vocation, string> = { knight: '🗡️', paladin: '🏹', sorcerer: '🔥', druid: '❄️', monk: '👊' };

export function PlaceCommunityStats({ slug }: { slug: string }) {
  const { t, locale } = useI18n();
  const [rows, setRows] = useState<(HuntPlaceStat & { vocation?: Vocation | null })[] | null>(null);

  useEffect(() => {
    hunts
      .places({ place: slug, period: 'all', by: 'vocation' })
      .then((r) => setRows(r.places))
      .catch(() => setRows([]));
  }, [slug]);

  const gpShort = (v: number) => `${v < 0 ? '-' : ''}${compact(Math.abs(v), locale)}`;
  const total = rows?.reduce((s, r) => s + r.hunts, 0) ?? 0;

  return (
    <section className="card">
      <h2>🏹 {t('place_stats_title')}</h2>
      {rows === null ? (
        <p className="muted">{t('loading')}</p>
      ) : !rows.length ? (
        <p className="muted">{t('place_stats_empty')}</p>
      ) : (
        <>
          <p className="muted small">{t('place_stats_note', { n: total })}</p>
          <div className="table-scroll">
            <table className="compact-table">
              <thead>
                <tr>
                  <th>{t('vocation')}</th>
                  <th className="num">{t('ha_hunts')}</th>
                  <th className="num">{t('ha_raw_xp_h')}</th>
                  <th className="num">{t('ha_xp_h')}</th>
                  <th className="num">{t('ha_balance_h')}</th>
                  <th className="num">Lv</th>
                </tr>
              </thead>
              <tbody>
                {rows
                  .sort((a, b) => b.hunts - a.hunts)
                  .map((r) => (
                    <tr key={r.vocation ?? 'none'}>
                      <td>{r.vocation ? `${VOC_ICON[r.vocation]} ${t(`voc_${r.vocation}`)}` : '—'}</td>
                      <td className="num">{r.hunts}</td>
                      <td className="num">{compact(r.raw_xp_h, locale)}</td>
                      <td className="num">{compact(r.xp_h, locale)}</td>
                      <td className={`num ${r.balance_h < 0 ? 'neg' : 'pos'}`}>{gpShort(r.balance_h)}</td>
                      <td className="num">
                        {r.min_level ?? '?'}–{r.max_level ?? '?'}
                      </td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
        </>
      )}
      <div className="button-row">
        <a className="button secondary" href={`/hunt-analyser/public?place=${slug}`} onClick={linkHandler(`/hunt-analyser/public?place=${slug}`)}>
          {t('place_stats_see')}
        </a>
        <a className="button" href="/hunt-analyser" onClick={linkHandler('/hunt-analyser')}>
          ＋ {t('place_stats_add')}
        </a>
      </div>
    </section>
  );
}
