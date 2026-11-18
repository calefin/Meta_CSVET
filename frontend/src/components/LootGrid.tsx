/** Loot as a grid of item squares (sprite + amount), most valuable first (D-35). */
import type { ParsedItemRow } from '../core/parser';
import { useI18n } from '../i18n';
import { ItemSprite, useSprites } from './ItemSprite';

export function LootGrid({ items }: { items: ParsedItemRow[] }) {
  const { t, n } = useI18n();
  const sprites = useSprites();
  if (!items.length) return null;
  const sorted = [...items].sort((a, b) => b.totalValue - a.totalValue || a.name.localeCompare(b.name));
  return (
    <ul className="loot-grid" aria-label={t('loot')}>
      {sorted.map((it) => (
        <li
          key={it.name}
          className={it.marketAlert ? 'loot-cell alert' : 'loot-cell'}
          title={`${n(it.count)}× ${it.name} — ${n(it.totalValue)} gp`}
        >
          <ItemSprite name={it.name} sprites={sprites} size={32} />
          {it.count > 1 && <span className="loot-count">{it.count > 999 ? `${Math.floor(it.count / 1000)}k` : it.count}</span>}
          <span className="sr-only">
            {n(it.count)}× {it.name}
          </span>
        </li>
      ))}
    </ul>
  );
}
