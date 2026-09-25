@php
    $stateStyles = [
        'active' => ['dot' => 'bg-emerald-500', 'badge' => 'badge-emerald'],
        'delayed' => ['dot' => 'bg-amber-500', 'badge' => 'badge-amber'],
        'offline' => ['dot' => 'bg-rose-500', 'badge' => 'badge-rose'],
        'checked_out' => ['dot' => 'bg-slate-400', 'badge' => 'badge-slate'],
        'not_checked_in' => ['dot' => 'bg-slate-200', 'badge' => 'badge-slate'],
    ];
@endphp

<div class="space-y-6" wire:poll.30s="refresh">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Live Staff Map</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Live
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ $todayLabel }} · refreshes every 30 seconds · last update {{ $refreshedAt }}</p>
        </div>
        <button class="btn-secondary text-xs" wire:click="refresh" wire:loading.attr="disabled">
            <svg class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="refresh" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            <span>Refresh now</span>
        </button>
    </div>

    {{-- Filters --}}
    <div class="card">
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label class="label">Region</label>
                <select class="input text-xs" wire:model.live="regionId">
                    <option value="">All regions</option>
                    @foreach ($options['regions'] as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Area</label>
                <select class="input text-xs" wire:model.live="areaId">
                    <option value="">All areas</option>
                    @foreach ($options['areas'] as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdCode">
                    <option value="">All distributors</option>
                    @foreach ($options['distributors'] as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- State counts --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ($states as $key => $label)
            <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3 shadow-xs">
                <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    <span class="h-2 w-2 rounded-full {{ $stateStyles[$key]['dot'] }}"></span> {{ $label }}
                </div>
                <div class="mt-1 text-2xl font-bold text-slate-900">{{ $counts[$key] ?? 0 }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3"
         x-data="fsLiveMap({ staff: @js($staff) })">
        {{-- Map --}}
        <div class="lg:col-span-2 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-xs">
            <template x-if="error"><p class="m-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800" x-text="error"></p></template>
            <div wire:ignore x-ref="map" class="h-[28rem] w-full rounded-xl bg-slate-100 lg:h-[36rem]"></div>
        </div>

        {{-- Staff list --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="border-b border-slate-100 px-4 py-3 text-xs font-bold text-slate-900">Field staff ({{ count($staff) }})</div>
            <ul class="max-h-[36rem] divide-y divide-slate-100 overflow-y-auto">
                @forelse ($staff as $s)
                    <li wire:key="staff-{{ $s['id'] }}" class="px-4 py-3 hover:bg-slate-50/70">
                        <div class="flex items-start justify-between gap-2">
                            <button type="button" class="min-w-0 text-left" @click="focus({{ $s['id'] }})" @disabled(! $s['last'])>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $stateStyles[$s['state']]['dot'] }}"></span>
                                    <span class="truncate text-xs font-bold text-slate-900">{{ $s['name'] }}</span>
                                </div>
                                <div class="mt-0.5 truncate pl-4 text-[11px] text-slate-500">
                                    {{ implode(', ', $s['rd_codes']) ?: 'No distributor' }}
                                </div>
                            </button>
                            <span class="badge {{ $stateStyles[$s['state']]['badge'] }} shrink-0">{{ $states[$s['state']] }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 pl-4 text-[11px] text-slate-500">
                            @if ($s['check_in_at'])<span>In {{ $s['check_in_at'] }}</span>@endif
                            @if ($s['check_out_at'])<span>Out {{ $s['check_out_at'] }}</span>@endif
                            @if ($s['last'])<span>Seen {{ $s['last']['at'] }} ({{ $s['last']['minutes_ago'] }} min ago)</span>@endif
                            @if ($s['last']['battery'] ?? null)<span>🔋 {{ $s['last']['battery'] }}%</span>@endif
                            <span class="font-semibold text-slate-700">{{ number_format($s['km'], 1) }} km</span>
                            @if ($s['check_in_at'])
                                <a href="{{ route('field-sales.route', ['user' => $s['id']]) }}" wire:navigate class="font-semibold text-indigo-600 hover:text-indigo-800">Route →</a>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-xs text-slate-400">No field staff match these filters.</li>
                @endforelse
            </ul>
        </div>
    </div>

    @include('livewire.field-sales.partials.gmaps-loader')
    <script>
        (function () {
            const register = () => {
                if (window.__fsLiveMapRegistered || ! window.Alpine) { return; }
                window.__fsLiveMapRegistered = true;

                const colours = { active: '#10b981', delayed: '#f59e0b', offline: '#f43f5e', checked_out: '#64748b' };

                Alpine.data('fsLiveMap', (cfg) => ({
                    error: null,
                    map: null,
                    info: null,
                    markers: {},
                    fitted: false,
                    async init() {
                        try {
                            await window.__loadGmaps();
                        } catch (e) {
                            this.error = window.__gmapsErrorText(e);
                            return;
                        }
                        this.map = new google.maps.Map(this.$refs.map, { center: window.__gmapsCentre, zoom: 7, streetViewControl: false, mapTypeControl: false });
                        this.info = new google.maps.InfoWindow();
                        this.draw(cfg.staff);
                        this.$wire.$watch('staff', (staff) => this.draw(staff));
                    },
                    draw(staff) {
                        if (! this.map) { return; }
                        const seen = {};
                        const bounds = new google.maps.LatLngBounds();
                        staff.filter((s) => s.last).forEach((s) => {
                            seen[s.id] = true;
                            const position = { lat: +s.last.lat, lng: +s.last.lng };
                            const icon = { path: google.maps.SymbolPath.CIRCLE, scale: 8, fillColor: colours[s.state] || '#64748b', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 2 };
                            let marker = this.markers[s.id];
                            if (! marker) {
                                marker = new google.maps.Marker({ map: this.map, position, icon, title: s.name });
                                marker.addListener('click', () => this.open(marker));
                                this.markers[s.id] = marker;
                            } else {
                                marker.setPosition(position);
                                marker.setIcon(icon);
                            }
                            marker.__staff = s;
                            bounds.extend(position);
                        });
                        Object.keys(this.markers).forEach((id) => {
                            if (! seen[id]) { this.markers[id].setMap(null); delete this.markers[id]; }
                        });
                        if (! this.fitted && ! bounds.isEmpty()) {
                            this.map.fitBounds(bounds);
                            if (this.map.getZoom() > 15) { this.map.setZoom(15); }
                            this.fitted = true;
                        }
                    },
                    open(marker) {
                        const s = marker.__staff;
                        const el = document.createElement('div');
                        el.className = 'text-xs';
                        el.innerHTML = '<div style="font-weight:700"></div><div></div><div></div>';
                        el.children[0].textContent = s.name;
                        el.children[1].textContent = 'Last seen ' + s.last.at + ' (' + s.last.minutes_ago + ' min ago)';
                        el.children[2].textContent = s.km.toFixed(1) + ' km today' + (s.last.battery ? ' · battery ' + s.last.battery + '%' : '');
                        this.info.setContent(el);
                        this.info.open({ map: this.map, anchor: marker });
                    },
                    focus(id) {
                        const marker = this.markers[id];
                        if (! marker) { return; }
                        this.map.panTo(marker.getPosition());
                        this.map.setZoom(15);
                        this.open(marker);
                    },
                }));
            };
            document.addEventListener('alpine:init', register);
            register();
        })();
    </script>
</div>
