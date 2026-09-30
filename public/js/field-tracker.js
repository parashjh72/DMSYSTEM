/*
 * DMS field tracker — background GPS breadcrumbs for a TSO on duty.
 *
 * Location fixes need no mobile data, so while the TSO is on duty every fix is
 * written to IndexedDB first ("dms-tracker" / queue) and sent when there is a
 * connection. With data off the queue simply grows; when data comes back it is
 * sent by the page, or by the service worker's Background Sync even if the app
 * has been closed. Used by the app layout (<x-field-tracker>) and by
 * /offline.html, so recording continues when the app is opened without data.
 */
(function () {
    if (window.DmsTracker) return;

    const DB_NAME = 'dms-tracker';
    const SYNC_TAG = 'dms-location-sync';
    const MIN_GAP_MS = 15000;

    /* ---------------- IndexedDB ---------------- */

    let dbPromise = null;
    function db() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve, reject) => {
            const open = indexedDB.open(DB_NAME, 1);
            open.onupgradeneeded = () => {
                const d = open.result;
                if (! d.objectStoreNames.contains('queue')) d.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
                if (! d.objectStoreNames.contains('meta')) d.createObjectStore('meta');
            };
            open.onsuccess = () => resolve(open.result);
            open.onerror = () => reject(open.error);
        });
        return dbPromise;
    }

    async function run(store, mode, fn) {
        const d = await db();
        return new Promise((resolve, reject) => {
            const t = d.transaction(store, mode);
            const req = fn(t.objectStore(store));
            t.oncomplete = () => resolve(req ? req.result : undefined);
            t.onerror = () => reject(t.error);
        });
    }

    const getMeta = () => run('meta', 'readonly', (s) => s.get('config')).catch(() => null);
    const putMeta = (value) => run('meta', 'readwrite', (s) => s.put(value, 'config')).catch(() => {});
    const addPing = (ping) => run('queue', 'readwrite', (s) => s.add(ping));
    const countQueue = () => run('queue', 'readonly', (s) => s.count()).catch(() => 0);
    const readQueue = (limit) => run('queue', 'readonly', (s) => s.getAll(undefined, limit));
    const dropIds = (ids) => run('queue', 'readwrite', (s) => { ids.forEach((id) => s.delete(id)); });

    const metres = (a, b) => {
        const r = 6371000, rad = (x) => x * Math.PI / 180;
        const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
        return 2 * r * Math.asin(Math.sqrt(h));
    };

    const T = window.DmsTracker = {
        cfg: null,
        state: 'off',
        message: null,
        watchId: null,
        timer: null,
        last: null,
        battery: null,
        lastSentAt: null,
        queued: 0,
        wakeLock: null,
        offlinePage: false,

        /** Called by the app layout on every page load / navigation. */
        async boot(cfg) {
            this.cfg = Object.assign({ interval: 60, minMove: 30, flush: 60 }, cfg);
            await this.migrateLocalStorage();
            const meta = (await getMeta()) || {};
            await putMeta({
                url: this.cfg.url,
                csrf: this.cfg.csrf,
                userId: this.cfg.userId,
                onDuty: !! this.cfg.active,
                interval: this.cfg.interval,
                minMove: this.cfg.minMove,
                flush: this.cfg.flush,
                lastSentAt: meta.lastSentAt || null,
            });
            this.lastSentAt = meta.lastSentAt || null;
            this.queued = await countQueue();
            this.sync(!! this.cfg.active);
        },

        /** Called by /offline.html: keep recording if the last known state was "on duty". */
        async bootOffline() {
            const meta = await getMeta();
            this.offlinePage = true;
            if (! meta || ! meta.onDuty) {
                this.emit('off');
                return false;
            }
            this.cfg = meta;
            this.lastSentAt = meta.lastSentAt || null;
            this.queued = await countQueue();
            this.start();
            return true;
        },

        sync(on) {
            on ? this.start() : this.finish();
        },

        start() {
            if (this.watchId !== null) {
                this.emit(this.state === 'off' ? 'on' : this.state);
                return;
            }
            if (! ('geolocation' in navigator)) {
                this.emit('error', 'This browser cannot share location.');
                return;
            }

            this.watchId = navigator.geolocation.watchPosition(
                (pos) => this.onFix(pos),
                (err) => this.emit('error', err.code === 1
                    ? 'Location permission denied — allow location for this app to keep tracking.'
                    : 'Waiting for GPS signal…'),
                { enableHighAccuracy: true, maximumAge: 10000, timeout: 60000 },
            );
            clearInterval(this.timer);
            this.timer = setInterval(() => this.flush(), (this.cfg.flush || 60) * 1000);

            if (navigator.getBattery) {
                navigator.getBattery().then((b) => {
                    const upd = () => { this.battery = Math.round(b.level * 100); };
                    upd();
                    b.addEventListener('levelchange', upd);
                }).catch(() => {});
            }

            this.emit('on');
            this.applyWakeLock();
            this.flush();
        },

        stopWatching() {
            if (this.watchId !== null) navigator.geolocation.clearWatch(this.watchId);
            clearInterval(this.timer);
            this.watchId = null;
            this.timer = null;
            this.releaseWakeLock();
        },

        /** Check-out: send what is queued, then stop. */
        async finish() {
            this.stopWatching();
            const meta = await getMeta();
            if (meta && meta.onDuty) await putMeta(Object.assign(meta, { onDuty: false }));
            await this.flush();
            this.emit('off');
        },

        async onFix(pos) {
            const c = pos.coords;
            const now = pos.timestamp || Date.now();
            const fix = { lat: c.latitude, lng: c.longitude };

            if (this.last) {
                const elapsed = now - this.last.t;
                if (elapsed < MIN_GAP_MS) return;
                if (elapsed < (this.cfg.interval || 60) * 1000 && metres(this.last, fix) < (this.cfg.minMove || 30)) return;
            }

            const ping = {
                userId: this.cfg.userId,
                lat: +c.latitude.toFixed(7),
                lng: +c.longitude.toFixed(7),
                accuracy: c.accuracy != null ? Math.round(c.accuracy * 10) / 10 : null,
                speed: c.speed != null && ! Number.isNaN(c.speed) ? Math.round(c.speed * 10) / 10 : null,
                battery: this.battery,
                t: now,
            };
            this.last = { lat: ping.lat, lng: ping.lng, t: now };

            try {
                await addPing(ping);
            } catch (e) {
                this.emit('error', 'Could not store location on this phone (storage full?).');
                return;
            }
            this.queued = await countQueue();
            this.emit(navigator.onLine === false ? 'offline' : 'on');

            if (this.queued >= 10) this.flush();
        },

        /** Send queued pings; one sender at a time across tabs and the service worker. */
        flush() {
            if (navigator.onLine === false) {
                this.requestBackgroundSync();
                this.emit(this.watchId !== null ? 'offline' : this.state);
                return Promise.resolve();
            }
            const send = () => this.sendQueued().catch(() => this.requestBackgroundSync());
            return navigator.locks ? navigator.locks.request('dms-location-flush', send) : send();
        },

        async sendQueued() {
            const meta = await getMeta();
            if (! meta || ! meta.url) return;

            for (;;) {
                const batch = await readQueue(200);
                if (! batch || ! batch.length) break;

                const foreign = batch.filter((p) => p.userId !== meta.userId).map((p) => p.id);
                if (foreign.length) await dropIds(foreign);
                const mine = batch.filter((p) => p.userId === meta.userId);
                if (! mine.length) continue;

                const res = await fetch(meta.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': meta.csrf || '',
                    },
                    body: JSON.stringify({ pings: mine.map(({ id, userId, ...p }) => p) }),
                });

                if (res.status === 401 || res.status === 419 || res.status >= 500) {
                    throw new Error('retry-later');
                }

                await dropIds(mine.map((p) => p.id));
                this.lastSentAt = Date.now();
                await putMeta(Object.assign(meta, { lastSentAt: this.lastSentAt }));

                const body = res.ok ? await res.json().catch(() => ({})) : {};
                if (body.tracking === false && this.watchId !== null && ! this.offlinePage) {
                    // Checked out elsewhere (or day closed): stop collecting.
                    this.stopWatching();
                    await putMeta(Object.assign(meta, { onDuty: false }));
                    this.state = 'off';
                }
            }

            this.queued = await countQueue();
            this.emit(this.watchId !== null ? 'on' : 'off');
        },

        requestBackgroundSync() {
            if (! ('serviceWorker' in navigator)) return;
            navigator.serviceWorker.ready
                .then((reg) => reg.sync && reg.sync.register(SYNC_TAG))
                .catch(() => {});
        },

        /* ---------- keep the screen awake while on duty (most reliable tracking) ---------- */

        keepAwakePreferred() {
            try { return localStorage.getItem('dms.keepAwake') !== '0'; } catch (e) { return true; }
        },

        setKeepAwake(on) {
            try { localStorage.setItem('dms.keepAwake', on ? '1' : '0'); } catch (e) {}
            on ? this.applyWakeLock() : this.releaseWakeLock();
            this.emit(this.state, this.message);
        },

        async applyWakeLock() {
            if (! ('wakeLock' in navigator) || this.watchId === null || ! this.keepAwakePreferred()) return;
            if (this.wakeLock || document.visibilityState !== 'visible') return;
            try {
                this.wakeLock = await navigator.wakeLock.request('screen');
                this.wakeLock.addEventListener('release', () => { this.wakeLock = null; this.emit(this.state, this.message); });
                this.emit(this.state, this.message);
            } catch (e) {
                this.wakeLock = null;
            }
        },

        releaseWakeLock() {
            if (this.wakeLock) this.wakeLock.release().catch(() => {});
            this.wakeLock = null;
        },

        /** Queue kept in localStorage by the first tracker version: move it into IndexedDB. */
        async migrateLocalStorage() {
            try {
                const key = 'dms.fieldTracker.queue.' + this.cfg.userId;
                const old = JSON.parse(localStorage.getItem(key) || '[]');
                for (const p of old) await addPing(Object.assign({ userId: this.cfg.userId }, p));
                localStorage.removeItem(key);
            } catch (e) {}
        },

        emit(state, message = null) {
            if (state === 'offline' && this.watchId === null) state = 'off';
            this.state = state;
            this.message = message;
            window.dispatchEvent(new CustomEvent('field-tracker-status', {
                detail: {
                    state,
                    message,
                    lastSentAt: this.lastSentAt,
                    queued: this.queued,
                    awake: !! this.wakeLock,
                    keepAwake: this.keepAwakePreferred(),
                    wakeLockSupported: 'wakeLock' in navigator,
                },
            }));
        },
    };

    window.addEventListener('field-tracking', (e) => {
        const detail = e.detail || {};
        const active = detail.active ?? (Array.isArray(detail) ? detail[0]?.active : undefined);
        T.sync(!! active);
    });
    window.addEventListener('online', () => T.flush());
    window.addEventListener('offline', () => T.emit(T.watchId !== null ? 'offline' : T.state));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            T.flush();
        } else {
            T.applyWakeLock();
            T.flush();
        }
    });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', async (e) => {
            if (e.data && e.data.type === 'location-synced') {
                T.lastSentAt = Date.now();
                T.queued = await countQueue();
                T.emit(T.state, T.message);
            }
        });
    }
})();
