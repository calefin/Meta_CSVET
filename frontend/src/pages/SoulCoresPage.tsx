/**
 * Soul core tracker (D-41): pick one of your characters, tick the soul cores it already has (Soulpit),
 * see progress and what is missing. Compact list by default, picture grid on demand.
 */
import { CreatureLink } from '../components/CreatureLink';
import { useEffect, useMemo, useState } from 'react';
import { CreatureImage } from '../components/CreatureImage';
import { ApiError, characters, soulcores, type CharacterListItem, type SoulCore } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

const DIFFICULTIES = ['Harmless', 'Trivial', 'Easy', 'Medium', 'Hard', 'Challenging'];
const CHAR_KEY = 'bagparser_soulcores_char';
const VIEW_KEY = 'bagparser_soulcores_view';
const LOCAL_STORAGE_SOULCORES = 'bagparser_soulcores_local';

type Status = 'missing' | 'owned' | 'all';
type View = 'list' | 'grid';

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

function readLocalOwned(): Set<string> {
  try {
    const raw = localStorage.getItem(LOCAL_STORAGE_SOULCORES);
    return raw ? new Set(JSON.parse(raw)) : new Set();
  } catch {
    return new Set();
  }
}

function writeLocalOwned(owned: Set<string>): void {
  try {
    localStorage.setItem(LOCAL_STORAGE_SOULCORES, JSON.stringify([...owned]));
  } catch {
    /* quota */
  }
}

const coreImage = (itemId: number) => `https://static.tibia.com/images/charactertrade/objects/${itemId}.gif`;

/** Creature sprite (official or TibiaWiki, D-57); the soul core item as the last fallback */
function CoreImage({ core }: { core: SoulCore }) {
  return <CreatureImage name={core.creature} url={core.image_url} fallback={coreImage(core.itemId)} />;
}

export function SoulCoresPage() {
  const { t, n } = useI18n();
  const { user } = useAuth();
  const [catalog, setCatalog] = useState<SoulCore[] | null>(null);
  const [chars, setChars] = useState<CharacterListItem[] | null>(null);
  const [charId, setCharId] = useState<number | null>(() => {
    const q = Number(new URLSearchParams(window.location.search).get('c'));
    return q || Number(store.get(CHAR_KEY)) || null;
  });
  const [owned, setOwned] = useState<Set<string>>(readLocalOwned);
  const [error, setError] = useState<string | null>(null);

  const [q, setQ] = useState('');
  const [status, setStatus] = useState<Status>('missing');
  const [cls, setCls] = useState('');
  const [diff, setDiff] = useState('');
  const [view, setView] = useState<View>(() => (store.get(VIEW_KEY) === 'grid' ? 'grid' : 'list'));
  const [pasteOpen, setPasteOpen] = useState(false);
  const [paste, setPaste] = useState('');
  const [msg, setMsg] = useState<string | null>(null);

  useEffect(() => {
    soulcores
      .catalog()
      .then((r) => setCatalog(r.soulcores))
      .catch(() => setError(t('api_error')));
  }, [t]);

  useEffect(() => {
    if (!user) return;
    characters
      .list()
      .then((r) => {
        setChars(r.characters);
        setCharId((id) => (id && r.characters.some((c) => c.id === id) ? id : (r.characters[0]?.id ?? null)));
      })
      .catch(() => setChars([]));
  }, [user]);

  useEffect(() => {
    if (!charId || !user) {
      setOwned(readLocalOwned());
      return;
    }
    store.set(CHAR_KEY, String(charId));
    soulcores
      .owned(charId)
      .then((r) => setOwned(new Set(r.owned)))
      .catch(() => setError(t('api_error')));
  }, [charId, user, t]);

  const classes = useMemo(
    () => [...new Set((catalog ?? []).map((s) => s.bestiaryClass ?? '').filter(Boolean))].sort(),
    [catalog],
  );

  const visible = useMemo(() => {
    if (!catalog) return [];
    const needle = q.trim().toLowerCase();
    return catalog.filter(
      (s) =>
        (status === 'all' || (status === 'owned') === owned.has(s.creature)) &&
        (!cls || s.bestiaryClass === cls) &&
        (!diff || s.difficulty === diff) &&
        (!needle || s.creature.toLowerCase().includes(needle)),
    );
  }, [catalog, owned, q, status, cls, diff]);

  const toggle = async (creature: string) => {
    const has = owned.has(creature);
    const next = new Set(owned);
    if (has) next.delete(creature);
    else next.add(creature);
    setOwned(next);

    if (user && charId) {
      try {
        await soulcores.set(charId, creature, !has);
      } catch {
        setOwned(owned);
        setMsg(t('api_error'));
      }
    } else {
      writeLocalOwned(next);
    }
  };

  const importPaste = async () => {
    const lines = paste.split(/[\n,;]+/).map((l) => l.trim()).filter(Boolean);
    if (user && charId) {
      try {
        const r = await soulcores.bulk(charId, lines, true);
        setMsg(t('soulcores_imported', { n: r.changed }) + (r.unknown.length ? ` ${t('soulcores_unknown', { list: r.unknown.join(', ') })}` : ''));
        setPaste('');
        setPasteOpen(false);
        setOwned(new Set((await soulcores.owned(charId)).owned));
      } catch (err) {
        setMsg(err instanceof ApiError && err.code === 'rate_limited' ? t('auth_err_rate_limited') : t('api_error'));
      }
    } else {
      const next = new Set(owned);
      let count = 0;
      for (const line of lines) {
        const match = catalog?.find((c) => c.creature.toLowerCase() === line.toLowerCase());
        if (match) {
          next.add(match.creature);
          count++;
        }
      }
      setOwned(next);
      writeLocalOwned(next);
      setMsg(t('soulcores_imported', { n: count }));
      setPaste('');
      setPasteOpen(false);
    }
  };

  const copyMissing = async () => {
    if (!catalog) return;
    const text = catalog.filter((s) => !owned.has(s.creature)).map((s) => s.creature).join('\n');
    try {
      await navigator.clipboard.writeText(text);
      setMsg(t('soulcores_copied'));
    } catch {
      setMsg(t('api_error'));
    }
  };

  const chooseView = (v: View) => {
    setView(v);
    store.set(VIEW_KEY, v);
  };

  const total = catalog?.length ?? 0;
  const have = owned.size;
  const pct = total ? Math.round((have / total) * 1000) / 10 : 0;
  const char = chars?.find((c) => c.id === charId);

  return (
    <div className="page soulcores-page">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', marginBottom: '0.5rem' }}>
        <div>
          <h1 style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', margin: 0 }}>
            <span>🔮</span> {t('soulcores_title')}
          </h1>
          <p className="muted" style={{ margin: '0.25rem 0 0 0' }}>{t('soulcores_intro')}</p>
        </div>

        {/* User Identity Banner */}
        {user ? (
          <div className="tag" style={{ fontSize: '0.9rem', padding: '0.4rem 0.8rem', background: 'rgba(59, 130, 246, 0.1)', border: '1px solid var(--color-accent, #3b82f6)' }}>
            👤 <strong>{user.display_name}</strong>
            {user.premium && <span style={{ marginLeft: '0.4rem', color: '#fbbf24' }}>★ PRO</span>}
          </div>
        ) : (
          <a href="/login?next=/soulcores" onClick={linkHandler('/login?next=/soulcores')} className="button secondary small">
            🔑 {t('login')} ({t('soulcores_sync_cloud')})
          </a>
        )}
      </div>

      {error && <p className="alert">{error}</p>}

      {/* Header bar: Character selector + Soulpit progress */}
      <section className="card soulcores-head" style={{ marginBottom: '1rem' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
          {user && chars && chars.length > 0 ? (
            <label className="field" style={{ margin: 0 }}>
              <span>🛡️ {t('soulcores_character')}</span>
              <select value={charId ?? ''} onChange={(e) => setCharId(Number(e.target.value))}>
                {chars.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} {c.level ? `(Lvl ${c.level})` : ''} {c.world ? `· ${c.world}` : ''}
                  </option>
                ))}
              </select>
            </label>
          ) : user && chars && chars.length === 0 ? (
            <div>
              <span className="muted small" style={{ display: 'block' }}>🛡️ {t('soulcores_character')}</span>
              <span className="tag" style={{ marginRight: '0.5rem' }}>{t('unassigned')}</span>
              <a href="/characters" onClick={linkHandler('/characters')} className="small" style={{ color: 'var(--color-accent, #3b82f6)' }}>
                + {t('add_character')} →
              </a>
            </div>
          ) : (
            <div>
              <span className="muted small" style={{ display: 'block' }}>💾 {t('storage_mode')}</span>
              <span className="tag">{t('local_storage')}</span>
            </div>
          )}

          <div className="soulcores-progress" aria-live="polite" style={{ minWidth: '220px' }}>
            <div className="soulcores-count">
              <strong>{n(have)}</strong>
              <span className="muted"> / {n(total)}</span>
              <span className="soulcores-pct" style={{ color: 'var(--color-accent, #3b82f6)', fontWeight: 'bold' }}>{pct}%</span>
            </div>
            <div className="meter" role="progressbar" aria-valuemin={0} aria-valuemax={total} aria-valuenow={have}>
              <span style={{ width: `${pct}%`, background: 'var(--color-accent, #3b82f6)' }} />
            </div>
            <span className="muted small">
              {t('soulcores_missing_n', { n: n(total - have) })}
              {char && !char.verified_at ? ` · ${t('soulcores_pending_char')}` : ''}
            </span>
          </div>
        </div>

        {user && chars && chars.length === 0 && (
          <p className="muted small" style={{ marginTop: '0.75rem', borderTop: '1px solid rgba(255, 255, 255, 0.08)', paddingTop: '0.5rem' }}>
            💡 {t('soulcores_no_chars_note')}{' '}
            <a href="/characters" onClick={linkHandler('/characters')}>
              {t('nav_characters')}
            </a>.
          </p>
        )}
      </section>

      {/* Filters and List/Grid Controls */}
      {catalog && (
        <section className="card">
          <div className="soulcores-filters">
            <input
              type="search"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder={t('soulcores_search')}
              aria-label={t('soulcores_search')}
            />
            <div className="segmented" role="group" aria-label={t('soulcores_status')}>
              {(['missing', 'owned', 'all'] as Status[]).map((s) => (
                <button key={s} type="button" className={status === s ? 'active' : ''} aria-pressed={status === s} onClick={() => setStatus(s)}>
                  {t(`soulcores_show_${s}`)}
                </button>
              ))}
            </div>
            <select value={cls} onChange={(e) => setCls(e.target.value)} aria-label={t('soulcores_class')}>
              <option value="">{t('soulcores_all_classes')}</option>
              {classes.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
            <select value={diff} onChange={(e) => setDiff(e.target.value)} aria-label={t('soulcores_difficulty')}>
              <option value="">{t('soulcores_all_difficulties')}</option>
              {DIFFICULTIES.map((d) => (
                <option key={d} value={d}>
                  {d}
                </option>
              ))}
            </select>
            <div className="segmented" role="group" aria-label={t('soulcores_view')}>
              <button type="button" className={view === 'list' ? 'active' : ''} aria-pressed={view === 'list'} onClick={() => chooseView('list')}>
                {t('view_list')}
              </button>
              <button type="button" className={view === 'grid' ? 'active' : ''} aria-pressed={view === 'grid'} onClick={() => chooseView('grid')}>
                {t('view_grid')}
              </button>
            </div>
          </div>

          <div className="button-row soulcores-actions" style={{ marginTop: '0.75rem' }}>
            <button type="button" className="secondary" onClick={copyMissing}>
              📋 {t('soulcores_copy_missing')}
            </button>
            <button type="button" className="secondary" onClick={() => setPasteOpen((v) => !v)}>
              📥 {t('soulcores_paste')}
            </button>
          </div>
          {pasteOpen && (
            <div className="soulcores-paste" style={{ marginTop: '0.75rem' }}>
              <textarea
                rows={5}
                value={paste}
                onChange={(e) => setPaste(e.target.value)}
                placeholder={t('soulcores_paste_hint')}
              />
              <button type="button" onClick={importPaste} disabled={!paste.trim()}>
                {t('soulcores_paste_save')}
              </button>
            </div>
          )}
          {msg && <p className="notice" style={{ marginTop: '0.5rem' }}>{msg}</p>}

          <p className="muted small" style={{ marginTop: '0.75rem' }}>{t('soulcores_showing', { n: visible.length })}</p>
          {visible.length === 0 ? (
            <p className="muted">{status === 'missing' && have === total ? t('soulcores_done') : t('soulcores_none')}</p>
          ) : view === 'list' ? (
            <ul className="soulcore-list">
              {visible.map((s) => {
                const has = owned.has(s.creature);
                return (
                  <li key={s.creature}>
                    <label className={has ? 'owned' : ''}>
                      <input type="checkbox" checked={has} onChange={() => toggle(s.creature)} />
                      <span className="soulcore-name">
                        <CreatureLink name={s.creature} />
                      </span>
                      <span className="muted small">
                        {s.bestiaryClass ?? '—'} · {s.difficulty ?? '—'}
                      </span>
                    </label>
                  </li>
                );
              })}
            </ul>
          ) : (
            <ul className="soulcore-grid">
              {visible.map((s) => {
                const has = owned.has(s.creature);
                return (
                  <li key={s.creature}>
                    <button
                      type="button"
                      className={has ? 'owned' : ''}
                      aria-pressed={has}
                      title={`${s.creature} · ${s.bestiaryClass ?? '—'} · ${s.difficulty ?? '—'}`}
                      onClick={() => toggle(s.creature)}
                    >
                      <CoreImage core={s} />
                      <span>{s.creature}</span>
                      {has && <span className="soulcore-check" aria-hidden="true">✓</span>}
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </section>
      )}
      <p className="muted small">{t('soulcores_source')}</p>
    </div>
  );
}
