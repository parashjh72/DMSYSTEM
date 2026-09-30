{{--
    Background GPS breadcrumbs for a checked-in TSO. Runs on every page of the app
    while they are on duty: queues a fix every `interval` seconds (sooner after
    moving `minMove` metres), keeps the queue in localStorage so nothing is lost
    offline, and posts it to tracking.pings. Stops when the server says the user
    is no longer checked in, or when the Attendance page dispatches check-out.
    Browsers only report location while the app is open, so the TSO should keep
    the tab open during the working day.
--}}
@php
    $todayRecord = auth()->user()?->todayAttendance;
    $onDuty = config('tracking.enabled') && $todayRecord && ! $todayRecord->isCheckedOut();
@endphp
<script>
(function () {
    const active = @json($onDuty);

    if (window.__fieldTracker) {
        window.__fieldTracker.sync(active);
        return;
    }

    const cfg = {
        url: @json(route('tracking.pings')),
        interval: @json((int) config('tracking.interval_seconds')) * 1000,
        minMove: @json((int) config('tracking.min_move_metres')),
        flush: @json((int) config('tracking.flush_seconds')) * 1000,
        minGap: 15000,
        storageKey: 'dms.fieldTracker.queue.{{ auth()->id() }}',
    };

    const read = () => { try { return JSON.parse(localStorage.getItem(cfg.storageKey) || '[]'); } catch (e) { return []; } };
    const write = (q) => { try { localStorage.setItem(cfg.storageKey, JSON.stringify(q.slice(-1000))); } catch (e) {} };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const metres = (a, b) => {
        const r = 6371000, rad = (d) => d * Math.PI / 180;
        const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
        return 2 * r * Math.asin(Math.sqrt(h));
    };

    const tracker = window.__fieldTracker = {
        state: 'off',
        watchId: null,
        timer: null,
        queue: read(),
        last: null,
        lastSentAt: null,
        battery: null,
        sending: false,

        sync(on) {
            on ? this.start() : this.finish();
        },

        start() {
            if (this.watchId !== null) return;
            if (! ('geolocation' in navigator)) { this.emit('error', 'This browser cannot share location.'); return; }

            this.watchId = navigator.geolocation.watchPosition(
                (pos) => this.onFix(pos),
                (err) => this.emit('error', err.code === 1 ? 'Location permission denied — live tracking is paused.' : 'Waiting for GPS signal…'),
                { enableHighAccuracy: true, maximumAge: 10000, timeout: 60000 },
            );
            this.timer = setInterval(() => this.flush(), cfg.flush);
            navigator.getBattery?.().then((b) => {
                const upd = () => { this.battery = Math.round(b.level * 100); };
                upd();
                b.addEventListener('levelchange', upd);
            }).catch(() => {});
            this.emit('on');
            this.flush();
        },

        /** Send anything still queued, then stop watching. */
        async finish() {
            if (this.watchId !== null) navigator.geolocation.clearWatch(this.watchId);
            clearInterval(this.timer);
            this.watchId = null;
            this.timer = null;
            await this.flush();
            this.queue = [];
            write(this.queue);
            this.emit('off');
        },

        onFix(pos) {
            const c = pos.coords;
            const fix = { lat: c.latitude, lng: c.longitude };
            const now = pos.timestamp || Date.now();

            if (this.last) {
                const elapsed = now - this.last.t;
                const moved = metres(this.last, fix);
                if (elapsed < cfg.minGap) return;
                if (elapsed < cfg.interval && moved < cfg.minMove) return;
            }

            const ping = {
                lat: +c.latitude.toFixed(7),
                lng: +c.longitude.toFixed(7),
                accuracy: c.accuracy != null ? Math.round(c.accuracy * 10) / 10 : null,
                speed: c.speed != null && ! Number.isNaN(c.speed) ? Math.round(c.speed * 10) / 10 : null,
                battery: this.battery,
                t: now,
            };
            this.last = ping;
            this.queue.push(ping);
            write(this.queue);
            this.emit('on');

            if (this.queue.length >= 10) this.flush();
        },

        async flush() {
            this.queue = this.queue.length ? this.queue : read();
            if (this.sending || ! this.queue.length || navigator.onLine === false) return;

            this.sending = true;
            const batch = this.queue.slice(0, 200);
            try {
                const res = await fetch(cfg.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: JSON.stringify({ pings: batch }),
                });

                if (res.status === 409) {
                    this.queue = [];
                    write(this.queue);
                    if (this.watchId !== null) navigator.geolocation.clearWatch(this.watchId);
                    clearInterval(this.timer);
                    this.watchId = null;
                    this.emit('off');
                    return;
                }

                // 422 = a malformed batch: drop it rather than retry it forever.
                if (res.ok || res.status === 422) {
                    this.queue.splice(0, batch.length);
                    write(this.queue);
                    this.lastSentAt = Date.now();
                    this.emit(this.watchId !== null ? 'on' : this.state);
                }
            } catch (e) {
                // Offline or server unreachable: keep the queue, retry next tick.
            } finally {
                this.sending = false;
            }
        },

        emit(state, message = null) {
            this.state = state;
            window.dispatchEvent(new CustomEvent('field-tracker-status', {
                detail: { state, message, lastSentAt: this.lastSentAt, queued: this.queue.length },
            }));
        },
    };

    window.addEventListener('field-tracking', (e) => tracker.sync(!! (e.detail?.active ?? e.detail?.[0]?.active)));
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') tracker.flush(); });
    window.addEventListener('online', () => tracker.flush());

    tracker.sync(active);
})();
</script>
