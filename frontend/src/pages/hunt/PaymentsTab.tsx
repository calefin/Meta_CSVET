/**
 * Payment distributions (D-60): settle several party hunts at once.
 * Pick hunts (mine + shared with me) → nets per player added up (characters of the same player merged) →
 * fewest bank transfers, grouped by payer, with the "transfer" command → save and tick each transfer as paid.
 */
import { useEffect, useMemo, useState } from 'react';
import { useI18n } from '../../i18n';
import { useAuth } from '../../auth';
import { linkHandler } from '../../router';
import { ApiError, friends as friendsApi, payouts as payoutsApi, type Friend, type PayoutHuntRow, type SavedPayout } from '../../api';
import { allPlayerNames, groupByPayer, payoutPlayers, payoutText, separateTransferCount, settleNets, transferCommand, type PayoutTransfer } from '../../core/payouts';
import { CopyText } from '../../components/CopyText';
import { fmtClientDate, gp } from './common';

const PERIODS = [7, 30, 90, 0] as const;
const ROUNDS = [1, 100, 1000, 10000] as const;

const huntLabel = (h: PayoutHuntRow) => h.title || h.place_name || h.character_name || h.id;

export function PaymentsTab() {
  const { t, locale } = useI18n();
  const { user } = useAuth();
  const preselect = useMemo(() => new URLSearchParams(window.location.search).get('hunt'), []);
  const [days, setDays] = useState<number>(preselect ? 0 : 30);
  const [rows, setRows] = useState<PayoutHuntRow[] | null>(null);
  const [hideSettled, setHideSettled] = useState(!preselect);
  const [selected, setSelected] = useState<string[]>(preselect ? [preselect] : []);
  const [aliases, setAliases] = useState<Record<string, string>>({});
  const [roundTo, setRoundTo] = useState<number>(1);
  const [name, setName] = useState('');
  const [shareWith, setShareWith] = useState<number[]>([]);
  const [friendList, setFriendList] = useState<Friend[]>([]);
  const [saved, setSaved] = useState<SavedPayout[]>([]);
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);
  const [openId, setOpenId] = useState<number | null>(null);

  useEffect(() => {
    if (!user) return;
    setRows(null);
    payoutsApi
      .hunts(days)
      .then((r) => setRows(r.hunts))
      .catch(() => setRows([]));
  }, [user, days]);

  useEffect(() => {
    if (!user) return;
    payoutsApi
      .list()
      .then((r) => setSaved(r.payouts))
      .catch(() => null);
    friendsApi
      .list()
      .then((r) => setFriendList(r.friends))
      .catch(() => null);
  }, [user]);

  const visible = useMemo(
    () => (rows ?? []).filter((h) => !hideSettled || !h.payout_ids.length || selected.includes(h.id)),
    [rows, hideSettled, selected],
  );
  const chosen = useMemo(() => (rows ?? []).filter((h) => selected.includes(h.id) && !h.anonymous), [rows, selected]);
  const names = useMemo(() => allPlayerNames(chosen), [chosen]);
  const players = useMemo(() => payoutPlayers(chosen, aliases), [chosen, aliases]);
  const transfers = useMemo(() => settleNets(players, roundTo), [players, roundTo]);
  const separate = useMemo(() => separateTransferCount(chosen), [chosen]);
  const profit = chosen.reduce((s, h) => s + h.balance - h.extra_expenses, 0);

  const defaultName = useMemo(() => {
    const dates = chosen.map((h) => h.started_at).filter((d): d is string => !!d).sort();
    if (!dates.length) return t('pay_title');
    const f = (d: string) => fmtClientDate(d, locale).split(/[ ,]+/)[0];
    const a = f(dates[0]);
    const b = f(dates[dates.length - 1]);
    return t('pay_default_name', { range: a === b ? a : `${a} – ${b}` });
  }, [chosen, locale, t]);

  if (user === undefined) return <p className="muted">{t('loading')}</p>;
  if (!user)
    return (
      <section className="card">
        <p>{t('pay_login')}</p>
        <a className="button" href="/login" onClick={linkHandler('/login')}>
          {t('login')}
        </a>
      </section>
    );

  const toggle = (id: string) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

  const save = async () => {
    setBusy(true);
    setMsg('');
    try {
      const body = {
        name: (name.trim() || defaultName).slice(0, 120),
        hunt_ids: chosen.map((h) => h.id),
        aliases,
        round_to: roundTo,
        players: players.map((p) => ({ name: p.name, chars: p.chars, net: p.net })),
        transfers: transfers.map((x) => ({ ...x, paid: false })),
        total: profit,
        shared_with: shareWith,
      };
      const r = await payoutsApi.create(body);
      const row: SavedPayout = { ...body, id: r.id, hunt_ids: r.hunt_ids, created_at: new Date().toISOString(), settled_at: null, mine: true, shared_with: friendList.filter((f) => shareWith.includes(f.id)) };
      setSaved((l) => [row, ...l]);
      setRows((l) => (l ?? []).map((h) => (r.hunt_ids.includes(h.id) ? { ...h, payout_ids: [...h.payout_ids, r.id] } : h)));
      setSelected([]);
      setAliases({});
      setName('');
      setOpenId(r.id);
      setMsg(t('pay_saved_ok'));
    } catch (e) {
      setMsg(e instanceof ApiError && e.code === 'payout_limit' ? t('pay_limit') : t('ha_err_generic'));
    } finally {
      setBusy(false);
    }
  };

  const setPaid = (p: SavedPayout, index: number, paid: boolean) => {
    const flags = p.transfers.map((x, i) => (i === index ? paid : x.paid));
    const next = { ...p, transfers: p.transfers.map((x, i) => ({ ...x, paid: flags[i] })), settled_at: flags.every(Boolean) ? new Date().toISOString() : null };
    setSaved((l) => l.map((x) => (x.id === p.id && x.mine === p.mine ? next : x)));
    payoutsApi.update(p.id, { paid: flags }).catch(() => {
      setSaved((l) => l.map((x) => (x.id === p.id && x.mine === p.mine ? p : x)));
      setMsg(t('ha_err_generic'));
    });
  };

  return (
    <div className="pay">
      <section className="card">
        <h2>💸 {t('pay_title')}</h2>
        <p className="muted small">{t('pay_intro')}</p>
        <div className="pay-filters">
          <div className="seg" role="group" aria-label={t('pay_period')}>
            {PERIODS.map((d) => (
              <button key={d} type="button" className={days === d ? 'active' : ''} aria-pressed={days === d} onClick={() => setDays(d)}>
                {d ? t('pay_days', { n: d }) : t('pay_all_time')}
              </button>
            ))}
          </div>
          <label className="field inline">
            <input type="checkbox" checked={hideSettled} onChange={(e) => setHideSettled(e.target.checked)} /> {t('pay_hide_settled')}
          </label>
        </div>

        {rows === null ? (
          <p className="muted">{t('loading')}</p>
        ) : visible.length === 0 ? (
          <p className="muted">{rows.length ? t('pay_all_settled') : t('pay_no_hunts')}</p>
        ) : (
          <>
            <div className="button-row">
              <button type="button" className="button secondary small-btn" onClick={() => setSelected(visible.filter((h) => !h.anonymous).map((h) => h.id))}>
                {t('pay_select_all')}
              </button>
              {selected.length > 0 && (
                <button type="button" className="button secondary small-btn" onClick={() => setSelected([])}>
                  {t('pay_clear')}
                </button>
              )}
            </div>
            <ul className="pay-hunts">
              {visible.map((h) => (
                <li key={h.id}>
                  <label className={selected.includes(h.id) ? 'on' : ''}>
                    <input type="checkbox" checked={selected.includes(h.id)} disabled={h.anonymous} onChange={() => toggle(h.id)} />
                    <span className="pay-hunt-main">
                      <strong>{huntLabel(h)}</strong>
                      <span className="muted small">
                        {fmtClientDate(h.started_at, locale)} · {t('pay_players_n', { n: h.members.length })}
                        {h.owner ? ` · ${t('pay_from_owner', { name: h.owner })}` : ''}
                        {h.anonymous ? ` · ${t('pay_anonymous')}` : ''}
                      </span>
                    </span>
                    <span className="pay-hunt-side">
                      <span className={h.balance - h.extra_expenses < 0 ? 'neg' : 'pos'}>{gp(h.balance - h.extra_expenses, locale)}</span>
                      {h.payout_ids.length > 0 && <span className="tag">{t('pay_settled_tag')}</span>}
                    </span>
                  </label>
                </li>
              ))}
            </ul>
          </>
        )}
      </section>

      {chosen.length > 0 && (
        <section className="card pay-result">
          <div className="row-between">
            <h3>{t('pay_selected', { n: chosen.length, gp: gp(profit, locale) })}</h3>
            <label className="pay-round">
              {t('pay_round')}{' '}
              <select value={roundTo} onChange={(e) => setRoundTo(Number(e.target.value))}>
                {ROUNDS.map((r) => (
                  <option key={r} value={r}>
                    {r === 1 ? t('pay_round_none') : gp(r, locale)}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <div className="table-scroll">
            <table className="compact-table pay-matrix">
              <thead>
                <tr>
                  <th>{t('pay_players')}</th>
                  <th className="num">{t('pay_total')}</th>
                  {chosen.length > 1 &&
                    chosen.map((h) => (
                      <th key={h.id} className="num" title={huntLabel(h)}>
                        {fmtClientDate(h.started_at, locale).split(/[ ,]+/)[0]}
                      </th>
                    ))}
                </tr>
              </thead>
              <tbody>
                {players.map((p) => (
                  <tr key={p.name}>
                    <td>
                      <strong>{p.name}</strong>
                      {p.chars.length > 1 && <span className="muted small"> + {p.chars.filter((c) => c.toLowerCase() !== p.name.toLowerCase()).join(', ')}</span>}
                    </td>
                    <td className={`num ${p.net > 0 ? 'neg' : p.net < 0 ? 'pos' : ''}`}>
                      <strong>{p.net > 0 ? `${t('pay_pays')} ${gp(p.net, locale)}` : p.net < 0 ? `${t('pay_receives')} ${gp(-p.net, locale)}` : t('pay_even')}</strong>
                    </td>
                    {chosen.length > 1 &&
                      chosen.map((h) => (
                        <td key={h.id} className={`num ${(p.perHunt[h.id] ?? 0) > 0 ? 'neg' : 'pos'}`}>
                          {h.id in p.perHunt ? gp(-p.perHunt[h.id], locale) : '—'}
                        </td>
                      ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {chosen.length > 1 && <p className="muted small">{t('pay_matrix_hint')}</p>}

          {names.length > 1 && (
            <details className="pay-merge">
              <summary>
                🔗 {t('pay_merge')}
                {Object.keys(aliases).length > 0 && <span className="tag">{Object.keys(aliases).length}</span>}
              </summary>
              <p className="muted small">{t('pay_merge_intro')}</p>
              <ul className="plain-list">
                {names.map((nm) => (
                  <li key={nm} className="row-between">
                    <span>{nm}</span>
                    <select
                      value={aliases[nm] ?? ''}
                      aria-label={`${nm}: ${t('pay_same_as')}`}
                      onChange={(e) =>
                        setAliases((a) => {
                          const next = { ...a };
                          if (e.target.value) next[nm] = e.target.value;
                          else delete next[nm];
                          return next;
                        })
                      }
                    >
                      <option value="">{t('pay_same_as')}…</option>
                      {names
                        .filter((x) => x !== nm && !aliases[x])
                        .map((x) => (
                          <option key={x} value={x}>
                            = {x}
                          </option>
                        ))}
                    </select>
                  </li>
                ))}
              </ul>
            </details>
          )}

          <h3>
            🏦 {t('pay_transfers')}{' '}
            <span className="muted small">
              {separate > transfers.length ? t('pay_saving', { n: transfers.length, m: separate }) : t('pay_count', { n: transfers.length })}
            </span>
          </h3>
          {transfers.length ? (
            <>
              <TransferGroups transfers={transfers} />
              <p className="muted small">
                {t('pay_command_hint')}
                {roundTo > 1 ? ` ${t('pay_round_note', { gp: gp(roundTo / 2, locale) })}` : ''}
              </p>
              <CopyText text={payoutText(name.trim() || defaultName, transfers, (v) => gp(v, locale))} label={`📋 ${t('pay_copy_all')}`} />
            </>
          ) : (
            <p className="muted">{t('pay_nothing')}</p>
          )}

          <div className="pay-save">
            <label className="field">
              <span>{t('pay_name')}</span>
              <input value={name} onChange={(e) => setName(e.target.value)} placeholder={defaultName} maxLength={120} />
            </label>
            {friendList.length > 0 && (
              <div>
                <span className="small">🤝 {t('pay_share')}</span>
                <div className="chip-row">
                  {friendList.map((f) => {
                    const on = shareWith.includes(f.id);
                    return (
                      <button
                        key={f.id}
                        type="button"
                        className={`chip toggle${on ? ' ok' : ''}`}
                        aria-pressed={on}
                        onClick={() => setShareWith((s) => (on ? s.filter((x) => x !== f.id) : [...s, f.id]))}
                      >
                        {on ? '✓ ' : ''}
                        {f.display_name}
                      </button>
                    );
                  })}
                </div>
              </div>
            )}
            <div className="button-row">
              <button type="button" className="button" disabled={busy} onClick={() => void save()}>
                💾 {t('pay_save')}
              </button>
            </div>
          </div>
        </section>
      )}

      {msg && <p className="callout small">{msg}</p>}

      <section className="card">
        <h2>🧾 {t('pay_saved_title')}</h2>
        {saved.length === 0 ? (
          <p className="muted small">{t('pay_saved_empty')}</p>
        ) : (
          <ul className="pay-saved">
            {saved.map((p) => {
              const done = p.transfers.filter((x) => x.paid).length;
              const key = `${p.mine ? 'm' : 's'}${p.id}`;
              return (
                <li key={key}>
                  <details open={openId === p.id && p.mine} onToggle={(e) => (e.currentTarget.open ? setOpenId(p.id) : openId === p.id && setOpenId(null))}>
                    <summary>
                      <span className="pay-hunt-main">
                        <strong>{p.name}</strong>
                        <span className="muted small">
                          {new Date(p.created_at).toLocaleDateString(locale)} · {t('pay_hunts_n', { n: p.hunt_ids.length })} · {gp(p.total, locale)}
                          {p.owner ? ` · ${t('pay_shared_by', { name: p.owner })}` : ''}
                        </span>
                      </span>
                      <span className={`tag${p.transfers.length && done === p.transfers.length ? ' ok' : ''}`}>
                        {p.transfers.length && done === p.transfers.length ? `✓ ${t('pay_done')}` : t('pay_progress', { done, n: p.transfers.length })}
                      </span>
                    </summary>
                    <div className="pay-saved-body">
                      {p.transfers.length ? (
                        <TransferGroups transfers={p.transfers} onPaid={(i, v) => setPaid(p, i, v)} />
                      ) : (
                        <p className="muted small">{t('pay_nothing')}</p>
                      )}
                      <div className="button-row">
                        <CopyText text={payoutText(p.name, p.transfers, (v) => gp(v, locale))} label={`📋 ${t('pay_copy_all')}`} />
                        {p.mine && (
                          <button
                            type="button"
                            className="button secondary small-btn danger"
                            onClick={() => {
                              if (!window.confirm(t('pay_remove_confirm', { name: p.name }))) return;
                              void payoutsApi.remove(p.id).then(() => {
                                setSaved((l) => l.filter((x) => !(x.id === p.id && x.mine)));
                                setRows((l) => (l ?? []).map((h) => ({ ...h, payout_ids: h.payout_ids.filter((x) => x !== p.id) })));
                              });
                            }}
                          >
                            🗑️ {t('delete')}
                          </button>
                        )}
                      </div>
                      <p className="small muted">
                        {t('pay_hunts_in')}:{' '}
                        {p.hunt_ids.map((id, i) => (
                          <span key={id}>
                            {i > 0 && ', '}
                            <a href={`/h/${id}`} onClick={linkHandler(`/h/${id}`)}>
                              {(rows ?? []).find((h) => h.id === id) ? huntLabel((rows ?? []).find((h) => h.id === id)!) : id}
                            </a>
                          </span>
                        ))}
                      </p>
                      {p.mine && p.shared_with && p.shared_with.length > 0 && (
                        <p className="small muted">🤝 {p.shared_with.map((f) => f.display_name).join(', ')}</p>
                      )}
                    </div>
                  </details>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </div>
  );
}

/** Transfers grouped by payer; with `onPaid`, each transfer gets a "paid" checkbox */
function TransferGroups({ transfers, onPaid }: { transfers: PayoutTransfer[]; onPaid?: (index: number, paid: boolean) => void }) {
  const { t, locale } = useI18n();
  return (
    <div className="pay-groups">
      {groupByPayer(transfers).map((g) => (
        <div key={g.from} className="pay-group">
          <h4>
            {g.from} <span className="muted small">· {t('pay_payer_total', { gp: gp(g.total, locale) })}</span>
          </h4>
          <ul>
            {g.items.map((x) => (
              <li key={x.index} className={x.paid ? 'paid' : ''}>
                {onPaid && (
                  <label className="pay-paid">
                    <input type="checkbox" checked={!!x.paid} onChange={(e) => onPaid(x.index, e.target.checked)} />
                    <span className="sr-only">{t('pay_mark_paid')}</span>
                  </label>
                )}
                <span className="pay-to">
                  → <strong>{x.to}</strong> <span className="muted">{gp(x.amount, locale)}</span>
                </span>
                <CopyText text={transferCommand(x)} />
              </li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  );
}
