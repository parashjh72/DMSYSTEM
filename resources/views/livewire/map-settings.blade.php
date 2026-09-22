<div>
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col gap-2">
        <a href="{{ route('settings.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Settings
        </a>
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Google Maps Platform Integration</h1>
                <p class="text-xs text-gray-500">Manage client-side Maps JavaScript credentials for retailer radar and GPS field routing.</p>
            </div>
        </div>
    </div>

    <div class="card mt-8 max-w-2xl border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h2 class="text-sm font-bold text-gray-900">API Key Configuration</h2>
            @if ($apiKey)
                <span class="badge bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200">Enabled & Active</span>
            @else
                <span class="badge bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200">Not Configured</span>
            @endif
        </div>

        <div class="mt-4 space-y-4">
            <div>
                <label class="label text-xs font-semibold text-gray-700">Google Maps JavaScript API Key</label>
                <div class="relative">
                    <input class="input font-mono text-xs pl-8" wire:model="apiKey" autocomplete="off"
                           placeholder="AIzaSy...">
                    <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                @error('apiKey') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-xl bg-blue-50/70 p-3.5 border border-blue-100 text-xs text-blue-950 space-y-2 leading-relaxed">
                <div class="font-bold flex items-center gap-1.5 text-blue-900">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Security & Domain Restrictions
                </div>
                <p>
                    Enable <strong>Maps JavaScript API</strong> in Google Cloud Console. For production security, restrict this key by <strong>HTTP referrer</strong> to allow only requests from:
                </p>
                <div class="rounded-lg bg-white/90 p-2 font-mono text-[11px] text-gray-800 border border-blue-200 select-all">
                    {{ config('app.url') }}/*
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                <span class="text-xs {{ $apiKey ? 'text-emerald-700 font-medium' : 'text-gray-400' }}">
                    {{ $apiKey ? '✓ Interactive map features ready.' : 'Fallback: Text coordinate inputs will be used if empty.' }}
                </span>
                <button class="btn-primary text-xs" wire:click="save">Save API Key</button>
            </div>
        </div>
    </div>
</div>
