/* BagParser service worker (PWA, D-21).
 * Bump VERSION to force old caches out.
 */
const VERSION = 'v8'; // v8: no personal API response cached; cleared on account change (D-74)
const VERSION_NOTE_V7 = 'v7'; // v5: drops TibiaWiki placeholders cached by v4 (D-49); v6: precaches every chunk (D-54)
const SHELL = `bagparser-shell-${VERSION}`;
const API = `bagparser-api-${VERSION}`;
const IMAGES = `bagparser-img-${VERSION}`;
const STATIC_FILES = ['/', '/manifest.webmanifest', '/favicon.svg', '/logo.svg', '/icon-192.png', '/icon-512.png'];

/** Asset URLs referenced by index.html (<script src>, <link href>) */
function assetsIn(html) {
  return [...html.matchAll(/(?:src|href)="(\/assets\/[^"]+)"/g)].map((m) => m[1]);
}

async function cacheShell() {
  const cache = await caches.open(SHELL);
  const res = await fetch('/', { cache: 'no-cache' });
  if (!res.ok) throw new Error(`shell HTTP ${res.status}`);
  const html = await res.clone().text();
  await cache.put('/', res);
  // Every JS/CSS chunk of this build (pages load on demand, D-54), plus whatever index.html references
  let listed = [];
  try {
    const list = await fetch('/asset-list.json', { cache: 'no-cache' });
    if (list.ok) listed = (await list.json()).files ?? [];
  } catch {
    /* older build without the list: index.html assets only */
  }
  const assets = [...new Set([...assetsIn(html), ...listed])];
  await cache.addAll([...STATIC_FILES.filter((f) => f !== '/'), ...assets]);
  // Drop assets of previous builds
  for (const req of await cache.keys()) {
    const path = new URL(req.url).pathname;
    if (path.startsWith('/assets/') && !assets.includes(path)) await cache.delete(req);
  }
}

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(cacheShell());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keep = new Set([SHELL, API, IMAGES]);
      for (const key of await caches.keys()) if (!keep.has(key)) await caches.delete(key);
      await self.clients.claim();
    })(),
  );
});

self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
  // D-74: another account signed in / out
  if (event.data === 'CLEAR_API') event.waitUntil(caches.delete(API));
});

async function networkFirst(request, cacheName, cacheKey) {
  const cache = await caches.open(cacheName);
  try {
    const res = await fetch(request);
    if (res.ok) cache.put(cacheKey ?? request, res.clone());
    return res;
  } catch (err) {
    const hit = await cache.match(cacheKey ?? request);
    if (hit) return hit;
    throw err;
  }
}

async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const hit = await cache.match(request);
  if (hit) return hit;
  const res = await fetch(request);
  if (res.ok || res.type === 'opaque') cache.put(request, res.clone());
  return res;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);

  if (url.origin === self.location.origin) {
    if (request.mode === 'navigate' && !url.pathname.startsWith('/api/')) {
      // Every SPA route is served by the same index.html. /api/ navigations (OAuth redirects, data export,
      // D-39) go straight to the network so they never overwrite the cached shell.
      event.respondWith(networkFirst(request, SHELL, '/'));
    } else if (url.pathname.startsWith('/assets/')) {
      event.respondWith(cacheFirst(request, SHELL));
    } else if (/^\/api\/(auth|admin|characters|yasir|hunts|hunt-templates|me|billing|builds|friends|payouts|notify|errors|promo|support|tickets|account)(\/|$)/.test(url.pathname)) {
      // Personal data: straight to the network, never stored
    } else if (url.pathname.startsWith('/api/')) {
      event.respondWith(networkFirst(request, API));
    } else if (url.pathname.startsWith('/bosses/') || (url.pathname.startsWith('/map/') && url.search)) {
      // Map tiles (D-61) carry ?v=<version>: a new import gives new URLs
      event.respondWith(cacheFirst(request, IMAGES));
    } else if (url.pathname.startsWith('/sprites/')) {
      // Item image index (D-35): network first so catalog updates arrive
      event.respondWith(networkFirst(request, SHELL));
    } else if (STATIC_FILES.includes(url.pathname)) {
      event.respondWith(cacheFirst(request, SHELL));
    }
    return;
  }

  // Creature images (CipSoft) and item images (TibiaWiki CDN, D-35): cache first
  if (url.hostname === 'static.tibia.com' || url.hostname === 'static.wikia.nocookie.net') {
    event.respondWith(cacheFirst(request, IMAGES));
  }
});
