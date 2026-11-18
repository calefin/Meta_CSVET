import { useCallback, useEffect, useMemo, useState } from 'react';
import { api, characters, HttpError, notify, type Boss, type BossStats, type CharacterListItem, type SubmitStatus } from '../api';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { BoostedBanner } from '../components/BoostedBanner';
import { CopyText } from '../components/CopyText';
import { parseBossDropLine } from '../core/bossLog';

export interface CharacterBossRecord {
  bossName: string;
  kills: number;
  lastKilledAt: string; // ISO string
  cooldownHours: number;
  lastDropLine?: string;
  lastDrops?: { count: number; name: string }[];
}

const STORAGE_KEY = 'bagparser_boss_kills_records';

function loadBossRecords(): Record<string, Record<string, CharacterBossRecord>> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

function persistBossRecords(data: Record<string, Record<string, CharacterBossRecord>>): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  } catch {
    /* quota */
  }
}

function formatCooldown(msRemaining: number): string {
  if (msRemaining <= 0) return 'Pronto / Ready';
  const totalMin = Math.floor(msRemaining / 60_000);
  const h = Math.floor(totalMin / 60);
  const m = totalMin % 60;
  return `${h}h ${String(m).padStart(2, '0')}m`;
}

export function BossLedgerPage() {
  const { t, n, locale } = useI18n();
  const { user } = useAuth();

  // Tab: 'tracker' (Boss Kill Tracker por Char) vs 'global' (Estatísticas Globais)
  const [activeTab, setActiveTab] = useState<'tracker' | 'global'>('tracker');

  // Global Boss Ledger data
  const [bosses, setBosses] = useState<Boss[] | null>(null);
  const [selected, setSelected] = useState<number | null>(null);
  const [stats, setStats] = useState<BossStats | null>(null);
  const [error, setError] = useState(false);
  const [logText, setLogText] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [results, setResults] = useState<{ line: string; status: SubmitStatus }[] | null>(null);

  // Character & Boss Tracker state
  const [charList, setCharList] = useState<CharacterListItem[]>([]);
  const [selectedCharName, setSelectedCharName] = useState<string>('');
  const [bossRecords, setBossRecords] = useState(loadBossRecords);
  const [dropInput, setDropInput] = useState('');
  const [manualBossName, setManualBossName] = useState('');
  const [recordMsg, setRecordMsg] = useState<string | null>(null);
  const [trackerFilterStatus, setTrackerFilterStatus] = useState<'all' | 'ready' | 'cooldown'>('all');
  const [trackerSearch, setTrackerSearch] = useState('');

  // Load characters if logged in
  useEffect(() => {
    if (user) {
      characters
        .list()
        .then((res) => {
          setCharList(res.characters);
          if (res.characters.length > 0) {
            setSelectedCharName(res.characters[0].name);
          }
        })
        .catch(() => undefined);
    }
  }, [user]);

  const activeCharKey = selectedCharName.trim() || (user ? user.display_name : 'default');

  // D-76: kills of the last days go to the account, so the bot can tell when a cooldown is over (Pro)
  useEffect(() => {
    if (!user) return;
    const handle = window.setTimeout(() => {
      const since = Date.now() - 3 * 86_400_000;
      const timers = Object.entries(bossRecords).flatMap(([character, map]) =>
        Object.values(map)
          .filter((r) => Date.parse(r.lastKilledAt) > since)
          .map((r) => ({ character, boss: r.bossName, killed_at: r.lastKilledAt, cooldown_hours: r.cooldownHours || 20 })),
      );
      notify.bossTimers(timers).catch(() => undefined);
    }, 1500);
    return () => window.clearTimeout(handle);
  }, [user, bossRecords]);

  const currentCharBosses = useMemo(() => {
    return bossRecords[activeCharKey] || {};
  }, [bossRecords, activeCharKey]);

  const loadBosses = useCallback(() => {
    api
      .bosses()
      .then(setBosses)
      .catch(() => setError(true));
  }, []);

  const loadStats = useCallback((id: number) => {
    api
      .bossStats(id)
      .then(setStats)
      .catch(() => setError(true));
  }, []);

  useEffect(loadBosses, [loadBosses]);

  useEffect(() => {
    setStats(null);
    if (selected != null) loadStats(selected);
  }, [selected, loadStats]);

  // Record a boss kill for the current character
  const recordKill = (bossName: string, dropLine?: string, drops?: { count: number; name: string }[]) => {
    const cleanBoss = bossName.trim();
    if (!cleanBoss) return;

    const charMap = { ...(bossRecords[activeCharKey] || {}) };
    const existing = charMap[cleanBoss.toLowerCase()];
    const cooldownHours = 20; // standard Tibia boss cooldown

    const updatedRecord: CharacterBossRecord = {
      bossName: existing?.bossName || cleanBoss,
      kills: (existing?.kills || 0) + 1,
      lastKilledAt: new Date().toISOString(),
      cooldownHours,
      lastDropLine: dropLine || existing?.lastDropLine,
      lastDrops: drops || existing?.lastDrops,
    };

    charMap[cleanBoss.toLowerCase()] = updatedRecord;

    const newAll = {
      ...bossRecords,
      [activeCharKey]: charMap,
    };

    setBossRecords(newAll);
    persistBossRecords(newAll);
  };

  // Submit drop line in the Tracker tab
  const handleTrackerDropSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!dropInput.trim()) return;
    setRecordMsg(null);

    const lines = dropInput.split('\n').map((l) => l.trim()).filter(Boolean);
    let recordedCount = 0;

    for (const line of lines) {
      const parsed = parseBossDropLine(line);
      if (parsed) {
        recordKill(parsed.bossName, line, parsed.drops);
        recordedCount++;
      }
    }

    // Submit to global API in the background as well
    api.submitDrops(dropInput).catch(() => undefined);

    if (recordedCount > 0) {
      setRecordMsg(`✓ ${t('boss_kill_recorded', { count: recordedCount, char: activeCharKey })}`);
      setDropInput('');
      setTimeout(() => setRecordMsg(null), 4000);
    } else {
      setRecordMsg(t('boss_drop_invalid_hint'));
    }
  };

  // Quick manual kill button
  const handleManualKill = (bossName: string) => {
    recordKill(bossName);
    setRecordMsg(`✓ +1 Kill: ${bossName} (${activeCharKey})`);
    setTimeout(() => setRecordMsg(null), 3000);
  };

  // List of tracked bosses filtered
  const trackedBossList = useMemo(() => {
    const list = Object.values(currentCharBosses);
    const now = Date.now();

    return list.filter((b) => {
      const elapsed = now - new Date(b.lastKilledAt).getTime();
      const cooldownMs = b.cooldownHours * 3600 * 1000;
      const isReady = elapsed >= cooldownMs;

      if (trackerFilterStatus === 'ready' && !isReady) return false;
      if (trackerFilterStatus === 'cooldown' && isReady) return false;
      if (trackerSearch.trim()) {
        const needle = trackerSearch.trim().toLowerCase();
        return b.bossName.toLowerCase().includes(needle) || (b.lastDropLine && b.lastDropLine.toLowerCase().includes(needle));
      }
      return true;
    }).sort((a, b) => new Date(b.lastKilledAt).getTime() - new Date(a.lastKilledAt).getTime());
  }, [currentCharBosses, trackerFilterStatus, trackerSearch]);

  const readyBossesCount = useMemo(() => {
    const list = Object.values(currentCharBosses);
    const now = Date.now();
    return list.filter((b) => now - new Date(b.lastKilledAt).getTime() >= b.cooldownHours * 3600 * 1000).length;
  }, [currentCharBosses]);

  // Global Ledger submit
  const onSubmitGlobal = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!logText.trim()) return;
    setSubmitting(true);
    setError(false);
    try {
      const res = await api.submitDrops(logText);
      setResults(res.results);
      if (res.results.some((r) => r.status === 'recorded')) {
        setLogText('');
        loadBosses();
        if (selected != null) loadStats(selected);
      }
    } catch (err) {
      if (err instanceof HttpError && err.status === 429) {
        setResults([{ line: logText.split('\n')[0].trim(), status: 'rate_limited' }]);
      } else {
        setError(true);
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="page bossledger-page">
      <header className="chars-head">
        <div>
          <h1 style={{ display: 'flex', alignItems: 'center', gap: '0.6rem', margin: 0 }}>
            <span>👑</span> {t('bossledger_title')}
          </h1>
          <p className="muted" style={{ margin: '0.25rem 0 0 0' }}>{t('bossledger_subtitle')}</p>
        </div>

        {/* Tab Switcher */}
        <div className="segmented" role="group">
          <button
            type="button"
            className={activeTab === 'tracker' ? 'active' : ''}
            onClick={() => setActiveTab('tracker')}
          >
            🛡️ {t('my_boss_tracker')}
          </button>
          <button
            type="button"
            className={activeTab === 'global' ? 'active' : ''}
            onClick={() => setActiveTab('global')}
          >
            🌍 {t('global_drop_stats')}
          </button>
        </div>
      </header>

      <BoostedBanner only="boss" />

      {error && <p className="alert">{t('api_error')}</p>}

      {/* ========================================================================= */}
      {/* TAB 1: BOSS KILL TRACKER POR PERSONAGEM */}
      {/* ========================================================================= */}
      {activeTab === 'tracker' && (
        <>
          {/* Identity & Character Selector */}
          <section className="card" style={{ marginBottom: '1rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' }}>
                {charList.length > 0 ? (
                  <label className="field" style={{ margin: 0 }}>
                    <span>🛡️ {t('character')}:</span>
                    <select
                      value={selectedCharName}
                      onChange={(e) => setSelectedCharName(e.target.value)}
                      style={{ padding: '0.4rem 0.75rem', borderRadius: '4px', fontWeight: 'bold' }}
                    >
                      {charList.map((c) => (
                        <option key={c.id} value={c.name}>
                          {c.name} {c.level ? `(Lvl ${c.level})` : ''} {c.world ? `· ${c.world}` : ''}
                        </option>
                      ))}
                    </select>
                  </label>
                ) : (
                  <div>
                    <span className="muted small" style={{ display: 'block' }}>🛡️ {t('character')}:</span>
                    <strong>{activeCharKey}</strong>
                  </div>
                )}
              </div>

              {/* Stats Counters */}
              <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
                <div className="tag" style={{ fontSize: '0.88rem' }}>
                  ⚔️ {t('total_bosses')}: <strong>{Object.keys(currentCharBosses).length}</strong>
                </div>
                <div className="tag" style={{ fontSize: '0.88rem', background: readyBossesCount > 0 ? 'rgba(16, 185, 129, 0.15)' : undefined, color: readyBossesCount > 0 ? '#10b981' : undefined }}>
                  ✅ {t('ready_to_kill')}: <strong>{readyBossesCount}</strong>
                </div>
              </div>
            </div>
          </section>

          {/* Record Kill or Drop Message */}
          <section className="card" style={{ marginBottom: '1.25rem', borderLeft: '4px solid var(--color-accent, #3b82f6)' }}>
            <h2 style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', margin: '0 0 0.5rem 0', fontSize: '1.15rem' }}>
              <span>📥</span> {t('record_boss_drop_title')}
            </h2>
            <p className="muted small" style={{ margin: '0 0 0.75rem 0' }}>
              {t('record_boss_drop_desc')}
            </p>

            <form onSubmit={handleTrackerDropSubmit}>
              <textarea
                value={dropInput}
                onChange={(e) => setDropInput(e.target.value)}
                placeholder="Ex: 14:32 Loot of Scarlett Etzel: a cobra sword, 2 platinum coins
Ex: Loot of Grand Master Oberon: nothing
Ex: Loot of Drume: a lion shield"
                rows={4}
                spellCheck={false}
                style={{ width: '100%', fontFamily: 'monospace', fontSize: '0.85rem' }}
              />

              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem', marginTop: '0.5rem' }}>
                <button type="submit" className="primary" disabled={!dropInput.trim()}>
                  ⚔️ {t('record_kill_and_loot')}
                </button>

                {/* Quick select +1 kill */}
                {bosses && (
                  <div style={{ display: 'flex', gap: '0.4rem', alignItems: 'center' }}>
                    <select
                      value={manualBossName}
                      onChange={(e) => setManualBossName(e.target.value)}
                      style={{ padding: '0.35rem 0.5rem', borderRadius: '4px', maxWidth: '200px' }}
                    >
                      <option value="">{t('quick_boss_select')}...</option>
                      {bosses.map((b) => (
                        <option key={b.id} value={b.name}>
                          {b.name}
                        </option>
                      ))}
                    </select>
                    <button
                      type="button"
                      className="secondary small"
                      disabled={!manualBossName}
                      onClick={() => {
                        handleManualKill(manualBossName);
                        setManualBossName('');
                      }}
                    >
                      +1 Kill
                    </button>
                  </div>
                )}
              </div>
            </form>

            {recordMsg && <p className="notice" style={{ marginTop: '0.75rem' }}>{recordMsg}</p>}
          </section>

          {/* Tracked Bosses Table with Filters */}
          <section className="card">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.75rem', marginBottom: '1rem' }}>
              <h2 style={{ margin: 0, fontSize: '1.2rem' }}>
                ⚔️ {t('my_boss_kills_table')} ({trackedBossList.length})
              </h2>

              <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', alignItems: 'center' }}>
                <div className="segmented" role="group">
                  <button
                    type="button"
                    className={trackerFilterStatus === 'all' ? 'active' : ''}
                    onClick={() => setTrackerFilterStatus('all')}
                  >
                    {t('all')}
                  </button>
                  <button
                    type="button"
                    className={trackerFilterStatus === 'ready' ? 'active' : ''}
                    onClick={() => setTrackerFilterStatus('ready')}
                  >
                    ✅ {t('ready')}
                  </button>
                  <button
                    type="button"
                    className={trackerFilterStatus === 'cooldown' ? 'active' : ''}
                    onClick={() => setTrackerFilterStatus('cooldown')}
                  >
                    ⏳ {t('cooldown')}
                  </button>
                </div>

                <input
                  type="search"
                  value={trackerSearch}
                  onChange={(e) => setTrackerSearch(e.target.value)}
                  placeholder={t('search_boss')}
                  style={{ padding: '0.3rem 0.6rem', borderRadius: '4px', maxWidth: '170px' }}
                />
              </div>
            </div>

            {trackedBossList.length === 0 ? (
              <p className="muted small" style={{ textAlign: 'center', padding: '1.5rem 0' }}>
                {t('no_boss_kills_tracked_yet')}
              </p>
            ) : (
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>👑 {t('boss')}</th>
                      <th className="num">⚔️ {t('kills')}</th>
                      <th>⏱️ {t('cooldown_status')}</th>
                      <th>🕒 {t('last_kill')}</th>
                      <th>💎 {t('last_recorded_loot')}</th>
                      <th style={{ textAlign: 'right' }}>⚙️ {t('actions')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {trackedBossList.map((b) => {
                      const elapsed = Date.now() - new Date(b.lastKilledAt).getTime();
                      const cooldownMs = b.cooldownHours * 3600 * 1000;
                      const isReady = elapsed >= cooldownMs;
                      const remainingMs = Math.max(0, cooldownMs - elapsed);

                      return (
                        <tr key={b.bossName}>
                          <td>
                            <strong>{b.bossName}</strong>
                          </td>
                          <td className="num">
                            <span className="tag" style={{ fontWeight: 'bold' }}>{n(b.kills)}</span>
                          </td>
                          <td>
                            {isReady ? (
                              <span className="tag" style={{ background: 'rgba(16, 185, 129, 0.15)', color: '#10b981', fontWeight: 'bold' }}>
                                ✅ {t('ready_to_kill_short')}
                              </span>
                            ) : (
                              <span className="tag" style={{ background: 'rgba(234, 179, 8, 0.15)', color: '#eab308' }}>
                                ⏳ {formatCooldown(remainingMs)}
                              </span>
                            )}
                          </td>
                          <td className="small muted">
                            {new Date(b.lastKilledAt).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short' })}
                          </td>
                          <td className="small">
                            {b.lastDrops && b.lastDrops.length > 0 ? (
                              <span style={{ color: 'var(--color-accent, #3b82f6)' }}>
                                {b.lastDrops.map((d) => `${d.count}x ${d.name}`).join(', ')}
                              </span>
                            ) : (
                              <span className="muted">{b.lastDropLine || '—'}</span>
                            )}
                          </td>
                          <td style={{ textAlign: 'right' }}>
                            <button
                              type="button"
                              className="secondary small"
                              title="+1 Kill"
                              onClick={() => handleManualKill(b.bossName)}
                              style={{ padding: '0.2rem 0.5rem', marginRight: '0.3rem' }}
                            >
                              +1 Kill
                            </button>
                            {bosses && (
                              <button
                                type="button"
                                className="secondary small"
                                title={t('view_drops')}
                                onClick={() => {
                                  const found = bosses.find((x) => x.name.toLowerCase() === b.bossName.toLowerCase());
                                  if (found) {
                                    setSelected(found.id);
                                    setActiveTab('global');
                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                  }
                                }}
                                style={{ padding: '0.2rem 0.5rem' }}
                              >
                                📊
                              </button>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}

      {/* ========================================================================= */}
      {/* TAB 2: ESTATÍSTICAS GLOBAIS DE DROPS (EXISTENTE) */}
      {/* ========================================================================= */}
      {activeTab === 'global' && (
        <>
          {!bosses && !error && <p className="muted">{t('loading')}</p>}

          {bosses && (
            <label className="field">
              <span>{t('select_boss')}</span>
              <select value={selected ?? ''} onChange={(e) => setSelected(e.target.value ? Number(e.target.value) : null)}>
                <option value="">—</option>
                {bosses.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name} ({n(b.kills)}){b.is_boosted ? ` ★ ${t('boosted')}` : ''}
                  </option>
                ))}
              </select>
            </label>
          )}

          {stats && (
            <section className="card">
              <h2>{stats.boss.name}</h2>
              <p className="muted">{t('kills_recorded', { count: n(stats.kills) })}</p>
              {stats.items.length === 0 ? (
                <p>{t('no_drops')}</p>
              ) : (
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>{t('item')}</th>
                        <th className="num">{t('drops')}</th>
                        <th className="num">{t('drop_rate')}</th>
                        <th className="num">{t('boss_npc_each')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {stats.items.map((it) => (
                        <tr key={it.name} className={it.market_priority_alert ? 'row-alert' : ''}>
                          <td>
                            <CopyText text={it.name} />
                          </td>
                          <td className="num">{n(it.drops)}</td>
                          <td className="num">{(it.drop_rate * 100).toFixed(1)}%</td>
                          {/* D-80: coins are money, not something an NPC buys ("Crystal Coin 10000" read as a broken quantity) */}
                          <td className="num">{it.npc_price > 0 && !/^(gold|platinum|crystal) coin$/i.test(it.name) ? `${n(it.npc_price)} gp` : '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          )}

          <section className="card">
            <h2>{t('submit_log_title')}</h2>
            <p className="muted small">
              Cole linhas do Server Log ou carregue o arquivo .txt completo. Linhas com <code>(Double bonus)</code>, bônus diários e timestamps são detectadas automaticamente.
            </p>
            <form onSubmit={onSubmitGlobal} className="submit-form">
              <div style={{ marginBottom: '0.75rem' }}>
                <label className="button secondary small" style={{ cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
                  <span>📁 {locale.startsWith('pt') ? 'Carregar arquivo .txt do Server Log' : 'Import Server Log .txt'}</span>
                  <input
                    type="file"
                    accept=".txt,text/plain"
                    style={{ display: 'none' }}
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (!file) return;
                      const reader = new FileReader();
                      reader.onload = (ev) => {
                        setLogText(String(ev.target?.result ?? ''));
                      };
                      reader.readAsText(file);
                    }}
                  />
                </label>
              </div>
              <textarea
                value={logText}
                onChange={(e) => setLogText(e.target.value)}
                placeholder={t('submit_log_placeholder')}
                rows={5}
                spellCheck={false}
              />
              <div style={{ display: 'flex', gap: '0.5rem', marginTop: '0.5rem' }}>
                <button type="submit" disabled={submitting || !logText.trim()}>
                  {submitting ? t('loading') : t('submit')}
                </button>
                {logText && (
                  <button type="button" className="button secondary" onClick={() => setLogText('')}>
                    {t('clear')}
                  </button>
                )}
              </div>
            </form>
            {results && (
              <ul className="submit-results">
                {results.map((r, i) => (
                  <li key={i} className={r.status === 'recorded' ? 'pos' : 'neg'}>
                    <code>{r.line}</code> — {t(`status_${r.status}`)}
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      )}
    </div>
  );
}
