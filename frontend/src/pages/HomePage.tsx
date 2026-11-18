/** Landing page (D-30): hero, "Today in Tibia" daily panel and tool cards. */
import { useCallback, useEffect, useState } from 'react';
import { api, ApiError, daily, YASIR_CITIES, type Boosted, type YasirCity, type YasirStatus } from '../api';
import { yasirCityName, yasirStatusText } from './YasirPage';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { rashidToday, weekdayName } from '../core/npcSchedule';
import { countdownParts, dreamScarBoss, dromeRotation, DREAM_BOSS_IMAGES } from '../core/daily';
import { ShareButtons } from '../components/ShareButtons';
import { ItemSearch } from '../components/ItemSearch';
import type { NewsArticle } from './NewsPage';
import { navigate } from '../router';
import { ItemSprite, useSprites } from '../components/ItemSprite';
import { CreatureImage } from '../components/CreatureImage';
import { wikiImage, type SpriteIndex } from '../core/sprites';

/** NPC sprite of the Rashid card (TibiaWiki, 64×64) */
const RASHID_SPRITE = wikiImage('https://static.wikia.nocookie.net/tibia/images/f/f5/Rashid.gif');

/** Decorative item per card (sprites from the item manifest, D-35) */
const CARD_ITEMS = {
  serverSave: 'mystical hourglass',
  yasir: 'treasure map',
  drome: 'golden fafnar trophy',
  // Dream Scar bosses are not boostable, so CipSoft has no creature image for them
  dreamScar: 'unrealized dream',
} as const;

const hideOnError = (e: React.SyntheticEvent<HTMLImageElement>) => (e.currentTarget.style.display = 'none');

/** D-83: the newest post from /news, so the landing page never goes out of date */
function LatestNews() {
  const { t, locale } = useI18n();
  const [article, setArticle] = useState<NewsArticle | null>(null);
  useEffect(() => {
    let alive = true;
    void import('./NewsPage').then((m) => {
      if (alive) setArticle(m.NEWS_ARTICLES[0] ?? null);
    });
    return () => {
      alive = false;
    };
  }, []);
  if (!article) return null;
  const lang = locale.split('-')[0];
  return (
    <a className="card home-news" href="/news" onClick={linkHandler('/news')}>
      <span className="muted small">
        📰 {t('news_title')} · {new Date(`${article.date}T12:00:00`).toLocaleDateString(locale, { dateStyle: 'long' })}
      </span>
      <strong>{article.title[lang] || article.title.en}</strong>
      <span className="muted small">{article.excerpt[lang] || article.excerpt.en}</span>
    </a>
  );
}

const WORLD_KEY = 'bagparser_world';

function readWorld(): string {
  try {
    return localStorage.getItem(WORLD_KEY) ?? '';
  } catch {
    return '';
  }
}

/** Next server save (10:00 Europe/Berlin) in ms */
function nextServerSave(now = Date.now()): number {
  const day = rashidToday(new Date(now)).serverDay; // UTC midnight of the current server day
  // server save of the next day happens at 10:00 Berlin = 08:00/09:00 UTC; search the exact minute
  let t = day.getTime() + 86_400_000 + 7 * 3_600_000;
  while (rashidToday(new Date(t)).serverDay.getTime() === day.getTime()) t += 60_000;
  return t;
}

function useNow(intervalMs = 30_000): number {
  const [now, setNow] = useState(Date.now());
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), intervalMs);
    return () => clearInterval(id);
  }, [intervalMs]);
  return now;
}

function Countdown({ to, now }: { to: number; now: number }) {
  const { t } = useI18n();
  const c = countdownParts(to - now);
  return <>{c.d > 0 ? t('countdown_dh', { d: c.d, h: c.h }) : t('countdown_hm', { h: c.h, m: c.m })}</>;
}

function YasirCard({ world, sprites, onChangeWorld }: { world: string; sprites: SpriteIndex | null; onChangeWorld: () => void }) {
  const { t } = useI18n();
  const { user } = useAuth();
  const [data, setData] = useState<{ status: YasirStatus; city: YasirCity | null; reports: number } | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [picking, setPicking] = useState(false);

  const load = useCallback(() => {
    if (!world) return;
    daily
      .yasir(world)
      .then(setData)
      .catch(() => setData(null));
  }, [world]);

  useEffect(load, [load]);

  const vote = async (present: boolean, city?: YasirCity) => {
    setMsg(null);
    setPicking(false);
    try {
      await daily.reportYasir(world, present, city);
      setMsg(t('yasir_thanks_world', { world }));
      load();
    } catch (err) {
      setMsg(err instanceof ApiError && err.status === 401 ? t('yasir_login') : t('api_error'));
    }
  };

  return (
    <div className="today-card">
      <span className="today-label">
        Yasir · {world || '—'}
        {world && (
          <button type="button" className="yasir-change small" onClick={onChangeWorld}>
            {t('yasir_change_world')}
          </button>
        )}
      </span>
      <span className="today-with-img">
        <ItemSprite name={CARD_ITEMS.yasir} sprites={sprites} size={32} />
        <strong className={data?.status === 'confirmed' ? 'pos' : ''}>
          {world ? yasirStatusText(t, data?.status ?? 'none', data?.city ?? null) : t('pick_world')}
        </strong>
      </span>
      {world && (
        <div className="today-actions">
          {!user ? (
            <a href="/login" onClick={linkHandler('/login')} className="small">
              {t('yasir_login')}
            </a>
          ) : picking ? (
            <>
              <span className="muted small yasir-where">{t('yasir_where')}</span>
              {YASIR_CITIES.map((c) => (
                <button key={c} type="button" className="secondary small-btn" onClick={() => vote(true, c)}>
                  {yasirCityName(t, c)}
                </button>
              ))}
            </>
          ) : (
            <>
              <span className="muted small yasir-where">{t('yasir_step_seen', { world })}</span>
              <button type="button" className="secondary small-btn" onClick={() => setPicking(true)}>
                {t('yasir_vote_yes')}
              </button>
              <button type="button" className="secondary small-btn" onClick={() => vote(false)}>
                {t('yasir_vote_no')}
              </button>
            </>
          )}
        </div>
      )}
      {msg && <span className="muted small">{msg}</span>}
      <a href="/yasir" onClick={linkHandler('/yasir')} className="small">
        {t('yasir_all_worlds')}
      </a>
    </div>
  );
}

export function HomePage() {
  const { t, locale } = useI18n();
  const now = useNow();
  const [boosted, setBoosted] = useState<Boosted | null>(null);
  const [worlds, setWorlds] = useState<string[]>([]);
  const [world, setWorld] = useState(readWorld);
  const sprites = useSprites();

  const [boostedState, setBoostedState] = useState<'loading' | 'ok' | 'error'>('loading');

  // Daily data with retries: a slow TibiaData / a cold API start used to leave the panel empty for the visit
  useEffect(() => {
    let alive = true;
    const attempt = <T,>(load: () => Promise<T>, done: (v: T) => void, fail?: () => void, tries = 3) => {
      load()
        .then((v) => alive && done(v))
        .catch(() => {
          if (!alive) return;
          if (tries > 1) setTimeout(() => attempt(load, done, fail, tries - 1), (4 - tries) * 3000);
          else fail?.();
        });
    };
    attempt(
      api.boosted,
      (b) => {
        setBoosted(b);
        setBoostedState(b.creature || b.boss ? 'ok' : 'error');
      },
      () => setBoostedState('error'),
    );
    attempt(daily.worlds, setWorlds);
    return () => {
      alive = false;
    };
  }, []);

  const chooseWorld = (w: string) => {
    setWorld(w);
    try {
      localStorage.setItem(WORLD_KEY, w);
    } catch {
      /* ignore */
    }
  };

  const rashid = rashidToday(new Date(now));
  const tomorrow = rashidToday(new Date(nextServerSave(now) + 60_000));
  const drome = dromeRotation(now);
  // D-66: the Dream Scar boss is the same on every world (no per-world offset)
  const dream = dreamScarBoss(0, now);
  const save = nextServerSave(now);

  return (
    <div className="home">
      <section className="hero">
        <div className="hero-text">
          <h1>{t('hero_title')}</h1>
          <p className="hero-sub">{t('hero_sub')}</p>
          <div className="hero-cta">
            <a className="button big" href="/hunt-analyser" onClick={linkHandler('/hunt-analyser')}>
              {t('home_ha_cta')}
            </a>
            <a className="button big secondary" href="/characters" onClick={linkHandler('/characters')}>
              {t('hero_cta_chars')}
            </a>
          </div>
          <p className="muted small">{t('hero_note')}</p>
        </div>
        <img className="hero-mark" src="/logo.svg" alt="" width={220} height={220} />
      </section>

      <LatestNews />

      {/* Rebranding: the four pillars of the site, right under the hero */}
      <nav className="hero-pillars" aria-label={t('tools_title')}>
        {[
          { href: '/hunt-analyser', icon: '🏹', title: t('home_ha_title'), text: t('hero_p_ha'), isNew: true },
          { href: '/characters', icon: '📈', title: t('nav_characters'), text: t('hero_p_chars') },
          { href: '/parser', icon: '💰', title: t('tool_parser'), text: t('hero_p_parser') },
          { href: '/hunting', icon: '🗺️', title: t('hero_p_more_title'), text: t('hero_p_more') },
        ].map((p) => (
          <a key={p.href} className="card hero-pillar" href={p.href} onClick={linkHandler(p.href)}>
            <span className="hero-pillar-icon" aria-hidden="true">
              {p.icon}
            </span>
            <strong>
              {p.title} {p.isNew && <span className="nav2-badge">{t('nav_badge_new')}</span>}
            </strong>
            <span className="muted small">{p.text}</span>
          </a>
        ))}
      </nav>

      {/* D-58: quick lookup — which creature drops the item a delivery task asks for */}
      <section className="card home-drops">
        <div className="home-drops-text">
          <strong>🎯 {t('drop_title')}</strong>
          <span className="muted small">{t('drop_home_text')}</span>
        </div>
        <ItemSearch onPick={(item) => navigate(`/drops?item=${encodeURIComponent(item)}`)} placeholder={t('drop_home_ph')} />
      </section>

      <section className="today">
        <div className="today-head">
          <h2>{t('today_title')}</h2>
          <label className="world-pick">
            <span className="muted small">{t('your_world')}</span>
            <select value={world} onChange={(e) => chooseWorld(e.target.value)}>
              <option value="">{t('pick_world')}</option>
              {worlds.map((w) => (
                <option key={w} value={w}>
                  {w}
                </option>
              ))}
            </select>
          </label>
        </div>

        <div className="today-grid">
          <div className="today-card">
            <span className="today-label">{t('server_save_in')}</span>
            <span className="today-with-img">
              <ItemSprite name={CARD_ITEMS.serverSave} sprites={sprites} size={32} />
              <strong>
                <Countdown to={save} now={now} />
              </strong>
            </span>
            <span className="muted small">
              {new Date(save).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })}
            </span>
          </div>

          <div className="today-card">
            <span className="today-label">Rashid · {weekdayName(rashid.serverDay, locale)}</span>
            <span className="today-with-img">
              <CreatureImage name="Rashid" url={RASHID_SPRITE} size={40} />
              <strong>{rashid.city}</strong>
            </span>
            <span className="muted small">{t('tomorrow_in', { city: tomorrow.city })}</span>
          </div>

          <YasirCard
            world={world}
            sprites={sprites}
            onChangeWorld={() => {
              const el = document.querySelector<HTMLSelectElement>('.world-pick select');
              el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
              el?.focus();
            }}
          />

          {!boosted?.creature && !boosted?.boss && (
            <div className="today-card" aria-busy={boostedState === 'loading'}>
              <span className="today-label">{t('boosted_creature')}</span>
              <strong className="muted">{boostedState === 'loading' ? t('loading') : t('daily_unavailable')}</strong>
            </div>
          )}

          {boosted?.creature && (
            <a className="today-card link" href="/spawnrank" onClick={linkHandler('/spawnrank')}>
              <span className="today-label">{t('boosted_creature')}</span>
              <span className="today-with-img">
                {boosted.creature.image_url && <img src={boosted.creature.image_url} alt="" width={40} height={40} onError={hideOnError} />}
                <strong>{boosted.creature.name}</strong>
              </span>
            </a>
          )}

          {boosted?.boss && (
            <a className="today-card link" href="/bossledger" onClick={linkHandler('/bossledger')}>
              <span className="today-label">{t('boosted_boss')}</span>
              <span className="today-with-img">
                {boosted.boss.image_url && <img src={boosted.boss.image_url} alt="" width={40} height={40} onError={hideOnError} />}
                <strong>{boosted.boss.name}</strong>
              </span>
            </a>
          )}

          <div className="today-card">
            <span className="today-label">{t('dream_scar_boss')}</span>
            <span className="today-with-img">
              <img
                src={DREAM_BOSS_IMAGES[dream]}
                alt={dream}
                width={36}
                height={36}
                style={{ objectFit: 'contain' }}
                referrerPolicy="no-referrer"
                onError={(e) => {
                  e.currentTarget.style.display = 'none';
                }}
              />
              <strong>{dream}</strong>
            </span>
            <span className="muted small">{t('dream_all_worlds')}</span>
          </div>

          <div className="today-card">
            <span className="today-label">{t('drome_ends')}</span>
            <span className="today-with-img">
              <ItemSprite name={CARD_ITEMS.drome} sprites={sprites} size={32} />
              <strong>
                <Countdown to={drome.endsAt} now={now} />
              </strong>
            </span>
            <span className="muted small">
              {t('drome_round', { n: drome.number })} ·{' '}
              {new Date(drome.endsAt).toLocaleString(locale, { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
            </span>
          </div>
        </div>
        <p className="muted small">{t('today_sources')}</p>
      </section>

      <section className="tools">
        <h2>{t('tools_title')}</h2>
        <div className="tools-grid">
          {[
            { href: '/parser', icon: '💰', title: t('tool_parser'), text: t('tool_parser_text') },
            { href: '/hunt-analyser', icon: '🏹', title: t('hunt_tracker_title'), text: t('tool_hunt_tracker_text') },
            { href: '/characters', icon: '🛡️', title: t('nav_characters'), text: t('tool_chars_text') },
            { href: '/drops', icon: '🎯', title: t('drop_title'), text: t('drop_home_text') },
            { href: '/hunting', icon: '🗺️', title: t('hunt_title'), text: t('tool_hunting_text') },
            { href: '/creatures', icon: '👾', title: t('nav_creatures'), text: t('tool_creatures_text') },
            { href: '/spawnrank', icon: '🐉', title: 'SpawnRank', text: t('tool_spawnrank_text') },
            { href: '/bossledger', icon: '👑', title: 'BossLedger', text: t('tool_bossledger_text') },
            { href: '/news', icon: '📰', title: t('news_title'), text: t('tool_news_text') },
            { href: '/quickhunts', icon: '📖', title: 'QuickHunts', text: t('tool_quickhunts_text') },
            { href: '/soulcores', icon: '🔮', title: t('soulcores_title'), text: t('tool_soulcores_text') },
            { href: '/yasir', icon: '⛵', title: t('yasir_title'), text: t('tool_yasir_text') },
          ].map((c) => (
            <a key={c.href} className="card tool-card" href={c.href} onClick={linkHandler(c.href)}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                <span style={{ fontSize: '1.25rem' }} aria-hidden="true">{c.icon}</span>
                <strong>{c.title}</strong>
              </div>
              <span className="muted small">{c.text}</span>
              <span className="tool-go" aria-hidden="true">
                →
              </span>
            </a>
          ))}
        </div>
      </section>

      <section className="card share-card">
        <p>{t('share_site_text')}</p>
        <ShareButtons text={t('share_site_message')} />
      </section>
    </div>
  );
}
