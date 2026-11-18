/** Combat stats of an equipment set, each line with its sources (D-59). */
import { useState } from 'react';
import { useI18n } from '../../../i18n';
import { PERCENT_STATS, type SetStats, type StatLine } from '../../../core/build';

export const titleCase = (s: string) => s.replace(/\b\w/g, (c) => c.toUpperCase());

function fmt(key: string, v: number, pct: boolean) {
  const s = `${v > 0 ? '+' : ''}${v.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
  return pct || PERCENT_STATS.has(key) ? `${s}%` : s;
}

function Lines({ lines, pct = false }: { lines: StatLine[]; pct?: boolean }) {
  const [open, setOpen] = useState<string | null>(null);
  return (
    <ul className="stat-lines">
      {lines.map((l) => (
        <li key={l.key}>
          <button type="button" className="stat-line" aria-expanded={open === l.key} onClick={() => setOpen((o) => (o === l.key ? null : l.key))}>
            <span>{titleCase(l.key)}</span>
            <strong className={l.total < 0 ? 'neg' : ''}>{fmt(l.key, l.total, pct)}</strong>
          </button>
          {open === l.key && (
            <ul className="stat-sources">
              {l.sources.map((s, i) => (
                <li key={i}>
                  {fmt(l.key, s.value, pct)} <span className="muted">· {s.from}</span>
                </li>
              ))}
            </ul>
          )}
        </li>
      ))}
    </ul>
  );
}

export function StatsPanel({ stats, compact = false }: { stats: SetStats; compact?: boolean }) {
  const { t } = useI18n();
  const empty = !stats.stats.length && !stats.resist.length && !stats.armor;
  if (empty) return <p className="muted small">{t('hs_stats_empty')}</p>;
  return (
    <div className={`stats-panel${compact ? ' compact' : ''}`}>
      <div>
        <h4>{t('hs_stats_combat')}</h4>
        {stats.armor > 0 && (
          <p className="stat-line static">
            <span>Armor</span>
            <strong>{stats.armor}</strong>
          </p>
        )}
        <Lines lines={stats.stats} />
        {stats.conversions.length > 0 && (
          <>
            <h5>{t('hs_stats_conversion')}</h5>
            <Lines lines={stats.conversions} pct />
          </>
        )}
      </div>
      {stats.resist.length > 0 && (
        <div>
          <h4>{t('hs_stats_resist')}</h4>
          <Lines lines={stats.resist} pct />
        </div>
      )}
      {stats.augments.length > 0 && (
        <div className="span-all">
          <h4>{t('hs_stats_augments')}</h4>
          <ul className="plain-list small">
            {stats.augments.map((a) => (
              <li key={a.from}>
                <strong>{a.from}:</strong> {a.text}
              </li>
            ))}
          </ul>
        </div>
      )}
      {stats.missing.length > 0 && <p className="muted small span-all">{t('hs_stats_missing', { items: stats.missing.join(', ') })}</p>}
      <p className="muted small span-all">{t('hs_stats_note')}</p>
    </div>
  );
}
