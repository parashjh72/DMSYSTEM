<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Retailer Map &amp; Geolocation</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                    Spatial Directory
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                {{ number_format($total) }} retailer store{{ $total === 1 ? '' : 's' }} in filtered scope &bull;
                <strong class="text-emerald-700 font-semibold">{{ $points->count() }} geocoded</strong>
                @if ($unmappedCount)
                    &bull; <span class="badge-amber text-[10px]">{{ number_format($unmappedCount) }} unmapped</span>
                @endif
            </p>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdCode">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Field Officer (TSO)</label>
                <select class="input text-xs" wire:model.live="tso">
                    <option value="">All TSOs</option>
                    @foreach ($tsoOptions as $name) <option value="{{ $name }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Territory Area</label>
                <select class="input text-xs" wire:model.live="area">
                    <option value="">All Areas</option>
                    @foreach ($areaOptions as $a) <option value="{{ $a }}">{{ $a }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Retailer Search</label>
                <input class="input text-xs" wire:model.live.debounce.300ms="search" placeholder="Type RT code or store name…">
            </div>
        </div>
    </div>

    {{-- Map Canvas --}}
    @if ($mapsEnabled)
        <div class="card !p-0 overflow-hidden border border-slate-200/80 shadow-md relative"
             wire:key="map-{{ md5($rdCode.'|'.$area.'|'.$tso.'|'.$search) }}"
             x-data="retailerMap({
                 apiKey: @js($apiKey),
                 centre: @js($centre),
                 points: @js($points->map(fn ($p) => [
                     'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude,
                     'code' => $p->code, 'name' => $p->name, 'rd' => $p->rd_code, 'area' => $p->area, 'phone' => $p->phone,
                 ])->values()),
             })">
            <div x-show="error" x-cloak class="border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800 font-medium" x-text="error"></div>
            <div x-ref="map" wire:ignore class="h-[55vh] min-h-[380px] w-full bg-slate-100"></div>
        </div>
        @if ($points->isEmpty())
            <div class="rounded-xl bg-amber-50/70 p-3 text-xs text-amber-800 border border-amber-200">
                ℹ️ No mapped retailers found for this filter. Use <strong>Set Location</strong> in the directory table below to assign GPS coordinates.
            </div>
        @endif
    @else
        <div class="card bg-slate-50 border-slate-200 text-xs text-slate-600">
            <div class="flex items-center gap-2 font-bold text-slate-800 mb-1">
                <span>Google Maps API Key Not Detected</span>
            </div>
            <p>
                Map rendering requires a Google Maps JavaScript API key. Coordinates and locations can still be manually managed in the directory below.
                @if (auth()->user()?->hasRole('Super Admin'))
                    Configure your API key under <a href="{{ route('settings.maps') }}" wire:navigate class="text-indigo-600 font-bold hover:underline">Settings &rarr; Map settings</a>.
                @endif
            </p>
        </div>
    @endif

    @error('map') <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p> @enderror

    {{-- Retailer Directory Table --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">RT Code</th>
                        <th class="th">Retail Store Name</th>
                        <th class="th">RD</th>
                        <th class="th">Area</th>
                        <th class="th">Phone</th>
                        <th class="th">GPS Coordinates</th>
                        <th class="th text-right">Audit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($list as $r)
                        <tr wire:key="rt-{{ $r->code }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="td font-mono font-bold text-slate-900">{{ $r->code }}</td>
                            <td class="td font-medium text-slate-800">{{ $r->name ?: '—' }}</td>
                            <td class="td font-mono text-slate-600">{{ $r->rd_code ?: '—' }}</td>
                            <td class="td text-slate-600">{{ $r->area ?: '—' }}</td>
                            <td class="td text-slate-500 font-mono">{{ $r->phone ?: '—' }}</td>
                            <td class="td whitespace-nowrap">
                                @php $mapped = $r->latitude !== null && $r->longitude !== null; @endphp
                                @if ($mapped)
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button"
                                                @click="$dispatch('focus-retailer', { lat: {{ $r->latitude }}, lng: {{ $r->longitude }}, code: '{{ $r->code }}' })"
                                                class="badge-emerald font-semibold hover:bg-emerald-100 cursor-pointer transition-colors"
                                                title="View on Map">
                                            <span>Mapped ✓</span>
                                        </button>
                                        <a class="text-slate-400 hover:text-indigo-600 transition-colors" target="_blank"
                                           href="https://www.google.com/maps?q={{ $r->latitude }},{{ $r->longitude }}"
                                           title="Open in external Google Maps">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    </div>
                                @else
                                    <span class="badge-amber font-medium">Unmapped</span>
                                @endif

                                @if ($canEditLocations)
                                    <x-map-picker :save="'mapRetailer'" :id="$r->code"
                                                  :lat="$mapped ? $r->latitude : null" :lng="$mapped ? $r->longitude : null"
                                                  :label="$mapped ? 'Edit GPS' : 'Set GPS'"
                                                  class="ml-2 text-xs {{ $mapped ? 'text-slate-500' : 'text-indigo-600 font-semibold' }} hover:underline cursor-pointer" />
                                @elseif ($canRequestLocation)
                                    @if (in_array($r->code, $pendingCodes, true))
                                        <span class="ml-2 text-xs text-amber-600 italic">Change Pending</span>
                                    @else
                                        <x-map-picker :save="'requestLocationChange'" :id="$r->code"
                                                      :lat="$mapped ? $r->latitude : null" :lng="$mapped ? $r->longitude : null"
                                                      :label="$mapped ? 'Request Relocate' : 'Request GPS'"
                                                      class="ml-2 text-xs text-indigo-600 font-semibold hover:underline cursor-pointer" />
                                    @endif
                                @endif
                            </td>
                            <td class="td text-right">
                                <button class="btn-ghost !py-1 !px-2.5 text-xs font-semibold text-indigo-600 hover:text-indigo-700" wire:click="showTimeline('{{ $r->code }}')">
                                    Visit Log
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                No retail stores match your filter parameters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $list->links() }}</div>

    {{-- Visit Timeline Modal --}}
    @if ($timelineRt !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" wire:click.self="closeTimeline">
            <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">
                            Store Visit History &bull; <span class="font-mono text-indigo-600">{{ $timelineRt }}</span>
                        </h2>
                        @if ($timelineRetailer)
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ $timelineRetailer->name }}
                                @if ($timelineRetailer->rd_code) &bull; RD {{ $timelineRetailer->rd_code }} @endif
                                @if ($timelineRetailer->area) &bull; {{ $timelineRetailer->area }} @endif
                            </p>
                        @endif
                    </div>
                    <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" wire:click="closeTimeline">&times;</button>
                </div>

                <div class="max-h-[60vh] overflow-y-auto px-6 py-5">
                    @forelse ($timeline as $v)
                        <div class="relative flex gap-3 pb-5 last:pb-0">
                            <div class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-900">
                                    Visited by <span class="text-indigo-600">{{ $v->tso_name ?: 'TSO Officer' }}</span>
                                    @if ($v->note) <span class="font-normal text-slate-600">&mdash; {{ $v->note }}</span> @endif
                                </p>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    {{ \Illuminate\Support\Carbon::parse($v->visited_at)->timezone(config('pjp.timezone'))->format('d M Y, H:i') }}
                                    @if ($v->latitude !== null)
                                        &bull; <a class="text-indigo-600 font-semibold hover:underline" target="_blank"
                                                 href="https://www.google.com/maps?q={{ $v->latitude }},{{ $v->longitude }}">View on Maps</a>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 text-center py-6">No previous check-in visits recorded for this store.</p>
                    @endforelse
                </div>

                <div class="border-t border-slate-100 px-6 py-3 bg-slate-50/50 flex justify-end">
                    <button class="btn-ghost text-xs" wire:click="closeTimeline">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>

@once
    <script>
        window.__gmapsKey = window.__gmapsKey || @json($apiKey);
        window.__gmapsCentre = window.__gmapsCentre || @json($centre);

        window.__loadGmaps = window.__loadGmaps || function () {
            if (window.__gmapsPromise) return window.__gmapsPromise;
            if (! window.__gmapsKey) return Promise.reject(new Error('no-key'));
            window.__gmapsPromise = new Promise((resolve, reject) => {
                window.__gmapsReject = reject;
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

        window.__registerRetailerMap = window.__registerRetailerMap || function () {
            if (window.__retailerMapRegistered || ! window.Alpine) { return; }
            window.__retailerMapRegistered = true;
            Alpine.data('retailerMap', (cfg) => ({
                map: null,
                markers: [],
                error: null,
                async init() {
                    window.__gmapsKey = window.__gmapsKey || cfg.apiKey;
                    window.__gmapsCentre = window.__gmapsCentre || cfg.centre;

                    try {
                        await window.__loadGmaps();
                    } catch (e) {
                        this.error = {
                            'no-key': 'Google Maps API key is not configured. Add one in Settings → Map settings.',
                            'auth': 'Google Maps authorization failed (gm_authFailure). In Google Cloud Console: ensure Maps JavaScript API is enabled, billing is active, and this domain (https://dms.parashojha.com/*) is authorized in API key restrictions.',
                            'load-failed': 'Google Maps failed to load due to a network error or script blocker.',
                        }[e.message] || 'Google Maps failed to load (' + e.message + ').';
                        return;
                    }

                    if (! this.$refs.map) return;

                    const defaultCentre = cfg.centre || window.__gmapsCentre || { lat: 28.3949, lng: 84.1240 };
                    this.map = new google.maps.Map(this.$refs.map, {
                        center: defaultCentre,
                        zoom: 7,
                        mapTypeControl: true,
                        streetViewControl: false,
                        fullscreenControl: true,
                    });

                    this.draw(cfg.points || []);

                    // Listen for custom event to focus a specific retailer on the map
                    window.addEventListener('focus-retailer', (e) => {
                        if (! this.map || ! e.detail) return;
                        const { lat, lng, code } = e.detail;
                        if (lat && lng) {
                            const target = { lat: parseFloat(lat), lng: parseFloat(lng) };
                            this.map.panTo(target);
                            this.map.setZoom(17);
                            const m = this.markers.find(x => x.get('code') === code);
                            if (m) {
                                google.maps.event.trigger(m, 'click');
                            }
                            this.$refs.map.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });
                },
                draw(points) {
                    this.markers.forEach(m => m.setMap(null));
                    this.markers = [];
                    if (! this.map || ! points || ! points.length) return;

                    const bounds = new google.maps.LatLngBounds();
                    const info = new google.maps.InfoWindow();

                    points.forEach(p => {
                        if (! p.lat || ! p.lng) return;
                        const pos = { lat: parseFloat(p.lat), lng: parseFloat(p.lng) };
                        const marker = new google.maps.Marker({
                            position: pos,
                            map: this.map,
                            title: `${p.code} — ${p.name || ''}`,
                        });
                        marker.set('code', p.code);

                        marker.addListener('click', () => {
                            info.setContent(
                                '<div style="font-family: inherit; padding: 6px 8px; font-size: 12px; color: #1e293b; max-width: 250px;">' +
                                '<div style="font-weight: 700; font-size: 13px; color: #0f172a; margin-bottom: 2px;">' + (p.name || 'Retail Store') + '</div>' +
                                '<div style="font-family: monospace; font-size: 11px; color: #4338ca; font-weight: 600; margin-bottom: 4px;">' + p.code + '</div>' +
                                '<div style="color: #64748b; font-size: 11px;">RD: ' + (p.rd || '—') + (p.area ? ' &bull; Area: ' + p.area : '') + '</div>' +
                                (p.phone ? '<div style="color: #64748b; font-size: 11px; margin-top: 2px;">Tel: ' + p.phone + '</div>' : '') +
                                '<div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #e2e8f0;">' +
                                '<a href="https://www.google.com/maps?q=' + p.lat + ',' + p.lng + '" target="_blank" style="color: #4f46e5; text-decoration: none; font-weight: 600; font-size: 11px;">Open in Google Maps &rarr;</a>' +
                                '</div></div>'
                            );
                            info.open(this.map, marker);
                        });

                        this.markers.push(marker);
                        bounds.extend(pos);
                    });

                    if (this.markers.length > 1) {
                        this.map.fitBounds(bounds);
                    } else if (this.markers.length === 1) {
                        this.map.setCenter({ lat: parseFloat(points[0].lat), lng: parseFloat(points[0].lng) });
                        this.map.setZoom(16);
                    }
                },
            }));
        };

        document.addEventListener('alpine:init', window.__registerRetailerMap);
        window.__registerRetailerMap();
    </script>
@endonce
