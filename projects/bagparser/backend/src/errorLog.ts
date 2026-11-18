/**
 * Error log (D-62): browser errors (window.onerror, rejected promises, React crashes) and API 5xx, grouped by
 * fingerprint so the admin sees "this error, N times, last at …" instead of a flood. No IP is stored and paths
 * lose their query string (reset tokens and similar never reach the log).
 */
import { createHash } from 'node:crypto';
import type pg from 'pg';

export type ErrorKind = 'client' | 'server';

export interface ErrorInput {
  kind: ErrorKind;
  message: string;
  stack?: string | null;
  path?: string | null;
  user_agent?: string | null;
  version?: string | null;
  user_id?: number | null;
}

/** "/hunt-analyser/h/abc?x=1#y" → "/hunt-analyser/h/abc"; anything odd → null */
export function cleanPath(v: unknown): string | null {
  if (typeof v !== 'string' || !v) return null;
  let p = v.trim();
  try {
    if (/^https?:\/\//i.test(p)) p = new URL(p).pathname;
  } catch {
    return null;
  }
  p = p.split(/[?#]/)[0];
  return p.startsWith('/') ? p.slice(0, 300) : null;
}

/** Bundle hashes, line/column numbers and ids change between builds or users: keep the shape of the error */
export function normalizeForFingerprint(s: string): string {
  return s
    .replace(/https?:\/\/[^\s)]+\/assets\//g, '/assets/')
    .replace(/-[A-Za-z0-9_]{8}\.js/g, '.js')
    .replace(/:\d+:\d+/g, '')
    .replace(/\b\d{3,}\b/g, 'N')
    .replace(/\s+/g, ' ')
    .trim();
}

export function errorFingerprint(e: Pick<ErrorInput, 'kind' | 'message' | 'stack'>): string {
  const firstFrame = (e.stack ?? '').split('\n').find((l) => /\bat\b|@/.test(l)) ?? '';
  return createHash('sha1')
    .update(`${e.kind}|${normalizeForFingerprint(e.message)}|${normalizeForFingerprint(firstFrame)}`)
    .digest('hex');
}

/** Browser noise that says nothing about our code */
export function isNoise(message: string, stack?: string | null): boolean {
  return (
    /ResizeObserver loop/i.test(message) ||
    /^Script error\.?$/i.test(message.trim()) ||
    /(chrome|moz|safari)-extension:\/\//i.test(`${message} ${stack ?? ''}`) ||
    /Failed to fetch|NetworkError|Load failed|network error/i.test(message) ||
    /AbortError/i.test(message)
  );
}

const clip = (v: unknown, n: number): string | null => (typeof v === 'string' && v.trim() ? v.trim().slice(0, n) : null);

/** Validates a browser report; null = ignore */
export function clientErrorInput(b: Record<string, unknown> | null | undefined, userId: number | null, userAgent: string | null): ErrorInput | null {
  const message = clip(b?.message, 500);
  if (!message) return null;
  const stack = clip(b?.stack, 4000);
  if (isNoise(message, stack)) return null;
  return {
    kind: 'client',
    message,
    stack,
    path: cleanPath(b?.path),
    user_agent: clip(userAgent, 300),
    version: clip(b?.version, 20),
    user_id: userId,
  };
}

/** Distinct errors kept at most (a flood of made-up messages stops here; repeats still count) */
export const MAX_ERROR_ROWS = 2000;

/** Upsert by fingerprint: count++, last seen, and an error that comes back after "resolved" is reopened */
export async function recordError(db: pg.Pool, e: ErrorInput): Promise<'new' | 'reopened' | 'repeat' | 'capped'> {
  if (e.kind === 'client') {
    const n = (await db.query<{ n: number }>('SELECT COUNT(*)::int AS n FROM app_errors')).rows[0].n;
    if (n >= MAX_ERROR_ROWS) {
      await db.query('UPDATE app_errors SET count = count + 1, last_seen = now() WHERE fingerprint = $1', [errorFingerprint(e)]);
      return 'capped';
    }
  }
  const r = await db.query<{ inserted: boolean; reopened: boolean | null }>(
    `WITH prev AS (SELECT resolved_at FROM app_errors WHERE fingerprint = $1)
     INSERT INTO app_errors (fingerprint, kind, message, stack, path, user_agent, version, last_user_id)
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
     ON CONFLICT (fingerprint) DO UPDATE SET
       count = app_errors.count + 1, last_seen = now(), path = EXCLUDED.path, user_agent = EXCLUDED.user_agent,
       version = EXCLUDED.version, last_user_id = COALESCE(EXCLUDED.last_user_id, app_errors.last_user_id),
       stack = COALESCE(EXCLUDED.stack, app_errors.stack), resolved_at = NULL
     RETURNING (xmax = 0) AS inserted, (SELECT resolved_at IS NOT NULL FROM prev) AS reopened`,
    [errorFingerprint(e), e.kind, e.message, e.stack ?? null, e.path ?? null, e.user_agent ?? null, e.version ?? null, e.user_id ?? null],
  );
  const row = r.rows[0];
  return row?.inserted ? 'new' : row?.reopened ? 'reopened' : 'repeat';
}
