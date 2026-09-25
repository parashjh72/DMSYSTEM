{{--
    Field Sales tracker card on the TSO Attendance page.
    - Shows today's check-in point result.
    - Asks for (and records) consent to duty-hours location sharing.
    - While checked in, takes a GPS fix every N minutes, queues it on the phone
      (survives no signal and page reloads) and uploads the queue in batches.
    Tracking only runs while this page is open: browsers pause GPS in the background.
--}}
<div class="space-y-3">
    @if ($day?->check_in_geofence === 'outside')
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
            <span class="font-bold">Checked in outside your check-in point.</span>
            You were {{ number_format($day->check_in_distance_metres) }} m from {{ $day->checkInGeofence?->name ?? 'your assigned point' }}. Your manager can see this.
        </div>
    @elseif ($day?->check_in_geofence === 'inside')
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
            Checked in at <span class="font-bold">{{ $day->checkInGeofence?->name }}</span>.
        </div>
    @endif

    @can('field-sales.track')
        @if (! $status['consented'])
            <div class="card space-y-3 rounded-2xl border border-indigo-200 bg-white p-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    </div>
                    <div class="text-xs text-slate-600">
                        <div class="text-sm font-bold text-slate-900">Share location during duty hours</div>
                        <p class="mt-1">While you are checked in, this app records your location every {{ $status['interval_minutes'] }} minutes so your manager can see field coverage and your daily kilometres.</p>
                        <ul class="mt-2 list-disc space-y-0.5 pl-4">
                            <li>Only between check-in and check-out — never after you check out.</li>
                            <li>Kept for {{ config('field_sales.tracking.retention_days') }} days, then deleted.</li>
                            <li>You can turn it off here at any time.</li>
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-primary w-full justify-center text-xs" wire:click="acceptTrackingConsent">I agree — share my location on duty</button>
            </div>
        @else
            <div wire:key="fs-tracker-{{ $status['tracking'] ? 'on' : 'off' }}"
                 x-data="fsTracker({
                    tracking: @js($status['tracking']),
                    intervalMinutes: @js($status['interval_minutes']),
                    userId: @js(auth()->id()),
                    locationsUrl: @js(route('field-sales.api.locations')),
                    maxBatch: @js(config('field_sales.tracking.max_batch')),
                 })"
                 class="card rounded-2xl border border-slate-200/80 bg-white p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="relative flex h-2.5 w-2.5">
                            <template x-if="tracking"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span></template>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="tracking ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                        </span>
                        <div>
                            <div class="text-xs font-bold text-slate-900" x-text="tracking ? 'Location sharing on' : 'Location sharing paused'"></div>
                            <div class="text-[11px] text-slate-500">
                                @if ($status['tracking'])
                                    Every {{ $status['interval_minutes'] }} min · <span x-text="lastLabel"></span>
                                @elseif ($status['checked_out'])
                                    Stopped — you have checked out.
                                @else
                                    Starts when you check in.
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="text-right text-[11px]">
                        <template x-if="queued > 0"><div class="font-semibold text-amber-600"><span x-text="queued"></span> waiting to upload</div></template>
                        <template x-if="error"><div class="font-semibold text-rose-600" x-text="error"></div></template>
                    </div>
                </div>

                @if ($status['tracking'])
                    <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 text-[11px]">
                        <template x-if="wakeLockSupported">
                            <label class="flex items-center gap-2 font-medium text-slate-700">
                                <input type="checkbox" class="rounded border-slate-300 text-indigo-600" x-model="keepAwake" @change="toggleWakeLock()"> Keep screen on while on duty
                            </label>
                        </template>
                        <span class="text-slate-400">Keep this page open for a complete route.</span>
                    </div>
                @endif

                <div class="mt-3 text-right">
                    <button type="button" class="text-[11px] font-semibold text-slate-400 hover:text-rose-600" wire:click="revokeTrackingConsent" wire:confirm="Stop sharing your location during duty hours?">Turn off location sharing</button>
                </div>
            </div>
        @endif
    @endcan

    <script>
        (function () {
            const register = () => {
                if (window.__fsTrackerRegistered || ! window.Alpine) { return; }
                window.__fsTrackerRegistered = true;

                const uuid = () => (window.crypto && crypto.randomUUID)
                    ? crypto.randomUUID()
                    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
                        const r = Math.random() * 16 | 0;
                        return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
                    });

                Alpine.data('fsTracker', (cfg) => ({
                    tracking: cfg.tracking,
                    queued: 0,
                    lastSentAt: null,
                    lastFixAt: null,
                    error: null,
                    timer: null,
                    flushing: false,
                    keepAwake: false,
                    wakeLock: null,
                    wakeLockSupported: 'wakeLock' in navigator,
                    storageKey: 'fs.pingQueue.' + cfg.userId,
                    get lastLabel() {
                        if (this.lastSentAt) { return 'last sent ' + this.lastSentAt; }
                        if (this.lastFixAt) { return 'last fix ' + this.lastFixAt; }
                        return 'waiting for first fix';
                    },
                    init() {
                        this.queued = this.readQueue().length;
                        this.onOnline = () => this.flush();
                        this.onVisible = () => {
                            if (document.visibilityState !== 'visible') { return; }
                            if (this.tracking) { this.capture(); }
                            if (this.keepAwake) { this.toggleWakeLock(); }
                        };
                        window.addEventListener('online', this.onOnline);
                        document.addEventListener('visibilitychange', this.onVisible);

                        // Always try to upload anything left from an earlier session.
                        this.flush();

                        if (this.tracking && navigator.geolocation) {
                            this.capture();
                            this.timer = setInterval(() => this.capture(), Math.max(1, cfg.intervalMinutes) * 60000);
                        }
                    },
                    destroy() {
                        clearInterval(this.timer);
                        window.removeEventListener('online', this.onOnline);
                        document.removeEventListener('visibilitychange', this.onVisible);
                        if (this.wakeLock) { this.wakeLock.release().catch(() => {}); }
                    },
                    readQueue() {
                        try { return JSON.parse(localStorage.getItem(this.storageKey) || '[]'); } catch (e) { return []; }
                    },
                    writeQueue(queue) {
                        // Cap the queue so a phone offline for days cannot fill storage.
                        const capped = queue.slice(-1000);
                        try { localStorage.setItem(this.storageKey, JSON.stringify(capped)); } catch (e) {}
                        this.queued = capped.length;
                    },
                    async battery() {
                        try {
                            if (! navigator.getBattery) { return null; }
                            const b = await navigator.getBattery();
                            return Math.round(b.level * 100);
                        } catch (e) { return null; }
                    },
                    capture() {
                        navigator.geolocation.getCurrentPosition(async (pos) => {
                            const ping = {
                                id: uuid(),
                                t: Math.round(pos.timestamp || Date.now()),
                                lat: pos.coords.latitude,
                                lng: pos.coords.longitude,
                                acc: pos.coords.accuracy,
                                speed: pos.coords.speed,
                                battery: await this.battery(),
                            };
                            this.writeQueue([...this.readQueue(), ping]);
                            this.lastFixAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            this.error = null;
                            this.flush();
                        }, (err) => {
                            this.error = err.code === 1 ? 'Location permission denied' : 'Could not get location';
                        }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 60000 });
                    },
                    async flush() {
                        if (this.flushing || ! navigator.onLine) { return; }
                        const queue = this.readQueue();
                        if (! queue.length) { return; }

                        this.flushing = true;
                        const batch = queue.slice(0, cfg.maxBatch);
                        try {
                            const res = await fetch(cfg.locationsUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ pings: batch }),
                            });

                            if (res.ok || res.status === 422) {
                                // Every ping in the batch was processed (stored, duplicate or refused).
                                const sent = new Set(batch.map((p) => p.id));
                                this.writeQueue(this.readQueue().filter((p) => ! sent.has(p.id)));
                                this.lastSentAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                if (res.ok && this.queued > 0) { this.flushing = false; return await this.flush(); }
                            } else if (res.status === 403) {
                                this.error = 'Location sharing is off';
                                this.tracking = false;
                                clearInterval(this.timer);
                            } else if (res.status === 419 || res.status === 401) {
                                this.error = 'Session expired — reload the page';
                            }
                        } catch (e) {
                            // Offline or server unreachable: keep the queue and retry later.
                        } finally {
                            this.flushing = false;
                        }
                    },
                    async toggleWakeLock() {
                        try {
                            if (this.keepAwake && ! this.wakeLock) {
                                this.wakeLock = await navigator.wakeLock.request('screen');
                                this.wakeLock.addEventListener('release', () => { this.wakeLock = null; });
                            } else if (! this.keepAwake && this.wakeLock) {
                                await this.wakeLock.release();
                                this.wakeLock = null;
                            }
                        } catch (e) {
                            this.keepAwake = false;
                        }
                    },
                }));
            };
            document.addEventListener('alpine:init', register);
            register();
        })();
    </script>
</div>
