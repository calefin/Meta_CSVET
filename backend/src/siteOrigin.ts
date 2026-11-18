/**
 * Canonical site origin for links the API builds (OAuth callbacks, Stripe return URLs). D-62: a PUBLIC_URL left
 * as "http://localhost:3080" on the server (copied from a local .env) must never send real visitors to localhost:
 * when the configured origin is local but the request came through a public host, the request's own origin wins.
 */
const LOCAL = new Set(['localhost', '127.0.0.1', '::1', '[::1]']);

export const isLocalHost = (host: string): boolean => LOCAL.has(host.replace(/:\d+$/, '').toLowerCase()) || /\.localhost$/i.test(host.replace(/:\d+$/, ''));

export function siteOrigin(configured: string | null | undefined, req: { protocol: string; headers: { host?: string } }): string {
  const host = String(req.headers.host ?? '');
  const fromRequest = host ? `${req.protocol}://${host}` : '';
  const conf = (configured ?? '').trim().replace(/\/+$/, '');
  if (!conf) return fromRequest || 'https://bagparser.com';
  try {
    const parsed = new URL(conf);
    if (isLocalHost(parsed.hostname) && host && !isLocalHost(host)) return fromRequest;
    return conf;
  } catch {
    return fromRequest || conf;
  }
}

/** For the admin panel: PUBLIC_URL points to a local address while the site is served from a public host */
export function publicUrlWarning(configured: string | null | undefined, host: string | undefined): string | null {
  const conf = (configured ?? '').trim();
  if (!conf || !host || isLocalHost(host)) return null;
  try {
    return isLocalHost(new URL(conf).hostname) ? `PUBLIC_URL=${conf}` : null;
  } catch {
    return `PUBLIC_URL=${conf}`;
  }
}
