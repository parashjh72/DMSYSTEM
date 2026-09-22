@props([
    'save',                 // Livewire method: save(id, lat, lng)
    'id',                   // identifier passed back to the method
    'lat' => null,
    'lng' => null,
    'label' => 'Pin on map',
    'class' => 'text-xs text-indigo-600',
])

@php $key = \App\Support\MapConfig::apiKey(); $centre = \App\Support\MapConfig::defaultCentre(); @endphp

@once
    <script>
        window.__gmapsKey = window.__gmapsKey || @json($key);
        window.__gmapsCentre = window.__gmapsCentre || @json($centre);
        // Single shared Google Maps JS loader. Uses the callback param so the
        // promise only resolves once google.maps is actually ready (with
        // loading=async, script onload fires too early). gm_authFailure (bad key,
        // referrer, billing / API not enabled) rejects it with 'auth'.
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

        window.__registerMapPicker = window.__registerMapPicker || function () {
            if (window.__mapPickerRegistered || ! window.Alpine) { return; }
            window.__mapPickerRegistered = true;
            Alpine.data('mapPicker', (cfg) => ({
                shown: false,
                lat: cfg.lat,
                lng: cfg.lng,
                error: null,
                map: null,
                marker: null,
                get coordsLabel() {
                    return this.lat && this.lng ? `${(+this.lat).toFixed(6)}, ${(+this.lng).toFixed(6)}` : 'no location chosen';
                },
                open() {
                    this.shown = true;
                    this.error = null;
                    this.$nextTick(() => this.initMap());
                },
                close() { this.shown = false; },
                mapFailed: false,
                async initMap() {
                    try {
                        await window.__loadGmaps();
                    } catch (e) {
                        this.mapFailed = true;
                        this.error = {
                            'no-key': 'No Google Maps key configured — a Super Admin sets one in Settings → Map settings.',
                            'auth': 'Google rejected the Maps key. In Google Cloud: enable the Maps JavaScript API, turn on billing, and allow this site in the key’s HTTP-referrer list. You can still set the location below.',
                            'load-failed': 'Google Maps could not load (network or blocked script). You can still set the location below.',
                        }[e.message] || 'Google Maps failed to load. You can still set the location below.';
                        return;
                    }
                    const start = (this.lat && this.lng)
                        ? { lat: +this.lat, lng: +this.lng }
                        : window.__gmapsCentre;
                    this.map = new google.maps.Map(this.$refs.map, { center: start, zoom: this.lat ? 17 : 7 });
                    this.marker = new google.maps.Marker({ position: start, map: this.map, draggable: true });
                    if (! this.lat) { this.lat = null; this.lng = null; }
                    this.marker.addListener('dragend', () => {
                        const p = this.marker.getPosition();
                        this.lat = p.lat(); this.lng = p.lng();
                    });
                    this.map.addListener('click', (ev) => {
                        this.marker.setPosition(ev.latLng);
                        this.lat = ev.latLng.lat(); this.lng = ev.latLng.lng();
                    });
                },
                useMyLocation() {
                    if (! navigator.geolocation) { this.error = 'This browser has no location support.'; return; }
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            this.lat = pos.coords.latitude; this.lng = pos.coords.longitude;
                            if (this.map && this.marker) {
                                const ll = { lat: this.lat, lng: this.lng };
                                this.map.setCenter(ll); this.map.setZoom(18); this.marker.setPosition(ll);
                            }
                        },
                        () => { this.error = 'Could not get your location — allow location access.'; },
                        { enableHighAccuracy: true, timeout: 15000 }
                    );
                },
                commit() {
                    if (! this.lat || ! this.lng) { this.error = 'Move the pin to the shop first.'; return; }
                    this.$wire[cfg.save](cfg.id, +this.lat, +this.lng);
                    this.shown = false;
                },
            }));
        };

        document.addEventListener('alpine:init', window.__registerMapPicker);
        window.__registerMapPicker();
    </script>
@endonce

<span x-data="mapPicker({ save: @js($save), id: @js($id), lat: {{ $lat !== null ? (float) $lat : 'null' }}, lng: {{ $lng !== null ? (float) $lng : 'null' }} })" class="inline">
    <button type="button" @click="open()" class="{{ $class }}">{{ $label }}</button>

    <template x-teleport="body">
        <div x-show="shown" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 transition-all" @keydown.escape.window="close()">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-gray-100 overflow-hidden" @click.outside="close()">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 bg-gray-50/50">
                    <div class="flex items-center gap-2">
                        <span class="flex h-2 w-2 rounded-full bg-indigo-600"></span>
                        <h2 class="text-sm font-bold text-gray-900">Pin Retailer Geolocation</h2>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600 rounded-lg p-1 transition" @click="close()">&times;</button>
                </div>
                <div class="p-5">
                    <template x-if="error"><p class="mb-3 rounded-xl bg-amber-50 p-3 border border-amber-200 text-xs font-medium text-amber-800" x-text="error"></p></template>
                    <div wire:ignore x-ref="map" x-show="!mapFailed" class="h-80 w-full rounded-xl bg-gray-100 border border-gray-200 shadow-inner"></div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <button type="button" class="btn-ghost text-xs border border-gray-200 flex items-center gap-1.5" @click="useMyLocation()">
                            <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            Use Current GPS Location
                        </button>
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <span x-show="!mapFailed" class="hidden sm:inline">Tap map or drag marker:</span>
                            <span class="font-mono font-semibold text-gray-900 bg-gray-100 px-2 py-0.5 rounded-md" x-text="coordsLabel"></span>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3" x-show="mapFailed">
                        <label class="text-xs font-semibold text-gray-700">Latitude
                            <input class="input mt-1 text-xs font-mono" type="number" step="any" x-model.number="lat" placeholder="27.7172">
                        </label>
                        <label class="text-xs font-semibold text-gray-700">Longitude
                            <input class="input mt-1 text-xs font-mono" type="number" step="any" x-model.number="lng" placeholder="85.3240">
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3.5 bg-gray-50/50">
                    <button class="btn-ghost text-xs" @click="close()">Cancel</button>
                    <button class="btn-primary text-xs" @click="commit()">Save & Confirm Location</button>
                </div>
            </div>
        </div>
    </template>
</span>
