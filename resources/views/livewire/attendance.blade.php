<div class="mx-auto max-w-lg space-y-6"
     x-data="{
        busy: false,
        statusText: '',
        capture(action) {
            this.busy = true;
            this.statusText = 'Connecting to GPS…';

            if (! ('geolocation' in navigator)) {
                this.busy = false;
                return $wire.reportGpsError('This device browser does not support GPS location. Please open in Chrome or Safari with location allowed.');
            }

            if (navigator.webdriver) {
                this.busy = false;
                return $wire.reportGpsError('Automated browser environment detected. Please open on a mobile phone.');
            }

            const samples = [];
            let watchId = null;
            let timeoutId = null;
            let completed = false;

            const cleanup = () => {
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
                if (timeoutId !== null) {
                    clearTimeout(timeoutId);
                    timeoutId = null;
                }
            };

            const finish = () => {
                if (completed) return;
                completed = true;
                cleanup();

                if (samples.length === 0) {
                    this.busy = false;
                    return $wire.reportGpsError('Unable to acquire GPS signal. Please move outdoors or near a window and retry.');
                }

                const best = [...samples].sort((a, b) => a.accuracy - b.accuracy)[0];

                if (best.accuracy <= 0.5) {
                    this.busy = false;
                    return $wire.reportGpsError('Suspicious GPS reading (0m accuracy). Mock location apps typically report 0 accuracy. Please disable Developer Options mock location apps and use authentic satellite GPS.');
                }

                // Check for zero-jitter static mock provider
                if (samples.length >= 3) {
                    const first = samples[0];
                    const allIdentical = samples.every(s =>
                        Math.abs(s.latitude - first.latitude) < 0.0000001 &&
                        Math.abs(s.longitude - first.longitude) < 0.0000001 &&
                        Math.abs(s.accuracy - first.accuracy) < 0.0001
                    );
                    const timeSpan = samples[samples.length - 1].timestamp - samples[0].timestamp;
                    if (allIdentical && timeSpan > 800) {
                        this.busy = false;
                        return $wire.reportGpsError('Developer Mock Location detected: Zero GPS jitter. Real satellite GPS signals show natural micro-variations. Please turn off Fake GPS / Mock Location apps in Android Developer Settings.');
                    }
                }

                this.statusText = 'Verifying satellite coordinates…';

                $wire[action](best.latitude, best.longitude, best.accuracy, {
                    samples: samples.map(s => ({
                        lat: s.latitude,
                        lng: s.longitude,
                        accuracy: s.accuracy,
                        t: s.timestamp
                    }))
                }).then(() => {
                    this.busy = false;
                    this.statusText = '';
                }).catch(() => {
                    this.busy = false;
                    this.statusText = '';
                });
            };

            // Fallback after 3.5 seconds to dispatch with available samples
            timeoutId = setTimeout(() => {
                finish();
            }, 3500);

            try {
                watchId = navigator.geolocation.watchPosition(
                    (pos) => {
                        samples.push({
                            latitude: pos.coords.latitude,
                            longitude: pos.coords.longitude,
                            accuracy: pos.coords.accuracy || 0,
                            timestamp: pos.timestamp || Date.now()
                        });

                        this.statusText = `Acquiring satellite fixes (${samples.length}/3)…`;

                        if (samples.length >= 3) {
                            finish();
                        }
                    },
                    (err) => {
                        if (samples.length === 0) {
                            cleanup();
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    samples.push({
                                        latitude: pos.coords.latitude,
                                        longitude: pos.coords.longitude,
                                        accuracy: pos.coords.accuracy || 0,
                                        timestamp: pos.timestamp || Date.now()
                                    });
                                    finish();
                                },
                                (fallbackErr) => {
                                    this.busy = false;
                                    const msg = {
                                        1: 'GPS permission denied. Please allow location access in your browser settings.',
                                        2: 'Location is unavailable right now. Move near a window or outdoors and retry.',
                                        3: 'GPS request timed out. Please try again.',
                                    }[fallbackErr.code || err.code] || 'Unable to retrieve GPS coordinates. Please try again.';
                                    $wire.reportGpsError(msg);
                                },
                                { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
                            );
                        } else {
                            finish();
                        }
                    },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
                );
            } catch (e) {
                cleanup();
                this.busy = false;
                $wire.reportGpsError('Error accessing GPS hardware: ' + e.message);
            }
        }
     }">
    {{-- Header --}}
    <div class="text-center sm:text-left">
        <div class="flex items-center justify-center sm:justify-start gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Field Attendance</h1>
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                GPS Verified
            </span>
        </div>
        <p class="mt-1 text-xs text-slate-500 font-medium">{{ $today->format('l, d F Y') }}</p>
    </div>

    {{-- Error Alert --}}
    @if ($error)
        <div class="rounded-2xl bg-rose-50 p-4 text-xs font-semibold text-rose-800 border border-rose-200 shadow-xs flex items-start gap-3">
            <svg class="h-5 w-5 shrink-0 text-rose-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="flex-1">{{ $error }}</div>
        </div>
    @endif

    {{-- Punch Card --}}
    <div class="card p-6 shadow-sm border border-slate-200/80 space-y-6">
        @if (! $record)
            <div class="text-center py-4">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-amber-50 text-amber-600 ring-8 ring-amber-50/50 mb-4">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="text-base font-bold text-slate-900">Ready to start your work day?</h2>
                <p class="text-xs text-slate-500 mt-1">Capture your current GPS coordinates to register Check-In.</p>
            </div>

            <button class="btn-primary w-full py-3.5 text-sm font-bold shadow-md shadow-indigo-600/20"
                    :disabled="busy" @click="capture('checkIn')">
                <span x-show="!busy" class="flex items-center justify-center gap-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Check In Now</span>
                </span>
                <span x-show="busy" x-cloak class="flex items-center justify-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <span x-text="statusText || 'Acquiring GPS Position…'">Acquiring GPS Position…</span>
                </span>
            </button>
        @else
            <div class="grid grid-cols-2 gap-4 text-xs border-b border-slate-100 pb-4">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Check-In Time</div>
                    <div class="mt-1 text-base font-extrabold text-slate-900">
                        {{ $record->check_in_at->timezone(config('attendance.timezone'))->format('h:i A') }}
                    </div>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">GPS Precision</div>
                    <div class="mt-1 flex items-center gap-1.5 text-base font-extrabold text-emerald-700">
                        <span>Captured</span>
                        @if ($record->check_in_accuracy && $record->check_in_accuracy > $poorAccuracy)
                            <span class="text-[10px] font-normal text-amber-600">(&plusmn;{{ round($record->check_in_accuracy) }}m)</span>
                        @endif
                    </div>
                </div>
                @if ($record->check_in_address)
                    <div class="col-span-2 text-xs text-slate-600 bg-slate-50/50 p-2.5 rounded-lg border border-slate-100 flex items-start gap-1.5">
                        <svg class="h-3.5 w-3.5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        <span>{{ $record->check_in_address }}</span>
                    </div>
                @endif
            </div>

            @if (! $record->isCheckedOut())
                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-xl bg-emerald-50/80 p-3 border border-emerald-200/80">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold text-emerald-900">Active Shift On Duty</span>
                        </div>
                        <span class="text-xs font-semibold text-emerald-700">Field Active</span>
                    </div>

                    <button class="btn-primary w-full py-3.5 text-sm font-bold shadow-md shadow-indigo-600/20"
                            :disabled="busy" @click="capture('checkOut')">
                        <span x-show="!busy" class="flex items-center justify-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            <span>Check Out (End Day)</span>
                        </span>
                        <span x-show="busy" x-cloak class="flex items-center justify-center gap-2">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span x-text="statusText || 'Acquiring GPS Position…'">Acquiring GPS Position…</span>
                        </span>
                    </button>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Check-Out Time</div>
                        <div class="mt-1 text-base font-extrabold text-slate-900">
                            {{ $record->check_out_at->timezone(config('attendance.timezone'))->format('h:i A') }}
                        </div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Duration</div>
                        <div class="mt-1 text-base font-extrabold text-indigo-700">
                            {{ $record->workingLabel() }}
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-emerald-50/80 p-4 text-center border border-emerald-200">
                    <div class="text-xs font-bold text-emerald-900">Daily Attendance Complete</div>
                    <p class="text-[11px] text-emerald-700 mt-0.5">Great job! Your shift hours and GPS log have been archived.</p>
                </div>
            @endif
        @endif
    </div>

    {{-- Upcoming Visits --}}
    @if ($upcoming->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900">Planned Retailer Route (PJP)</h2>
                <a href="{{ route('pjp') }}" wire:navigate class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                    Full Month Schedule &rarr;
                </a>
            </div>

            <div class="space-y-2.5">
                @foreach ($upcoming as $d)
                    @php $isToday = $d->plan_date->isToday(); @endphp
                    <div class="card !p-4 transition {{ $isToday ? 'border-indigo-300 ring-2 ring-indigo-200/50 bg-indigo-50/20' : '' }}">
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
                                <li class="flex items-center justify-between rounded-lg p-1.5 {{ $isVisited ? 'bg-emerald-50/60' : 'bg-slate-50/60' }}">
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
</div>
