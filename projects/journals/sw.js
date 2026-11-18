/*
 * Service worker da NVAS (journals.csvet.com.br): app instalável no celular.
 *
 * - Páginas: sempre da rede (o OJS é dinâmico e tem área logada); sem conexão,
 *   mostra /offline.html. Nenhuma página HTML é guardada em cache.
 * - Estilos: da rede (o CSS do tema não tem versão na URL; um deploy aparece na hora),
 *   com a cópia guardada usada só sem conexão.
 * - Fontes e imagens: cache com atualização em segundo plano (abre rápido).
 * - POST, API, downloads e outros domínios: passam direto, sem cache.
 *
 * Ao mudar este arquivo, troque VERSION para descartar o cache antigo.
 */
const VERSION = 'nvas-2026-11-14';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/plugins/themes/csvet/images/pwa/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.includes('/api/')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (request.destination === 'style') {
        event.respondWith(
            fetch(request, { cache: 'no-cache' })
                .then((response) => {
                    if (response.ok && response.type === 'basic') {
                        const copy = response.clone();
                        caches.open(VERSION).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    if (['font', 'image'].includes(request.destination)) {
        event.respondWith(
            caches.open(VERSION).then((cache) =>
                cache.match(request).then((cached) => {
                    const network = fetch(request)
                        .then((response) => {
                            if (response.ok && response.type === 'basic') {
                                cache.put(request, response.clone());
                            }
                            return response;
                        })
                        .catch(() => cached);
                    return cached || network;
                })
            )
        );
    }
});
