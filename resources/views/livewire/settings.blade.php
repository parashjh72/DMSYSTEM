<div>
    <!-- Page Header -->
    <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-bold tracking-tight text-gray-900">System Administration & Settings</h1>
            <p class="text-xs text-gray-500">Configure global parameters, authentication integrations, inventory transfers, and user permissions.</p>
        </div>
    </div>

    <!-- Settings Navigation Grid -->
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Transfer Records -->
        <a href="{{ route('settings.transfer') }}" wire:navigate class="card group flex flex-col justify-between border border-gray-100 p-5 shadow-xs transition hover:border-indigo-200 hover:shadow-md">
            <div>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-600 group-hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
                <h2 class="mt-4 text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition">Transfer Records</h2>
                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                    Reassign individual IMEIs or an entire retailer's inventory stock to a different partner or distributor.
                </p>
            </div>
            <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-indigo-600">
                Launch tool <span>&rarr;</span>
            </div>
        </a>

        <!-- Return Requests -->
        @can('returns.review')
        <a href="{{ route('returns') }}" wire:navigate class="card group flex flex-col justify-between border border-gray-100 p-5 shadow-xs transition hover:border-indigo-200 hover:shadow-md">
            <div>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition group-hover:bg-blue-600 group-hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                </div>
                <h2 class="mt-4 text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition">Return Requests (RA)</h2>
                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                    Audit and approve or reject distributor requests to pull devices back from retailer stock into warehouse custody.
                </p>
            </div>
            <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-indigo-600">
                Review queue <span>&rarr;</span>
            </div>
        </a>
        @endcan

        <!-- Master Data -->
        <a href="{{ route('masterdata') }}" wire:navigate class="card group flex flex-col justify-between border border-gray-100 p-5 shadow-xs transition hover:border-indigo-200 hover:shadow-md">
            <div>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition group-hover:bg-emerald-600 group-hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                </div>
                <h2 class="mt-4 text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition">Master Data & Model Types</h2>
                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                    Distributors (RD), Retailers (RT), Device models (running vs phase-out), and TSO territory representatives.
                </p>
            </div>
            <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-indigo-600">
                Manage catalogs <span>&rarr;</span>
            </div>
        </a>

        <!-- Mail Settings (SMTP) -->
        <a href="{{ route('settings.mail') }}" wire:navigate class="card group flex flex-col justify-between border border-gray-100 p-5 shadow-xs transition hover:border-indigo-200 hover:shadow-md">
            <div>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition group-hover:bg-amber-600 group-hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <h2 class="mt-4 text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition">Mail Settings (SMTP)</h2>
                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                    Configure custom SMTP outbound servers for password resets and automated operational email alerts.
                </p>
            </div>
            <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-indigo-600">
                Configure SMTP <span>&rarr;</span>
            </div>
        </a>

        <!-- Map Settings -->
        @if (auth()->user()?->hasRole('Super Admin'))
        <a href="{{ route('settings.maps') }}" wire:navigate class="card group flex flex-col justify-between border border-gray-100 p-5 shadow-xs transition hover:border-indigo-200 hover:shadow-md">
            <div>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition group-hover:bg-rose-600 group-hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h2 class="mt-4 text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition">Google Maps API Integration</h2>
                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                    API key configuration for retailer geolocation mapping, territory visualization, and TSO GPS pin drops.
                </p>
            </div>
            <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-indigo-600">
                Manage API key <span>&rarr;</span>
            </div>
        </a>
        @endif

        <!-- Users & Roles -->
        @can('users.manage')
        <a href="{{ route('users.index') }}" wire:navigate class="card group flex flex-col justify-between border border-gray-100 p-5 shadow-xs transition hover:border-indigo-200 hover:shadow-md">
            <div>
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600 transition group-hover:bg-purple-600 group-hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <h2 class="mt-4 text-sm font-bold text-gray-900 group-hover:text-indigo-600 transition">Users &amp; Roles (RBAC)</h2>
                <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                    Manage team access, role assignments, hierarchical reporting managers, and distributor data scoping.
                </p>
            </div>
            <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-indigo-600">
                User accounts <span>&rarr;</span>
            </div>
        </a>
        @endcan
    </div>
</div>
