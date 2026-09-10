<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Retailer Map</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ number_format($total) }} retailer{{ $total === 1 ? '' : 's' }} in view ·
                {{ $points->count() }} mapped
                @if ($unmappedCount) · <span class="text-amber-600">{{ number_format($unmappedCount) }} not yet mapped</span> @endif
            </p>
        </div>
    </div>

    <div class="card mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="label">Distributor (RD)</label>
            <select class="input" wire:model.live="rdCode">
                <option value="">All distributors</option>
                @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="label">TSO</label>
            <select class="input" wire:model.live="tso">
                <option value="">All TSOs</option>
                @foreach ($tsoOptions as $name) <option value="{{ $name }}">{{ $name }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="label">Area</label>
            <select class="input" wire:model.live="area">
                <option value="">All areas</option>
                @foreach ($areaOptions as $a) <option value="{{ $a }}">{{ $a }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="label">Retailer (RT code / name)</label>
            <input class="input" wire:model.live.debounce.300ms="search" placeholder="Search RT code or name">
        </div>
    </div>

    @if ($mapsEnabled)
        <div class="card mt-4 p-0"
             wire:key="map-{{ md5($rdCode.'|'.$area.'|'.$tso.'|'.$search) }}"
             x-data="retailerMap({
                 apiKey: @js($apiKey),
                 centre: @js($centre),
                 points: @js($points->map(fn ($p) => [
                     'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude,
                     'code' => $p->code, 'name' => $p->name, 'rd' => $p->rd_code, 'area' => $p->area, 'phone' => $p->phone,
                 ])->values()),
             })"
             x-init="init()">
            <div x-ref="map" wire:ignore class="h-[60vh] w-full rounded-xl bg-gray-100"></div>
        </div>
    @else
        <div class="card mt-4 text-sm text-amber-700">
            Google Maps is not configured — the list below still works.
            @if (auth()->user()?->hasRole('Super Admin'))
                Add an API key in <a href="{{ route('settings.maps') }}" wire:navigate class="underline">Settings → Map settings</a> to see the map.
            @else
                Ask a Super Admin to add an API key under Settings → Map settings to see the map.
            @endif
        </div>
    @endif

    {{-- ---- Retailer list ------------------------------------------------- --}}
    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">RT Code</th>
                <th class="th">RT Name</th>
                <th class="th">RD</th>
                <th class="th">Area</th>
                <th class="th">Phone</th>
                <th class="th">Location</th>
                <th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($list as $r)
                <tr wire:key="rt-{{ $r->code }}">
                    <td class="td font-mono">{{ $r->code }}</td>
                    <td class="td">{{ $r->name ?: '—' }}</td>
                    <td class="td">{{ $r->rd_code ?: '—' }}</td>
                    <td class="td">{{ $r->area ?: '—' }}</td>
                    <td class="td">{{ $r->phone ?: '—' }}</td>
                    <td class="td">
                        @if ($r->latitude !== null && $r->longitude !== null)
                            <a class="text-indigo-600 underline" target="_blank"
                               href="https://www.google.com/maps?q={{ $r->latitude }},{{ $r->longitude }}">on map</a>
                        @else
                            <span class="text-amber-600">not mapped</span>
                        @endif
                    </td>
                    <td class="td text-right">
                        <button class="text-xs text-indigo-600" wire:click="showTimeline('{{ $r->code }}')">View</button>
                    </td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="7">No retailers match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $list->links() }}</div>

    {{-- ---- TSO visit timeline modal ------------------------------------- --}}
    @if ($timelineRt !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeTimeline">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="flex items-start justify-between border-b border-gray-100 px-5 py-3">
                    <div>
                        <h2 class="text-sm font-semibold">
                            TSO visits · <span class="font-mono">{{ $timelineRt }}</span>
                        </h2>
                        @if ($timelineRetailer)
                            <p class="text-xs text-gray-500">
                                {{ $timelineRetailer->name }}
                                @if ($timelineRetailer->rd_code) · RD {{ $timelineRetailer->rd_code }} @endif
                                @if ($timelineRetailer->area) · {{ $timelineRetailer->area }} @endif
                            </p>
                        @endif
                    </div>
                    <button class="text-gray-400 hover:text-gray-600" wire:click="closeTimeline">&times;</button>
                </div>
                <div class="max-h-[60vh] overflow-y-auto px-5 py-4">
                    @forelse ($timeline as $v)
                        <div class="relative flex gap-3 pb-4 last:pb-0">
                            <div class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></div>
                            <div class="min-w-0">
                                <p class="text-sm">
                                    Visited by <span class="font-medium">{{ $v->tso_name ?: 'Unknown TSO' }}</span>
                                    @if ($v->note) <span class="text-gray-500">— {{ $v->note }}</span> @endif
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ \Illuminate\Support\Carbon::parse($v->visited_at)->timezone(config('pjp.timezone'))->format('d M Y, H:i') }}
                                    @if ($v->latitude !== null)
                                        · <a class="text-indigo-600 underline" target="_blank"
                                             href="https://www.google.com/maps?q={{ $v->latitude }},{{ $v->longitude }}">location</a>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No TSO visits recorded at this retailer yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    Alpine.data('retailerMap', (cfg) => ({
        map: null,
        markers: [],
        async init() {
            if (! window.__gmapsPromise) {
                window.__gmapsPromise = new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = `https://maps.googleapis.com/maps/api/js?key=${cfg.apiKey}&loading=async`;
                    s.async = true; s.onload = resolve; s.onerror = reject;
                    document.head.appendChild(s);
                });
            }
            try { await window.__gmapsPromise; } catch (e) { return; }
            this.map = new google.maps.Map(this.$refs.map, { center: cfg.centre, zoom: 7 });
            this.draw(cfg.points);
        },
        draw(points) {
            this.markers.forEach(m => m.setMap(null));
            this.markers = [];
            if (! this.map || ! points.length) return;
            const bounds = new google.maps.LatLngBounds();
            const info = new google.maps.InfoWindow();
            points.forEach(p => {
                const pos = { lat: p.lat, lng: p.lng };
                const marker = new google.maps.Marker({ position: pos, map: this.map, title: `${p.code} — ${p.name}` });
                marker.addListener('click', () => {
                    info.setContent(
                        `<div style="font-size:12px"><strong>${p.code}</strong> — ${p.name}<br>` +
                        `RD ${p.rd || '—'}${p.area ? ' · ' + p.area : ''}${p.phone ? '<br>' + p.phone : ''}</div>`
                    );
                    info.open(this.map, marker);
                });
                this.markers.push(marker);
                bounds.extend(pos);
            });
            if (points.length > 1) {
                this.map.fitBounds(bounds);
            } else {
                this.map.setCenter({ lat: points[0].lat, lng: points[0].lng });
                this.map.setZoom(15);
            }
        },
    }));
</script>
@endscript
