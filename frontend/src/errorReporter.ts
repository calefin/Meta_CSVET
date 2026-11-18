/**
 * Error reporting (D-62): uncaught errors, rejected promises and React crashes go to /api/errors so the admin
 * sees them grouped in /admin/errors. At most 10 reports per page load, each distinct error once; the path is
 * sent without its query string (the server strips it again). Nothing in development.
 */
const sent = new Set<string>();
let budget = 10;
const recent: string[] = [];

/** D-70: last errors of this visit, attached to "report a problem" */
export const recentErrors = (): string[] => [...recent];

export function reportError(message: string, stack?: string | null): void {
  if (message) {
    recent.push(message.slice(0, 200));
    if (recent.length > 3) recent.shift();
  }
  if (import.meta.env.DEV || !message || budget <= 0) return;
  const key = `${message}|${(stack ?? '').split('\n')[1] ?? ''}`;
  if (sent.has(key)) return;
  sent.add(key);
  budget--;
  try {
    void fetch('/api/errors', {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: message.slice(0, 500), stack: stack?.slice(0, 4000) ?? null, path: location.pathname, version: __APP_VERSION__ }),
    }).catch(() => undefined);
  } catch {
    /* never let reporting break the page */
  }
}

export function installErrorReporter(): void {
  window.addEventListener('error', (e) => {
    if (e.error instanceof Error) reportError(e.error.message || e.message, e.error.stack);
    else if (e.message) reportError(e.message, null);
  });
  window.addEventListener('unhandledrejection', (e) => {
    const r = e.reason as unknown;
    if (r instanceof Error) reportError(r.message, r.stack);
    else if (typeof r === 'string') reportError(r, null);
  });
}
