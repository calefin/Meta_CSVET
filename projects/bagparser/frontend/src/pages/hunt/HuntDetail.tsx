/** Hunt detail / share page and the side-by-side comparison (D-49). */
import { useEffect, useMemo, useState } from 'react';
import { useI18n } from '../../i18n';
import { useAuth } from '../../auth';
import { linkHandler, navigate } from '../../router';
import { ApiError, friends as friendsApi, hunts, type Friend, type HuntDetail as Detail, type HuntRow } from '../../api';
import { BuildView } from './build/BuildView';
import { CreatureAnalysis } from './CreatureAnalysis';
import { compareLoot, type HuntData } from '../../core/huntLog';
import { ShareButtons } from '../../components/ShareButtons';
import { CopyText } from '../../components/CopyText';
import { compact } from '../../components/Charts';
import { HuntView } from './HuntView';
import { HunterName } from './PublicTabs';
import { averages, Delta, fmtClientDate, fmtDuration, gp } from './common';

function asHuntData(h: Detail): HuntData {
  return {
    kind: h.kind,
    started_at: h.started_at,
    ended_at: h.ended_at,
    duration_s: h.duration_s,
    raw_xp: h.raw_xp,
    xp: h.xp,
    raw_xp_h: h.raw_xp_h,
    xp_h: h.xp_h,
    loot: h.loot,
    supplies: h.supplies,
    balance: h.balance,
    balance_h: h.balance_h,
    damage: h.damage,
    damage_h: h.damage_h,
    healing: h.healing,
    healing_h: h.healing_h,
    monsters: h.monsters,
    items: h.items,
    party: h.party,
  };
}

function useHunt(id: string) {
  const [state, setState] = useState<{ hunt: Detail; mine: boolean; shared?: boolean } | null | 'missing'>(null);
  useEffect(() => {
    setState(null);
    hunts
      .get(id)
      .then(setState)
      .catch(() => setState('missing'));
  }, [id]);
  return [state, setState] as const;
}

export function HuntDetailPage({ id }: { id: string }) {
  const { t, locale } = useI18n();
  const { user } = useAuth();
  const [state, setState] = useHunt(id);
  const [mineList, setMineList] = useState<HuntRow[]>([]);
  const [compareWith, setCompareWith] = useState('');
  const [notice, setNotice] = useState('');
  const [friendList, setFriendList] = useState<Friend[]>([]);
  const dup = new URLSearchParams(window.location.search).get('dup');

  const hunt = state && state !== 'missing' ? state.hunt : null;
  const mine = state && state !== 'missing' ? state.mine : false;

  useEffect(() => {
    if (!user) return;
    hunts
      .mine()
      .then((r) => setMineList(r.hunts))
      .catch(() => null);
    friendsApi
      .list()
      .then((r) => setFriendList(r.friends))
      .catch(() => null);
  }, [user]);

  const avg = useMemo(() => {
    if (!hunt || !mine) return null;
    const same = mineList.filter((r) => r.public_id !== hunt.public_id && r.character_name === hunt.character_name && r.duration_s >= 600);
    return averages(same);
  }, [hunt, mine, mineList]);

  useEffect(() => {
    if (hunt) document.title = `${hunt.title ?? hunt.place_name ?? hunt.top_monster ?? 'Hunt'} — ${compact(hunt.xp_h, 'en')} XP/h | BagParser`;
  }, [hunt]);

  if (state === null) return <p className="muted">{t('loading')}</p>;
  if (state === 'missing' || !hunt)
    return (
      <section className="card">
        <h1>{t('ha_not_found')}</h1>
        <p className="muted">{t('ha_not_found_text')}</p>
        <a className="button" href="/hunt-analyser/public" onClick={linkHandler('/hunt-analyser/public')}>
          {t('ha_tab_public')}
        </a>
      </section>
    );

  const url = `${window.location.origin}/h/${hunt.public_id}`;
  const patch = async (p: Record<string, unknown>, ok: string) => {
    try {
      await hunts.update(hunt.public_id, p);
      setState({ hunt: { ...hunt, ...p } as Detail, mine });
      setNotice(ok);
    } catch (err) {
      setNotice(err instanceof ApiError ? err.code : 'error');
    }
  };

  return (
    <div className="hunt-detail">
      <a href={mine ? '/hunt-analyser/mine' : '/hunt-analyser/public'} className="back-link" onClick={linkHandler(mine ? '/hunt-analyser/mine' : '/hunt-analyser/public')}>
        ← {t('back')}
      </a>
      <header className="hunt-detail-head">
        <div>
          <h1>{hunt.title ?? hunt.place_name ?? hunt.top_monster ?? t('ha_hunt')}</h1>
          {hunt.title && hunt.place_name && hunt.title !== hunt.place_name && (
            <p className="small">
              📍{' '}
              {hunt.place_slug ? (
                <a href={`/hunting/${hunt.place_slug}`} onClick={linkHandler(`/hunting/${hunt.place_slug}`)}>
                  {hunt.place_name}
                </a>
              ) : (
                hunt.place_name
              )}
            </p>
          )}
          <p className="muted">
            <HunterName h={hunt} /> · {fmtClientDate(hunt.started_at ?? hunt.created_at, locale, true)} · {fmtDuration(hunt.duration_s)}
            {hunt.kind === 'party' && ' · party'}
            {hunt.world && ` · ${hunt.world}`}
          </p>
          {Object.keys(hunt.setup ?? {}).length > 0 && (
            <p className="tags">
              {hunt.setup.prey && <span className="tag">Prey: {String(hunt.setup.prey)}</span>}
              {hunt.setup.boosted && <span className="tag">{t('ha_flag_boosted')}</span>}
              {hunt.setup.double_event && <span className="tag">{t('ha_flag_event')}</span>}
            </p>
          )}
        </div>
        <span className={`tag ${hunt.visibility === 'public' ? 'ok' : ''}`}>{hunt.visibility === 'public' ? `🌐 ${t('ha_public')}` : `🔒 ${t('ha_private')}`}</span>
      </header>
      {dup && <p className="callout">{t('ha_duplicate')}</p>}
      {state.shared && (
        <p className="callout">
          <span>🤝 {t('hs_shared_with_you')}</span>
          {hunt.kind === 'party' && (
            <a href={`/hunt-analyser/payments?hunt=${hunt.public_id}`} onClick={linkHandler(`/hunt-analyser/payments?hunt=${hunt.public_id}`)}>
              💸 {t('pay_settle_more')}
            </a>
          )}
        </p>
      )}
      {hunt.description && <p className="hunt-description">{hunt.description}</p>}

      <HuntView
        hunt={asHuntData(hunt)}
        input={hunt.input}
        serverLog={hunt.server_log}
        premium={!!user?.premium || !!hunt.server_log}
        avg={avg}
        expenses={hunt.expenses ?? null}
      />

      {hunt.build && <BuildView build={hunt.build} monsters={hunt.monsters} />}
      <CreatureAnalysis monsters={hunt.monsters} charms={hunt.build?.charms ?? []} serverLog={hunt.server_log} />

      {mine && (
        <section className="card">
          <h3>{t('ha_manage')}</h3>
          <div className="check-row">
            <label className="check">
              <input
                type="checkbox"
                checked={hunt.visibility === 'public'}
                onChange={(e) => void patch({ visibility: e.target.checked ? 'public' : 'private' }, t('ha_saved'))}
              />{' '}
              {t('ha_make_public')}
            </label>
            {hunt.visibility === 'public' && (
              <label className="check">
                <input type="checkbox" checked={hunt.anonymous} onChange={(e) => void patch({ anonymous: e.target.checked }, t('ha_saved'))} /> {t('ha_anonymous')}
              </label>
            )}
          </div>
          {hunt.notes && <p className="small">📝 {hunt.notes}</p>}
          {friendList.length > 0 && (
            <div>
              <h4>🤝 {t('hs_share_friends')}</h4>
              <div className="chip-row">
                {friendList.map((f) => {
                  const ids = (hunt.shared_with ?? []).map((x) => x.id);
                  const on = ids.includes(f.id);
                  return (
                    <button
                      key={f.id}
                      type="button"
                      className={`chip toggle${on ? ' ok' : ''}`}
                      aria-pressed={on}
                      onClick={() => {
                        const next = on ? ids.filter((x) => x !== f.id) : [...ids, f.id];
                        void hunts
                          .update(hunt.public_id, { shared_with: next })
                          .then(() => {
                            setState({ hunt: { ...hunt, shared_with: friendList.filter((x) => next.includes(x.id)) }, mine });
                            setNotice(t('ha_saved'));
                          })
                          .catch(() => setNotice(t('ha_err_generic')));
                      }}
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
            <select value={compareWith} onChange={(e) => setCompareWith(e.target.value)} aria-label={t('ha_compare_with')}>
              <option value="">{t('ha_compare_with')}…</option>
              {mineList
                .filter((r) => r.public_id !== hunt.public_id)
                .slice(0, 100)
                .map((r) => (
                  <option key={r.public_id} value={r.public_id}>
                    {fmtClientDate(r.started_at ?? r.created_at, locale)} · {r.place_name ?? r.top_monster ?? '—'} · {compact(r.xp_h, locale)} XP/h
                  </option>
                ))}
            </select>
            <button type="button" className="button" disabled={!compareWith} onClick={() => navigate(`/hunt-analyser/compare/${hunt.public_id}/${compareWith}`)}>
              ⚖️ {t('ha_compare')}
            </button>
            <a className="button secondary" href={`/hunt-analyser?from=${hunt.public_id}`} onClick={linkHandler(`/hunt-analyser?from=${hunt.public_id}`)}>
              📋 {t('hs_reuse')}
            </a>
            {hunt.kind === 'party' && (
              <a className="button secondary" href={`/hunt-analyser/payments?hunt=${hunt.public_id}`} onClick={linkHandler(`/hunt-analyser/payments?hunt=${hunt.public_id}`)}>
                💸 {t('pay_settle_more')}
              </a>
            )}
            <button
              type="button"
              className="button secondary danger"
              onClick={async () => {
                if (!window.confirm(t('ha_confirm_delete'))) return;
                await hunts.remove(hunt.public_id).catch(() => null);
                navigate('/hunt-analyser/mine');
              }}
            >
              🗑️ {t('delete')}
            </button>
          </div>
          {notice && <p className="muted small">{notice}</p>}
        </section>
      )}

      {hunt.visibility === 'public' && (
        <section className="card share-card">
          <p>{t('ha_share_text')}</p>
          <div className="button-row">
            <CopyText text={url} label={t('ha_copy_link')} />
          </div>
          <ShareButtons
            text={t('ha_share_message', {
              place: hunt.place_name ?? hunt.top_monster ?? 'hunt',
              xp: compact(hunt.xp_h, locale),
              gp: gp(hunt.balance_h, locale),
            })}
            url={url}
          />
        </section>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------

export function CompareView({ a, b }: { a: string; b: string }) {
  const { t, n, locale } = useI18n();
  const [sa] = useHunt(a);
  const [sb] = useHunt(b);
  const ha = sa && sa !== 'missing' ? sa.hunt : null;
  const hb = sb && sb !== 'missing' ? sb.hunt : null;
  const loot = useMemo(() => (ha && hb ? compareLoot(ha, hb) : []), [ha, hb]);
  const monsters = useMemo(
    () => (ha && hb ? compareLoot({ items: ha.monsters, duration_s: ha.duration_s }, { items: hb.monsters, duration_s: hb.duration_s }) : []),
    [ha, hb],
  );

  if (sa === null || sb === null) return <p className="muted">{t('loading')}</p>;
  if (!ha || !hb) return <p className="card">{t('ha_not_found')}</p>;

  const rows: { label: string; k: keyof Detail; money?: boolean; invert?: boolean }[] = [
    { label: t('ha_xp_h'), k: 'xp_h' },
    { label: t('ha_raw_xp_h'), k: 'raw_xp_h' },
    { label: t('ha_balance_h'), k: 'balance_h', money: true },
    { label: t('ha_loot'), k: 'loot', money: true },
    { label: t('ha_supplies'), k: 'supplies', money: true, invert: true },
    { label: t('ha_damage_h'), k: 'damage_h' },
    { label: t('ha_healing_h'), k: 'healing_h' },
    { label: 'Level', k: 'level' },
  ];
  const head = (h: Detail, tag: string) => (
    <th>
      <span className="tag">{tag}</span>{' '}
      <a href={`/hunt-analyser/h/${h.public_id}`} onClick={linkHandler(`/hunt-analyser/h/${h.public_id}`)}>
        {h.place_name ?? h.top_monster ?? '—'}
      </a>
      <div className="muted small">
        {h.character_name ?? ''} · {fmtClientDate(h.started_at ?? h.created_at, locale)} · {fmtDuration(h.duration_s)}
      </div>
    </th>
  );
  const fmt = (v: unknown, money?: boolean) => (typeof v === 'number' ? (money ? gp(v, locale) : n(v)) : '—');

  return (
    <div className="compare">
      <h1>⚖️ {t('ha_compare_title')}</h1>
      <div className="table-scroll card">
        <table className="compact-table compare-table">
          <thead>
            <tr>
              <th />
              {head(ha, 'A')}
              {head(hb, 'B')}
              <th className="num">B − A</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.k}>
                <th scope="row">{r.label}</th>
                <td className="num">{fmt(ha[r.k], r.money)}</td>
                <td className="num">{fmt(hb[r.k], r.money)}</td>
                <td className="num">
                  {typeof ha[r.k] === 'number' && typeof hb[r.k] === 'number' && (
                    <Delta a={ha[r.k] as number} b={hb[r.k] as number} money={r.money} invert={r.invert} />
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="hunt-cols">
        <section className="card">
          <h3>{t('ha_loot_diff')}</h3>
          <p className="muted small">{t('ha_per_hour_note')}</p>
          <DiffTable rows={loot} />
        </section>
        <section className="card">
          <h3>{t('ha_monster_diff')}</h3>
          <p className="muted small">{t('ha_per_hour_note')}</p>
          <DiffTable rows={monsters} />
        </section>
      </div>

      {ha.input && hb.input && (
        <section className="card">
          <h3>{t('ha_input_title')}</h3>
          <p>
            {t('ha_received')}: {n(Math.round(ha.input.total / (ha.duration_s / 3600)))}/h → {n(Math.round(hb.input.total / (hb.duration_s / 3600)))}/h ·{' '}
            Max-DPS: {n(ha.input.max_dps)} → {n(hb.input.max_dps)}
          </p>
        </section>
      )}
    </div>
  );
}

function DiffTable({ rows }: { rows: { name: string; a: number; b: number; delta: number }[] }) {
  const { t, n } = useI18n();
  if (!rows.length) return <p className="muted small">{t('ha_none')}</p>;
  return (
    <table className="compact-table">
      <thead>
        <tr>
          <th />
          <th className="num">A/h</th>
          <th className="num">B/h</th>
          <th className="num">Δ</th>
        </tr>
      </thead>
      <tbody>
        {rows.slice(0, 25).map((r) => (
          <tr key={r.name}>
            <td>{r.name}</td>
            <td className="num">{n(r.a)}</td>
            <td className="num">{n(r.b)}</td>
            <td className={`num ${r.delta > 0 ? 'pos' : r.delta < 0 ? 'neg' : ''}`}>
              {r.delta > 0 ? '+' : ''}
              {n(r.delta)}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
