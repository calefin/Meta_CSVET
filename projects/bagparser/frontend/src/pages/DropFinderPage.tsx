/**
 * Drop finder (D-58) — lookup only, no account: which creature is most likely to drop each item a delivery
 * task asks for, how many kills that takes on average, and the hunting places where you get them —
 * including places that cover several items of your list in the same hunt.
 * Data: TibiaWiki "Dropped By" + "Loot Statistics" (CC BY-SA), our hunting places and spawn densities.
 */
import { CreatureLink } from '../components/CreatureLink';
import { useEffect, useMemo, useState } from 'react';
import { weekly, type DropSources } from '../api';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { ItemSearch } from '../components/ItemSearch';
import { ItemSprite, useSprites } from '../components/ItemSprite';
import { CreatureImage } from '../components/CreatureImage';

const MAX_ITEMS = 6;

function readUrl(): { items: string[]; amount: number } {
  const q = new URLSearchParams(window.location.search);
  const items = q
    .getAll('item')
    .map((s) => s.trim().toLowerCase())
    .filter(Boolean)
    .slice(0, MAX_ITEMS);
  const amount = Math.min(10_000, Math.max(1, Number(q.get('n')) || 1));
  return { items: [...new Set(items)], amount };
}

function writeUrl(items: string[], amount: number) {
  const q = new URLSearchParams();
  for (const i of items) q.append('item', i);
  if (amount !== 1) q.set('n', String(amount));
  const s = q.toString();
  window.history.replaceState({}, '', `/drops${s ? `?${s}` : ''}`);
}

/** "12.5%", "0.48%", "0.012%" */
const pct = (r: number) => `${(r * 100).toLocaleString(undefined, { maximumFractionDigits: r >= 0.1 ? 1 : r >= 0.01 ? 2 : 3 })}%`;

export function DropFinderPage() {
  const { t, n } = useI18n();
  const sprites = useSprites();
  const initial = useMemo(readUrl, []);
  const [items, setItems] = useState<string[]>(initial.items);
  const [amount, setAmount] = useState(initial.amount);
  const [data, setData] = useState<Record<string, DropSources | 'loading' | 'error'>>({});

  useEffect(() => writeUrl(items, amount), [items, amount]);

  useEffect(() => {
    for (const item of items) {
      if (data[item]) continue;
      setData((d) => ({ ...d, [item]: 'loading' }));
      weekly
        .drops(item)
        .then((r) => setData((d) => ({ ...d, [item]: r })))
        .catch(() => setData((d) => ({ ...d, [item]: 'error' })));
    }
  }, [items]); // eslint-disable-line react-hooks/exhaustive-deps

  const add = (item: string) => setItems((list) => (list.includes(item) ? list : [...list, item].slice(-MAX_ITEMS)));
  const remove = (item: string) => setItems((list) => list.filter((i) => i !== item));

  /** Places where several items of the list drop: more items first, then the sum of the item scores */
  const combined = useMemo(() => {
    const loaded = items.map((i) => [i, data[i]] as const).filter((x): x is [string, DropSources] => typeof x[1] === 'object');
    if (loaded.length < 2) return [];
    const places = new Map<string, { zone: string; slug?: string | null; city: string; min_level: number; items: Set<string>; score: number }>();
    for (const [item, d] of loaded) {
      for (const p of d.places) {
        const k = p.slug ?? p.zone;
        const cur = places.get(k) ?? { zone: p.zone, slug: p.slug, city: p.city, min_level: p.min_level, items: new Set<string>(), score: 0 };
        cur.items.add(item);
        cur.score += p.score ?? 0;
        places.set(k, cur);
      }
    }
    return [...places.values()]
      .filter((p) => p.items.size >= 2)
      .sort((a, b) => b.items.size - a.items.size || b.score - a.score)
      .slice(0, 8);
  }, [items, data]);

  return (
    <div className="page drop-finder">
      <h1>🎯 {t('drop_title')}</h1>
      <p className="muted">{t('drop_intro')}</p>

      <section className="card drop-controls">
        <ItemSearch onPick={add} autoFocus={!items.length} />
        <label className="drop-amount">
          <span className="small">{t('drop_amount')}</span>
          <input
            type="number"
            min={1}
            max={10000}
            inputMode="numeric"
            value={amount}
            onChange={(e) => setAmount(Math.min(10_000, Math.max(1, Number(e.target.value) || 1)))}
          />
        </label>
        {items.length > 0 && (
          <div className="drop-chips">
            {items.map((i) => (
              <span key={i} className="chip ok">
                <ItemSprite name={i} sprites={sprites} size={20} /> {i}
                <button type="button" className="icon-btn" aria-label={`${t('delete')} ${i}`} onClick={() => remove(i)}>
                  ✕
                </button>
              </span>
            ))}
            <button type="button" className="button secondary small-btn" onClick={() => setItems([])}>
              {t('ha_clear')}
            </button>
          </div>
        )}
      </section>

      {!items.length && <p className="muted">{t('drop_empty')}</p>}

      {combined.length > 0 && (
        <section className="card drop-combined">
          <h2>🗺️ {t('drop_combined_title')}</h2>
          <p className="muted small">{t('drop_combined_note')}</p>
          <ul className="plain-list">
            {combined.map((p) => (
              <li key={p.slug ?? p.zone}>
                {p.slug ? (
                  <a href={`/hunting/${p.slug}`} onClick={linkHandler(`/hunting/${p.slug}`)}>
                    <strong>{p.zone}</strong>
                  </a>
                ) : (
                  <strong>{p.zone}</strong>
                )}{' '}
                <span className="muted small">
                  · {p.city} · Lv {p.min_level}+ · {t('drop_covers', { n: p.items.size, total: items.length })}: {[...p.items].join(', ')}
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {items.map((item) => {
        const d = data[item];
        return (
          <section key={item} className="card drop-item">
            <div className="drop-item-head">
              <ItemSprite name={item} sprites={sprites} size={32} />
              <h2>{item}</h2>
            </div>
            {d === 'loading' || !d ? (
              <p className="muted">{t('loading_wiki')}</p>
            ) : d === 'error' ? (
              <p className="error">{t('drop_error')}</p>
            ) : !d.creatures.length ? (
              <p className="muted">{t('drop_no_creatures')}</p>
            ) : (
              <>
                <h3>{t('drop_best_creatures')}</h3>
                <div className="table-scroll">
                  <table className="compact-table">
                    <thead>
                      <tr>
                        <th>{t('ha_creature')}</th>
                        <th className="num">{t('drop_chance')}</th>
                        <th className="num">{t('drop_kills_for', { n: amount })}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(d.creatures.some((c) => !c.boss) ? d.creatures.filter((c) => !c.boss) : d.creatures).slice(0, 10).map((c, i) => (
                        <tr key={c.name} className={i === 0 && c.rate ? 'best' : ''}>
                          <td>
                            <span className="item-cell">
                              <CreatureImage name={c.name} url={c.image_url} size={28} />
                              <CreatureLink name={c.name} />
                              {i === 0 && c.rate ? <span className="tag ok">{t('drop_best')}</span> : null}
                            </span>
                          </td>
                          <td className="num" title={c.kills ? t('drop_sample', { n: n(c.kills) }) : ''}>
                            {c.rate != null ? pct(c.rate) : '—'}
                          </td>
                          <td className="num">{c.rate ? `≈ ${n(Math.ceil(amount / c.rate))}` : '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                {d.creatures.some((c) => !c.boss) && d.creatures.some((c) => c.boss) && (
                  <p className="small drop-bosses">
                    👑 <strong>{t('drop_bosses')}</strong>{' '}
                    {d.creatures
                      .filter((c) => c.boss)
                      .slice(0, 8)
                      .map((c) => c.name)
                      .join(', ')}
                  </p>
                )}
                {!d.creatures.some((c) => !c.boss) && <p className="muted small">👑 {t('drop_only_bosses')}</p>}
                {d.places.length > 0 && (
                  <>
                    <h3>{t('drop_best_places')}</h3>
                    <ul className="plain-list drop-places">
                      {d.places.slice(0, 5).map((p) => (
                        <li key={`${p.zone}-${p.creature}`}>
                          {p.slug ? (
                            <a href={`/hunting/${p.slug}`} onClick={linkHandler(`/hunting/${p.slug}`)}>
                              <strong>{p.zone}</strong>
                            </a>
                          ) : (
                            <strong>{p.zone}</strong>
                          )}{' '}
                          <span className="muted small">
                            · {p.city} · Lv {p.min_level}+ · {p.creature}
                            {p.rate != null ? ` (${pct(p.rate)})` : ''}
                          </span>
                        </li>
                      ))}
                    </ul>
                  </>
                )}
                <p className="muted small">{t('drop_note')}</p>
              </>
            )}
          </section>
        );
      })}
    </div>
  );
}
