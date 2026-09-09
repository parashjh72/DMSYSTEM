<div class="mx-auto max-w-md"
     x-data="{
        busy: false,
        capture(action) {
            this.busy = true;
            if (! ('geolocation' in navigator)) {
                this.busy = false;
                return $wire.reportGpsError('This browser does not support location. Use a modern mobile browser.');
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.busy = false;
                    $wire[action](pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                },
                (err) => {
                    this.busy = false;
                    const msg = {
                        1: 'Please allow location access to check in / out.',
                        2: 'Location is unavailable right now. Move to an open area and try again.',
                        3: 'Location timed out. Please try again.',
                    }[err.code] || 'Could not get your location. Please try again.';
                    $wire.reportGpsError(msg);
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }
     }">
    <h1 class="text-xl font-semibold tracking-tight">Today's Attendance</h1>
    <p class="mt-1 text-sm text-gray-500">{{ $today->format('l, d M Y') }}</p>

    @if ($error)
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{{ $error }}</div>
    @endif

    <div class="card mt-4 space-y-4">
        @if (! $record)
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500">Status</div>
                <div class="mt-1 text-lg font-semibold text-amber-600">Not checked in</div>
            </div>
            <button class="btn-primary w-full py-3 text-base" :disabled="busy" @click="capture('checkIn')">
                <span x-show="!busy">Check in</span>
                <span x-show="busy" x-cloak>Getting location…</span>
            </button>
        @else
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Check in</div>
                    <div class="mt-1 font-semibold">{{ $record->check_in_at->timezone(config('attendance.timezone'))->format('h:i A') }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Location</div>
                    <div class="mt-1 font-semibold text-emerald-600">Captured
                        @if ($record->check_in_accuracy && $record->check_in_accuracy > $poorAccuracy)
                            <span class="block text-xs font-normal text-amber-600">low accuracy (±{{ round($record->check_in_accuracy) }}m)</span>
                        @endif
                    </div>
                </div>
                @if ($record->check_in_address)
                    <div class="col-span-2 text-xs text-gray-500">{{ $record->check_in_address }}</div>
                @endif
            </div>

            @if (! $record->isCheckedOut())
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Status</div>
                    <div class="mt-1 text-lg font-semibold text-emerald-600">Checked in</div>
                </div>
                <button class="btn-primary w-full py-3 text-base" :disabled="busy" @click="capture('checkOut')">
                    <span x-show="!busy">Check out</span>
                    <span x-show="busy" x-cloak>Getting location…</span>
                </button>
            @else
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500">Check out</div>
                        <div class="mt-1 font-semibold">{{ $record->check_out_at->timezone(config('attendance.timezone'))->format('h:i A') }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500">Working duration</div>
                        <div class="mt-1 font-semibold">{{ $record->workingLabel() }}</div>
                    </div>
                    @if ($record->check_out_address)
                        <div class="col-span-2 text-xs text-gray-500">{{ $record->check_out_address }}</div>
                    @endif
                </div>
                <div class="rounded-lg bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700">
                    Attendance complete for today.
                </div>
            @endif
        @endif
    </div>

    <p class="mt-3 text-center text-xs text-gray-400">Location is captured automatically from your device — it cannot be entered manually.</p>

    {{-- ---- Upcoming visits (from the TSO's PJP) ---------------------- --}}
    @if ($upcoming->isNotEmpty())
        <div class="mt-6">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">Upcoming visits</h2>
                <a href="{{ route('pjp') }}" wire:navigate class="text-xs text-indigo-600">Open PJP →</a>
            </div>
            <div class="mt-2 space-y-3">
                @foreach ($upcoming as $d)
                    @php $isToday = $d->plan_date->isToday(); @endphp
                    <div class="card p-3 {{ $isToday ? 'ring-1 ring-indigo-300' : '' }}">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold">
                                {{ $d->plan_date->format('D, d M') }}
                                @if ($isToday) <span class="badge ml-1 bg-indigo-100 text-indigo-800">Today</span> @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ count($d->visited_codes) }} / {{ $d->retailers->count() }} visited
                            </div>
                        </div>
                        <ul class="mt-2 space-y-1 text-xs">
                            @foreach ($d->retailers as $r)
                                <li class="flex items-center justify-between">
                                    <span><span class="font-mono">{{ $r->rt_code }}</span> {{ $r->rt_name }}</span>
                                    @if (in_array($r->rt_code, $d->visited_codes, true))
                                        <span class="text-emerald-600">✓ visited</span>
                                    @else
                                        <span class="text-gray-400">planned</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($d->notes) <p class="mt-1 text-xs text-gray-400">{{ $d->notes }}</p> @endif
                    </div>
                @endforeach
            </div>
        </div>
    @elseif (auth()->user()->can('pjp.create'))
        <div class="card mt-6 text-center text-sm text-gray-500">
            No upcoming planned visits. <a href="{{ route('pjp') }}" wire:navigate class="text-indigo-600">Plan your month in PJP →</a>
        </div>
    @endif
</div>
