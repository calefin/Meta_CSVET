/** Charms, preys and stances pickers for a hunt session (D-59). Compact rows, creatures from the hunt's kills. */
import { useState } from 'react';
import { useI18n } from '../../../i18n';
import { CHARMS, PREY_TYPES, STANCES, charmInfo, type CharmPick, type PreyPick, type Voc } from '../../../core/build';
import { titleCase } from './StatsPanel';

function CreatureSelect({ value, creatures, onChange, label }: { value: string | null; creatures: string[]; onChange: (v: string | null) => void; label: string }) {
  const { t } = useI18n();
  const list = value && !creatures.includes(value) ? [value, ...creatures] : creatures;
  const [free, setFree] = useState(false);
  if (free || !list.length)
    return (
      <input
        aria-label={label}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value.toLowerCase() || null)}
        onBlur={() => setFree(false)}
        placeholder={t('hs_creature_ph')}
        maxLength={80}
      />
    );
  return (
    <select
      aria-label={label}
      value={value ?? ''}
      onChange={(e) => (e.target.value === '__other' ? setFree(true) : onChange(e.target.value || null))}
    >
      <option value="">{t('hs_pick_creature')}</option>
      {list.map((c) => (
        <option key={c} value={c}>
          {titleCase(c)}
        </option>
      ))}
      <option value="__other">{t('hs_other_creature')}</option>
    </select>
  );
}

export function CharmsEditor({ value, onChange, creatures }: { value: CharmPick[]; onChange: (v: CharmPick[]) => void; creatures: string[] }) {
  const { t } = useI18n();
  const update = (i: number, patch: Partial<CharmPick>) => onChange(value.map((c, k) => (k === i ? { ...c, ...patch } : c)));
  const free = CHARMS.filter((c) => !value.some((v) => v.name === c.name));
  return (
    <div className="pick-list">
      {value.map((c, i) => {
        const info = charmInfo(c.name);
        return (
          <div key={c.name} className="pick-row">
            <span className="pick-name">
              <strong>{c.name}</strong> <span className={`tag ${c.kind === 'major' ? 'ok' : ''}`}>{t(c.kind === 'major' ? 'hs_major' : 'hs_minor')}</span>
            </span>
            <div className="seg" role="group" aria-label={`${c.name} tier`}>
              {[1, 2, 3].map((tier) => (
                <button key={tier} type="button" className={c.tier === tier ? 'active' : ''} onClick={() => update(i, { tier })}>
                  {'I'.repeat(tier)} <small>{info?.values[tier - 1]}%</small>
                </button>
              ))}
            </div>
            <CreatureSelect value={c.creature} creatures={creatures} onChange={(creature) => update(i, { creature })} label={`${c.name}: ${t('ha_creature')}`} />
            <button type="button" className="icon-btn" aria-label={`${t('delete')} ${c.name}`} onClick={() => onChange(value.filter((_, k) => k !== i))}>
              ✕
            </button>
          </div>
        );
      })}
      <select
        className="pick-add"
        value=""
        onChange={(e) => {
          const info = charmInfo(e.target.value);
          if (info) onChange([...value, { name: info.name, kind: info.kind, tier: 3, creature: creatures[0] ?? null }]);
        }}
        aria-label={t('hs_add_charm')}
      >
        <option value="">+ {t('hs_add_charm')}</option>
        <optgroup label={t('hs_major')}>
          {free
            .filter((c) => c.kind === 'major')
            .map((c) => (
              <option key={c.name} value={c.name}>
                {c.name} ({c.values.join('/')}%)
              </option>
            ))}
        </optgroup>
        <optgroup label={t('hs_minor')}>
          {free
            .filter((c) => c.kind === 'minor')
            .map((c) => (
              <option key={c.name} value={c.name}>
                {c.name} ({c.values.join('/')}%)
              </option>
            ))}
        </optgroup>
      </select>
    </div>
  );
}

export function PreysEditor({ value, onChange, creatures }: { value: PreyPick[]; onChange: (v: PreyPick[]) => void; creatures: string[] }) {
  const { t } = useI18n();
  const update = (i: number, patch: Partial<PreyPick>) => onChange(value.map((p, k) => (k === i ? { ...p, ...patch } : p)));
  return (
    <div className="pick-list">
      {value.map((p, i) => (
        <div key={i} className="pick-row">
          <select aria-label={`Prey ${i + 1}`} value={p.type} onChange={(e) => update(i, { type: e.target.value as PreyPick['type'] })}>
            {PREY_TYPES.map((type) => (
              <option key={type} value={type}>
                {t(`hs_prey_${type}`)}
              </option>
            ))}
          </select>
          <select aria-label={t('hs_prey_grade')} value={p.grade} onChange={(e) => update(i, { grade: Number(e.target.value) })}>
            {Array.from({ length: 10 }, (_, k) => (
              <option key={k} value={k + 1}>
                ★ {k + 1}
              </option>
            ))}
          </select>
          <CreatureSelect value={p.creature} creatures={creatures} onChange={(creature) => update(i, { creature })} label={`Prey ${i + 1}: ${t('ha_creature')}`} />
          <button type="button" className="icon-btn" aria-label={`${t('delete')} prey ${i + 1}`} onClick={() => onChange(value.filter((_, k) => k !== i))}>
            ✕
          </button>
        </div>
      ))}
      {value.length < 3 && (
        <button
          type="button"
          className="button secondary small-btn pick-add"
          onClick={() => onChange([...value, { type: 'xp', grade: 10, creature: creatures[value.length] ?? creatures[0] ?? null }])}
        >
          + {t('hs_add_prey')}
        </button>
      )}
    </div>
  );
}

export function StancesEditor({ value, onChange, vocation }: { value: string[]; onChange: (v: string[]) => void; vocation: Voc | null }) {
  const { t } = useI18n();
  const options = vocation ? STANCES[vocation] : [];
  if (!vocation) return <p className="muted small">{t('hs_stance_pick_voc')}</p>;
  return (
    <div className="chip-row">
      {options.map((s) => {
        const on = value.includes(s);
        return (
          <button
            key={s}
            type="button"
            className={`chip toggle${on ? ' ok' : ''}`}
            aria-pressed={on}
            onClick={() => onChange(on ? value.filter((x) => x !== s) : [...value, s].slice(-2))}
          >
            {on ? '✓ ' : ''}
            {s}
          </button>
        );
      })}
    </div>
  );
}
