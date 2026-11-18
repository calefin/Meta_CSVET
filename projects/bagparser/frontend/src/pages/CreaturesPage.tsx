/**
 * Creatures (D-80): a compact searchable list and one sheet per creature. The sheet answers first what to hit it with,
 * what to protect from and what it drops; the rest of the wiki data stays folded below. Data: TibiaWiki (CC BY-SA).
 */
import { useEffect, useMemo, useState } from 'react';
import { creatureInfo, type CreatureSheet } from '../api';
import { CreatureImage } from '../components/CreatureImage';
import { ElementValue, elementName, useDeltaText } from '../components/ElementValue';
import { ItemSprite, useSprites } from '../components/ItemSprite';
import { BESTIARY, bestiaryKills, elementIcon, loadCreatures, type CreatureStats } from '../core/creatures';
import { alertsOf, barWidth, bestHits, creatureSlug, damageDealt, lootByRarity, resistances, worstHits } from '../core/creatureView';
import { defaultLookupDb, findItem } from '../core/items';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

const WIKI = 'https://tibia.fandom.com/wiki/';
type Sort = 'name' | 'exp' | 'hp';
const LIST_LIMIT = 150;

function CreatureList() {
  const { t, n } = useI18n();
  const delta = useDeltaText();
  const [all, setAll] = useState<CreatureStats[] | null>(null);
  const [q, setQ] = useState('');
  const [sort, setSort] = useState<Sort>('name');
  const [bosses, setBosses] = useState(false);

  useEffect(() => {
    void loadCreatures().then((m) => setAll([...m.values()]));
  }, []);

  const rows = useMemo(() => {
    const needle = q.trim().toLowerCase();
    const by: Record<Sort, (a: CreatureStats, b: CreatureStats) => number> = {
      name: (a, b) => a.name.localeCompare(b.name),
      exp: (a, b) => b.exp - a.exp,
      hp: (a, b) => b.hp - a.hp,
    };
    return (all ?? []).filter((c) => (bosses || !c.boss) && (!needle || c.name.toLowerCase().includes(needle))).sort(by[sort]);
  }, [all, q, sort, bosses]);

  return (
    <div className="page">
      <h1>{t('nav_creatures')}</h1>
      <p className="muted">{t('cr_intro')}</p>
      <section className="card">
        <div className="hunt-filters">
          <label className="field">
            <span>{t('hunt_search')}</span>
            <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Mitmah Seer" />
          </label>
          <label className="field">
            <span>{t('hunt_sort')}</span>
            <select value={sort} onChange={(e) => setSort(e.target.value as Sort)}>
              <option value="name">{t('hunt_sort_name')}</option>
              <option value="exp">XP</option>
              <option value="hp">HP</option>
            </select>
          </label>
          <label className="check">
            <input type="checkbox" checked={bosses} onChange={(e) => setBosses(e.target.checked)} />
            <span>{t('cr_show_bosses')}</span>
          </label>
        </div>
        {!all && <p className="muted">{t('loading')}</p>}
        {all && (
          <>
            <p className="muted small">{t('cr_count', { n: n(rows.length) })}</p>
            <div className="cr-table-wrap">
              <table className="cr-table">
                <thead>
                  <tr>
                    <th>{t('cr_creature')}</th>
                    <th className="num">HP</th>
                    <th className="num">XP</th>
                    <th>{t('cr_hit_with')}</th>
                    <th>{t('cr_avoid')}</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.slice(0, LIST_LIMIT).map((c) => {
                    const res = resistances(c);
                    const href = `/creatures/${creatureSlug(c.name)}`;
                    return (
                      <tr key={c.name}>
                        <td>
                          <a className="cr-name" href={href} onClick={linkHandler(href)}>
                            <CreatureImage name={c.name} size={32} />
                            <span>{c.name}</span>
                          </a>
                        </td>
                        <td className="num">{n(c.hp)}</td>
                        <td className="num">{n(c.exp)}</td>
                        <td>{bestHits(res).slice(0, 1).map((r) => <ElementValue key={r.element} element={r.element} value={delta(r.pct)} tone="pos" />)}</td>
                        <td>{worstHits(res).slice(0, 1).map((r) => <ElementValue key={r.element} element={r.element} value={delta(r.pct)} tone="neg" />)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            {rows.length > LIST_LIMIT && <p className="muted small">{t('cr_list_more', { n: n(rows.length - LIST_LIMIT) })}</p>}
          </>
        )}
        <p className="muted small">{t('hunt_source')} TibiaWiki (CC BY-SA 3.0)</p>
      </section>
    </div>
  );
}

function Folded({ title, text }: { title: string; text?: string | null }) {
  if (!text) return null;
  return (
    <details className="cr-fold">
      <summary>{title}</summary>
      <p lang="en">{text}</p>
    </details>
  );
}

function Sheet({ slug }: { slug: string }) {
  const { t, n } = useI18n();
  const delta = useDeltaText();
  const sprites = useSprites();
  const [s, setS] = useState<CreatureSheet | null>(null);
  const [error, setError] = useState<number | null>(null);

  useEffect(() => {
    setS(null);
    setError(null);
    creatureInfo
      .get(slug)
      .then((d) => {
        setS(d);
        document.title = `${d.name} | BagParser`;
      })
      .catch((e) => setError(e?.status ?? 500));
  }, [slug]);

  const back = (
    <a href="/creatures" onClick={linkHandler('/creatures')} className="small">
      ← {t('nav_creatures')}
    </a>
  );
  if (error) {
    return (
      <div className="page">
        <p className="alert">{error === 404 ? t('cr_not_found') : t('api_error')}</p>
        {back}
      </div>
    );
  }
  if (!s) return <p className="muted">{t('loading')}</p>;

  const c = s.stats;
  const rows = c ? resistances(c) : [];
  const best = bestHits(rows);
  const worst = worstHits(rows);
  const dealt = c ? damageDealt(c) : [];
  const alerts = alertsOf(s);
  const price = (name: string) => findItem(name, defaultLookupDb)?.npc_price ?? 0;
  const groups = lootByRarity(s.loot, price);
  const kills = c ? bestiaryKills(c) : null;
  const points = c?.lvl ? BESTIARY[c.lvl]?.points : undefined;

  return (
    <div className="page cr-sheet">
      {back}
      <header className="cr-head">
        <CreatureImage name={s.name} size={64} />
        <div>
          <h1>{s.name}</h1>
          <p className="muted small">
            {[c?.lvl, kills ? t('cr_kills', { n: n(kills) }) : null, points ? t('cr_charm', { n: n(points) }) : null, c?.boss ? t('cr_boss') : null]
              .filter(Boolean)
              .join(' · ')}
          </p>
        </div>
      </header>
      {c && (
        <dl className="cr-stats">
          <div>
            <dt>HP</dt>
            <dd>{n(c.hp)}</dd>
          </div>
          <div>
            <dt>XP</dt>
            <dd>{n(c.exp)}</dd>
          </div>
          <div>
            <dt>{t('cr_xp_hp')}</dt>
            <dd>{(c.exp / c.hp).toFixed(2)}</dd>
          </div>
          {s.maxHit && (
            <div>
              <dt>{t('cr_max_hit')}</dt>
              <dd>{s.maxHit}</dd>
            </div>
          )}
        </dl>
      )}

      <div className="cr-answer">
        <section className="card">
          <h2>{t('cr_hit_with')}</h2>
          {best.length ? (
            <ul className="cr-picks">
              {best.map((r) => (
                <li key={r.element}>
                  <ElementValue element={r.element} value={delta(r.pct)} tone="pos" showName />
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">{t('cr_no_weakness')}</p>
          )}
          {worst.length > 0 && (
            <p className="small cr-avoid">
              {t('cr_avoid')}:{' '}
              {worst.map((r) => (
                <ElementValue key={r.element} element={r.element} value={delta(r.pct)} tone="neg" />
              ))}
            </p>
          )}
        </section>
        <section className="card">
          <h2>{t('cr_protect')}</h2>
          {dealt.length ? (
            <ul className="cr-picks">
              {dealt.slice(0, 3).map(([e, pct]) => (
                <li key={e}>
                  <ElementValue element={e} value={`${pct}%`} showName />
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">{t('cr_no_data')}</p>
          )}
          {dealt.length > 0 && <p className="muted small">{t('cr_dealt_note')}</p>}
        </section>
      </div>

      {alerts.length > 0 && (
        <ul className="cr-alerts">
          {alerts.map((a) => (
            <li key={a.key}>⚠ {t(a.key, a.vars)}</li>
          ))}
        </ul>
      )}

      {rows.length > 0 && (
        <section className="card">
          <h2>{t('cr_weak_strong')}</h2>
          <div className="cr-bars-legend muted small" aria-hidden="true">
            <span>{t('cr_less')}</span>
            <span>{t('cr_more')}</span>
          </div>
          <ul className="cr-bars">
            {rows.map((r) => (
              <li key={r.element} className={r.delta > 0 ? 'pos' : r.delta < 0 ? 'neg' : ''}>
                <span className="cr-bar-label">
                  <span aria-hidden="true">{elementIcon(r.element)}</span> {elementName(r.element)}
                </span>
                <span className="cr-bar-value">{delta(r.pct)}</span>
                <span className="cr-bar" aria-hidden="true">
                  <span className="cr-bar-half left">{r.delta < 0 && <i style={{ width: `${barWidth(r.delta, rows)}%` }} />}</span>
                  <span className="cr-bar-half right">{r.delta > 0 && <i style={{ width: `${barWidth(r.delta, rows)}%` }} />}</span>
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="card">
        <h2>{t('cr_drops')}</h2>
        {groups.length === 0 && <p className="muted">{t('cr_no_loot')}</p>}
        {groups.map((g) => (
          <div key={g.rarity} className="cr-loot-group">
            <h3>{t(`rar_${g.rarity.replace(/[ -]/g, '_')}`)}</h3>
            <ul className="cr-loot">
              {g.items.map((l) => {
                const v = /^(gold|platinum|crystal) coin$/i.test(l.i) ? 0 : price(l.i);
                return (
                  <li key={l.i}>
                    <ItemSprite name={l.i} sprites={sprites} />
                    <span>
                      {l.i}
                      {l.q && l.q !== '1' ? <span className="muted small"> × {l.q}</span> : null}
                    </span>
                    {v > 0 && <span className="muted small cr-loot-price">{t('cr_npc', { n: n(v) })}</span>}
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </section>

      <section className="card">
        <h2>{t('cr_where')}</h2>
        {s.places.length ? (
          <ul className="cr-places">
            {s.places.map((p) => (
              <li key={p.slug}>
                <a href={`/hunting/${p.slug}`} onClick={linkHandler(`/hunting/${p.slug}`)}>
                  {p.name}
                </a>
                <span className="muted small">
                  {p.city}
                  {p.level ? ` · ${t('level_short')} ${n(p.level)}+` : ''}
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="muted">{s.loc || t('cr_no_data')}</p>
        )}
      </section>

      <section className="card cr-more">
        {s.ab.length > 0 && (
          <details className="cr-fold">
            <summary>{t('cr_abilities')}</summary>
            <ul className="cr-abilities">
              {s.ab.map((a, i) => (
                <li key={`${a.n}${i}`}>
                  <span aria-hidden="true">{a.e ? elementIcon(a.e) : a.k === 'heal' ? '💚' : '•'}</span> {a.n}
                  {a.r && <span className="muted small"> {a.r}</span>}
                </li>
              ))}
            </ul>
          </details>
        )}
        <details className="cr-fold">
          <summary>{t('cr_traits')}</summary>
          <dl className="hunt-facts">
            {c?.spd != null && (
              <>
                <dt>{t('cr_speed')}</dt>
                <dd>{n(c.spd)}</dd>
              </>
            )}
            {c?.arm != null && (
              <>
                <dt>{t('cr_armor')}</dt>
                <dd>{n(c.arm)}</dd>
              </>
            )}
            {c?.mit != null && (
              <>
                <dt>{t('cr_mitigation')}</dt>
                <dd>{c.mit}%</dd>
              </>
            )}
            {s.kind && (
              <>
                <dt>{t('cr_kind')}</dt>
                <dd>{s.kind}</dd>
              </>
            )}
            {s.tr.push != null && (
              <>
                <dt>{t('cr_pushable')}</dt>
                <dd>{s.tr.push ? t('cr_yes') : t('cr_no')}</dd>
              </>
            )}
            {s.tr.through && (
              <>
                <dt>{t('cr_through')}</dt>
                <dd lang="en">{s.tr.through}</dd>
              </>
            )}
            {s.tr.summon != null && (
              <>
                <dt>{t('cr_summon')}</dt>
                <dd>{n(s.tr.summon)}</dd>
              </>
            )}
            {s.tr.convince != null && (
              <>
                <dt>{t('cr_convince')}</dt>
                <dd>{n(s.tr.convince)}</dd>
              </>
            )}
          </dl>
        </details>
        <Folded title={t('cr_behaviour')} text={s.beh} />
        <Folded title={t('cr_strategy')} text={s.strat} />
        <Folded title={t('cr_notes')} text={s.notes} />
        <Folded title={t('cr_description')} text={s.desc} />
      </section>

      <p className="muted small">
        {t('hunt_source')}{' '}
        <a href={WIKI + encodeURIComponent(s.title.replace(/ /g, '_'))} target="_blank" rel="noopener">
          TibiaWiki: {s.title}
        </a>{' '}
        (CC BY-SA 3.0)
      </p>
    </div>
  );
}

export function CreaturesPage({ slug }: { slug?: string }) {
  return slug ? <Sheet slug={slug} /> : <CreatureList />;
}
