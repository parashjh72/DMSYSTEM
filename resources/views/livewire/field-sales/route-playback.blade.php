<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Route Playback</h1>
            <p class="mt-1 text-xs text-slate-500">Replay a field officer's day: travel path, stops and distance. Travel detail only — no allowance amounts.</p>
        </div>
        <a href="{{ route('field-sales.map') }}" wire:navigate class="btn-ghost text-xs">← Live map</a>
    </div>

    {{-- Pickers --}}
    <div class="card">
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="label">Field officer</label>
                <select class="input text-xs" wire:model.live="user">
                    <option value="">Choose a field officer…</option>
                    @foreach ($staffOptions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Date <span class="font-normal text-slate-400">· {{ $bsDate }} BS</span></label>
                <input type="date" class="input text-xs" wire:model.live="date" max="{{ now(config('field_sales.timezone'))->toDateString() }}">
            </div>
        </div>
    </div>

    @if (! $subject)
        <div class="card py-12 text-center text-xs text-slate-400">Choose a field officer to see their route.</div>
    @else
        {{-- Day summary --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            @php
                $stats = [
                    ['Distance', number_format($route?->distance_km ?? 0, 2).' km'],
                    ['Check-in', $attendance?->check_in_at?->setTimezone($tz)->format('h:i A') ?? '—'],
                    ['Check-out', $attendance?->check_out_at?->setTimezone($tz)->format('h:i A') ?? '—'],
                    ['Idle time', intdiv($route?->idle_minutes ?? 0, 60).'h '.(($route?->idle_minutes ?? 0) % 60).'m'],
                    ['GPS points', ($route?->kept_count ?? 0).' used / '.($route?->ping_count ?? 0)],
                ];
            @endphp
            @foreach ($stats as [$label, $value])
                <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3 shadow-xs">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</div>
                    <div class="mt-1 text-lg font-bold text-slate-900">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        @if ($day)
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="badge badge-indigo">{{ \App\Models\FieldSales\AttendanceDay::STATUSES[$day->status][1] ?? 'In progress' }}</span>
                @if ($day->late_minutes)<span class="badge badge-amber">Late by {{ $day->late_minutes }} min</span>@endif
                @if ($day->missed_checkout)<span class="badge badge-rose">Missed check-out</span>@endif
                @if ($day->check_in_geofence === 'outside')
                    <span class="badge badge-rose">Checked in {{ number_format($day->check_in_distance_metres) }} m from {{ $day->checkInGeofence?->name ?? 'assigned point' }}</span>
                @elseif ($day->check_in_geofence === 'inside')
                    <span class="badge badge-emerald">Checked in at {{ $day->checkInGeofence?->name }}</span>
                @endif
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200/80 bg-white p-2 shadow-xs"
             wire:key="playback-{{ $subject->id }}-{{ $date }}"
             x-data="fsRoutePlayback({ path: @js($path), punches: @js($punches) })">
            <template x-if="error"><p class="m-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800" x-text="error"></p></template>
            <div wire:ignore x-ref="map" class="h-[28rem] w-full rounded-xl bg-slate-100 lg:h-[34rem]"></div>

            <div class="flex flex-wrap items-center gap-3 px-2 py-3" x-show="path.length > 1">
                <button type="button" class="btn-primary text-xs" @click="toggle()" x-text="playing ? 'Pause' : 'Play'"></button>
                <input type="range" min="0" :max="path.length - 1" x-model.number="index" @input="seek()" class="min-w-0 flex-1 accent-indigo-600">
                <span class="w-14 text-right font-mono text-xs font-semibold text-slate-700" x-text="timeLabel"></span>
            </div>
            <p class="px-2 pb-2 text-xs text-slate-400" x-show="path.length <= 1">
                No travel recorded for this day. Tracking runs only while the field app is open between check-in and check-out.
            </p>
        </div>
    @endif

    @include('livewire.field-sales.partials.gmaps-loader')
    <script>
        (function () {
            const register = () => {
                if (window.__fsRoutePlaybackRegistered || ! window.Alpine) { return; }
                window.__fsRoutePlaybackRegistered = true;

                Alpine.data('fsRoutePlayback', (cfg) => ({
                    path: cfg.path,
                    error: null,
                    map: null,
                    cursor: null,
                    index: 0,
                    playing: false,
                    timer: null,
                    get timeLabel() {
                        const p = this.path[this.index];
                        if (! p) { return ''; }
                        return new Date(p[2] * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: @js(config('field_sales.timezone')) });
                    },
                    async init() {
                        try {
                            await window.__loadGmaps();
                        } catch (e) {
                            this.error = window.__gmapsErrorText(e);
                            return;
                        }
                        const points = this.path.map((p) => ({ lat: p[0], lng: p[1] }));
                        this.map = new google.maps.Map(this.$refs.map, { center: points[0] || window.__gmapsCentre, zoom: points.length ? 14 : 7, streetViewControl: false, mapTypeControl: false });
                        const bounds = new google.maps.LatLngBounds();

                        if (points.length > 1) {
                            new google.maps.Polyline({ map: this.map, path: points, strokeColor: '#4f46e5', strokeOpacity: 0.85, strokeWeight: 4 });
                        }
                        points.forEach((p) => bounds.extend(p));

                        cfg.punches.forEach((p) => {
                            const position = { lat: p.lat, lng: p.lng };
                            new google.maps.Marker({ map: this.map, position, title: p.label, label: { text: p.type === 'check_in' ? 'IN' : 'OUT', color: '#fff', fontSize: '10px', fontWeight: '700' },
                                icon: { path: google.maps.SymbolPath.CIRCLE, scale: 13, fillColor: p.type === 'check_in' ? '#10b981' : '#f43f5e', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 } });
                            bounds.extend(position);
                        });

                        if (points.length) {
                            this.cursor = new google.maps.Marker({ map: this.map, position: points[0], zIndex: 999,
                                icon: { path: google.maps.SymbolPath.CIRCLE, scale: 7, fillColor: '#4f46e5', fillOpacity: 1, strokeColor: '#fff', strokeWeight: 3 } });
                        }
                        if (! bounds.isEmpty()) {
                            this.map.fitBounds(bounds);
                        }
                    },
                    seek() {
                        const p = this.path[this.index];
                        if (p && this.cursor) { this.cursor.setPosition({ lat: p[0], lng: p[1] }); }
                    },
                    toggle() {
                        if (this.playing) { this.stop(); return; }
                        if (this.index >= this.path.length - 1) { this.index = 0; }
                        this.playing = true;
                        const step = Math.max(1, Math.round(this.path.length / 300));
                        this.timer = setInterval(() => {
                            this.index = Math.min(this.path.length - 1, this.index + step);
                            this.seek();
                            if (this.index >= this.path.length - 1) { this.stop(); }
                        }, 60);
                    },
                    stop() {
                        this.playing = false;
                        clearInterval(this.timer);
                    },
                    destroy() { this.stop(); },
                }));
            };
            document.addEventListener('alpine:init', register);
            register();
        })();
    </script>
</div>
