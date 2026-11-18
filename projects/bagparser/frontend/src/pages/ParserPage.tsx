import { useEffect, useMemo, useState } from 'react';
import { useI18n } from '../i18n';
import { parseTibiaSession, type DestinationBucket } from '../core/parser';
import { DESTINATION_ORDER, defaultLookupDb, type Destination } from '../core/items';
import { rashidToday, weekdayName } from '../core/npcSchedule';
import { daily, type YasirCity, type YasirStatus } from '../api';
import { readSavedWorld, saveWorld, yasirStatusText } from './YasirPage';
import { CopyText } from '../components/CopyText';
import { ShareButtons } from '../components/ShareButtons';
import { LootGrid } from '../components/LootGrid';
import { ItemSprite, useSprites } from '../components/ItemSprite';
import { npcInfo, npcPlace } from '../core/npcs';
import { linkHandler } from '../router';
import { buildSellRoute, type SellRoute } from '../core/sellRoute';

const LAST_LOG_KEY = 'bagparser_last_log';

function readLastLog(): string {
  try {
    return localStorage.getItem(LAST_LOG_KEY) ?? '';
  } catch {
    return '';
  }
}

function writeLastLog(text: string): void {
  try {
    if (text) localStorage.setItem(LAST_LOG_KEY, text);
    else localStorage.removeItem(LAST_LOG_KEY);
  } catch {
    /* storage unavailable */
  }
}

const routePlace = (npc: string) => {
  const info = npcInfo(npc);
  return info && !info.travelling ? { city: info.city, area: info.area } : undefined;
};

const canReadClipboard = typeof navigator !== 'undefined' && !!navigator.clipboard?.readText;

/**
 * D-67: Yasir comes from today's player reports for the chosen world (same world as the home page and /yasir),
 * not from a yes/no checkbox: his stop goes to the reported city, and with no report he is left out.
 */
function useYasirToday(world: string, tick: number) {
  const [data, setData] = useState<{ status: YasirStatus; city: YasirCity | null } | null>(null);
  useEffect(() => {
    if (!world) {
      setData(null);
      return;
    }
    let alive = true;
    daily
      .yasir(world)
      .then((r) => alive && setData({ status: r.status, city: r.city }))
      .catch(() => alive && setData(null));
    return () => {
      alive = false;
    };
  }, [world, tick]);
  return data;
}

function formatDuration(minutes: number): string {
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return `${h}:${String(m).padStart(2, '0')}h`;
}

export function ParserPage() {
  const { t, n, locale } = useI18n();
  // Last log survives reloads / reopening the installed app (stays on this device only)
  const [text, setText] = useState(readLastLog);
  // Re-evaluate Rashid's city / Yasir after server save while the app stays open
  const [tick, setTick] = useState(0);
  const [world, setWorld] = useState(readSavedWorld);
  const [worlds, setWorlds] = useState<string[]>([]);
  useEffect(() => {
    if (!world)
      daily
        .worlds()
        .then(setWorlds)
        .catch(() => null);
  }, [world]);
  const yasir = useYasirToday(world, tick);
  const yasirHere = !!yasir && (yasir.status === 'confirmed' || yasir.status === 'unconfirmed') && !!yasir.city;
  const yasirCity = yasirHere && yasir!.city !== 'other' ? yasir!.city : null;
  useEffect(() => {
    const id = window.setTimeout(() => writeLastLog(text), 400);
    return () => window.clearTimeout(id);
  }, [text]);
  useEffect(() => {
    const id = window.setInterval(() => setTick((x) => x + 1), 5 * 60 * 1000);
    return () => window.clearInterval(id);
  }, []);

  const pasteFromClipboard = async () => {
    try {
      const clip = await navigator.clipboard.readText();
      if (clip.trim()) setText(clip);
    } catch {
      /* permission denied: the user can still paste with Ctrl+V / long press */
    }
  };

  // FR-CORE-01/02: parse locally on every change
  const result = useMemo(() => (text.trim() ? parseTibiaSession(text, defaultLookupDb) : null), [text]);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const rashid = useMemo(() => rashidToday(), [tick]);
  const route = useMemo(
    () => (result ? buildSellRoute(result.destinations, { place: routePlace, rashidCity: rashid.city, yasirHere, yasirCity }) : null),
    [result, rashid.city, yasirHere, yasirCity],
  );

  const gp = (v: number) => `${n(v)} gp`;
  const oz = (v: number) => `${n(Number(v.toFixed(2)))} oz`;

  return (
    <div className="page parser-page">
      <div className="npc-bar">
        <div className="npc-pill">
          {t('rashid_location', { day: weekdayName(rashid.serverDay, locale), city: rashid.city })}
        </div>
        {world ? (
          <div className={`npc-pill ${yasirHere ? 'ok' : 'muted'}`}>
            Yasir · {world}: {yasir ? yasirStatusText(t, yasir.status, yasir.city) : t('loading')}
            <a href="/yasir" onClick={linkHandler('/yasir')} className="npc-pill-link">
              {t('yasir_report_short')}
            </a>
          </div>
        ) : (
          <label className="npc-pill">
            {t('yasir_pick_world')}{' '}
            <select
              value=""
              onChange={(e) => {
                setWorld(e.target.value);
                saveWorld(e.target.value);
              }}
            >
              <option value="">{t('pick_world')}</option>
              {worlds.map((w) => (
                <option key={w} value={w}>
                  {w}
                </option>
              ))}
            </select>
          </label>
        )}
        <div className={`npc-pill ${yasirHere ? 'ok' : 'muted'}`}>
          {yasirHere ? t('yasir_route_in', { city: yasirCity ?? t('yasir_city_other') }) : t('yasir_alert_inactive')}
        </div>
      </div>

      <div className="input-area">
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          placeholder={t('paste_placeholder')}
          spellCheck={false}
          rows={10}
          aria-label={t('paste_placeholder')}
        />
        <div className="button-row">
          {canReadClipboard && (
            <button type="button" onClick={pasteFromClipboard}>
              {t('paste')}
            </button>
          )}
          <button type="button" className="secondary" onClick={() => setText('')} disabled={!text}>
            {t('clear')}
          </button>
        </div>
      </div>

      {!result && <p className="empty-state">{t('empty_state')}</p>}

      {result && (
        <>
          <section className="card">
            <h2>{t('session_summary')}</h2>
            <div className="metrics">
              <Metric label={t('duration')} value={formatDuration(result.metrics.durationMinutes)} />
              {/* Party Hunt Analyser logs carry no XP */}
              {result.metrics.xpTotal > 0 && <Metric label={t('xp_total')} value={n(result.metrics.xpTotal)} />}
              {result.metrics.xpTotal > 0 && <Metric label={t('xp_hour')} value={n(result.metrics.xpHour)} />}
              <Metric label={t('loot')} value={gp(result.metrics.lootTotal)} />
              <Metric label={t('supplies')} value={gp(result.metrics.suppliesTotal)} />
              <Metric
                label={t('balance')}
                value={gp(result.metrics.balanceNet)}
                tone={result.metrics.balanceNet >= 0 ? 'pos' : 'neg'}
              />
              <Metric
                label={t('profit_hour')}
                value={gp(result.metrics.balanceHour)}
                tone={result.metrics.balanceHour >= 0 ? 'pos' : 'neg'}
              />
              {result.destinations.Coins.subtotalGps > 0 && (
                <Metric label={t('coins_in_loot')} value={gp(result.destinations.Coins.subtotalGps)} />
              )}
              {result.totalWeightOz > 0 && <Metric label={t('total_weight')} value={oz(result.totalWeightOz)} />}
            </div>
            <LootGrid
              items={DESTINATION_ORDER.filter((d) => d !== 'Coins').flatMap((d) => result.destinations[d].items)}
            />
            <ShareButtons
              compact
              url="https://bagparser.com/parser"
              text={t('share_hunt_message', {
                loot: gp(result.metrics.lootTotal),
                balance: gp(result.metrics.balanceNet),
                profit: gp(result.metrics.balanceHour),
              })}
            />
          </section>

          {result.party && (
            <section className="card">
              <h2>{t('party_split')}</h2>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>{t('player')}</th>
                      <th className="num">{t('loot')}</th>
                      <th className="num">{t('supplies')}</th>
                      <th className="num">{t('balance')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {result.party.members.map((m) => (
                      <tr key={m.name}>
                        <td>
                          {m.name}
                          {m.isLeader && <span className="tag">leader</span>}
                        </td>
                        <td className="num">{n(m.loot)}</td>
                        <td className="num">{n(m.supplies)}</td>
                        <td className={`num ${m.balance >= 0 ? 'pos' : 'neg'}`}>{n(m.balance)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p>{t('share_each', { value: n(result.party.split.sharePerPlayer) })}</p>
              {result.party.split.transfers.length === 0 ? (
                <p className="muted">{t('no_transfers')}</p>
              ) : (
                <ul className="transfers">
                  {result.party.split.transfers.map((tr) => (
                    <li key={`${tr.from}->${tr.to}`}>
                      <span>
                        {t('transfer', { from: tr.from, to: tr.to })}: <strong>{gp(tr.amount)}</strong>
                      </span>
                      <CopyText text={`transfer ${tr.amount} to ${tr.to}`} />
                    </li>
                  ))}
                </ul>
              )}
              {result.party.split.transfers.length > 1 && (
                <div className="copy-all">
                  <CopyText
                    label={t('copy_all_transfers')}
                    text={result.party.split.transfers
                      .map((tr) => `${tr.from} → ${tr.to}: transfer ${tr.amount} to ${tr.to}`)
                      .join('\n')}
                  />
                </div>
              )}
            </section>
          )}

          {route && route.cities.length > 0 && <SellRouteCard route={route} />}

          <div className="destinations">
            {/* Coins are already money: they are never part of a sale (shown in the summary only) */}
            {DESTINATION_ORDER.filter((dest) => dest !== 'Coins').map((dest) => (
              <DestinationCard
                key={dest}
                dest={dest}
                bucket={result.destinations[dest]}
                yasirHere={yasirHere}
                rashidCity={rashid.city}
              />
            ))}
          </div>

          {result.unrecognizedItems.length > 0 && (
            <section className="card warn">
              <h2>{t('unrecognized')}</h2>
              <p className="muted small">{t('unrecognized_hint')}</p>
              <ul className="plain-list">
                {result.unrecognizedItems.map((line) => (
                  <li key={line}>{line}</li>
                ))}
              </ul>
            </section>
          )}

          {result.killedMonsters.length > 0 && (
            <section className="card">
              <h2>{t('killed_monsters')}</h2>
              <ul className="chips">
                {result.killedMonsters.map((m) => (
                  <li key={m.name}>
                    {n(m.count)}× {m.name}
                  </li>
                ))}
              </ul>
            </section>
          )}
        </>
      )}
    </div>
  );
}

function DestinationCard({
  dest,
  bucket,
  yasirHere,
  rashidCity,
}: {
  dest: Destination;
  bucket: DestinationBucket;
  yasirHere: boolean;
  rashidCity: string;
}) {
  const { t, n } = useI18n();
  const sprites = useSprites(); // before the early return (rules of hooks)
  if (bucket.items.length === 0) return null;
  const gp = (v: number) => `${n(v)} gp`;
  const oz = (v: number) => `${n(Number(v.toFixed(2)))} oz`;
  const isMarket = dest === 'Market / Imbuement Alerts';
  const showBuyers = dest !== 'Coins' && bucket.items.some((i) => i.sellTo.length > 0);
  return (
    <section className={`card dest ${isMarket ? 'market' : ''} ${dest === 'Yasir' && !yasirHere ? 'dim' : ''}`}>
      <header className="dest-head">
        <h2>
          {t(`dest_${dest}`)}
          {dest === 'Rashid' && <span className="tag">{rashidCity}</span>}
        </h2>
        <div className="dest-totals">
          <strong>{gp(bucket.subtotalGps)}</strong>
          <span className="muted small">{oz(bucket.subtotalWeightOz)}</span>
        </div>
      </header>
      {isMarket && <p className="alert">{t('warning_market')}</p>}
      {dest === 'Yasir' && !yasirHere && <p className="muted small">{t('yasir_alert_inactive')}</p>}
      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              <th>{t('item')}</th>
              <th className="num">{t('qty')}</th>
              <th className="num col-unit">{t('unit_price')}</th>
              <th className="num">{t('value')}</th>
              <th className="num">{t('weight')}</th>
              {showBuyers && <th className="buyers-col">{t('sell_to')}</th>}
            </tr>
          </thead>
          <tbody>
            {bucket.items.map((it) => (
              <tr key={it.name} className={it.marketAlert && !isMarket ? 'row-alert' : ''}>
                <td>
                  <span className="item-cell">
                    <ItemSprite name={it.name} sprites={sprites} size={24} />
                    <CopyText text={it.name} />
                  </span>
                  {showBuyers && (
                    // Narrow screens: buyers under the item name instead of a column
                    <div className="buyers-inline">
                      <NpcList names={it.sellTo} rashidCity={rashidCity} />
                    </div>
                  )}
                </td>
                <td className="num">{n(it.count)}</td>
                <td className="num col-unit">{n(it.unitNpcPrice)}</td>
                <td className="num">{n(it.totalValue)}</td>
                <td className="num">{n(it.weightOz)}</td>
                {showBuyers && (
                  <td className="buyers buyers-col">
                    <NpcList names={it.sellTo} rashidCity={rashidCity} />
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

/** Cities to visit, fewest first, with the NPC and items for each stop. */
function SellRouteCard({ route }: { route: SellRoute }) {
  const { t, n } = useI18n();
  return (
    <section className="card route">
      <h2>{t('sell_route')}</h2>
      <p className="muted small">{t('sell_route_hint', { count: route.cities.length })}</p>
      <ol className="route-list">
        {route.cities.map((c) => (
          <li key={c.city}>
            <div className="route-city">
              <strong>{c.city === 'Yasir' ? t('yasir_ship') : c.city}</strong>
              <span className="route-value">{n(c.value)} gp</span>
            </div>
            <ul className="route-stops">
              {c.stops.map((s) => (
                <li key={s.npc}>
                  <span className="route-npc">
                    {s.npc}
                    {s.area && <span className="muted"> · {s.area}</span>}
                  </span>
                  <span className="route-items">
                    {s.items.map((i) => `${n(i.count)}× ${i.name}`).join(', ')}
                  </span>
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ol>
      {route.unrouted.length > 0 && (
        <p className="muted small">
          {t('route_unrouted')}: {route.unrouted.map((u) => u.name).join(', ')}
        </p>
      )}
    </section>
  );
}

const MAX_NPCS_SHOWN = 3;

/** Buyer NPCs with their city (TibiaWiki), e.g. "Fiona (Edron)"; long lists collapse to "+N". */
function NpcList({ names, rashidCity }: { names: string[]; rashidCity: string }) {
  const { t } = useI18n();
  if (names.length === 0) return <span className="muted">{t('no_npc_buyer')}</span>;
  const label = (name: string) => {
    if (name === 'Rashid') return `Rashid (${rashidCity})`;
    if (npcInfo(name)?.travelling) return name;
    const place = npcPlace(name);
    return place ? `${name} (${place})` : name;
  };
  const shown = names.slice(0, MAX_NPCS_SHOWN);
  const rest = names.length - shown.length;
  return (
    <span title={names.map(label).join('\n')}>
      {shown.map(label).join(', ')}
      {rest > 0 && <span className="muted"> {t('more_npcs', { count: rest })}</span>}
    </span>
  );
}

function Metric({ label, value, tone }: { label: string; value: string; tone?: 'pos' | 'neg' }) {
  return (
    <div className="metric">
      <div className="muted small">{label}</div>
      <div className={`metric-value ${tone ?? ''}`}>{value}</div>
    </div>
  );
}
