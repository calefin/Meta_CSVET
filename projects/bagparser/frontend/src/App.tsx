import { useI18n } from './i18n';
import { linkHandler, usePathname } from './router';
import { ErrorBoundary } from './components/ErrorBoundary';
import { VerifyEmailBar } from './components/VerifyEmailBar';
import { AdSlot } from './components/AdSlot';
import { ReportButton } from './components/ReportButton';
import { HomePage } from './pages/HomePage';
import { LoginPage } from './pages/LoginPage';
import { NotFoundPage } from './pages/NotFoundPage';
import { TermsBanner } from './components/TermsBanner';
import { PwaBar } from './components/PwaBar';
import { NavBar } from './components/NavBar';
import { useAuth } from './auth';
import { SITE } from './site';
import { trackPageView } from './api';
import { lazyPage } from './lazyPage';
import { Suspense, useEffect } from 'react';

// Everything except the home page and login loads on demand (D-49): the first visit downloads
// far less, and lazyPage() reloads once when a deploy removed the chunk an old tab still points to.
// The parser carries the 300 KB item catalog: loaded on demand too (precached by the service worker for offline use)
const ParserPage = lazyPage(() => import('./pages/ParserPage'), 'ParserPage');
const HuntAnalyserPage = lazyPage(() => import('./pages/hunt/HuntAnalyserPage'), 'HuntAnalyserPage');
const CharacterPage = lazyPage(() => import('./pages/CharacterPage'), 'CharacterPage');
const CharactersPage = lazyPage(() => import('./pages/CharactersPage'), 'CharactersPage');
const AdminPage = lazyPage(() => import('./pages/AdminPage'), 'AdminPage');
const SpawnRankPage = lazyPage(() => import('./pages/SpawnRankPage'), 'SpawnRankPage');
const BossLedgerPage = lazyPage(() => import('./pages/BossLedgerPage'), 'BossLedgerPage');
const QuickHuntsPage = lazyPage(() => import('./pages/QuickHuntsPage'), 'QuickHuntsPage');
const AccountPage = lazyPage(() => import('./pages/AccountPage'), 'AccountPage');
const ResetPage = lazyPage(() => import('./pages/ResetPage'), 'ResetPage');
const ForgotPage = lazyPage(() => import('./pages/ForgotPage'), 'ForgotPage');
const VerifyPage = lazyPage(() => import('./pages/VerifyPage'), 'VerifyPage');
const AboutPage = lazyPage(() => import('./pages/AboutPage'), 'AboutPage');
const LegalPage = lazyPage(() => import('./pages/LegalPage'), 'LegalPage');
const SupportPage = lazyPage(() => import('./pages/SupportPage'), 'SupportPage');
const PricingPage = lazyPage(() => import('./pages/PricingPage'), 'PricingPage');
const NewsPage = lazyPage(() => import('./pages/NewsPage'), 'NewsPage');
const YasirPage = lazyPage(() => import('./pages/YasirPage'), 'YasirPage');
const SoulCoresPage = lazyPage(() => import('./pages/SoulCoresPage'), 'SoulCoresPage');
const HuntingPlacesPage = lazyPage(() => import('./pages/HuntingPlacesPage'), 'HuntingPlacesPage');
const CreaturesPage = lazyPage(() => import('./pages/CreaturesPage'), 'CreaturesPage');
const DropFinderPage = lazyPage(() => import('./pages/DropFinderPage'), 'DropFinderPage');
const RetroPage = lazyPage(() => import('./pages/RetroPage'), 'RetroPage');
const PromoLink = lazyPage(() => import('./components/PromoBox'), 'PromoLink');

const TITLE_KEYS: [string, string][] = [
  ['/parser', 'nav_parser'],
  ['/drops', 'drop_title'],
  ['/hunt-analyser', 'hunt_tracker_title'],
  ['/h/', 'hunt_tracker_title'],
  ['/characters', 'nav_characters'],
  ['/hunting', 'nav_hunting'],
  ['/creatures', 'nav_creatures'],
  ['/spawnrank', 'nav_spawnrank'],
  ['/bossledger', 'nav_bossledger'],
  ['/quickhunts', 'nav_quickhunts'],
  ['/news', 'news_title'],
  ['/pricing', 'nav_pricing'],
  ['/support', 'nav_support'],
  ['/yasir', 'yasir_title'],
  ['/soulcores', 'soulcores_title'],
  ['/account', 'account'],
  ['/retro', 'retro_title'],
  ['/admin', 'admin_title'],
  ['/about', 'about_title'],
  ['/privacy', 'privacy_policy'],
  ['/terms', 'terms_of_use'],
  ['/login', 'login'],
  ['/register', 'register'],
  ['/', 'app_title'],
];

/** Old or alternative addresses that land on the current page */
const REDIRECTS: Record<string, string> = {
  '/hunt-tracker': '/hunt-analyser',
  '/hunts': '/hunt-analyser',
  '/hunt': '/hunt-analyser',
  '/analyser': '/hunt-analyser',
  '/loot': '/parser',
  '/character': '/characters',
  '/bosses': '/bossledger',
  '/tutorials': '/quickhunts',
  '/tutoriais': '/quickhunts',
  '/onde-dropar': '/drops',
  '/delivery': '/drops',
  '/drop': '/drops',
  '/signup': '/register',
  '/signin': '/login',
  '/premium': '/pricing',
  '/pro': '/pricing',
};

/** Path → page; null = 404 (unknown paths used to fall back to the home page silently) */
function route(path: string) {
  const seg = path.split('/').filter(Boolean);
  const first = seg[0] ?? '';
  const one = seg.length === 1;
  switch (first) {
    case '':
      return <HomePage />;
    case 'parser':
      return <ParserPage />;
    case 'hunt-analyser':
    case 'h':
      return <HuntAnalyserPage path={path} />;
    case 'characters':
      if (seg[1] && /^\d+$/.test(seg[1])) return <CharacterPage id={Number(seg[1])} />;
      return one ? <CharactersPage /> : null;
    case 'quickhunts':
      return <QuickHuntsPage slug={seg[1]} />;
    case 'hunting':
      return <HuntingPlacesPage slug={seg[1] ? decodeURIComponent(seg[1]) : undefined} />;
    case 'creatures':
      return <CreaturesPage slug={seg[1] ? decodeURIComponent(seg[1]) : undefined} />;
    case 'news':
      return <NewsPage />;
    case 'cupom':
    case 'promo':
      // D-78: share link that applies a trial code (bagparser.com/cupom/TIBIA30)
      return <PromoLink code={seg[1] ?? ''} />;
    case 'admin':
      return <AdminPage path={path} />;
  }
  if (!one) return null;
  switch (first) {
    case 'spawnrank':
      return <SpawnRankPage />;
    case 'bossledger':
      return <BossLedgerPage />;
    case 'login':
      return <LoginPage mode="login" />;
    case 'register':
      return <LoginPage mode="register" />;
    case 'account':
      return <AccountPage />;
    case 'retro':
      return <RetroPage />;
    case 'reset':
      return <ResetPage />;
    case 'forgot':
      return <ForgotPage />;
    case 'verify':
      return <VerifyPage />;
    case 'about':
      return <AboutPage />;
    case 'support':
      return <SupportPage />;
    case 'pricing':
      return <PricingPage />;
    case 'privacy':
      return <LegalPage kind="privacy" />;
    case 'terms':
      return <LegalPage kind="terms" />;
    case 'yasir':
      return <YasirPage />;
    case 'drops':
      return <DropFinderPage />;
    case 'soulcores':
      return <SoulCoresPage />;
  }
  return null;
}

/** "/Parser/" → "/parser"; repeated slashes collapsed (path segments that carry ids keep their case) */
function normalise(raw: string): string {
  let p = raw.replace(/\/{2,}/g, '/');
  if (p.length > 1) p = p.replace(/\/+$/, '');
  const [first, ...rest] = p.split('/').filter(Boolean);
  return first ? `/${[first.toLowerCase(), ...rest].join('/')}` : '/';
}

export function App() {
  const { t, locale } = useI18n();
  const rawPath = usePathname();
  const { user, accountKey } = useAuth();
  const path = normalise(rawPath);
  const redirect = REDIRECTS[path];

  // Fix the address bar for redirects and non-canonical paths (no extra history entry)
  useEffect(() => {
    if (redirect) {
      window.history.replaceState({}, '', redirect + window.location.search);
      window.dispatchEvent(new PopStateEvent('popstate'));
    } else if (path !== rawPath) {
      window.history.replaceState({}, '', path + window.location.search);
    }
  }, [path, rawPath, redirect]);

  // Anonymous page view for the monthly fansite traffic report (D-27)
  useEffect(() => {
    if (!redirect) trackPageView(path);
  }, [path, redirect]);

  // Tab title per route (D-36); the pre-rendered HTML already has it for crawlers
  useEffect(() => {
    const section = TITLE_KEYS.find(([p]) => (p === '/' ? path === '/' : path.startsWith(p)));
    document.title = section && section[0] !== '/' ? `${t(section[1])} | BagParser` : t('app_title');
  }, [path, t]);

  const page = redirect ? null : route(path);

  return (
    <div className="app">
      <NavBar path={path} />
      <PwaBar />
      <VerifyEmailBar path={path} />
      <ReportButton path={path} />
      <TermsBanner />
      <main className="content">
        <Suspense fallback={<p className="muted page-loading">{t('loading')}</p>}>
          <ErrorBoundary key={`${accountKey}|${path}`}>{redirect ? null : (page ?? <NotFoundPage path={path} />)}</ErrorBoundary>
          {!redirect && page && <AdSlot path={path} />}
        </Suspense>
      </main>
      <footer className="footer">
        <p>
          {locale.startsWith('pt') ? 'Criador & Jogador' : 'Creator & Player'}: <strong>{SITE.owner}</strong> ({SITE.contactEmail}) ·{' '}
          {t('credit_made_by')}{' '}
          <a href="https://www.cipsoft.com" target="_blank" rel="noopener noreferrer">
            CipSoft GmbH
          </a>
          . {t('footer_note')}{' '}
          <a href="https://www.tibia.com" target="_blank" rel="noopener noreferrer">
            tibia.com
          </a>
        </p>
        <p>
          <a href="/hunt-analyser" onClick={linkHandler('/hunt-analyser')}>
            🏹 {t('nav_hunt_tracker')}
          </a>
          {' · '}
          <a href="/news" onClick={linkHandler('/news')}>
            📰 {t('nav_news')}
          </a>
          {' · '}
          <a href="/pricing" onClick={linkHandler('/pricing')}>
            {t('nav_pricing')}
          </a>
          {' · '}
          <a href="/support" onClick={linkHandler('/support')}>
            {t('nav_support')}
          </a>
          {' · '}
          <a href="/about" onClick={linkHandler('/about')}>
            {t('footer_about')}
          </a>
          {' · '}
          <a href="/privacy" onClick={linkHandler('/privacy')}>
            {t('privacy_policy')}
          </a>
          {' · '}
          <a href="/terms" onClick={linkHandler('/terms')}>
            {t('terms_of_use')}
          </a>
          {' · '}
          <span className="muted">v{__APP_VERSION__}</span>
          {user?.role === 'admin' && (
            <>
              {' · '}
              <a href="/admin" onClick={linkHandler('/admin')}>
                Admin
              </a>
            </>
          )}
        </p>
      </footer>
    </div>
  );
}
