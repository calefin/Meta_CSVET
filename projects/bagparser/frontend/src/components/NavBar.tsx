/**
 * Navigation v2 (D-49): grouped menus instead of a long scrolling row.
 * Desktop: Hunt Analyser as the main call to action + three dropdown groups with a short description per tool.
 * Mobile / installed app: compact top bar, bottom tab bar (thumb reach) and a full-height menu sheet.
 */
import { useEffect, useRef, useState } from 'react';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';
import { useAuth } from '../auth';
import { admin, type User } from '../api';
import { promptInstall, usePwa } from '../pwa';
import { LanguageSelector } from './LanguageSelector';

interface NavItem {
  path: string;
  key: string;
  desc: string;
  icon: string;
  badge?: string;
}

interface NavGroup {
  id: string;
  key: string;
  items: NavItem[];
}

export const NAV_GROUPS: NavGroup[] = [
  {
    id: 'hunts',
    key: 'navg_hunts',
    items: [
      { path: '/hunt-analyser', key: 'nav_hunt_tracker', desc: 'navd_hunt_analyser', icon: '🏹', badge: 'new' },
      { path: '/parser', key: 'nav_parser', desc: 'navd_parser', icon: '💰' },
      { path: '/drops', key: 'nav_drops', desc: 'navd_drops', icon: '🎯' },
      { path: '/hunting', key: 'nav_hunting', desc: 'navd_hunting', icon: '🗺️' },
      { path: '/creatures', key: 'nav_creatures', desc: 'navd_creatures', icon: '👾' },
      { path: '/spawnrank', key: 'nav_spawnrank', desc: 'navd_spawnrank', icon: '🐉' },
      { path: '/quickhunts', key: 'nav_quickhunts', desc: 'navd_quickhunts', icon: '📖' },
    ],
  },
  {
    id: 'chars',
    key: 'navg_chars',
    items: [
      { path: '/characters', key: 'nav_characters', desc: 'navd_characters', icon: '🛡️' },
      { path: '/hunt-analyser/ranking', key: 'nav_hunt_ranking', desc: 'navd_hunt_ranking', icon: '🏆' },
      { path: '/soulcores', key: 'soulcores_title', desc: 'navd_soulcores', icon: '🔮' },
    ],
  },
  {
    id: 'world',
    key: 'navg_world',
    items: [
      { path: '/bossledger', key: 'nav_bossledger', desc: 'navd_bossledger', icon: '👑' },
      { path: '/yasir', key: 'yasir_title', desc: 'navd_yasir', icon: '⛵' },
      { path: '/news', key: 'nav_news', desc: 'navd_news', icon: '📰' },
    ],
  },
];

const BOTTOM_TABS: { path: string; key: string; icon: string }[] = [
  { path: '/', key: 'nav_home', icon: '🏠' },
  { path: '/hunt-analyser', key: 'nav_tab_hunts', icon: '🏹' },
  { path: '/parser', key: 'nav_tab_loot', icon: '💰' },
  { path: '/characters', key: 'nav_tab_chars', icon: '🛡️' },
];

/** Most specific match wins, so /hunt-analyser/ranking does not also light up /hunt-analyser. */
function activePath(path: string): string | null {
  const all = [...NAV_GROUPS.flatMap((g) => g.items.map((i) => i.path)), '/pricing', '/account', '/login', '/admin'];
  return all.filter((p) => path === p || path.startsWith(p + '/')).sort((a, b) => b.length - a.length)[0] ?? null;
}

export function NavBar({ path }: { path: string }) {
  const { t } = useI18n();
  const { user } = useAuth();
  // D-62: admins see a shortcut to the dashboard with open tickets + errors
  const [adminBadge, setAdminBadge] = useState(0);
  useEffect(() => {
    if (user?.role !== 'admin') return;
    admin
      .overview()
      .then((o) => setAdminBadge(o.tickets.pending + o.tickets.open + o.errors.open))
      .catch(() => null);
  }, [user, path]);
  const { canInstall } = usePwa();
  const [openGroup, setOpenGroup] = useState<string | null>(null);
  const [sheet, setSheet] = useState(false);
  const navRef = useRef<HTMLElement>(null);
  const active = activePath(path);

  // Close menus on navigation, outside click and Escape
  useEffect(() => {
    setOpenGroup(null);
    setSheet(false);
  }, [path]);
  useEffect(() => {
    const onDown = (e: MouseEvent) => {
      if (navRef.current && !navRef.current.contains(e.target as Node)) setOpenGroup(null);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setOpenGroup(null);
        setSheet(false);
      }
    };
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onKey);
    };
  }, []);
  useEffect(() => {
    document.body.classList.toggle('sheet-open', sheet);
  }, [sheet]);

  const accountPath = user ? '/account' : '/login';
  const groupActive = (g: NavGroup) => g.items.some((i) => i.path === active);
  const showPro = user !== undefined && !user?.premium;

  const itemLink = (i: NavItem, compact = false) => (
    <a
      key={i.path}
      href={i.path}
      className={`nav2-item${active === i.path ? ' active' : ''}`}
      onClick={linkHandler(i.path)}
      aria-current={active === i.path ? 'page' : undefined}
    >
      <span className="nav2-item-icon" aria-hidden="true">
        {i.icon}
      </span>
      <span className="nav2-item-text">
        <strong>
          {t(i.key)}
          {i.badge && <span className="nav2-badge">{t('nav_badge_new')}</span>}
        </strong>
        {!compact && <span className="muted small">{t(i.desc)}</span>}
      </span>
    </a>
  );

  return (
    <>
      <header className="topbar2">
        <a href="/" className="brand" onClick={linkHandler('/')}>
          <img src="/logo.svg" alt="" width={32} height={32} />
          <span className="brand-word">
            <b>Bag</b>Parser
          </span>
        </a>

        <nav className="nav2" ref={navRef} aria-label={t('nav_main')}>
          <a
            href="/hunt-analyser"
            className={`nav2-cta${active === '/hunt-analyser' ? ' active' : ''}`}
            onClick={linkHandler('/hunt-analyser')}
          >
            <span aria-hidden="true">🏹</span> {t('nav_hunt_tracker')}
          </a>
          <a href="/parser" className={`nav2-link${active === '/parser' ? ' active' : ''}`} onClick={linkHandler('/parser')}>
            {t('nav_parser')}
          </a>
          {NAV_GROUPS.map((g) => (
            <div key={g.id} className={`nav2-group${openGroup === g.id ? ' open' : ''}`}>
              <button
                type="button"
                className={`nav2-link${groupActive(g) ? ' active' : ''}`}
                aria-expanded={openGroup === g.id}
                aria-controls={`nav2-menu-${g.id}`}
                onClick={() => setOpenGroup((o) => (o === g.id ? null : g.id))}
              >
                {t(g.key)} <span className="nav2-caret" aria-hidden="true">▾</span>
              </button>
              <div className="nav2-menu" id={`nav2-menu-${g.id}`} role="menu">
                {g.items.map((i) => itemLink(i))}
              </div>
            </div>
          ))}
        </nav>

        <div className="nav2-right">
          {canInstall && (
            <button type="button" className="install-btn nav2-desktop" onClick={promptInstall}>
              {t('install_app')}
            </button>
          )}
          {showPro && (
            <a href="/pricing" className="nav2-pro" onClick={linkHandler('/pricing')}>
              ⭐ Pro
            </a>
          )}
          {user?.role === 'admin' && (
            <a href="/admin" className={`nav2-admin${active === '/admin' ? ' active' : ''}`} onClick={linkHandler('/admin')} title="Painel do admin">
              🛡️ <span className="nav2-admin-label">Admin</span>
              {adminBadge > 0 && <span className="adm-badge">{adminBadge}</span>}
            </a>
          )}
          {user !== undefined && (
            <a
              href={accountPath}
              className={`account-link${active === accountPath ? ' active' : ''}`}
              onClick={linkHandler(accountPath)}
            >
              {user ? (
                <>
                  <span className="avatar" aria-hidden="true">
                    {user.display_name.slice(0, 1).toUpperCase()}
                  </span>
                  <span className="account-name">{user.display_name}</span>
                  <PlanBadge user={user} />
                </>
              ) : (
                t('login')
              )}
            </a>
          )}
          <span className="nav2-desktop">
            <LanguageSelector />
          </span>
        </div>
      </header>

      {/* Mobile: bottom tab bar */}
      <nav className="bottombar" aria-label={t('nav_main')}>
        {BOTTOM_TABS.map((tab) => {
          const on = tab.path === '/' ? path === '/' : active === tab.path || (tab.path === '/hunt-analyser' && !!active?.startsWith('/hunt-analyser'));
          return (
            <a key={tab.path} href={tab.path} className={on ? 'active' : ''} onClick={linkHandler(tab.path)} aria-current={on ? 'page' : undefined}>
              <span aria-hidden="true">{tab.icon}</span>
              <small>{t(tab.key)}</small>
            </a>
          );
        })}
        <button type="button" className={sheet ? 'active' : ''} aria-expanded={sheet} onClick={() => setSheet((s) => !s)}>
          <span aria-hidden="true">☰</span>
          <small>{t('nav_tab_menu')}</small>
        </button>
      </nav>

      {sheet && (
        <div className="nav-sheet" role="dialog" aria-modal="true" aria-label={t('nav_main')}>
          <div className="nav-sheet-head">
            <strong>{t('nav_tab_menu')}</strong>
            <LanguageSelector />
            <button type="button" className="button secondary" onClick={() => setSheet(false)} aria-label={t('close')}>
              ✕
            </button>
          </div>
          {NAV_GROUPS.map((g) => (
            <section key={g.id}>
              <h3>{t(g.key)}</h3>
              <div className="nav-sheet-grid">{g.items.map((i) => itemLink(i, true))}</div>
            </section>
          ))}
          <section>
            <h3>{t('navg_more')}</h3>
            <div className="nav-sheet-grid">
              {[
                { path: '/pricing', key: 'nav_pricing', desc: '', icon: '⭐' },
                { path: '/support', key: 'nav_support', desc: '', icon: '💬' },
                { path: '/about', key: 'footer_about', desc: '', icon: 'ℹ️' },
              ].map((i) => itemLink(i, true))}
              {canInstall && (
                <button type="button" className="nav2-item" onClick={promptInstall}>
                  <span className="nav2-item-icon" aria-hidden="true">
                    📲
                  </span>
                  <span className="nav2-item-text">
                    <strong>{t('install_app')}</strong>
                  </span>
                </button>
              )}
            </div>
          </section>
        </div>
      )}
    </>
  );
}

/** D-74: plan of the signed-in account, always visible in the header (also on the phone) */
function PlanBadge({ user }: { user: User }) {
  const { t } = useI18n();
  const tier = user.tier ?? (user.premium ? 'pro' : 'free');
  const plan = tier === 'admin' ? 'admin' : tier === 'guild' ? 'guild' : user.premium || tier === 'pro' ? 'pro' : 'free';
  const label = t(`plan_badge_${plan}`);
  return (
    <span className={`plan-badge plan-${plan}`} title={t('plan_badge_title', { plan: label })}>
      {label}
    </span>
  );
}
