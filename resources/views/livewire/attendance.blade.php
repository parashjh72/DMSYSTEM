@php
    $user = auth()->user();
    $hour = now(config('attendance.timezone'))->hour;
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $userInitial = strtoupper(substr($user?->name ?? 'U', 0, 1));
@endphp

<script>
window.attendanceTracker = function($wireInstance, config) {
    return {
        busy: false,
        statusText: '',
        clientError: '',
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
        async capture(action) {
            if (this.busy) return;
            this.busy = true;
            this.clientError = '';
            this.statusText = 'Contacting GPS…';

            const wire = $wireInstance || this.$wire;

            // 1. Geolocation Support Check
            if (! ('geolocation' in navigator)) {
                this.busy = false;
                this.statusText = '';
                this.clientError = 'This device browser does not support GPS location. Please open in Chrome or Safari with location allowed.';
                if (wire && typeof wire.reportGpsError === 'function') {
                    try { await wire.reportGpsError(this.clientError); } catch (e) {}
                }
                return;
            }

            const getPosition = (opts) => {
                return new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, opts);
                });
            };

            try {
                this.statusText = 'Acquiring GPS fix…';
                let pos;
                try {
                    pos = await getPosition({ enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
                } catch (gpsErr) {
                    // If satellite GPS times out indoors, gracefully fall back to network/wifi location
                    this.statusText = 'Acquiring location…';
                    pos = await getPosition({ enableHighAccuracy: false, timeout: 8000, maximumAge: 5000 });
                }

                // Check 1: Synthetic 0m or <= 0.5m accuracy (typical mock location marker)
                if (pos.coords.accuracy <= 0.5) {
                    this.busy = false;
                    this.statusText = '';
                    this.clientError = 'Developer Mock Location detected: Suspicious 0m accuracy. Real satellite GPS signals have natural accuracy margins.';
                    if (wire && typeof wire.reportGpsError === 'function') {
                        try { await wire.reportGpsError(this.clientError); } catch (e) {}
                    }
                    return;
                }

                this.statusText = 'Verifying coordinates…';

                const telemetry = {
                    samples: [{
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                        accuracy: pos.coords.accuracy,
                        alt: pos.coords.altitude,
                        t: pos.timestamp
                    }]
                };

                if (!wire || typeof wire[action] !== 'function') {
                    throw new Error('Connection initializing. Please refresh the page and try again.');
                }

                await wire[action](pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy, telemetry);

                this.busy = false;
                this.statusText = '';
            } catch (err) {
                this.busy = false;
                this.statusText = '';
                console.error('Attendance GPS error:', err);
                const msg = {
                    1: 'GPS permission denied. Please allow location access in your browser settings.',
                    2: 'Location is unavailable right now. Move near a window or outdoors and retry.',
                    3: 'GPS request timed out. Please ensure Location is enabled in High Accuracy mode and try again.',
                }[err?.code] || (err?.message ? err.message : 'Could not acquire location. Please try again.');
                
                this.clientError = msg;
                if (wire && typeof wire.reportGpsError === 'function') {
                    try {
                        await wire.reportGpsError(msg);
                    } catch (e) {
                        console.error('Failed to report GPS error to server:', e);
                    }
                }
            }
        }
    };
};
</script>

<div class="mx-auto max-w-xl space-y-6"
     x-data="attendanceTracker($wire, {
        elapsed: {{ $record && !$record->isCheckedOut() && $record->check_in_at ? max(0, now()->diffInSeconds($record->check_in_at)) : 0 }},
        hasActiveRecord: {{ ($record && !$record->isCheckedOut()) ? 'true' : 'false' }}
     })">

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

    {{-- Error Alert --}}
    <div x-show="clientError" x-cloak class="rounded-2xl bg-rose-50 p-4 text-xs font-semibold text-rose-800 border border-rose-200 shadow-xs flex items-start gap-3 animate-headShake">
        <svg class="h-5 w-5 shrink-0 text-rose-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div class="flex-1 leading-relaxed" x-text="clientError"></div>
        <button type="button" @click="clientError = ''" class="text-rose-500 hover:text-rose-700 font-bold ml-2">×</button>
    </div>
    @if ($error)
        <div class="rounded-2xl bg-rose-50 p-4 text-xs font-semibold text-rose-800 border border-rose-200 shadow-xs flex items-start gap-3 animate-headShake">
            <svg class="h-5 w-5 shrink-0 text-rose-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="flex-1 leading-relaxed">{{ $error }}</div>
        </div>
    @endif

    {{-- Main Tactile Punch Card --}}
    @if (! $record)
        {{-- Ready to Check-In --}}
        <div class="card p-6 sm:p-8 shadow-sm border border-slate-200/80 rounded-3xl bg-white text-center relative overflow-hidden">
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

            <div class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3.5 py-1.5 text-[11px] font-medium text-slate-600 border border-slate-200/60">
                <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>Hardware GPS satellite jitter verification active</span>
            </div>
        </div>
    @elseif (! $record->isCheckedOut())
        {{-- On Duty (Ready to Check-Out) --}}
        <div class="card p-6 sm:p-8 shadow-sm border border-emerald-200/80 rounded-3xl bg-gradient-to-b from-emerald-50/40 via-white to-white text-center relative overflow-hidden">
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
        <div class="card p-6 sm:p-8 shadow-sm border border-emerald-200/80 rounded-3xl bg-white text-center space-y-6">
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

