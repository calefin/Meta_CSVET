/**
 * Hunts per vocation (D-80): level, XP/h, profit/h, best element to hit with and what to protect from.
 * shared/hunt-table.json is built by scripts/build-hunt-table.mjs. No team hunts. "var" = no number defined yet.
 */
import { Fragment, useEffect, useMemo, useState } from 'react';
import { creatureSlug } from '../core/creatureView';
import { elementIcon } from '../core/creatures';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { ElementValue, elementName, useDeltaText } from './ElementValue';

export type HuntVoc = 'knight' | 'paladin' | 'monk' | 'sorcerer' | 'druid';
interface Row {
  v: HuntVoc;
  lv: number;
  name: string;
  slug: string | null;
  x: string | null;
  l: string | null;
  xs: number | null;
  ls: number | null;
  c: string[];
  hit: [string, number][];
  pr: [string, number][];
  m: Record<string, number>;
}
type Sort = 'level' | 'xp' | 'profit' | 'name';

const VOC_ELEMENTS: Record<HuntVoc, string[]> = {
  knight: ['physical', 'fire', 'ice', 'energy', 'earth', 'death'],
  paladin: ['physical', 'holy', 'fire', 'ice', 'energy', 'earth', 'death'],
  monk: ['physical', 'energy', 'earth'],
  sorcerer: ['fire', 'energy', 'death'],
  druid: ['ice', 'earth', 'fire', 'energy', 'death'],
};

let table: Promise<Row[]> | null = null;
const loadTable = () => {
  table ??= import('../../../shared/hunt-table.json').then((m) => (m.default as unknown as { rows: Row[] }).rows);
  return table;
};

function Amount({ value }: { value: string | null }) {
  const { t } = useI18n();
  if (!value) {
    return (
      <abbr className="ht-var" title={t('ht_var_title')}>
        var
      </abbr>
    );
  }
  if (value === 'loss') return <span className="neg">{t('ht_loss')}</span>;
  // A range may only break at its dash ("2,7kk–" / "3,3kk")
  const [from, to] = value.split('–');
  return (
    <span>
      {from}
      {to !== undefined && (
        <>
          –<wbr />
          {to}
        </>
      )}
    </span>
  );
}

/** Nulls always last, whichever way the numbers go */
const byNumber = (a: number | null, b: number | null) => (a == null || b == null ? (a == null ? 1 : 0) - (b == null ? 1 : 0) : b - a);

export function HuntTable({ voc, level }: { voc: HuntVoc; level: number }) {
  const { t, n } = useI18n();
  const delta = useDeltaText();
  const [rows, setRows] = useState<Row[] | null>(null);
  const [q, setQ] = useState('');
  const [sort, setSort] = useState<Sort>('level');
  const [element, setElement] = useState('');
  const [open, setOpen] = useState<string | null>(null);

  useEffect(() => {
    void loadTable().then(setRows);
  }, []);
  useEffect(() => setElement(''), [voc]);

  const shown = useMemo(() => {
    const needle = q.trim().toLowerCase();
    const list = (rows ?? []).filter(
      (r) => r.v === voc && (!level || r.lv <= level) && (!needle || `${r.name} ${r.c.join(' ')}`.toLowerCase().includes(needle)),
    );
    const by: Record<Sort, (a: Row, b: Row) => number> = {
      level: (a, b) => (level ? b.lv - a.lv : a.lv - b.lv),
      xp: (a, b) => byNumber(a.xs, b.xs),
      profit: (a, b) => byNumber(a.ls, b.ls),
      name: (a, b) => a.name.localeCompare(b.name),
    };
    // With "my element", the hunts where it does the most damage come first
    const order = element ? (a: Row, b: Row) => byNumber(a.m[element] ?? null, b.m[element] ?? null) : by[sort];
    return list.sort((a, b) => order(a, b) || a.lv - b.lv || a.name.localeCompare(b.name));
  }, [rows, voc, level, q, sort, element]);

  if (!rows) return <p className="muted">{t('loading')}</p>;

  return (
    <>
      <div className="hunt-filters">
        <label className="field">
          <span>{t('hunt_search')}</span>
          <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('hunt_search_hint')} />
        </label>
        <label className="field">
          <span>{t('ht_my_element')}</span>
          <select value={element} onChange={(e) => setElement(e.target.value)}>
            <option value="">{t('ht_any_element')}</option>
            {VOC_ELEMENTS[voc].map((e) => (
              <option key={e} value={e}>
                {elementIcon(e)} {elementName(e)}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          <span>{t('hunt_sort')}</span>
          <select value={sort} disabled={!!element} onChange={(e) => setSort(e.target.value as Sort)}>
            <option value="level">{t('hunt_sort_level')}</option>
            <option value="xp">{t('ht_xp')}</option>
            <option value="profit">{t('ht_profit')}</option>
            <option value="name">{t('hunt_sort_name')}</option>
          </select>
        </label>
      </div>
      <p className="muted small">{t('hunt_count', { n: n(shown.length) })}</p>
      <div className="cr-table-wrap">
        <table className="ht-table">
          <thead>
            <tr>
              <th className="num">{t('level_short')}</th>
              <th>{t('ht_place')}</th>
              <th className="num">{t('ht_xp')}</th>
              <th className="num">{t('ht_profit')}</th>
              <th>{t('ht_hit')}</th>
              <th>{t('ht_protect')}</th>
            </tr>
          </thead>
          <tbody>
            {shown.map((r) => {
              const key = r.name;
              const isOpen = open === key;
              const chosen = element && r.m[element] != null ? ([[element, r.m[element]]] as [string, number][]) : r.hit;
              return (
                <Fragment key={key}>
                  <tr className={isOpen ? 'open' : ''}>
                    <td className="num">{n(r.lv)}+</td>
                    <td>
                      <button type="button" className="ht-name" aria-expanded={isOpen} onClick={() => setOpen(isOpen ? null : key)}>
                        {r.name}
                      </button>
                    </td>
                    <td className="num">
                      <Amount value={r.x} />
                    </td>
                    <td className="num">
                      <Amount value={r.l} />
                    </td>
                    <td className="ht-els">
                      {chosen.map(([e, pct]) => (
                        <ElementValue key={e} element={e} value={delta(pct)} tone={pct > 100 ? 'pos' : pct < 100 ? 'neg' : undefined} />
                      ))}
                      {!chosen.length && <span className="muted">—</span>}
                    </td>
                    <td className="ht-els">
                      {r.pr.map(([e, pct]) => (
                        <ElementValue key={e} element={e} value={`${pct}%`} />
                      ))}
                      {!r.pr.length && <span className="muted">—</span>}
                    </td>
                  </tr>
                  {isOpen && (
                    <tr className="ht-detail">
                      <td colSpan={6}>
                        {r.c.length > 0 ? (
                          <span>
                            {r.c.map((c, i) => (
                              <span key={c}>
                                {i > 0 && ', '}
                                <a href={`/creatures/${creatureSlug(c)}`} onClick={linkHandler(`/creatures/${creatureSlug(c)}`)}>
                                  {c}
                                </a>
                              </span>
                            ))}
                          </span>
                        ) : (
                          <span className="muted">{t('cr_no_data')}</span>
                        )}
                        {r.slug && (
                          <>
                            {' · '}
                            <a href={`/hunting/${r.slug}`} onClick={linkHandler(`/hunting/${r.slug}`)}>
                              {t('ht_see_place')}
                            </a>
                          </>
                        )}
                      </td>
                    </tr>
                  )}
                </Fragment>
              );
            })}
          </tbody>
        </table>
      </div>
      <p className="muted small">{t('ht_note')}</p>
      <p className="muted small">{t('ht_source')}</p>
    </>
  );
}
