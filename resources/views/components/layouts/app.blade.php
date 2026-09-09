<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'DM System' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full text-gray-900 antialiased">
@php
    $nav = [
        ['route' => 'dashboard',       'label' => 'Dashboard',     'perm' => 'dashboard.view'],
        ['route' => 'imports.index',   'label' => 'Imports',       'perm' => 'imports.access'],
        ['route' => 'explorer',        'label' => 'Data Explorer', 'perm' => 'explorer.view'],
        ['label' => 'Reports', 'perm' => 'reports.view', 'children' => [
            ['route' => 'stock',            'label' => 'Stock Report'],
            ['route' => 'sellout',          'label' => 'Sellout Report'],
            // Standard Reports + Quick Reports hidden for now (routes still live).
            ['route' => 'scheduled-reports', 'label' => 'Scheduled Reports', 'perm' => 'exports.create'],
        ]],
        ['route' => 'imei-search',     'label' => 'IMEI Search',   'perm' => 'reports.view'],
        ['route' => 'exports.index',   'label' => 'Exports',       'perm' => 'exports.view'],
        ['route' => 'masterdata',      'label' => 'Master Data',   'perm' => 'masterdata.view'],
        ['route' => 'model-prices',    'label' => 'Model Prices',  'perm' => 'masterdata.view'],
        ['route' => 'schemes.index',   'label' => 'Schemes',       'perm' => 'settings.manage'],
        ['route' => 'settings.index',  'label' => 'Settings',      'perm' => 'settings.manage'],
        ['route' => 'users.index',     'label' => 'Users',         'perm' => 'users.manage'],
    ];
    $activeClass = 'bg-indigo-50 text-indigo-700';
    $idleClass = 'text-gray-600 hover:bg-gray-50';
@endphp
<div class="min-h-full">
    <div class="flex">
        {{-- Sidebar --}}
        <aside class="hidden md:flex md:w-60 md:flex-col md:fixed md:inset-y-0 bg-white ring-1 ring-gray-200">
            <div class="px-5 py-4 text-lg font-semibold tracking-tight">DM<span class="text-indigo-600">System</span></div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                @foreach ($nav as $item)
                    @can($item['perm'])
                        @if (! empty($item['children']))
                            @php $groupActive = collect($item['children'])->contains(fn ($c) => request()->routeIs($c['route']) || request()->routeIs($c['route'].'.*')); @endphp
                            <div x-data="{ open: @js($groupActive) }">
                                <button type="button" @click="open = !open"
                                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium {{ $groupActive ? $activeClass : $idleClass }}">
                                    {{ $item['label'] }}
                                    <svg class="h-4 w-4 transition" :class="open && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5V5z"/></svg>
                                </button>
                                <div x-show="open" x-cloak class="mt-1 space-y-1 pl-3">
                                    @foreach ($item['children'] as $child)
                                        @continue(! empty($child['perm']) && ! auth()->user()?->can($child['perm']))
                                        <a href="{{ route($child['route']) }}"
                                           class="block rounded-lg px-3 py-1.5 text-sm {{ request()->routeIs($child['route']) || request()->routeIs($child['route'].'.*') ? $activeClass : $idleClass }}">
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <a href="{{ route($item['route']) }}"
                               class="block rounded-lg px-3 py-2 text-sm font-medium
                               {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*') ? $activeClass : $idleClass }}">
                                {{ $item['label'] }}
                            </a>
                        @endif
                    @endcan
                @endforeach
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="p-3 border-t border-gray-100">
                @csrf
                <div class="px-3 pb-2 text-xs text-gray-500">{{ auth()->user()?->name }}</div>
                <button class="btn-ghost w-full">Sign out</button>
            </form>
        </aside>

        {{-- Content --}}
        <main class="flex-1 md:pl-60">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                @if (session('status'))
                    <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
                        {{ session('status') }}
                    </div>
                @endif
                {{ $slot }}
            </div>
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
