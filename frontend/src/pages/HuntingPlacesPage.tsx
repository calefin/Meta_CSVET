/**
 * Hunting places directory (D-42): data from TibiaWiki (CC BY-SA). List with vocation/level/quest filters and
 * one page per place with levels, creatures, access quests and the tibiamaps.io map (MIT, CipSoft map data).
 */
import { useEffect, useMemo, useState } from 'react';
import { CreatureImage } from '../components/CreatureImage';
import { ElementValue, useDeltaText } from '../components/ElementValue';
import { HuntTable, type HuntVoc } from '../components/HuntTable';
import { findCreature, loadCreatures, type CreatureStats } from '../core/creatures';
import { bestHits, creatureSlug, damageDealt, resistances, worstHits } from '../core/creatureView';
import { TibiaMap } from '../components/TibiaMap';
import { huntingPlaces, type HuntingPlaceDetail, type HuntingPlaceSummary } from '../api';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { PlaceCommunityStats } from '../components/PlaceCommunityStats';

/**
 * D-56: the five vocations. TibiaWiki only has knights / paladins / mages: sorcerer and druid use the mage level;
 * Monk has no wiki data yet, so it shows the knight level as an estimate (marked ≈).
 */
type Voc = 'all' | 'knight' | 'paladin' | 'sorcerer' | 'druid' | 'monk';
const VOCS: Exclude<Voc, 'all'>[] = ['knight', 'paladin', 'sorcerer', 'druid', 'monk'];
const VOC_ICONS: Record<Exclude<Voc, 'all'>, string> = { knight: '🗡️', paladin: '🏹', sorcerer: '🔥', druid: '❄️', monk: '👊' };
/** Saved choice from the old 3-way filter */
const LEGACY_VOC: Record<string, Voc> = { knights: 'knight', paladins: 'paladin', mages: 'sorcerer' };
const readVoc = (): Voc => {
  const v = store.get('bagparser_hunt_voc') ?? 'all';
  return (LEGACY_VOC[v] ?? ((['all', ...VOCS] as string[]).includes(v) ? v : 'all')) as Voc;
};
type Sort = 'level' | 'exp' | 'loot' | 'name';
const VOC_KEY = 'bagparser_hunt_voc';
const LEVEL_KEY = 'bagparser_hunt_level';
const VIEW_KEY = 'bagparser_hunt_view';

const store = {
  get: (k: string) => {
    try {
      return localStorage.getItem(k);
    } catch {
      return null;
    }
  },
  set: (k: string, v: string) => {
    try {
      localStorage.setItem(k, v);
    } catch {
      /* private mode */
    }
  },
};

const WIKI = 'https://tibia.fandom.com/wiki/';
const wikiUrl = (title: string) => WIKI + encodeURIComponent(title.replace(/ /g, '_'));

export function Stars({ n, label }: { n: number | null; label: string }) {
  if (!n) return <span className="muted">—</span>;
  return (
    <span className="stars" title={`${label}: ${n}/5`} aria-label={`${label}: ${n}/5`}>
      {'★'.repeat(n)}
      <span className="stars-off">{'★'.repeat(Math.max(0, 5 - n))}</span>
    </span>
  );
}

function levelFor(p: Pick<HuntingPlaceSummary, 'lvl_knights' | 'lvl_paladins' | 'lvl_mages'>, voc: Voc): number | null {
  if (voc === 'knight' || voc === 'monk') return p.lvl_knights;
  if (voc === 'paladin') return p.lvl_paladins;
  if (voc === 'sorcerer' || voc === 'druid') return p.lvl_mages;
  const all = [p.lvl_knights, p.lvl_paladins, p.lvl_mages].filter((v): v is number => v != null);
  return all.length ? Math.min(...all) : null;
}


export function QuestBadge({ quests }: { quests: string[] }) {
  const { t } = useI18n();
  if (!quests.length) return null;
  return (
    <span className="tag quest" title={quests.join(', ')}>
      {t('hunt_needs_quest')}
    </span>
  );
}

function HuntingList() {
  const { t, n } = useI18n();
  const [places, setPlaces] = useState<HuntingPlaceSummary[] | null>(null);
  const [error, setError] = useState(false);
  const [voc, setVoc] = useState<Voc>(readVoc);
  const [level, setLevel] = useState(() => store.get(LEVEL_KEY) ?? '');
  const [q, setQ] = useState('');
  const [city, setCity] = useState('');
  const [noQuest, setNoQuest] = useState(false);
  const [sort, setSort] = useState<Sort>('level');
  // D-80: the per-vocation table is what most people come for; the full directory is one tap away
  const [view, setView] = useState<'table' | 'all'>(() => (store.get(VIEW_KEY) === 'all' ? 'all' : 'table'));
  const tableVoc: HuntVoc = voc === 'all' ? 'knight' : voc;

  useEffect(() => {
    huntingPlaces
      .list()
      .then(setPlaces)
      .catch(() => setError(true));
  }, []);

  const cities = useMemo(() => [...new Set((places ?? []).map((p) => p.city))].sort(), [places]);

  const rows = useMemo(() => {
    if (!places) return [];
    const lv = Number(level) || 0;
    const needle = q.trim().toLowerCase();
    const out = places.filter((p) => {
      const req = levelFor(p, voc);
      if (voc !== 'all' && req == null) return false;
      if (lv && req != null && req > lv) return false;
      if (city && p.city !== city) return false;
      if (noQuest && p.access_quests.length) return false;
      if (needle && !`${p.name} ${p.city} ${p.creatures.join(' ')}`.toLowerCase().includes(needle)) return false;
      return true;
    });
    const by: Record<Sort, (a: HuntingPlaceSummary, b: HuntingPlaceSummary) => number> = {
      // With "my level": closest to it first (the best places the character can already do)
      // Places without a recommended level always go last
      level: (a, b) => {
        const la = levelFor(a, voc);
        const lb = levelFor(b, voc);
        if (la == null || lb == null) return (la == null ? 1 : 0) - (lb == null ? 1 : 0);
        return lv ? lb - la : la - lb;
      },
      exp: (a, b) => (b.exp_stars ?? 0) - (a.exp_stars ?? 0),
      loot: (a, b) => (b.loot_stars ?? 0) - (a.loot_stars ?? 0),
      name: (a, b) => a.name.localeCompare(b.name),
    };
    return out.sort((a, b) => by[sort](a, b) || a.name.localeCompare(b.name));
  }, [places, voc, level, q, city, noQuest, sort]);

  const chooseVoc = (v: Voc) => {
    setVoc(v);
    store.set(VOC_KEY, v);
  };

  return (
    <div className="page hunting-page">
      <h1>{t('hunt_title')}</h1>
      <p className="muted">{t('hunt_intro')}</p>
      {error && <p className="alert">{t('api_error')}</p>}
      {!places && !error && <p className="muted">{t('loading')}</p>}

      {places && (
        <section className="card">
          <div className="segmented ht-view" role="group" aria-label={t('ht_view')}>
            {(['table', 'all'] as const).map((v) => (
              <button
                key={v}
                type="button"
                className={view === v ? 'active' : ''}
                aria-pressed={view === v}
                onClick={() => {
                  setView(v);
                  store.set(VIEW_KEY, v);
                }}
              >
                {t(`ht_tab_${v}`)}
              </button>
            ))}
          </div>
          <div className="segmented hunt-vocs" role="group" aria-label={t('vocation')}>
            {((view === 'table' ? VOCS : ['all', ...VOCS]) as Voc[]).map((v) => (
              <button
                key={v}
                type="button"
                className={(view === 'table' ? tableVoc : voc) === v ? 'active' : ''}
                aria-pressed={(view === 'table' ? tableVoc : voc) === v}
                onClick={() => chooseVoc(v)}
              >
                {v === 'all' ? t('hunt_voc_all') : `${VOC_ICONS[v]} ${t(`hv_${v}`)}`}
              </button>
            ))}
          </div>
          {view === 'table' && (
            <div className="hunt-filters">
              <label className="field">
                <span>{t('hunt_my_level')}</span>
                <input
                  type="number"
                  inputMode="numeric"
                  min={1}
                  max={3000}
                  value={level}
                  onChange={(e) => {
                    setLevel(e.target.value);
                    store.set(LEVEL_KEY, e.target.value);
                  }}
                  placeholder="400"
                />
              </label>
            </div>
          )}
          {view === 'table' && <HuntTable voc={tableVoc} level={Number(level) || 0} />}
          {view === 'all' && (
          <>
          <div className="hunt-filters">
            <label className="field">
              <span>{t('hunt_my_level')}</span>
              <input
                type="number"
                inputMode="numeric"
                min={1}
                max={3000}
                value={level}
                onChange={(e) => {
                  setLevel(e.target.value);
                  store.set(LEVEL_KEY, e.target.value);
                }}
                placeholder="400"
              />
            </label>
            <label className="field">
              <span>{t('hunt_search')}</span>
              <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('hunt_search_hint')} />
            </label>
            <label className="field">
              <span>{t('city')}</span>
              <select value={city} onChange={(e) => setCity(e.target.value)}>
                <option value="">{t('hunt_all_cities')}</option>
                {cities.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>{t('hunt_sort')}</span>
              <select value={sort} onChange={(e) => setSort(e.target.value as Sort)}>
                {(['level', 'exp', 'loot', 'name'] as Sort[]).map((s) => (
                  <option key={s} value={s}>
                    {t(`hunt_sort_${s}`)}
                  </option>
                ))}
              </select>
            </label>
            <label className="check">
              <input type="checkbox" checked={noQuest} onChange={(e) => setNoQuest(e.target.checked)} />
              <span>{t('hunt_no_quest')}</span>
            </label>
          </div>
          <p className="muted small">{t('hunt_count', { n: rows.length })}</p>

          <ul className="hunt-list">
            {rows.slice(0, 200).map((p) => (
              <li key={p.slug}>
                <a href={`/hunting/${p.slug}`} onClick={linkHandler(`/hunting/${p.slug}`)}>
                  <span className="hunt-name">
                    <strong>{p.name}</strong>
                    <span className="muted small">{p.city}</span>
                  </span>
                  <span className="hunt-meta">
                    <span className="hunt-level">
                      {levelFor(p, voc) != null ? `${voc === 'monk' ? '≈ ' : ''}${t('level_short')} ${n(levelFor(p, voc)!)}+` : '—'}
                    </span>
                    <Stars n={p.exp_stars} label={t('hunt_exp')} />
                    <Stars n={p.loot_stars} label={t('hunt_loot')} />
                    <QuestBadge quests={p.access_quests} />
                  </span>
                  <span className="hunt-creatures muted small">
                    {p.creatures.slice(0, 6).join(', ')}
                    {p.creatures.length > 6 ? ` +${p.creatures.length - 6}` : ''}
                  </span>
                </a>
              </li>
            ))}
          </ul>
          {rows.length > 200 && <p className="muted small">{t('hunt_more', { n: rows.length - 200 })}</p>}
          <p className="muted small">{t('hunt_source')} TibiaWiki (CC BY-SA 3.0)</p>
          </>
          )}
        </section>
      )}
    </div>
  );
}

/** D-80: one line per creature with what matters to fight it; opens the full sheet */
function PlaceCreatures({ creatures }: { creatures: HuntingPlaceDetail['creatures'] }) {
  const { t, n } = useI18n();
  const delta = useDeltaText();
  const [stats, setStats] = useState<Map<string, CreatureStats> | null>(null);
  useEffect(() => {
    void loadCreatures().then(setStats);
  }, []);
  return (
    <div className="cr-table-wrap">
      <table className="cr-table">
        <thead>
          <tr>
            <th>{t('cr_creature')}</th>
            <th className="num">HP</th>
            <th>{t('cr_hit_with')}</th>
            <th>{t('cr_avoid')}</th>
            <th>{t('cr_deals')}</th>
          </tr>
        </thead>
        <tbody>
          {creatures.map((c) => {
            const s = stats ? findCreature(stats, c.name) : null;
            const res = s ? resistances(s) : [];
            const href = `/creatures/${creatureSlug(s?.name ?? c.name)}`;
            return (
              <tr key={c.id}>
                <td>
                  <a className="cr-name" href={href} onClick={linkHandler(href)}>
                    <CreatureImage name={c.name} url={c.image_url} size={32} />
                    <span>{c.name}</span>
                  </a>
                </td>
                <td className="num">{s ? n(s.hp) : '—'}</td>
                <td>{bestHits(res).slice(0, 1).map((r) => <ElementValue key={r.element} element={r.element} value={delta(r.pct)} tone="pos" />)}</td>
                <td>{worstHits(res).slice(0, 1).map((r) => <ElementValue key={r.element} element={r.element} value={delta(r.pct)} tone="neg" />)}</td>
                <td className="ht-els">
                  {s && damageDealt(s).slice(0, 2).map(([e, pct]) => <ElementValue key={e} element={e} value={`${pct}%`} />)}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function HuntingDetail({ slug }: { slug: string }) {
  const { t, n } = useI18n();
  const [p, setP] = useState<HuntingPlaceDetail | null>(null);
  const [error, setError] = useState<number | null>(null);

  useEffect(() => {
    setP(null);
    huntingPlaces
      .get(slug)
      .then((d) => {
        setP(d);
        document.title = `${d.name} | BagParser`;
      })
      .catch((e) => setError(e?.status ?? 500));
  }, [slug]);

  if (error) {
    return (
      <div className="page">
        <p className="alert">{error === 404 ? t('hunt_not_found') : t('api_error')}</p>
        <a href="/hunting" onClick={linkHandler('/hunting')}>
          ← {t('hunt_title')}
        </a>
      </div>
    );
  }
  if (!p) return <p className="muted">{t('loading')}</p>;

  const rows: { key: Exclude<Voc, 'all'>; lvl: number | null; sk: number | null; estimate?: boolean }[] = [
    { key: 'knight', lvl: p.lvl_knights, sk: p.sk_knights },
    { key: 'paladin', lvl: p.lvl_paladins, sk: p.sk_paladins },
    { key: 'sorcerer', lvl: p.lvl_mages, sk: p.sk_mages },
    { key: 'druid', lvl: p.lvl_mages, sk: p.sk_mages },
    { key: 'monk', lvl: p.lvl_knights, sk: null, estimate: true },
  ];

  return (
    <div className="page hunting-detail">
      <a href="/hunting" onClick={linkHandler('/hunting')} className="small">
        ← {t('hunt_title')}
      </a>
      <h1>{p.name}</h1>
      <p className="muted">
        {p.city}
        {p.location ? ` · ${p.location}` : ''}
      </p>

      <div className="hunt-detail-grid">
        <section className="card">
          <h2>{t('hunt_summary')}</h2>
          <dl className="hunt-facts">
            <dt>{t('hunt_exp')}</dt>
            <dd>
              <Stars n={p.exp_stars} label={t('hunt_exp')} /> {p.exp_text && <span className="muted small">{p.exp_text}</span>}
            </dd>
            <dt>{t('hunt_loot')}</dt>
            <dd>
              <Stars n={p.loot_stars} label={t('hunt_loot')} /> {p.loot_text && <span className="muted small">{p.loot_text}</span>}
            </dd>
            <dt>{t('hunt_access')}</dt>
            <dd>
              {p.access_quests.length ? (
                <>
                  {p.access_quests.map((q, i) => (
                    <span key={q}>
                      {i > 0 && ', '}
                      <a href={wikiUrl(q)} target="_blank" rel="noopener">
                        {q}
                      </a>
                    </span>
                  ))}
                  <div className="muted small">{t('hunt_quest_note')}</div>
                </>
              ) : (
                <span className="muted">{t('hunt_no_quest_known')}</span>
              )}
            </dd>
          </dl>
          <table className="hunt-levels">
            <thead>
              <tr>
                <th>{t('vocation')}</th>
                <th className="num">{t('min_level')}</th>
                <th className="num">{t('hunt_skill')}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.key}>
                  <td>
                    {VOC_ICONS[r.key]} {t(`hv_${r.key}`)}
                  </td>
                  <td className="num">{r.lvl != null ? `${r.estimate ? '≈ ' : ''}${n(r.lvl)}` : '—'}</td>
                  <td className="num">{r.sk ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="muted small">{t('hv_monk_note')}</p>
          {p.best_loot.length > 0 && (
            <>
              <h3>{t('hunt_best_loot')}</h3>
              <p>{p.best_loot.join(', ')}</p>
            </>
          )}
        </section>

        <section className="card">
          <h2>{t('hunt_map')}</h2>
          {p.coord_x != null && p.coord_y != null && p.coord_z != null ? (
            <>
              <TibiaMap x={p.coord_x} y={p.coord_y} z={p.coord_z} label={`${t('hunt_map')}: ${p.name}`} />
              <p className="muted small">{t('hunt_map_credit')}</p>
            </>
          ) : (
            <p className="muted">{t('hunt_no_map')}</p>
          )}
        </section>
      </div>

      <section className="card">
        <h2>{t('hunt_creatures')}</h2>
        <PlaceCreatures creatures={p.creatures} />
      </section>

      <PlaceCommunityStats slug={p.slug} />

      <p className="muted small">
        {t('hunt_source')}{' '}
        <a href={wikiUrl(p.wiki_title)} target="_blank" rel="noopener">
          TibiaWiki: {p.wiki_title}
        </a>
      </p>
    </div>
  );
}

export function HuntingPlacesPage({ slug }: { slug?: string }) {
  return slug ? <HuntingDetail slug={slug} /> : <HuntingList />;
}
