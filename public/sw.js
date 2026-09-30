/*
 * DMS service worker.
 * - Installable PWA with an offline fallback page and cached build assets.
 * - Background Sync for field GPS breadcrumbs: the page stores pings in
 *   IndexedDB ("dms-tracker"); when the network returns — even if the app has
 *   been closed — the browser wakes this worker to post them to the server.
 */
const VERSION = 'dms-v1';
const STATIC_CACHE = VERSION + '-static';
const OFFLINE_URL = '/offline.html';
const SYNC_TAG = 'dms-location-sync';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/js/field-tracker.js', '/icons/icon-192.png', '/manifest.webmanifest']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => ! k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Hashed Vite assets and icons never change under the same URL: cache first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(STATIC_CACHE).then((c) => c.put(req, copy));
                }
                return res;
            })),
        );
        return;
    }

    // The tracker script must also work on the offline page: network first, cached copy offline.
    if (url.pathname === '/js/field-tracker.js') {
        event.respondWith(
            fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(STATIC_CACHE).then((c) => c.put('/js/field-tracker.js', copy));
                }
                return res;
            }).catch(() => caches.match('/js/field-tracker.js')),
        );
        return;
    }

    // Pages are always live (they carry session + CSRF state); offline page when there is no network.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(flushQueue());
    }
});

self.addEventListener('message', (event) => {
    if (event.data === 'flush-location') {
        event.waitUntil(flushQueue());
    }
});

/* ---- IndexedDB queue (same schema as the page-side tracker) ---- */

function openDb() {
    return new Promise((resolve, reject) => {
        const open = indexedDB.open('dms-tracker', 1);
        open.onupgradeneeded = () => {
            const db = open.result;
            if (! db.objectStoreNames.contains('queue')) db.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
            if (! db.objectStoreNames.contains('meta')) db.createObjectStore('meta');
        };
        open.onsuccess = () => resolve(open.result);
        open.onerror = () => reject(open.error);
    });
}

function tx(db, store, mode, fn) {
    return new Promise((resolve, reject) => {
        const t = db.transaction(store, mode);
        const result = fn(t.objectStore(store));
        t.oncomplete = () => resolve(result && 'result' in result ? result.result : undefined);
        t.onerror = () => reject(t.error);
    });
}

/** One sender at a time across the page and this worker. */
function flushQueue() {
    return self.navigator.locks
        ? self.navigator.locks.request('dms-location-flush', sendQueued)
        : sendQueued();
}

async function sendQueued() {
    const db = await openDb();
    const meta = await tx(db, 'meta', 'readonly', (s) => s.get('config'));
    if (! meta || ! meta.url) return;

    for (;;) {
        const batch = await tx(db, 'queue', 'readonly', (s) => s.getAll(undefined, 200));
        if (! batch || ! batch.length) return;

        // Pings belong to the user who was signed in when they were taken; never
        // send them under someone else's session.
        const foreign = batch.filter((p) => p.userId !== meta.userId);
        if (foreign.length) {
            await tx(db, 'queue', 'readwrite', (s) => foreign.forEach((p) => s.delete(p.id)));
        }
        const all = batch.filter((p) => p.userId === meta.userId);
        if (! all.length) continue;

        const res = await fetch(meta.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': meta.csrf || '',
            },
            body: JSON.stringify({ pings: all.map(({ id, userId, ...p }) => p) }),
        });

        // Session expired / CSRF stale: keep the queue; the app retries once it is reopened.
        if (res.status === 401 || res.status === 419 || res.status >= 500) {
            throw new Error('retry-later');
        }

        // Accepted (or a malformed batch that will never be accepted): drop what was sent.
        await tx(db, 'queue', 'readwrite', (s) => all.forEach((p) => s.delete(p.id)));

        const clients = await self.clients.matchAll({ includeUncontrolled: true });
        clients.forEach((c) => c.postMessage({ type: 'location-synced', count: all.length }));
    }
}
