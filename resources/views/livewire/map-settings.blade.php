<div>
    <div class="flex items-center gap-2">
        <a href="{{ route('settings.index') }}" wire:navigate class="text-sm text-indigo-600">&larr; Settings</a>
    </div>
    <h1 class="mt-1 text-xl font-semibold tracking-tight">Map settings</h1>
    <p class="mt-1 text-sm text-gray-500">
        Google Maps JavaScript API key — used for the retailer map and for TSOs to pin an unmapped retailer.
    </p>

    <div class="card mt-6 max-w-xl space-y-4">
        <div>
            <label class="label">Google Maps API key</label>
            <input class="input font-mono text-sm" wire:model="apiKey" autocomplete="off"
                   placeholder="AIza…">
            @error('apiKey') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <p class="text-xs text-gray-500">
            Enable <strong>Maps JavaScript API</strong> in Google Cloud, then restrict the key by
            <strong>HTTP referrer</strong> to <code>{{ config('app.url') }}/*</code>. The key is sent to the browser
            (this is normal for the Maps JS API) — the referrer restriction is what protects it.
        </p>
        <button class="btn-primary" wire:click="save">Save</button>
        @if ($apiKey)
            <p class="text-xs text-emerald-600">A key is configured — map features are enabled.</p>
        @else
            <p class="text-xs text-amber-600">No key set — map pickers show a message and fall back to typed coordinates.</p>
        @endif
    </div>
</div>
