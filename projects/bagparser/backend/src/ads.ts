/**
 * Ads (D-66, decided in D-48): Google AdSense for visitors and free accounts only; Pro is ad-free. Two steps:
 *   1. ADSENSE_CLIENT=ca-pub-1234567890123456  → /ads.txt and the ownership meta tag (site verification by Google)
 *   2. ADSENSE_SLOT=1234567890 (ad unit created after approval) → ads actually show
 * Both live only in the server .env.
 */
export interface AdsConfig {
  client: string;
  /** null until an ad unit exists: verification only, no ads */
  slot: string | null;
}

export function adsConfig(env: NodeJS.ProcessEnv): AdsConfig | null {
  const client = env.ADSENSE_CLIENT?.trim() ?? '';
  const slot = env.ADSENSE_SLOT?.trim() ?? '';
  if (!/^ca-pub-\d{10,20}$/.test(client)) return null;
  return { client, slot: /^\d{5,20}$/.test(slot) ? slot : null };
}

/** What the browser needs to show ads (null = no ads yet) */
export const adsForBrowser = (cfg: AdsConfig | null): { client: string; slot: string } | null => (cfg?.slot ? { client: cfg.client, slot: cfg.slot } : null);

/** ads.txt line for Google (f08c47fec0942fa0 is Google's fixed certification id) */
export function adsTxt(cfg: AdsConfig | null): string | null {
  return cfg ? `google.com, ${cfg.client.replace(/^ca-/, '')}, DIRECT, f08c47fec0942fa0\n` : null;
}
