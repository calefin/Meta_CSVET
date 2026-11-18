/**
 * D-82: a creature's name always opens its sheet, and tools that talk about one creature show what matters to fight it.
 */
import { useEffect, useState } from 'react';
import { bestHits, creatureSlug, damageDealt, resistances, worstHits } from '../core/creatureView';
import { findCreature, loadCreatures, type CreatureStats } from '../core/creatures';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { ElementValue, useDeltaText } from './ElementValue';

/** `resolve` is for names typed by people or read from logs ("dragon lords"): without a match it stays plain text */
export function CreatureLink({ name, resolve }: { name: string; resolve?: boolean }) {
  const [target, setTarget] = useState<string | null>(resolve ? null : name);
  useEffect(() => {
    if (!resolve) {
      setTarget(name);
      return;
    }
    let alive = true;
    void loadCreatures().then((index) => {
      if (alive) setTarget(findCreature(index, name)?.name ?? null);
    });
    return () => {
      alive = false;
    };
  }, [name, resolve]);
  if (!target) return <>{name}</>;
  const href = `/creatures/${creatureSlug(target)}`;
  return (
    <a href={href} onClick={linkHandler(href)}>
      {name}
    </a>
  );
}

export function CreatureSummary({ name }: { name: string }) {
  const { t, n } = useI18n();
  const delta = useDeltaText();
  const [c, setC] = useState<CreatureStats | null>(null);
  useEffect(() => {
    let alive = true;
    setC(null);
    void loadCreatures().then((index) => {
      if (alive) setC(findCreature(index, name));
    });
    return () => {
      alive = false;
    };
  }, [name]);
  if (!c) return null;
  const rows = resistances(c);
  const best = bestHits(rows);
  const worst = worstHits(rows);
  const dealt = damageDealt(c).slice(0, 3);
  const href = `/creatures/${creatureSlug(c.name)}`;
  return (
    <div className="cr-summary">
      <dl>
        <div>
          <dt>HP</dt>
          <dd>{n(c.hp)}</dd>
        </div>
        <div>
          <dt>XP</dt>
          <dd>{n(c.exp)}</dd>
        </div>
        <div>
          <dt>{t('cr_hit_with')}</dt>
          <dd>{best.length ? best.map((r) => <ElementValue key={r.element} element={r.element} value={delta(r.pct)} tone="pos" />) : '—'}</dd>
        </div>
        <div>
          <dt>{t('cr_avoid')}</dt>
          <dd>{worst.length ? worst.map((r) => <ElementValue key={r.element} element={r.element} value={delta(r.pct)} tone="neg" />) : '—'}</dd>
        </div>
        <div>
          <dt>{t('cr_deals')}</dt>
          <dd>{dealt.length ? dealt.map(([e, pct]) => <ElementValue key={e} element={e} value={`${pct}%`} />) : '—'}</dd>
        </div>
      </dl>
      <a className="button secondary small" href={href} onClick={linkHandler(href)}>
        {t('cr_full_sheet')}
      </a>
    </div>
  );
}
