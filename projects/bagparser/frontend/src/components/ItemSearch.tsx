/**
 * Item search with suggestions (D-58), for the drop finder and the home page shortcut.
 * Creature products — the items delivery tasks ask for — are listed first and tagged.
 */
import { useEffect, useRef, useState } from 'react';
import { weekly } from '../api';
import { useI18n } from '../i18n';
import { ItemSprite, useSprites } from './ItemSprite';

type Suggestion = { name: string; creature_product: boolean; npc_price: number };

export function ItemSearch({ onPick, placeholder, autoFocus }: { onPick: (item: string) => void; placeholder?: string; autoFocus?: boolean }) {
  const { t } = useI18n();
  const sprites = useSprites();
  const [q, setQ] = useState('');
  const [list, setList] = useState<Suggestion[]>([]);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const boxRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (q.trim().length < 2) {
      setList([]);
      return;
    }
    const id = setTimeout(() => {
      weekly
        .suggest(q)
        .then((r) => {
          setList(r.items);
          setActive(0);
          setOpen(true);
        })
        .catch(() => setList([]));
    }, 180);
    return () => clearTimeout(id);
  }, [q]);

  useEffect(() => {
    const close = (e: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, []);

  const pick = (name: string) => {
    onPick(name);
    setQ('');
    setList([]);
    setOpen(false);
  };

  return (
    <div className="item-search" ref={boxRef}>
      <input
        type="search"
        value={q}
        autoFocus={autoFocus}
        onChange={(e) => setQ(e.target.value)}
        onFocus={() => list.length && setOpen(true)}
        onKeyDown={(e) => {
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => Math.min(a + 1, list.length - 1));
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => Math.max(a - 1, 0));
          } else if (e.key === 'Enter' && list[active]) {
            e.preventDefault();
            pick(list[active].name);
          } else if (e.key === 'Escape') setOpen(false);
        }}
        placeholder={placeholder ?? t('drop_search_ph')}
        aria-label={t('drop_search_ph')}
        aria-autocomplete="list"
        aria-expanded={open}
        autoComplete="off"
      />
      {open && list.length > 0 && (
        <ul className="item-search-list" role="listbox">
          {list.map((s, i) => (
            <li key={s.name} role="option" aria-selected={i === active}>
              <button
                type="button"
                className={i === active ? 'active' : ''}
                onMouseEnter={() => setActive(i)}
                onClick={() => pick(s.name)}
              >
                <ItemSprite name={s.name} sprites={sprites} size={24} />
                <span className="item-search-name">{s.name}</span>
                {s.creature_product && <span className="tag">{t('drop_task_item')}</span>}
              </button>
            </li>
          ))}
        </ul>
      )}
      {open && q.trim().length >= 2 && list.length === 0 && <p className="muted small item-search-empty">{t('drop_no_item')}</p>}
    </div>
  );
}
