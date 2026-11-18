/**
 * Hunt Analyser (D-49) — routes:
 *   /hunt-analyser              analyse a paste / files and save it
 *   /hunt-analyser/mine         my hunts, charts and comparisons
 *   /hunt-analyser/public       public hunts from the community
 *   /hunt-analyser/payments     settle several party hunts at once (D-60)
 *   /hunt-analyser/ranking      best sessions and community place statistics
 *   /hunt-analyser/compare/A/B  side by side
 *   /hunt-analyser/character/N  one character's performance (D-61)
 *   /hunt-analyser/h/:id, /h/:id  one hunt (share link)
 */
import { useI18n } from '../../i18n';
import { linkHandler } from '../../router';
import { AnalyseTab } from './AnalyseTab';
import { MyHuntsTab } from './MyHuntsTab';
import { PublicFeedTab, RankingTab } from './PublicTabs';
import { CompareView, HuntDetailPage } from './HuntDetail';
import { SetupTab } from './SetupTab';
import { PaymentsTab } from './PaymentsTab';
import { CharacterPerformance } from './CharacterPerformance';

const TABS = [
  { path: '/hunt-analyser', key: 'ha_tab_analyse', icon: '🔍' },
  { path: '/hunt-analyser/mine', key: 'ha_tab_mine', icon: '📈' },
  { path: '/hunt-analyser/payments', key: 'ha_tab_payments', icon: '💸' },
  { path: '/hunt-analyser/public', key: 'ha_tab_public', icon: '🌐' },
  { path: '/hunt-analyser/ranking', key: 'ha_tab_ranking', icon: '🏆' },
  { path: '/hunt-analyser/setup', key: 'ha_tab_setup', icon: '⚙️' },
];

const safeDecode = (s: string) => {
  try {
    return decodeURIComponent(s);
  } catch {
    return s;
  }
};

export function HuntAnalyserPage({ path }: { path: string }) {
  const { t } = useI18n();
  const parts = path.split('/').filter(Boolean); // ['hunt-analyser', ...] or ['h', id]

  if (parts[0] === 'h' && parts[1]) return <HuntDetailPage id={parts[1]} />;
  if (parts[1] === 'h' && parts[2]) return <HuntDetailPage id={parts[2]} />;

  const sub = parts[1] ?? '';
  let body;
  if (sub === 'compare' && parts[2] && parts[3]) body = <CompareView a={parts[2]} b={parts[3]} />;
  else if (sub === 'character' && parts[2]) body = <CharacterPerformance name={safeDecode(parts[2])} />;
  else if (sub === 'mine') body = <MyHuntsTab />;
  else if (sub === 'public') body = <PublicFeedTab />;
  else if (sub === 'ranking') body = <RankingTab />;
  else if (sub === 'setup') body = <SetupTab />;
  else if (sub === 'payments') body = <PaymentsTab />;
  else body = <AnalyseTab />;

  const current = sub === 'compare' || sub === 'character' ? '/hunt-analyser/mine' : `/hunt-analyser${sub && ['mine', 'payments', 'public', 'ranking', 'setup'].includes(sub) ? `/${sub}` : ''}`;

  return (
    <div className="page ha-page">
      <header className="ha-head">
        <div>
          <h1>🏹 {t('hunt_tracker_title')}</h1>
          <p className="muted">{t('ha_subtitle')}</p>
        </div>
      </header>
      <nav className="tabs" aria-label={t('hunt_tracker_title')}>
        {TABS.map((tab) => (
          <a
            key={tab.path}
            href={tab.path}
            className={current === tab.path ? 'tab active' : 'tab'}
            aria-current={current === tab.path ? 'page' : undefined}
            onClick={linkHandler(tab.path)}
          >
            <span aria-hidden="true">{tab.icon}</span> {t(tab.key)}
          </a>
        ))}
      </nav>
      {body}
    </div>
  );
}
