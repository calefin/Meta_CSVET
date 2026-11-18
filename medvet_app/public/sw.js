const CACHE_NAME = 'medvetlegal-admin-shell-v2';
const SHELL_ASSETS = [
    '/assets/icon-192.png',
    '/assets/icon-512.png',
    '/assets/icon-maskable-192.png',
    '/assets/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

// Painel exibe dados ao vivo (tickets/mensagens), então não fazemos cache de páginas:
// só garantimos que o ícone do app fique disponível para instalação/exibição offline do "shell".
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (SHELL_ASSETS.includes(url.pathname)) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request))
        );
    }
});
