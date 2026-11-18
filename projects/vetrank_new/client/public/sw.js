const CACHE_NAME = "vetrank-cache-v2";

self.addEventListener("install", () => {
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(
        names.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (request.method !== "GET" || url.pathname.startsWith("/api/")) return;

  // Navegações — sempre rede, nunca cacheia index.html
  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request).catch(() => caches.match("/index.html"))
    );
    return;
  }

  // Assets Vite com content-hash — cache-first (imutáveis)
  if (url.pathname.startsWith("/assets/")) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          // Clonar SINCRONAMENTE antes de qualquer operação assíncrona
          const toCache = response.clone();
          if (response.ok) {
            caches.open(CACHE_NAME).then((cache) => cache.put(request, toCache));
          }
          return response;
        });
      })
    );
    return;
  }

  // Demais recursos estáticos — network-first
  event.respondWith(
    fetch(request)
      .then((response) => {
        // Clonar SINCRONAMENTE antes de qualquer operação assíncrona
        const toCache = response.clone();
        if (response.ok) {
          caches.open(CACHE_NAME).then((cache) => cache.put(request, toCache));
        }
        return response;
      })
      .catch(() => caches.match(request))
  );
});
