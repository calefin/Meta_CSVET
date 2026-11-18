/** Public hunts feed + rankings + community place statistics (D-49). Only sessions their owners made public. */
import { useEffect, useState } from 'react';
import { useI18n } from '../../i18n';
import { linkHandler } from '../../router';
import { hunts, type HuntFilters, type HuntPlaceStat, type HuntRow } from '../../api';
import { compact } from '../../components/Charts';
import { fmtClientDate, fmtDuration, gp, VOC_ICON, VocationSelect } from './common';

function Filters({ f, set, withSort, withPeriod }: { f: HuntFilters; set: (f: HuntFilters) => void; withSort?: boolean; withPeriod?: boolean }) {
  const { t } = useI18n();
  return (
    <div className="filter-bar">
      <VocationSelect value={f.vocation ?? ''} onChange={(v) => set({ ...f, vocation: v, page: '' })} />
      <input
        className="num-input"
        inputMode="numeric"
        placeholder={t('ha_min_level')}
        value={f.min_level ?? ''}
        onChange={(e) => set({ ...f, min_level: e.target.value.replace(/\D/g, ''), page: '' })}
      />
      <input
        className="num-input"
        inputMode="numeric"
        placeholder={t('ha_max_level')}
        value={f.max_level ?? ''}
        onChange={(e) => set({ ...f, max_level: e.target.value.replace(/\D/g, ''), page: '' })}
      />
      <select value={f.kind ?? ''} onChange={(e) => set({ ...f, kind: e.target.value, page: '' })} aria-label={t('ha_kind')}>
        <option value="">{t('ha_solo_party')}</option>
        <option value="solo">Solo</option>
        <option value="party">Party</option>
      </select>
      {withPeriod && (
        <select value={f.period ?? '7d'} onChange={(e) => set({ ...f, period: e.target.value })} aria-label={t('ha_period')}>
          <option value="7d">{t('ha_last_days', { n: 7 })}</option>
          <option value="30d">{t('ha_last_days', { n: 30 })}</option>
          <option value="90d">{t('ha_last_days', { n: 90 })}</option>
          <option value="all">{t('ha_all_time')}</option>
        </select>
      )}
      {withSort && (
        <select value={f.sort ?? ''} onChange={(e) => set({ ...f, sort: e.target.value, page: '' })} aria-label={t('ha_sort')}>
          <option value="">{t('ha_sort_recent')}</option>
          <option value="xp_h">{t('ha_xp_h')}</option>
          <option value="balance_h">{t('ha_balance_h')}</option>
        </select>
      )}
    </div>
  );
}

export function HunterName({ h }: { h: HuntRow }) {
  const { t } = useI18n();
  return (
    <span className="hunter">
      {h.vocation && <span aria-hidden="true">{VOC_ICON[h.vocation]} </span>}
      {h.character_name ?? <em className="muted">{t('ha_anonymous_hunter')}</em>}
      {h.verified && (
        <span className="verified" title={t('ha_verified_owner')}>
          ✔
        </span>
      )}
      {h.level ? <span className="muted"> · {h.level}</span> : null}
    </span>
  );
}

function HuntCard({ h, rank, metric }: { h: HuntRow; rank?: number; metric?: string }) {
  const { t, locale } = useI18n();
  const href = `/hunt-analyser/h/${h.public_id}`;
  return (
    <a className={`card hunt-card${rank && rank <= 3 ? ` top${rank}` : ''}`} href={href} onClick={linkHandler(href)}>
      {rank && <span className="rank">#{rank}</span>}
      <div className="hunt-card-main">
        <strong>{h.place_name ?? h.top_monster ?? '—'}</strong>
        <HunterName h={h} />
        <span className="muted small">
          {fmtClientDate(h.started_at ?? h.created_at, locale)} · {fmtDuration(h.duration_s)}
          {h.kind === 'party' && ' · party'}
        </span>
      </div>
      <div className="hunt-card-nums">
        <span className={metric === 'xp_h' || metric === 'raw_xp_h' || !metric ? 'hl' : ''}>
          <small>{metric === 'raw_xp_h' ? t('ha_raw_xp_h') : t('ha_xp_h')}</small>
          {compact(metric === 'raw_xp_h' ? h.raw_xp_h : h.xp_h, locale)}
        </span>
        <span className={`${metric === 'balance_h' ? 'hl' : ''} ${h.balance_h < 0 ? 'neg' : 'pos'}`}>
          <small>{t('ha_balance_h')}</small>
          {gp(h.balance_h, locale)}
        </span>
      </div>
    </a>
  );
}

/** Filters from the address (?place=cobra-bastion&vocation=knight), e.g. links from a hunting place page */
function filtersFromUrl(): HuntFilters {
  const q = new URLSearchParams(window.location.search);
  const out: HuntFilters = {};
  for (const k of ['place', 'vocation', 'min_level', 'max_level', 'kind'] as const) {
    const v = q.get(k);
    if (v && /^[a-z0-9-]{1,160}$/.test(v)) out[k] = v;
  }
  return out;
}

/** D-61: best public sessions of the last 7 days — one per vocation (XP/h) and the top 3 profit/h */
function WeeklyBest() {
  const { t, locale } = useI18n();
  const [data, setData] = useState<{ xp: HuntRow[]; profit: HuntRow[] } | null>(null);
  useEffect(() => {
    hunts
      .weeklyBest()
      .then(setData)
      .catch(() => null);
  }, []);
  if (!data || (!data.xp.length && !data.profit.length)) return null;
  const item = (h: HuntRow, value: string, label: string) => (
    <li key={`${label}-${h.public_id}`}>
      <a href={`/hunt-analyser/h/${h.public_id}`} onClick={linkHandler(`/hunt-analyser/h/${h.public_id}`)}>
        <span className="muted small">{label}</span>
        <strong>{value}</strong>
        <HunterName h={h} />
        <span className="muted small">{h.place_name ?? h.top_monster ?? '—'}</span>
      </a>
    </li>
  );
  return (
    <section className="weekly-best" aria-label={t('wb_title')}>
      <h2>🏅 {t('wb_title')}</h2>
      <ul>
        {data.xp.map((h) => item(h, `${compact(h.xp_h, locale)} XP/h`, h.vocation ? `${VOC_ICON[h.vocation]} ${t(`voc_${h.vocation}`)}` : 'XP/h'))}
        {data.profit.map((h, i) => item(h, `${gp(h.balance_h, locale)}/h`, `💰 ${t('wb_profit', { n: i + 1 })}`))}
      </ul>
    </section>
  );
}

export function PublicFeedTab() {
  const { t } = useI18n();
  const [f, setF] = useState<HuntFilters>(filtersFromUrl);
  const [rows, setRows] = useState<HuntRow[] | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setFailed(false);
    const id = setTimeout(() => {
      hunts
        .publicFeed(f)
        .then((r) => setRows((prev) => (f.page && Number(f.page) > 0 && prev ? [...prev, ...r.hunts] : r.hunts)))
        .catch(() => setFailed(true));
    }, 250);
    return () => clearTimeout(id);
  }, [f]);

  return (
    <div>
      <p className="muted">{t('ha_public_intro')}</p>
      {!f.place && <WeeklyBest />}
      {f.place && (
        <p>
          <span className="chip ok">
            📍 {f.place}{' '}
            <button type="button" className="icon-btn" aria-label={t('ha_clear')} onClick={() => setF({ ...f, place: '', page: '' })}>
              ✕
            </button>
          </span>
        </p>
      )}
      <Filters f={f} set={setF} withSort />
      {failed ? (
        <p className="error">{t('ha_err_generic')}</p>
      ) : rows === null ? (
        <p className="muted">{t('loading')}</p>
      ) : !rows.length ? (
        <p className="card muted">{t('ha_public_empty')}</p>
      ) : (
        <>
          <div className="hunt-cards">
            {rows.map((h) => (
              <HuntCard key={h.public_id} h={h} metric={f.sort} />
            ))}
          </div>
          {rows.length % 30 === 0 && (
            <button type="button" className="button secondary" onClick={() => setF({ ...f, page: String(Number(f.page || 0) + 1) })}>
              {t('ha_more')}
            </button>
          )}
        </>
      )}
    </div>
  );
}

export function RankingTab() {
  const { t, locale } = useI18n();
  const [f, setF] = useState<HuntFilters>({ period: '7d', metric: 'xp_h' });
  const [rows, setRows] = useState<HuntRow[] | null>(null);
  const [places, setPlaces] = useState<HuntPlaceStat[] | null>(null);

  useEffect(() => {
    const id = setTimeout(() => {
      hunts.ranking(f).then((r) => setRows(r.hunts)).catch(() => setRows([]));
      hunts.places(f).then((r) => setPlaces(r.places)).catch(() => setPlaces([]));
    }, 250);
    return () => clearTimeout(id);
  }, [f]);

  return (
    <div>
      <p className="muted">{t('ha_ranking_intro')}</p>
      <div className="seg" role="group" aria-label={t('ha_metric')}>
        {(['xp_h', 'raw_xp_h', 'balance_h'] as const).map((m) => (
          <button key={m} type="button" className={f.metric === m ? 'active' : ''} onClick={() => setF({ ...f, metric: m })}>
            {t(m === 'xp_h' ? 'ha_xp_h' : m === 'raw_xp_h' ? 'ha_raw_xp_h' : 'ha_balance_h')}
          </button>
        ))}
      </div>
      <Filters f={f} set={setF} withPeriod />

      <div className="ranking-layout">
        <section>
          <h2>🏆 {t('ha_top_hunters')}</h2>
          {rows === null ? (
            <p className="muted">{t('loading')}</p>
          ) : !rows.length ? (
            <p className="card muted">{t('ha_ranking_empty')}</p>
          ) : (
            <div className="hunt-cards">
              {rows.map((h, i) => (
                <HuntCard key={h.public_id} h={h} rank={i + 1} metric={f.metric} />
              ))}
            </div>
          )}
        </section>
        <section>
          <h2>🗺️ {t('ha_community_places')}</h2>
          <p className="muted small">{t('ha_community_places_note')}</p>
          {places && places.length > 0 ? (
            <div className="table-scroll card">
              <table className="compact-table">
                <thead>
                  <tr>
                    <th>{t('ha_place')}</th>
                    <th className="num">{t('ha_hunts')}</th>
                    <th className="num">{t('ha_raw_xp_h')}</th>
                    <th className="num">{t('ha_balance_h')}</th>
                    <th className="num hide-sm">Lv</th>
                  </tr>
                </thead>
                <tbody>
                  {[...places]
                    .sort((a, b) => (f.metric === 'balance_h' ? b.balance_h - a.balance_h : b.raw_xp_h - a.raw_xp_h))
                    .map((p) => (
                      <tr key={p.place_slug}>
                        <td>
                          <a href={`/hunting/${p.place_slug}`} onClick={linkHandler(`/hunting/${p.place_slug}`)}>
                            {p.place_name}
                          </a>
                        </td>
                        <td className="num">{p.hunts}</td>
                        <td className="num">{compact(p.raw_xp_h, locale)}</td>
                        <td className={`num ${p.balance_h < 0 ? 'neg' : 'pos'}`}>{gp(p.balance_h, locale)}</td>
                        <td className="num hide-sm">
                          {p.min_level ?? '?'}–{p.max_level ?? '?'}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="card muted small">{t('ha_places_empty')}</p>
          )}
        </section>
      </div>
    </div>
  );
}
