<div>
    <h1 class="text-xl font-semibold tracking-tight">Settings</h1>
    <p class="mt-1 text-sm text-gray-500">Administrative tools.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('settings.transfer') }}" wire:navigate class="card block hover:ring-indigo-300">
            <h2 class="text-sm font-semibold">Transfer records</h2>
            <p class="mt-1 text-xs text-gray-500">
                Reassign IMEIs or a whole retailer's stock to a different retailer / distributor.
            </p>
        </a>

        <div class="card opacity-60">
            <h2 class="text-sm font-semibold">Activation-lag buckets</h2>
            <p class="mt-1 text-xs text-gray-500">Configure 0 / 1–7 / 8–15 / 16–30 / 31+ day thresholds. (coming soon)</p>
        </div>

        <a href="{{ route('masterdata') }}" wire:navigate class="card block hover:ring-indigo-300">
            <h2 class="text-sm font-semibold">Master data &amp; model types</h2>
            <p class="mt-1 text-xs text-gray-500">Distributors, retailers, models (running / out), TSOs.</p>
        </a>
    </div>
</div>
