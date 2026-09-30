<div class="space-y-6" @if ($isToday) wire:poll.60s @endif>
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                @if ($mode === 'path')
                    <button type="button" wire:click="backToAll" class="text-slate-400 hover:text-indigo-600 transition" title="Back to all field staff">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Salesman GPS Path</h1>
                @else
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Live Tracking</h1>
                @endif
                @if ($isToday)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live · refreshes every minute
                    </span>
                @endif
            </div>
            <p class="mt-1 text-xs text-slate-500">
                @if ($mode === 'path')
                    {{ $person?->name }} @if ($person?->reportsTo) &bull; ASM {{ $person->reportsTo->name }} @endif &bull; {{ \Illuminate\Support\Carbon::parse($date)->format('D, d M Y') }}
                @else
                    Field employees' GPS location, distance travelled and retailer visits for {{ \Illuminate\Support\Carbon::parse($date)->format('D, d M Y') }}.
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2 print:hidden">
            @can('exports.view')
                <button class="btn-ghost text-xs" wire:click="export">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span>Excel (CSV)</span>
                </button>
            @endcan
            <button class="btn-ghost text-xs" onclick="window.print()">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                <span>Print</span>
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card print:hidden">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Date</label>
                <div class="flex items-center gap-1.5">
                    <button type="button" class="btn-ghost !px-2.5 text-xs" wire:click="shiftDay(-1)" title="Previous day">&lsaquo;</button>
                    <input type="date" class="input text-xs" wire:model.live="date" max="{{ now($tz)->toDateString() }}">
                    <button type="button" class="btn-ghost !px-2.5 text-xs" wire:click="shiftDay(1)" title="Next day" @disabled($isToday)>&rsaquo;</button>
                </div>
            </div>
            <div>
                <label class="label">Field Officer (TSO)</label>
                <select class="input text-xs" wire:model.live="userId">
                    <option value="">All field staff</option>
                    @foreach ($userOptions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            @if ($mode === 'overview')
                <div>
                    <label class="label">Status</label>
                    <select class="input text-xs" wire:model.live="status">
                        <option value="">All statuses</option>
                        <option value="live">Live (seen in last {{ $liveMinutes }} min)</option>
                        <option value="idle">No recent signal</option>
                        <option value="checked_out">Checked out</option>
                    </select>
                </div>
            @endif
        </div>
    </div>

    {{-- Summary cards --}}
    @if ($mode === 'overview')
        <div class="grid gap-3 grid-cols-2 lg:grid-cols-6">
            @foreach ([
                ['Live now', $counts['live'], 'text-emerald-600'],
                ['No recent signal', $counts['idle'], 'text-amber-600'],
                ['Checked out', $counts['checked_out'], 'text-slate-700'],
                ['Not checked in', $counts['absent'], 'text-rose-600'],
                ['Total distance', $counts['km'].' km', 'text-indigo-600'],
                ['Retailer visits', $counts['visits'], 'text-sky-600'],
            ] as [$label, $value, $colour])
                <div class="card !p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</div>
                    <div class="mt-1 text-xl font-extrabold {{ $colour }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    @elseif ($attendance)
        <div class="grid gap-3 grid-cols-2 lg:grid-cols-6">
            @foreach ([
                ['Distance travelled', ($activity['km'] ?? 0).' km', 'text-indigo-600'],
                ['Check in', $attendance->check_in_at?->timezone($tz)->format('h:i A') ?? '—', 'text-emerald-600'],
                ['Check out', $attendance->check_out_at?->timezone($tz)->format('h:i A') ?? 'On duty', 'text-slate-700'],
                ['Working time', $attendance->workingLabel() ?? ($attendance->check_in_at ? intdiv((int) $attendance->check_in_at->diffInMinutes(now()), 60).'h '.((int) $attendance->check_in_at->diffInMinutes(now()) % 60).'m' : '—'), 'text-slate-700'],
                ['Retailer visits', count($activity['visits'] ?? []), 'text-sky-600'],
                ['Stops', count($activity['stops'] ?? []), 'text-amber-600'],
            ] as [$label, $value, $colour])
                <div class="card !p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</div>
                    <div class="mt-1 text-xl font-extrabold {{ $colour }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        @if ($summary)
            <div class="flex flex-wrap items-center gap-2 text-xs">
                @if ($summary['status'] === 'live')
                    <span class="badge-emerald">Live · last seen {{ $summary['last_seen']?->timezone($tz)->format('h:i A') }}</span>
                @elseif ($summary['status'] === 'idle')
                    <span class="badge-amber">No signal since {{ $summary['last_seen']?->timezone($tz)->format('h:i A') }} ({{ $summary['last_seen']?->diffForHumans() }})</span>
                @else
                    <span class="badge-slate">Shift completed</span>
                @endif
                @if ($summary['battery'] !== null)
                    <span class="badge-slate">Battery {{ $summary['battery'] }}%</span>
                @endif
                <span class="badge-slate">{{ number_format($activity['pings'] ?? 0) }} GPS points</span>
            </div>
        @endif
    @endif

    {{-- Map --}}
    @if ($mapsEnabled)
        <div class="card !p-0 overflow-hidden relative"
             wire:key="tracking-map"
             x-data="trackingMap({ apiKey: @js($apiKey), centre: @js($centre) })">
            <div x-ref="payload" class="hidden" data-payload="{{ json_encode($mapPayload) }}"></div>
            <div x-show="error" x-cloak class="border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800 font-medium" x-text="error"></div>
            <div x-ref="map" wire:ignore class="h-[60vh] min-h-[380px] w-full bg-slate-100"></div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 px-4 py-2 text-[11px] text-slate-500">
                @if ($mode === 'overview')
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Live</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> No recent signal</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-500"></span> Checked out</span>
                    <span>Click a marker to open that person's path.</span>
                @else
                    <span class="flex items-center gap-1.5"><span class="h-1 w-5 rounded bg-red-600"></span> Route travelled</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Check-in</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-600"></span> Check-out</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span> Current position</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span> Retailer visit</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Stop</span>
                @endif
            </div>
        </div>
    @else
        <div class="card bg-slate-50 text-xs text-slate-600">
            <div class="font-bold text-slate-800 mb-1">Google Maps API key not configured</div>
            <p>
                The map needs a Google Maps JavaScript API key; the tables below still work without it.
                @if (auth()->user()?->hasRole('Super Admin'))
                    Add one under <a href="{{ route('settings.maps') }}" wire:navigate class="text-indigo-600 font-bold hover:underline">Settings &rarr; Map settings</a>.
                @endif
            </p>
        </div>
    @endif

    @if ($mode === 'overview')
        {{-- Everyone on the date --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="th">Field Officer</th>
                            <th class="th">ASM</th>
                            <th class="th">Status</th>
                            <th class="th">Check In</th>
                            <th class="th">Check Out</th>
                            <th class="th">Last Seen</th>
                            <th class="th text-right">Distance</th>
                            <th class="th text-right">Visits</th>
                            <th class="th text-right print:hidden">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr wire:key="trk-{{ $r['user_id'] }}" class="hover:bg-slate-50/70">
                                <td class="td font-semibold text-slate-900">{{ $r['name'] }}</td>
                                <td class="td text-slate-500">{{ $r['asm'] ?: '—' }}</td>
                                <td class="td">
                                    @if ($r['status'] === 'live') <span class="badge-emerald">Live</span>
                                    @elseif ($r['status'] === 'idle') <span class="badge-amber">No signal</span>
                                    @else <span class="badge-slate">Checked out</span>
                                    @endif
                                </td>
                                <td class="td">{{ $r['attendance']->check_in_at?->timezone($tz)->format('h:i A') ?? '—' }}</td>
                                <td class="td">{{ $r['attendance']->check_out_at?->timezone($tz)->format('h:i A') ?? '—' }}</td>
                                <td class="td text-slate-500">
                                    {{ $r['last_seen']?->timezone($tz)->format('h:i A') ?? '—' }}
                                    @if ($r['battery'] !== null) <span class="text-[10px] text-slate-400">· {{ $r['battery'] }}%</span> @endif
                                </td>
                                <td class="td text-right font-semibold">{{ number_format($r['km'], 2) }} km</td>
                                <td class="td text-right">{{ $r['visits'] }}</td>
                                <td class="td text-right print:hidden">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($r['last_lat'] !== null)
                                            <button type="button" title="Show on map" class="text-emerald-600 hover:text-emerald-700"
                                                    @click="$dispatch('tracking-focus', { lat: {{ $r['last_lat'] }}, lng: {{ $r['last_lng'] }} })">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a7 7 0 00-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 00-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                                            </button>
                                        @endif
                                        <button type="button" class="btn-ghost !py-1 !px-2.5 text-xs font-semibold text-indigo-600" wire:click="showUser({{ $r['user_id'] }})">
                                            View path
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="td text-center text-slate-400 py-10">No field staff checked in on this date.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif (! $attendance)
        <div class="card text-center text-sm text-slate-500 py-10">
            {{ $person?->name ?? 'This user' }} did not check in on {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}.
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-5">
            {{-- Activity timeline --}}
            <div class="card lg:col-span-2">
                <h2 class="text-sm font-bold text-slate-900">Day activity</h2>
                <p class="text-[11px] text-slate-500 mb-4">Check-in, retailer visits, stops and check-out in order.</p>
                <ol class="relative border-l border-slate-200 ml-2 space-y-4">
                    @foreach ($activity['timeline'] as $e)
                        @php
                            $dot = ['check_in' => 'bg-emerald-500', 'visit' => 'bg-indigo-600', 'stop' => 'bg-amber-500', 'check_out' => 'bg-rose-600'][$e['type']] ?? 'bg-slate-400';
                        @endphp
                        <li class="ml-4" wire:key="tl-{{ $loop->index }}">
                            <span class="absolute -left-1.5 mt-1 h-3 w-3 rounded-full ring-4 ring-white {{ $dot }}"></span>
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="text-xs font-semibold text-slate-900">{{ $e['title'] }}</div>
                                <div class="shrink-0 text-[11px] font-mono text-slate-500">{{ $e['at']->timezone($tz)->format('h:i A') }}</div>
                            </div>
                            @if ($e['detail']) <div class="text-[11px] text-slate-500 mt-0.5">{{ $e['detail'] }}</div> @endif
                            @if ($e['lat'] !== null)
                                <button type="button" class="text-[11px] text-indigo-600 hover:underline mt-0.5 print:hidden"
                                        @click="$dispatch('tracking-focus', { lat: {{ $e['lat'] }}, lng: {{ $e['lng'] }} })">Show on map</button>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Visits / meeting locations --}}
            <div class="lg:col-span-3 space-y-6">
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                    <div class="px-4 pt-4 pb-2">
                        <h2 class="text-sm font-bold text-slate-900">Visit details &amp; meeting locations</h2>
                        <p class="text-[11px] text-slate-500">"At store" means the visit was logged within {{ (int) config('tracking.visit_match_metres') }} m of the retailer's saved location.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr>
                                    <th class="th">Time</th>
                                    <th class="th">Retailer</th>
                                    <th class="th">Location check</th>
                                    <th class="th">Note</th>
                                    <th class="th text-right print:hidden">Map</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activity['visits'] as $v)
                                    <tr wire:key="v-{{ $loop->index }}">
                                        <td class="td font-mono">{{ $v['at']->timezone($tz)->format('h:i A') }}</td>
                                        <td class="td">
                                            <div class="font-semibold text-slate-900">{{ $v['name'] ?: '—' }}</div>
                                            <div class="font-mono text-[11px] text-indigo-700">{{ $v['rt_code'] }} @if ($v['area']) <span class="text-slate-400 font-sans">· {{ $v['area'] }}</span> @endif</div>
                                        </td>
                                        <td class="td">
                                            @if ($v['at_store'] === true) <span class="badge-emerald">At store · {{ $v['offset_metres'] }} m</span>
                                            @elseif ($v['at_store'] === false) <span class="badge-rose">{{ number_format($v['offset_metres']) }} m away</span>
                                            @elseif ($v['lat'] === null) <span class="badge-slate">No GPS</span>
                                            @else <span class="badge-slate">Store not mapped</span>
                                            @endif
                                        </td>
                                        <td class="td text-slate-500 whitespace-normal max-w-[16rem]">{{ $v['note'] ?: '—' }}</td>
                                        <td class="td text-right print:hidden">
                                            @if ($v['lat'] !== null)
                                                <button type="button" class="text-indigo-600 hover:underline"
                                                        @click="$dispatch('tracking-focus', { lat: {{ $v['lat'] }}, lng: {{ $v['lng'] }} })">Show</button>
                                                <a class="ml-2 text-slate-400 hover:text-indigo-600" target="_blank" rel="noopener"
                                                   href="https://www.google.com/maps?q={{ $v['lat'] }},{{ $v['lng'] }}">&nearr;</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="td text-center text-slate-400 py-8">No retailer visits logged on this day.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Recent days --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                    <div class="px-4 pt-4 pb-2">
                        <h2 class="text-sm font-bold text-slate-900">Day-to-day activity (last 14 days)</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr>
                                    <th class="th">Date</th>
                                    <th class="th">In</th>
                                    <th class="th">Out</th>
                                    <th class="th">Working</th>
                                    <th class="th text-right">Distance</th>
                                    <th class="th text-right">Visits</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentDays as $d)
                                    <tr wire:key="day-{{ $d['date'] }}"
                                        class="cursor-pointer hover:bg-slate-50 {{ $d['date'] === $date ? 'bg-indigo-50/60' : '' }}"
                                        wire:click="$set('date', '{{ $d['date'] }}')">
                                        <td class="td font-semibold">{{ \Illuminate\Support\Carbon::parse($d['date'])->format('D, d M') }}</td>
                                        <td class="td">{{ $d['check_in']?->timezone($tz)->format('h:i A') ?? '—' }}</td>
                                        <td class="td">{{ $d['check_out']?->timezone($tz)->format('h:i A') ?? '—' }}</td>
                                        <td class="td">{{ $d['working'] ?? '—' }}</td>
                                        <td class="td text-right font-semibold">{{ number_format($d['km'], 2) }} km</td>
                                        <td class="td text-right">{{ $d['visits'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@once
    <script>
        window.__loadGmaps = window.__loadGmaps || function () {
            if (window.__gmapsPromise) return window.__gmapsPromise;
            if (! window.__gmapsKey) return Promise.reject(new Error('no-key'));
            window.__gmapsPromise = new Promise((resolve, reject) => {
                window.__gmapsReady = () => resolve();
                window.gm_authFailure = () => reject(new Error('auth'));
                const s = document.createElement('script');
                s.src = 'https://maps.googleapis.com/maps/api/js?key='
                    + encodeURIComponent(window.__gmapsKey)
                    + '&loading=async&callback=__gmapsReady';
                s.async = true;
                s.onerror = () => reject(new Error('load-failed'));
                document.head.appendChild(s);
            });
            return window.__gmapsPromise;
        };

        window.__registerTrackingMap = window.__registerTrackingMap || function () {
            if (window.__trackingMapRegistered || ! window.Alpine) { return; }
            window.__trackingMapRegistered = true;

            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const dot = (color, scale = 8) => ({
                path: google.maps.SymbolPath.CIRCLE, scale, fillColor: color, fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2,
            });
            const statusColour = { live: '#10b981', idle: '#f59e0b', checked_out: '#64748b' };

            Alpine.data('trackingMap', (cfg) => ({
                map: null,
                info: null,
                overlays: [],
                fittedKey: null,
                observer: null,
                error: null,

                async init() {
                    window.__gmapsKey = window.__gmapsKey || cfg.apiKey;
                    try {
                        await window.__loadGmaps();
                    } catch (e) {
                        this.error = {
                            'no-key': 'Google Maps API key is not configured. Add one in Settings → Map settings.',
                            'auth': 'Google Maps rejected the API key. Check that the Maps JavaScript API is enabled and this domain is allowed.',
                            'load-failed': 'Google Maps failed to load due to a network error or script blocker.',
                        }[e.message] || 'Google Maps failed to load (' + e.message + ').';
                        return;
                    }

                    this.map = new google.maps.Map(this.$refs.map, {
                        center: cfg.centre, zoom: 7, streetViewControl: false, fullscreenControl: true, mapTypeControl: true,
                    });
                    this.info = new google.maps.InfoWindow();
                    this.draw();

                    this.observer = new MutationObserver(() => this.draw());
                    this.observer.observe(this.$refs.payload, { attributes: true, attributeFilter: ['data-payload'] });

                    this._focus = (e) => {
                        if (! this.map || ! e.detail) return;
                        this.map.panTo({ lat: +e.detail.lat, lng: +e.detail.lng });
                        this.map.setZoom(17);
                        this.$refs.map.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    };
                    window.addEventListener('tracking-focus', this._focus);
                },

                destroy() {
                    this.observer?.disconnect();
                    window.removeEventListener('tracking-focus', this._focus);
                },

                payload() {
                    try { return JSON.parse(this.$refs.payload.dataset.payload || '{}'); } catch (e) { return {}; }
                },

                marker(pos, icon, title, html, zIndex = 1) {
                    const m = new google.maps.Marker({ position: pos, map: this.map, icon, title, zIndex });
                    if (html) {
                        m.addListener('click', () => { this.info.setContent(html); this.info.open(this.map, m); });
                    }
                    this.overlays.push(m);
                    return m;
                },

                draw() {
                    if (! this.map) return;
                    const p = this.payload();
                    this.overlays.forEach((o) => o.setMap(null));
                    this.overlays = [];
                    const bounds = new google.maps.LatLngBounds();
                    const box = (title, lines) => '<div style="font-size:12px;color:#1e293b;max-width:240px"><div style="font-weight:700;margin-bottom:2px">' + esc(title) + '</div>' + lines.map((l) => '<div style="color:#64748b">' + esc(l) + '</div>').join('') + '</div>';

                    if (p.mode === 'overview') {
                        (p.people || []).forEach((person) => {
                            const pos = { lat: person.lat, lng: person.lng };
                            const node = document.createElement('div');
                            node.innerHTML = box(person.name, [
                                { live: 'Live', idle: 'No recent signal', checked_out: 'Checked out' }[person.status] + (person.seen ? ' · ' + person.seen : ''),
                                person.km + ' km · ' + person.visits + ' visit(s)',
                            ]);
                            const btn = document.createElement('button');
                            btn.textContent = 'View GPS path →';
                            btn.style.cssText = 'margin-top:6px;color:#4f46e5;font-weight:600;font-size:11px;cursor:pointer;background:none;border:0;padding:0';
                            btn.onclick = () => this.$wire.showUser(person.id);
                            node.appendChild(btn);

                            const m = this.marker(pos, dot(statusColour[person.status] || '#64748b', 10), person.name, node, person.status === 'live' ? 3 : 2);
                            m.setLabel({ text: (person.name || '?').trim().charAt(0).toUpperCase(), color: '#ffffff', fontSize: '10px', fontWeight: '700' });
                            bounds.extend(pos);
                        });
                    } else {
                        const path = (p.path || []).map((pt) => ({ lat: pt.lat, lng: pt.lng }));
                        if (path.length > 1) {
                            this.overlays.push(new google.maps.Polyline({
                                path, map: this.map, strokeColor: '#dc2626', strokeOpacity: 0.9, strokeWeight: 4,
                                icons: [{ icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, scale: 2.5, strokeColor: '#991b1b', fillColor: '#991b1b', fillOpacity: 1 }, offset: '30px', repeat: '90px' }],
                            }));
                        }
                        path.forEach((pt) => bounds.extend(pt));

                        (p.stops || []).forEach((s) => {
                            this.marker({ lat: s.lat, lng: s.lng }, dot('#f59e0b', 7), s.label, box(s.label, [s.time]), 2);
                            bounds.extend({ lat: s.lat, lng: s.lng });
                        });
                        (p.visits || []).forEach((v) => {
                            this.marker({ lat: v.lat, lng: v.lng }, dot('#4f46e5', 8), v.label,
                                box(v.label, ['Visited ' + v.time, v.atStore === true ? 'At the store' : (v.atStore === false ? 'Away from the store location' : '')]), 4);
                            bounds.extend({ lat: v.lat, lng: v.lng });
                        });
                        if (p.checkIn) {
                            this.marker({ lat: p.checkIn.lat, lng: p.checkIn.lng }, dot('#10b981', 9), p.checkIn.label, box('Start', [p.checkIn.label]), 5);
                            bounds.extend(p.checkIn);
                        }
                        if (p.checkOut) {
                            this.marker({ lat: p.checkOut.lat, lng: p.checkOut.lng }, dot('#e11d48', 9), p.checkOut.label, box('End', [p.checkOut.label]), 5);
                            bounds.extend(p.checkOut);
                        }
                        if (p.current) {
                            this.marker({ lat: p.current.lat, lng: p.current.lng }, dot('#0ea5e9', 10), p.current.label, box('Current position', [p.current.label]), 6);
                            bounds.extend(p.current);
                        }
                    }

                    // Refit only when switching person / date, so a live refresh keeps the manager's zoom.
                    if (this.fittedKey !== p.key && ! bounds.isEmpty()) {
                        this.map.fitBounds(bounds, 48);
                        google.maps.event.addListenerOnce(this.map, 'idle', () => { if (this.map.getZoom() > 17) this.map.setZoom(17); });
                    }
                    this.fittedKey = p.key;
                },
            }));
        };

        document.addEventListener('alpine:init', window.__registerTrackingMap);
        window.__registerTrackingMap();
    </script>
@endonce
