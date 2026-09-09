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

        @can('returns.review')
        <a href="{{ route('returns') }}" wire:navigate class="card block hover:ring-indigo-300">
            <h2 class="text-sm font-semibold">Return requests</h2>
            <p class="mt-1 text-xs text-gray-500">
                Approve or reject RD requests to pull devices back from a retailer into distributor stock.
            </p>
        </a>
        @endcan

        <div class="card opacity-60">
            <h2 class="text-sm font-semibold">Activation-lag buckets</h2>
            <p class="mt-1 text-xs text-gray-500">Configure 0 / 1–7 / 8–15 / 16–30 / 31+ day thresholds. (coming soon)</p>
        </div>

        <a href="{{ route('masterdata') }}" wire:navigate class="card block hover:ring-indigo-300">
            <h2 class="text-sm font-semibold">Master data &amp; model types</h2>
            <p class="mt-1 text-xs text-gray-500">Distributors, retailers, models (running / out), TSOs.</p>
        </a>

        <a href="{{ route('settings.mail') }}" wire:navigate class="card block hover:ring-indigo-300">
            <h2 class="text-sm font-semibold">Mail settings (SMTP)</h2>
            <p class="mt-1 text-xs text-gray-500">
                Outgoing email for password resets &amp; notifications. Includes a "send test" button.
            </p>
        </a>

        @if (auth()->user()?->hasRole('Super Admin'))
        <a href="{{ route('settings.maps') }}" wire:navigate class="card block hover:ring-indigo-300">
            <h2 class="text-sm font-semibold">Map settings</h2>
            <p class="mt-1 text-xs text-gray-500">
                Google Maps API key for the retailer map and for TSOs pinning an unmapped retailer.
            </p>
        </a>
        @endif

        @can('users.manage')
        <a href="{{ route('users.index') }}" wire:navigate class="card block hover:ring-indigo-300">
            <h2 class="text-sm font-semibold">Users &amp; roles</h2>
            <p class="mt-1 text-xs text-gray-500">
                Add logins. <strong>ASM / TSO / RD</strong> users see only rows for their assigned distributor codes.
            </p>
        </a>
        @endcan
    </div>
</div>
