/*
 * Vitaliq offline portal (feature 58). Served at /sw.js so its scope is the
 * whole site.
 *  - Static assets: cache first, refreshed in the background.
 *  - Pages: network first. A copy of a few patient pages (overview,
 *    passport, appointments, tests) is kept so they can be read offline;
 *    other pages fall back to /offline.
 *  - Signing out sends "clear-pages", which deletes every stored page, so
 *    the next person on a shared device sees nothing.
 */
const VERSION = '__VERSION__';
const ASSETS = 'vq-assets-' + VERSION;
const PAGES = 'vq-pages';
const KEEP = [/\/patient$/, /\/patient\/passport$/, /\/patient\/appointments$/, /\/patient\/requests$/];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(ASSETS).then((c) => c.addAll(['offline'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('vq-assets-') && k !== ASSETS).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data === 'clear-pages') {
    event.waitUntil(caches.delete(PAGES));
  }
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  if (url.pathname.includes('/assets/')) {
    event.respondWith(
      caches.open(ASSETS).then(async (cache) => {
        const hit = await cache.match(req);
        const fresh = fetch(req).then((res) => {
          if (res.ok) cache.put(req, res.clone());
          return res;
        }).catch(() => hit);
        return hit || fresh;
      })
    );
    return;
  }

  if (req.mode === 'navigate') {
    const keep = KEEP.some((re) => re.test(url.pathname)) && url.search === '';
    event.respondWith(
      fetch(req).then((res) => {
        // Only pages that loaded for a signed-in person (no redirect) are kept.
        if (keep && res.ok && !res.redirected) {
          const copy = res.clone();
          caches.open(PAGES).then((c) => c.put(req, copy));
        }
        return res;
      }).catch(async () => {
        const page = keep ? await caches.match(req, { cacheName: PAGES }) : null;
        return page || (await caches.match('offline', { cacheName: ASSETS })) || new Response('Offline', { status: 503 });
      })
    );
  }
});
