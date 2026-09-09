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
        window.__loadGmaps = window.__loadGmaps || function () {
            if (window.__gmapsPromise) return window.__gmapsPromise;
            if (! window.__gmapsKey) return Promise.reject(new Error('no-key'));
            window.__gmapsPromise = new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = `https://maps.googleapis.com/maps/api/js?key=${window.__gmapsKey}&loading=async`;
                s.async = true;
                s.onload = resolve;
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
                async initMap() {
                    try {
                        await window.__loadGmaps();
                    } catch (e) {
                        this.error = e.message === 'no-key'
                            ? 'No Google Maps key configured — ask a Super Admin to set one in Settings → Maps.'
                            : 'Google Maps failed to load.';
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
        <div x-show="shown" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="close()">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl" @click.outside="close()">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <h2 class="text-sm font-semibold">Set retailer location</h2>
                    <button class="text-gray-400" @click="close()">&times;</button>
                </div>
                <div class="p-4">
                    <template x-if="error"><p class="mb-2 rounded bg-amber-50 px-3 py-2 text-xs text-amber-700" x-text="error"></p></template>
                    <div wire:ignore x-ref="map" class="h-72 w-full rounded-lg bg-gray-100"></div>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
                        <button type="button" class="btn-ghost text-xs" @click="useMyLocation()">Use my current location</button>
                        <span class="text-gray-500">Tap the map or drag the pin. <span class="font-mono" x-text="coordsLabel"></span></span>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-4 py-3">
                    <button class="btn-ghost" @click="close()">Cancel</button>
                    <button class="btn-primary" @click="commit()">Save location</button>
                </div>
            </div>
        </div>
    </template>
</span>
