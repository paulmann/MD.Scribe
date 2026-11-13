/*
 * MD.Scribe service worker — offline reading, nothing else.
 *
 * Assets are cache-first: they carry a ?v=<mtime> stamp, so a rebuilt file gets
 * a new URL and the stale copy is never served.
 *
 * Documents and pages are network-first with the last cached copy as a fallback:
 * an updated document must be visible immediately, but a reader who loses the
 * network can still open what they already read.
 *
 * JSON APIs are never cached — a stale search result is worse than an error.
 */
const SHELL_CACHE = 'mdscribe-shell-v1';
const ASSET_CACHE = 'mdscribe-assets-v1';
const SHELL_URL = new URL('./', self.location).href;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(SHELL_CACHE)
      .then((cache) => cache.add(new Request(SHELL_URL, { cache: 'reload' })))
      .catch(() => undefined)
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== SHELL_CACHE && key !== ASSET_CACHE)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.searchParams.has('api')) return;

  if (url.pathname.includes('/assets/')) {
    event.respondWith(
      caches.match(request).then(
        (hit) =>
          hit ||
          fetch(request).then((response) => {
            if (response.ok) {
              const copy = response.clone();
              caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
            }
            return response;
          })
      )
    );
    return;
  }

  if (request.mode === 'navigate' || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => caches.match(request).then((hit) => hit || caches.match(SHELL_URL)))
    );
  }
});
