/**
 * Ads (D-66, decided in D-48): one Google AdSense unit at the end of the content of public tool pages, for
 * visitors and free accounts only (Pro is ad-free). Nothing loads until the server has ADSENSE_* set; Google's
 * script is added only when a slot is actually shown. Always labelled "Publicidade" (Fansite Programme: ads
 * clearly identified, suitable for all ages — categories are blocked in the AdSense panel).
 */
import { useEffect, useRef, useState } from 'react';
import { useAuth } from '../auth';
import { useI18n } from '../i18n';
import { linkHandler } from '../router';

interface AdsConfig {
  client: string;
  slot: string;
}

let configPromise: Promise<AdsConfig | null> | null = null;
const loadConfig = () => {
  configPromise ??= fetch('/api/config')
    .then((r) => (r.ok ? r.json() : { ads: null }))
    .then((j: { ads?: AdsConfig | null; adsense_client?: string | null }) => {
      // Site ownership tag for AdSense (works before any ad unit exists)
      if (j.adsense_client && !document.querySelector('meta[name="google-adsense-account"]')) {
        const m = document.createElement('meta');
        m.name = 'google-adsense-account';
        m.content = j.adsense_client;
        document.head.appendChild(m);
      }
      return j.ads ?? null;
    })
    .catch(() => null);
  return configPromise;
};

let scriptAdded = false;
function addScript(client: string) {
  if (scriptAdded) return;
  scriptAdded = true;
  const s = document.createElement('script');
  s.async = true;
  s.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(client)}`;
  s.crossOrigin = 'anonymous';
  document.head.appendChild(s);
}

/** Pages with an ad at the end: public tools and lists. Never account, admin, payment, sign-in or hunt forms. */
export function adsAllowedOn(path: string): boolean {
  const p = path.split(/[?#]/)[0].replace(/\/+$/, '') || '/';
  if (p === '/') return true;
  return /^\/(parser|hunting|spawnrank|soulcores|quickhunts|bossledger|yasir|drops|news)(\/|$)/.test(p) || /^\/hunt-analyser\/(public|ranking)$/.test(p);
}

export function AdSlot({ path }: { path: string }) {
  const { t } = useI18n();
  const { user } = useAuth();
  const [cfg, setCfg] = useState<AdsConfig | null>(null);
  const ins = useRef<HTMLModElement>(null);
  // Pro (and admins) never see ads; while the session is loading nothing is decided yet
  const show = !!cfg && user !== undefined && !user?.premium && user?.role !== 'admin' && adsAllowedOn(path);

  useEffect(() => {
    void loadConfig().then(setCfg);
  }, []);

  useEffect(() => {
    if (!show || !cfg || !ins.current) return;
    addScript(cfg.client);
    try {
      ((window as unknown as { adsbygoogle?: unknown[] }).adsbygoogle ??= []).push({});
    } catch {
      /* the ad simply does not fill */
    }
  }, [show, cfg, path]);

  if (!show) return null;
  return (
    <aside className="ad-slot" aria-label={t('ad_label')}>
      <div className="ad-head">
        <span>{t('ad_label')}</span>
        <a href="/pricing" onClick={linkHandler('/pricing')}>
          {t('ad_remove')}
        </a>
      </div>
      <ins
        key={path}
        ref={ins}
        className="adsbygoogle"
        style={{ display: 'block' }}
        data-ad-client={cfg!.client}
        data-ad-slot={cfg!.slot}
        data-ad-format="auto"
        data-full-width-responsive="true"
      />
    </aside>
  );
}
