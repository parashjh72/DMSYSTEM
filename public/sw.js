/*
 * DM System service worker — makes the app installable and shows an offline
 * page when there is no connection. Pages and data are never cached: every
 * request goes to the network so users always see live data. The GPS queue
 * lives in the page (localStorage) and uploads when back online.
 */
const CACHE = 'dm-shell-v1';
const SHELL = ['/offline.html', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }
    event.respondWith(fetch(event.request).catch(() => caches.match('/offline.html')));
});
