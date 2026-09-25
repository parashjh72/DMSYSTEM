@php
    $user = auth()->user();
    $hour = now(config('attendance.timezone'))->hour;
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $userInitial = strtoupper(substr($user?->name ?? 'U', 0, 1));
@endphp

<div class="mx-auto max-w-xl space-y-6"
     x-data="attendanceTracker($wire, {
        elapsed: {{ $record && !$record->isCheckedOut() && $record->check_in_at ? max(0, now()->diffInSeconds($record->check_in_at)) : 0 }},
        hasActiveRecord: {{ ($record && !$record->isCheckedOut()) ? 'true' : 'false' }}
     })">

    {{-- Must live inside the root element: Livewire binds the component to the first top-level tag. --}}
<script>
window.attendanceTracker = function($wireInstance, config) {
    return {
        busy: false,
        statusText: '',
        clientError: '',
        showAccuracyModal: false,
        accuracyData: {
            accuracy: 0,
            latitude: '0.000000',
            longitude: '0.000000',
            altitude: 'N/A',
            timestamp: '',
            quality: 'Checking…',
            isMock: false,
            message: '',
        },
        currentTime: '',
        elapsedSeconds: config.elapsed || 0,
        timerInterval: null,
        init() {
            this.updateClock();
            setInterval(() => this.updateClock(), 1000);
            if (config.hasActiveRecord) {
                this.timerInterval = setInterval(() => {
                    this.elapsedSeconds++;
                }, 1000);
            }
        },
        updateClock() {
            const now = new Date();
            this.currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },
        get formattedElapsed() {
            const h = String(Math.floor(this.elapsedSeconds / 3600)).padStart(2, '0');
            const m = String(Math.floor((this.elapsedSeconds % 3600) / 60)).padStart(2, '0');
            const s = String(this.elapsedSeconds % 60).padStart(2, '0');
            return `${h}h ${m}m ${s}s`;
        },
        acquireGps() {
            if (! ('geolocation' in navigator)) {
                throw new Error('This browser does not support GPS location. Please open in Google Chrome or Safari.');
            }
            return new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, (err) => {
                    // Fallback to network/wifi location if pure satellite GNSS times out indoors
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: false,
                        timeout: 8000,
                        maximumAge: 5000
                    });
                }, {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0
                });
            });
        },
        // Watches GPS briefly and returns up to `wanted` distinct fixes. Real satellites drift
        // slightly between fixes; Fake GPS apps repeat identical coordinates (checked server-side).
        async collectSamples(wanted = 3, windowMs = 7000) {
            const first = await this.acquireGps();
            const fixes = [first];

            if (! navigator.geolocation.watchPosition) {
                return fixes;
            }

            await new Promise((resolve) => {
                let watchId = null;
                const finish = () => {
                    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
                    clearTimeout(timer);
                    resolve();
                };
                const timer = setTimeout(finish, windowMs);
                watchId = navigator.geolocation.watchPosition((pos) => {
                    if (fixes.some((f) => f.timestamp === pos.timestamp)) return;
                    fixes.push(pos);
                    this.statusText = `Verifying GPS signal (${Math.min(fixes.length, wanted)}/${wanted})…`;
                    if (fixes.length >= wanted) finish();
                }, finish, { enableHighAccuracy: true, timeout: windowMs, maximumAge: 0 });
            });

            return fixes;
        },
        // Largest distance (metres) of any fix from the first one; real GPS is rarely exactly 0.
        spreadMetres(fixes) {
            const [first, ...rest] = fixes;
            const toRad = (d) => d * Math.PI / 180;
            return rest.reduce((max, f) => {
                const dLat = toRad(f.coords.latitude - first.coords.latitude);
                const dLng = toRad(f.coords.longitude - first.coords.longitude) * Math.cos(toRad(first.coords.latitude));
                return Math.max(max, Math.sqrt(dLat * dLat + dLng * dLng) * 6371000);
            }, 0).toFixed(2);
        },
        telemetryFrom(fixes) {
            return {
                samples: fixes.map((p) => ({
                    lat: p.coords.latitude,
                    lng: p.coords.longitude,
                    accuracy: p.coords.accuracy,
                    alt: p.coords.altitude,
                    t: p.timestamp
                }))
            };
        },
        evaluateAccuracy(pos, fixes = [pos]) {
            const acc = pos.coords.accuracy;
            const isMock = acc <= 0.5;
            let quality = 'Good';
            let message = 'Authentic satellite GPS fix.';

            if (isMock) {
                quality = 'Mock Location Detected (0m)';
                message = 'Suspicious 0m accuracy detected. Real satellite signals always have natural accuracy margins (5m–25m). Turn off Fake GPS in Android Developer Options.';
            } else if (acc <= 10) {
                quality = 'Excellent (Satellite GNSS)';
                message = 'High-precision satellite lock. Verified authentic.';
            } else if (acc <= 30) {
                quality = 'Good (Standard GPS)';
                message = 'Reliable physical position. Natural satellite variance verified.';
            } else if (acc <= 75) {
                quality = 'Moderate (Assisted Fix)';
                message = 'Indoor or cellular network position fix.';
            } else {
                quality = 'Low Precision (>75m)';
                message = 'Wide margin of error. For best results, move closer to an open window or outdoors.';
            }

            this.accuracyData = {
                rawPos: pos,
                rawFixes: fixes,
                samples: fixes.map((f) => `${f.coords.latitude.toFixed(7)}, ${f.coords.longitude.toFixed(7)} ±${Math.round(f.coords.accuracy * 10) / 10}m`),
                spread: this.spreadMetres(fixes),
                accuracy: acc ? Math.round(acc * 10) / 10 : 0,
                latitude: pos.coords.latitude ? pos.coords.latitude.toFixed(6) : '0.000000',
                longitude: pos.coords.longitude ? pos.coords.longitude.toFixed(6) : '0.000000',
                altitude: (pos.coords.altitude !== null && pos.coords.altitude !== undefined) ? Math.round(pos.coords.altitude) + 'm' : 'N/A',
                timestamp: new Date(pos.timestamp).toLocaleTimeString(),
                quality: quality,
                isMock: isMock,
                message: message,
            };
        },
        async testGpsAccuracy() {
            if (this.busy) return;
            this.busy = true;
            this.clientError = '';
            this.statusText = 'Querying GPS…';
            const wire = $wireInstance || this.$wire;

            try {
                const fixes = await this.collectSamples();
                this.evaluateAccuracy(fixes[fixes.length - 1], fixes);
                this.showAccuracyModal = true;
            } catch (err) {
                this.handleError(err, wire);
            } finally {
                this.busy = false;
                this.statusText = '';
            }
        },
        async submitPosition(action) {
            if (this.busy) return;
            this.busy = true;
            this.clientError = '';
            this.statusText = 'Recording attendance…';

            const wire = $wireInstance || this.$wire;
            const pos = this.accuracyData.rawPos;

            if (!pos) {
                this.busy = false;
                return this.capture(action);
            }

            try {
                const telemetry = this.telemetryFrom(this.accuracyData.rawFixes || [pos]);

                if (!wire || typeof wire[action] !== 'function') {
                    throw new Error('Connection initializing. Please refresh the page and try again.');
                }

                await wire[action](pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy, telemetry);

                if (wire.error) {
                    this.clientError = wire.error;
                }

                this.showAccuracyModal = false;
                this.busy = false;
                this.statusText = '';
            } catch (err) {
                this.busy = false;
                this.statusText = '';
                this.handleError(err, wire);
            }
        },
        async capture(action) {
            if (this.busy) return;
            this.busy = true;
            this.clientError = '';
            this.statusText = 'Contacting GPS…';

            const wire = $wireInstance || this.$wire;

            try {
                this.statusText = 'Acquiring GPS fix…';
                const fixes = await this.collectSamples();
                const pos = fixes[fixes.length - 1];

                this.evaluateAccuracy(pos, fixes);
                this.showAccuracyModal = true;

                // Check 1: Synthetic 0m or <= 0.5m accuracy (typical mock location marker)
                if (this.accuracyData.isMock) {
                    this.busy = false;
                    this.statusText = '';
                    this.clientError = 'Developer Mock Location detected: Suspicious 0m accuracy. Real satellite GPS signals have natural accuracy margins. Please disable "Select mock location app" in Developer Options.';
                    if (wire && typeof wire.reportGpsError === 'function') {
                        try { await wire.reportGpsError(this.clientError); } catch (e) {}
                    }
                    return;
                }

                this.statusText = 'Verifying coordinates…';

                const telemetry = this.telemetryFrom(fixes);

                if (!wire || typeof wire[action] !== 'function') {
                    throw new Error('Connection initializing. Please refresh the page and try again.');
                }

                await wire[action](pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy, telemetry);

                if (wire.error) {
                    this.clientError = wire.error;
                } else {
                    this.showAccuracyModal = false;
                }

                this.busy = false;
                this.statusText = '';
            } catch (err) {
                this.busy = false;
                this.statusText = '';
                this.handleError(err, wire);
            }
        },
        handleError(err, wire) {
            console.error('Attendance GPS error:', err);
            const msg = {
                1: 'GPS permission denied. Please allow location access in your browser settings (Chrome/Safari).',
                2: 'Location is unavailable right now. Please turn ON location/GPS in phone quick settings.',
                3: 'GPS request timed out. Please ensure Location is enabled in High Accuracy mode and try again.',
            }[err?.code] || (err?.message ? err.message : 'Could not acquire location. Please try again.');
            
            this.clientError = msg;
            if (wire && typeof wire.reportGpsError === 'function') {
                try {
                    wire.reportGpsError(msg);
                } catch (e) {
                    console.error('Failed to report GPS error to server:', e);
                }
            }
        }
    };
};
</script>

    {{-- User Header & Status Card --}}
    <div class="card p-4 sm:p-5 shadow-xs border border-slate-200/80 rounded-2xl bg-white flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-600 to-violet-500 font-extrabold text-white text-base shadow-sm ring-4 ring-indigo-50">
                {{ $userInitial }}
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ $greeting }}</div>
                <div class="text-sm font-bold text-slate-900 truncate">{{ $user?->name ?? 'Field Officer' }}</div>
                <div class="text-[11px] font-medium text-slate-500 flex items-center gap-1.5 mt-0.5">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                    <span>{{ $today->format('D, d M Y') }}</span>
                </div>
            </div>
        </div>

        <div class="shrink-0 text-right">
            @if (! $record)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    Ready
                </span>
            @elseif (! $record->isCheckedOut())
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 shadow-2xs">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    On Duty
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                    <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Completed
                </span>
            @endif
        </div>
    </div>

    {{-- Success Alert --}}
    @if (session()->has('status'))
        <div class="rounded-2xl bg-emerald-50 p-4 text-xs font-bold text-emerald-800 border border-emerald-200 shadow-xs flex items-center justify-between gap-3 animate-headShake">
            <div class="flex items-center gap-2.5">
                <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif

    {{-- Comprehensive Error Alert (Client & Server) --}}
    <div x-show="clientError || {{ $error ? 'true' : 'false' }}"
         x-cloak
         class="rounded-2xl bg-rose-50 p-4 sm:p-5 text-xs text-rose-900 border border-rose-200 shadow-xs space-y-3 animate-headShake">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2.5">
                <svg class="h-5 w-5 shrink-0 text-rose-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="flex-1">
                    <div class="font-extrabold text-sm text-rose-800">Attendance Error</div>
                    <div class="font-medium text-rose-700 mt-1 leading-relaxed" x-text="clientError || '{{ addslashes($error ?? '') }}'">
                        {{ $error }}
                    </div>
                </div>
            </div>
            <button type="button" @click="clientError = ''" class="text-rose-400 hover:text-rose-700 font-bold text-base p-1 leading-none">&times;</button>
        </div>

        {{-- Troubleshooting Guidance Checklist --}}
        <div class="border-t border-rose-200/60 pt-3 text-[11px] text-rose-800 space-y-1.5 bg-rose-100/40 p-3 rounded-xl">
            <div class="font-bold uppercase tracking-wider text-[10px] text-rose-600">Quick Resolution Checklist:</div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                <span>Ensure device <strong>Location (GPS)</strong> is turned ON in phone settings.</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                <span>Allow browser location permission in Chrome/Safari address bar.</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                <span>Disable <strong>Fake GPS</strong> or <em>"Select mock location app"</em> in Developer Options.</span>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
            <button type="button"
                    @click="testGpsAccuracy()"
                    class="rounded-xl bg-white border border-rose-200 px-3.5 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 transition shadow-2xs cursor-pointer flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke-width="2"/></svg>
                <span>Test GPS Accuracy Popup &rarr;</span>
            </button>
        </div>
    </div>

    {{-- Accuracy Diagnostics Pop-Up Modal --}}
    <div x-show="showAccuracyModal"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">

        <div @click.away="showAccuracyModal = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="w-full max-w-sm rounded-3xl bg-white p-5 sm:p-6 shadow-2xl border border-slate-100 text-center relative flex flex-col gap-3.5">

            {{-- Header Row with Center Icon and Right Close Button --}}
            <div class="relative flex items-center justify-center pt-1">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl shadow-xs"
                     :class="accuracyData.isMock ? 'bg-rose-50 text-rose-600 ring-8 ring-rose-50/50' : 'bg-indigo-50 text-indigo-600 ring-8 ring-indigo-50/50'">
                    <template x-if="accuracyData.isMock">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </template>
                    <template x-if="!accuracyData.isMock">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" stroke-width="2"/>
                            <circle cx="12" cy="12" r="3" stroke-width="2"/>
                            <line x1="12" y1="2" x2="12" y2="5" stroke-width="2"/>
                            <line x1="12" y1="19" x2="12" y2="22" stroke-width="2"/>
                            <line x1="2" y1="12" x2="5" y2="12" stroke-width="2"/>
                            <line x1="19" y1="12" x2="22" y2="12" stroke-width="2"/>
                        </svg>
                    </template>
                </div>
                <button type="button"
                        @click="showAccuracyModal = false"
                        class="absolute right-0 top-0 text-slate-400 hover:text-slate-600 p-2 rounded-full hover:bg-slate-100 transition cursor-pointer">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div>
                <h3 class="text-base font-extrabold text-slate-900">GPS Accuracy Report</h3>
                <p class="text-xs text-slate-500 mt-0.5">Live reading from your device hardware</p>
            </div>

            {{-- Main Accuracy Display --}}
            <div class="rounded-2xl p-4 border"
                 :class="accuracyData.isMock ? 'bg-rose-50/80 border-rose-200' : (accuracyData.accuracy <= 20 ? 'bg-emerald-50/80 border-emerald-200' : 'bg-blue-50/80 border-blue-200')">
                <div class="text-[10px] font-bold uppercase tracking-wider"
                     :class="accuracyData.isMock ? 'text-rose-600' : (accuracyData.accuracy <= 20 ? 'text-emerald-700' : 'text-blue-700')">
                    Reported Accuracy Margin
                </div>
                <div class="mt-1 font-mono text-3xl font-extrabold tracking-tight"
                     :class="accuracyData.isMock ? 'text-rose-700' : (accuracyData.accuracy <= 20 ? 'text-emerald-800' : 'text-blue-800')"
                     x-text="'± ' + accuracyData.accuracy + 'm'">
                    ± 0m
                </div>
                <div class="mt-1.5 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                     :class="accuracyData.isMock ? 'bg-rose-100 text-rose-800' : (accuracyData.accuracy <= 20 ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800')"
                     x-text="accuracyData.quality">
                </div>
                <p class="text-[11px] mt-2 leading-relaxed"
                   :class="accuracyData.isMock ? 'text-rose-800 font-semibold' : 'text-slate-600'"
                   x-text="accuracyData.message"></p>
            </div>

            {{-- Readings breakdown --}}
            <div class="grid grid-cols-2 gap-2 text-left text-xs bg-slate-50 p-3 rounded-2xl border border-slate-100">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Latitude</span>
                    <span class="font-mono font-bold text-slate-800" x-text="accuracyData.latitude"></span>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Longitude</span>
                    <span class="font-mono font-bold text-slate-800" x-text="accuracyData.longitude"></span>
                </div>
                <div class="mt-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Altitude</span>
                    <span class="font-mono font-bold text-slate-800" x-text="accuracyData.altitude"></span>
                </div>
                <div class="mt-1">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Fix Time</span>
                    <span class="font-mono font-bold text-slate-800" x-text="accuracyData.timestamp"></span>
                </div>
                <div class="col-span-2 mt-1 border-t border-slate-200 pt-2">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">
                        Readings: <span x-text="(accuracyData.samples || []).length"></span> &middot; Movement: <span x-text="(accuracyData.spread ?? '0.00') + 'm'"></span>
                    </span>
                    <template x-for="(sample, index) in (accuracyData.samples || [])" :key="index">
                        <span class="font-mono text-[10px] text-slate-600 block" x-text="sample"></span>
                    </template>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="pt-1 space-y-2">
                <template x-if="accuracyData.isMock">
                    <button type="button"
                            @click="showAccuracyModal = false"
                            class="w-full rounded-2xl bg-rose-600 hover:bg-rose-700 active:scale-95 py-3 text-xs font-extrabold text-white transition shadow-sm cursor-pointer">
                        Dismiss & Disable Fake GPS
                    </button>
                </template>
                <template x-if="!accuracyData.isMock">
                    <div class="space-y-2">
                        @if (! $record)
                            <button type="button"
                                    :disabled="busy"
                                    @click="submitPosition('checkIn')"
                                    class="w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-95 py-3 px-4 text-xs font-extrabold text-white transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-75">
                                <template x-if="busy">
                                    <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                </template>
                                <template x-if="!busy">
                                    <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </template>
                                <span x-text="busy ? (statusText || 'Submitting…') : 'Punch In with this Location'">Punch In with this Location</span>
                            </button>
                        @elseif (! $record->isCheckedOut())
                            <button type="button"
                                    :disabled="busy"
                                    @click="submitPosition('checkOut')"
                                    class="w-full rounded-2xl bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-700 hover:to-amber-700 active:scale-95 py-3 px-4 text-xs font-extrabold text-white transition shadow-md shadow-rose-600/20 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-75">
                                <template x-if="busy">
                                    <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                </template>
                                <template x-if="!busy">
                                    <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                </template>
                                <span x-text="busy ? (statusText || 'Submitting…') : 'Punch Out with this Location'">Punch Out with this Location</span>
                            </button>
                        @endif
                        <button type="button"
                                @click="showAccuracyModal = false"
                                class="w-full rounded-2xl bg-slate-100 hover:bg-slate-200 active:scale-95 py-2.5 text-xs font-bold text-slate-700 transition cursor-pointer">
                            Close
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Main Tactile Punch Card --}}
    @if (! $record)
        {{-- Ready to Check-In --}}
        <div wire:key="punch-card-check-in" class="card p-6 sm:p-8 shadow-sm border border-slate-200/80 rounded-3xl bg-white text-center relative overflow-hidden">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Current Local Time</div>
            <div class="mt-1 font-mono text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight" x-text="currentTime">
                {{ now()->timezone(config('attendance.timezone'))->format('h:i:s A') }}
            </div>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Acquire authentic GPS satellites to register your daily attendance.</p>

            {{-- Large Tactile Circular Punch In Button --}}
            <div class="my-8 flex items-center justify-center">
                <button type="button"
                        :disabled="busy"
                        @click="capture('checkIn')"
                        class="group relative flex h-48 w-48 sm:h-52 sm:w-52 items-center justify-center rounded-full bg-gradient-to-tr from-emerald-600 via-emerald-500 to-teal-400 p-1 text-white shadow-xl shadow-emerald-500/25 transition-all duration-300 hover:scale-105 active:scale-95 disabled:opacity-95 disabled:cursor-not-allowed cursor-pointer">
                    
                    {{-- Ambient animated pulse ring --}}
                    <span class="absolute inset-0 -z-10 rounded-full bg-emerald-500/20 blur-md transition group-hover:bg-emerald-500/30"></span>
                    <span x-show="busy" x-cloak class="absolute -inset-2 rounded-full border-2 border-emerald-400 border-dashed animate-spin"></span>

                    {{-- Inner tactile surface --}}
                    <div class="flex h-full w-full flex-col items-center justify-center rounded-full border-4 border-white/30 bg-emerald-600/30 backdrop-blur-xs transition group-hover:bg-emerald-600/40">
                        <template x-if="!busy">
                            <div class="flex flex-col items-center justify-center">
                                <svg class="h-12 w-12 text-white/95 transition duration-300 group-hover:scale-110 drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="mt-2 text-base font-extrabold uppercase tracking-wider drop-shadow-sm">CHECK IN</span>
                                <span class="text-[11px] font-medium text-emerald-100">Tap to Punch GPS</span>
                            </div>
                        </template>
                        <template x-if="busy">
                            <div class="flex flex-col items-center justify-center px-4 text-center">
                                <svg class="h-10 w-10 animate-spin text-white mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span class="text-xs font-bold uppercase tracking-wider leading-tight" x-text="statusText || 'Acquiring…'">Acquiring…</span>
                            </div>
                        </template>
                    </div>
                </button>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-2">
                <div class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3.5 py-1.5 text-[11px] font-medium text-slate-600 border border-slate-200/60">
                    <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Hardware GPS satellite verification active</span>
                </div>
                <button type="button"
                        :disabled="busy"
                        @click="testGpsAccuracy()"
                        class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 hover:bg-indigo-100 active:scale-95 px-3 py-1.5 text-[11px] font-bold text-indigo-700 transition cursor-pointer border border-indigo-200/60 shadow-2xs">
                    <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke-width="2"/></svg>
                    <span>View GPS Accuracy Pop-up</span>
                </button>
            </div>
        </div>
    @elseif (! $record->isCheckedOut())
        {{-- On Duty (Ready to Check-Out) --}}
        <div wire:key="punch-card-check-out" class="card p-6 sm:p-8 shadow-sm border border-emerald-200/80 rounded-3xl bg-gradient-to-b from-emerald-50/40 via-white to-white text-center relative overflow-hidden">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Shift Elapsed</div>
            <div class="mt-1 font-mono text-3xl sm:text-4xl font-extrabold text-indigo-700 tracking-tight" x-text="formattedElapsed">
                {{ $record->workingLabel() ?? 'Active' }}
            </div>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">When your field route is completed, tap the button below to end shift.</p>

            {{-- Large Tactile Circular Punch Out Button --}}
            <div class="my-8 flex items-center justify-center">
                <button type="button"
                        :disabled="busy"
                        @click="capture('checkOut')"
                        class="group relative flex h-48 w-48 sm:h-52 sm:w-52 items-center justify-center rounded-full bg-gradient-to-tr from-rose-600 via-rose-500 to-amber-500 p-1 text-white shadow-xl shadow-rose-500/25 transition-all duration-300 hover:scale-105 active:scale-95 disabled:opacity-95 disabled:cursor-not-allowed cursor-pointer">
                    
                    {{-- Ambient animated pulse ring --}}
                    <span class="absolute inset-0 -z-10 rounded-full bg-rose-500/20 blur-md transition group-hover:bg-rose-500/30"></span>
                    <span x-show="busy" x-cloak class="absolute -inset-2 rounded-full border-2 border-rose-400 border-dashed animate-spin"></span>

                    {{-- Inner tactile surface --}}
                    <div class="flex h-full w-full flex-col items-center justify-center rounded-full border-4 border-white/30 bg-rose-600/30 backdrop-blur-xs transition group-hover:bg-rose-600/40">
                        <template x-if="!busy">
                            <div class="flex flex-col items-center justify-center">
                                <svg class="h-12 w-12 text-white/95 transition duration-300 group-hover:scale-110 drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <span class="mt-2 text-base font-extrabold uppercase tracking-wider drop-shadow-sm">CHECK OUT</span>
                                <span class="text-[11px] font-medium text-rose-100">End Work Shift</span>
                            </div>
                        </template>
                        <template x-if="busy">
                            <div class="flex flex-col items-center justify-center px-4 text-center">
                                <svg class="h-10 w-10 animate-spin text-white mb-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span class="text-xs font-bold uppercase tracking-wider leading-tight" x-text="statusText || 'Acquiring…'">Acquiring…</span>
                            </div>
                        </template>
                    </div>
                </button>
            </div>

            <div class="mb-4 flex items-center justify-center">
                <button type="button"
                        :disabled="busy"
                        @click="testGpsAccuracy()"
                        class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 hover:bg-indigo-100 active:scale-95 px-3 py-1.5 text-[11px] font-bold text-indigo-700 transition cursor-pointer border border-indigo-200/60 shadow-2xs">
                    <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke-width="2"/></svg>
                    <span>View Live GPS Accuracy Pop-up</span>
                </button>
            </div>

            {{-- Metrics Row --}}
            <div class="grid grid-cols-3 gap-2.5 text-left border-t border-slate-100 pt-5">
                <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Check-In</div>
                    <div class="mt-1 text-sm font-extrabold text-slate-900">
                        {{ $record->check_in_at->timezone(config('attendance.timezone'))->format('h:i A') }}
                    </div>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Check-Out</div>
                    <div class="mt-1 text-sm font-extrabold text-slate-400">
                        Pending
                    </div>
                </div>
                <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Precision</div>
                    <div class="mt-1 text-sm font-extrabold text-emerald-700">
                        &plusmn;{{ round($record->check_in_accuracy ?? 0) }}m
                    </div>
                </div>
            </div>

            @if ($record->check_in_address)
                <div class="mt-3 text-xs text-slate-600 bg-slate-50 p-3 rounded-2xl border border-slate-100 flex items-start gap-2 text-left">
                    <svg class="h-4 w-4 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <div class="flex-1 min-w-0">
                        <span class="font-medium text-slate-800">{{ $record->check_in_address }}</span>
                    </div>
                    <a href="https://www.google.com/maps?q={{ $record->check_in_latitude }},{{ $record->check_in_longitude }}"
                       target="_blank"
                       class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 shrink-0">
                        Map &rarr;
                    </a>
                </div>
            @endif
        </div>
    @else
        {{-- Completed Day Card --}}
        <div wire:key="punch-card-completed" class="card p-6 sm:p-8 shadow-sm border border-emerald-200/80 rounded-3xl bg-white text-center space-y-6">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-8 ring-emerald-50/50">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Shift Completed!</h2>
                <p class="text-xs text-slate-500 mt-1">Great job! Your shift hours and verified GPS locations have been archived.</p>
            </div>

            <div class="grid grid-cols-3 gap-3 text-left">
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Check-In</div>
                    <div class="mt-1 text-sm font-extrabold text-slate-900">
                        {{ $record->check_in_at->timezone(config('attendance.timezone'))->format('h:i A') }}
                    </div>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Check-Out</div>
                    <div class="mt-1 text-sm font-extrabold text-slate-900">
                        {{ $record->check_out_at->timezone(config('attendance.timezone'))->format('h:i A') }}
                    </div>
                </div>
                <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-100">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Duration</div>
                    <div class="mt-1 text-sm font-extrabold text-indigo-700">
                        {{ $record->workingLabel() }}
                    </div>
                </div>
            </div>

            @if ($record->check_out_address || $record->check_in_address)
                <div class="text-xs text-slate-600 bg-slate-50/80 p-3.5 rounded-2xl border border-slate-100 text-left space-y-2">
                    @if ($record->check_in_address)
                        <div class="flex items-start gap-2">
                            <span class="badge-emerald text-[10px] font-bold shrink-0">In</span>
                            <span class="truncate flex-1">{{ $record->check_in_address }}</span>
                        </div>
                    @endif
                    @if ($record->check_out_address)
                        <div class="flex items-start gap-2">
                            <span class="badge-indigo text-[10px] font-bold shrink-0">Out</span>
                            <span class="truncate flex-1">{{ $record->check_out_address }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Upcoming Visits (PJP Route) --}}
    @if ($upcoming->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold text-slate-900">Planned Retailer Route (PJP)</h2>
                    <span class="badge-indigo text-[10px] font-bold">Today</span>
                </div>
                <a href="{{ route('pjp') }}" wire:navigate class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                    Full Schedule &rarr;
                </a>
            </div>

            <div class="space-y-2.5">
                @foreach ($upcoming as $d)
                    @php $isToday = $d->plan_date->isToday(); @endphp
                    <div class="card !p-4 transition rounded-2xl {{ $isToday ? 'border-indigo-300 ring-2 ring-indigo-200/50 bg-indigo-50/20' : '' }}">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-900">{{ $d->plan_date->format('l, d M') }}</span>
                                @if ($isToday)
                                    <span class="badge-indigo text-[10px] font-bold">Today</span>
                                @endif
                            </div>
                            <span class="text-xs font-semibold text-slate-500">
                                {{ count($d->visited_codes) }} / {{ $d->retailers->count() }} Visited
                            </span>
                        </div>

                        <ul class="space-y-1.5 text-xs">
                            @foreach ($d->retailers as $r)
                                @php $isVisited = in_array($r->rt_code, $d->visited_codes, true); @endphp
                                <li class="flex items-center justify-between rounded-xl p-2 {{ $isVisited ? 'bg-emerald-50/60' : 'bg-slate-50/60' }}">
                                    <span class="truncate">
                                        <span class="font-mono font-bold text-slate-700">{{ $r->rt_code }}</span>
                                        <span class="text-slate-600 ml-1">{{ $r->rt_name }}</span>
                                    </span>
                                    @if ($isVisited)
                                        <span class="badge-emerald text-[10px] font-bold">Visited ✓</span>
                                    @else
                                        <span class="badge-slate text-[10px]">Planned</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recent Attendance Log (History) --}}
    @if (isset($recentHistory) && $recentHistory->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900">Recent Attendance Log</h2>
                @can('attendance.view_all')
                    <a href="{{ route('attendance.report') }}" wire:navigate class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                        All Reports &rarr;
                    </a>
                @endcan
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden divide-y divide-slate-100">
                @foreach ($recentHistory as $past)
                    <div class="p-3.5 flex items-center justify-between gap-3 text-xs hover:bg-slate-50/70 transition">
                        <div>
                            <div class="font-bold text-slate-900">{{ $past->attendance_date?->format('D, d M Y') }}</div>
                            <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                                <span>In: {{ $past->check_in_at?->timezone(config('attendance.timezone'))->format('h:i A') ?? '—' }}</span>
                                <span>&bull;</span>
                                <span>Out: {{ $past->check_out_at?->timezone(config('attendance.timezone'))->format('h:i A') ?? '—' }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono font-bold text-indigo-600 text-xs">{{ $past->workingLabel() ?? '—' }}</div>
                            <span class="badge-emerald text-[10px] font-semibold mt-1">Present</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

