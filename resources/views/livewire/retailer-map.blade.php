<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Retailer Map</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $points->count() }} retailer{{ $points->count() === 1 ? '' : 's' }} mapped
                @if ($unmappedCount) · <span class="text-amber-600">{{ $unmappedCount }} not yet mapped</span> @endif
            </p>
        </div>
    </div>

    <div class="card mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">RD code</label>
            <select class="input w-auto" wire:model.live="rdCode">
                <option value="">All</option>
                @foreach ($rdOptions as $rd) <option value="{{ $rd }}">{{ $rd }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="label">Area</label>
            <select class="input w-auto" wire:model.live="area">
                <option value="">All</option>
                @foreach ($areaOptions as $a) <option value="{{ $a }}">{{ $a }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="label">Search</label>
            <input class="input" wire:model.live.debounce.300ms="search" placeholder="RT code / name">
        </div>
    </div>

    @if (! $mapsEnabled)
        <div class="card mt-4 text-sm text-amber-700">
            Google Maps is not configured.
            @if (auth()->user()?->hasRole('Super Admin'))
                Add an API key in <a href="{{ route('settings.maps') }}" wire:navigate class="underline">Settings → Map settings</a>.
            @else
                Ask a Super Admin to add an API key under Settings → Map settings.
            @endif
        </div>

        <div class="card mt-4 overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="text-gray-500"><tr>
                    <th class="px-2 py-1 text-left">RT code</th><th class="px-2 py-1 text-left">Name</th>
                    <th class="px-2 py-1 text-left">RD</th><th class="px-2 py-1 text-left">Area</th>
                    <th class="px-2 py-1 text-left">Lat, Lng</th>
                </tr></thead>
                <tbody>
                    @forelse ($points as $p)
                        <tr class="border-t border-gray-100">
                            <td class="px-2 py-1 font-mono">{{ $p->code }}</td>
                            <td class="px-2 py-1">{{ $p->name }}</td>
                            <td class="px-2 py-1">{{ $p->rd_code }}</td>
                            <td class="px-2 py-1">{{ $p->area }}</td>
                            <td class="px-2 py-1">
                                <a class="text-indigo-600 underline" target="_blank"
                                   href="https://www.google.com/maps?q={{ $p->latitude }},{{ $p->longitude }}">{{ $p->latitude }}, {{ $p->longitude }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-3 text-gray-400">No mapped retailers match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="card mt-4 p-0"
             wire:key="map-{{ md5($rdCode.'|'.$area.'|'.$search) }}"
             x-data="retailerMap({
                 apiKey: @js($apiKey),
                 centre: @js($centre),
                 points: @js($points->map(fn ($p) => [
                     'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude,
                     'code' => $p->code, 'name' => $p->name, 'rd' => $p->rd_code, 'area' => $p->area, 'phone' => $p->phone,
                 ])->values()),
             })"
             x-init="init()">
            <div x-ref="map" wire:ignore class="h-[70vh] w-full rounded-xl bg-gray-100"></div>
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
