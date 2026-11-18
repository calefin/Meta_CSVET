/** An element with its value: icon + text, so meaning never depends on colour alone (D-80). */
import { elementIcon } from '../core/creatures';
import { useI18n } from '../i18n';

const NAMES: Record<string, string> = { lifedrain: 'Life Drain', manadrain: 'Mana Drain' };
export const elementName = (e: string): string => NAMES[e] ?? e.charAt(0).toUpperCase() + e.slice(1);

/** Damage the creature takes, as a difference from normal: "+20%", "−15%", "Immune", "Heals" */
export function useDeltaText() {
  const { t } = useI18n();
  return (pct: number): string => {
    if (pct === 0) return t('cr_immune');
    if (pct < 0) return t('cr_heals');
    const d = pct - 100;
    return d === 0 ? '0' : `${d > 0 ? '+' : '−'}${Math.abs(d)}%`;
  };
}

export function ElementValue({ element, value, tone, showName }: { element: string; value: string; tone?: 'pos' | 'neg'; showName?: boolean }) {
  return (
    <span className={`el-value${tone ? ` ${tone}` : ''}`} title={`${elementName(element)} ${value}`}>
      <span aria-hidden="true">{elementIcon(element)}</span>
      {showName ? <span className="el-name">{elementName(element)}</span> : <span className="sr-only">{elementName(element)}</span>}
      <span className="el-num">{value}</span>
    </span>
  );
}
