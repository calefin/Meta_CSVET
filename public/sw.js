const CACHE_VERSION = 'profatilio-v2';
const STATIC_ASSETS = [
    '/images/pwa-icon-192.png',
    '/images/pwa-icon-512.png',
    '/images/logo.png',
    '/images/favicon.png',
    '/offline.html',
];

// Instala e pré-cacheia assets estáticos
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Remove caches antigas ao ativar nova versão
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignora requisições de terceiros (analytics, fonts CDN, etc.)
    if (url.origin !== self.location.origin) return;

    // Ignora webhooks e rotas de API que nunca devem ser cacheadas
    if (url.pathname.startsWith('/telegram/') || url.pathname.startsWith('/livewire/')) return;

    // Cache-first para assets estáticos buildados pelo Vite e imagens
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        caches.open(CACHE_VERSION).then((cache) => cache.put(request, response.clone()));
                    }
                    return response;
                }).catch(() => cached);
            })
        );
        return;
    }

    // Network-first para navegação HTML — fallback para offline.html
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Cacheia a página de admin/portal para acesso offline
                    if (response.ok && (url.pathname.startsWith('/admin') || url.pathname.startsWith('/portal'))) {
                        caches.open(CACHE_VERSION).then((cache) => cache.put(request, response.clone()));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(request).then((cached) => cached || caches.match('/offline.html'))
                )
        );
        return;
    }

    // Network-first padrão para demais requisições
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});

// Recebe push notifications da plataforma
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let data = {};
    try { data = event.data.json(); } catch { data = { title: 'profatilio', body: event.data.text() }; }

    event.waitUntil(
        self.registration.showNotification(data.title || 'profatilio', {
            body:    data.body    || '',
            icon:    data.icon    || '/images/pwa-icon-192.png',
            badge:   data.badge   || '/images/pwa-icon-192.png',
            data:    { url: data.url || '/portal' },
            vibrate: [200, 100, 200],
        })
    );
});

// Clique na notificação → abre a URL correspondente
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data?.url || '/portal';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if (client.url.includes(target) && 'focus' in client) return client.focus();
            }
            return clients.openWindow(target);
        })
    );
});
