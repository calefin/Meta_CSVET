/**
 * React.lazy for named page exports, with recovery for stale tabs (D-49).
 * After a deploy the old hashed chunks are gone: a tab opened before it fails with
 * "Failed to fetch dynamically imported module". We retry once, then reload the page a single time
 * (a sessionStorage flag prevents loops) so the new index.html and chunks are used.
 */
import { lazy, type ComponentType } from 'react';

const FLAG = 'bagparser_chunk_reload';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function lazyPage<M extends Record<string, any>>(load: () => Promise<M>, name: keyof M) {
  return lazy(async () => {
    try {
      const m = await load().catch(() => new Promise<M>((r) => setTimeout(r, 800)).then(load));
      try {
        sessionStorage.removeItem(FLAG);
      } catch {
        /* ignore */
      }
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      return { default: m[name] as ComponentType<any> };
    } catch (err) {
      let reloaded = false;
      try {
        reloaded = sessionStorage.getItem(FLAG) === '1';
        if (!reloaded) sessionStorage.setItem(FLAG, '1');
      } catch {
        reloaded = true;
      }
      if (!reloaded) {
        window.location.reload();
        return new Promise<never>(() => undefined);
      }
      throw err;
    }
  });
}
