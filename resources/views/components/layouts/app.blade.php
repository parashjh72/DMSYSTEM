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
        ['route' => 'dashboard',        'label' => 'Dashboard',      'perm' => 'dashboard.view'],
        ['route' => 'attendance',       'label' => 'Attendance',     'perm' => 'attendance.check'],
        ['route' => 'attendance.report','label' => 'Attendance Report','perm' => 'attendance.view_all'],
        ['route' => 'imports.index',    'label' => 'Imports',        'perm' => 'imports.access'],
        ['route' => 'explorer',         'label' => 'Data Explorer',  'perm' => 'explorer.view'],
        ['label' => 'Reports', 'perm' => 'reports.view', 'children' => [
            ['route' => 'stock',            'label' => 'Stock Report'],
            ['route' => 'sellout',          'label' => 'Sellout Report'],
            ['route' => 'scheduled-reports', 'label' => 'Scheduled Reports', 'perm' => 'scheduled-reports.manage'],
        ]],
        ['route' => 'imei-search',      'label' => 'IMEI Search',    'perm' => 'reports.view'],
        ['route' => 'returns',          'label' => 'Returns',        'perm' => 'returns.access'],
        ['route' => 'exports.index',    'label' => 'Exports',        'perm' => 'exports.view'],
        ['route' => 'masterdata',       'label' => 'Master Data',    'perm' => 'masterdata.view'],
        ['route' => 'model-prices',     'label' => 'Model Prices',   'perm' => 'masterdata.view'],
        ['route' => 'promoters',        'label' => 'Promoters (RA)', 'perm' => 'promoters.access'],
        ['route' => 'schemes.index',    'label' => 'Schemes',        'perm' => 'settings.manage'],
        ['route' => 'settings.index',   'label' => 'Settings',       'perm' => 'settings.manage'],
        ['route' => 'users.index',      'label' => 'Users',          'perm' => 'users.manage'],
    ];
    $activeClass = 'bg-indigo-50 text-indigo-700';
    $idleClass = 'text-gray-600 hover:bg-gray-50';
@endphp
<div class="min-h-full" x-data="{ mobileNav: false }">
    {{-- Mobile top bar --}}
    <div class="sticky top-0 z-30 flex items-center gap-3 border-b border-gray-200 bg-white px-4 py-3 md:hidden">
        <button type="button" @click="mobileNav = true" class="text-gray-600" aria-label="Open menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <span class="text-lg font-semibold tracking-tight">DM<span class="text-indigo-600">System</span></span>
    </div>

    {{-- Mobile slide-over --}}
    <div x-show="mobileNav" x-cloak class="fixed inset-0 z-40 md:hidden">
        <div class="absolute inset-0 bg-black/40" @click="mobileNav = false"></div>
        <aside class="absolute inset-y-0 left-0 flex w-64 flex-col bg-white shadow-xl" @click.outside="mobileNav = false">
            <div class="flex items-center justify-between px-5 py-4">
                <span class="text-lg font-semibold tracking-tight">DM<span class="text-indigo-600">System</span></span>
                <button @click="mobileNav = false" class="text-gray-400">&times;</button>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3" @click="mobileNav = false">
                @include('components.layouts.nav-items', ['nav' => $nav, 'activeClass' => $activeClass, 'idleClass' => $idleClass])
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 p-3">
                @csrf
                <div class="px-3 pb-2 text-xs text-gray-500">{{ auth()->user()?->name }}</div>
                <button class="btn-ghost w-full">Sign out</button>
            </form>
        </aside>
    </div>

    <div class="flex">
        {{-- Desktop sidebar --}}
        <aside class="hidden md:flex md:w-60 md:flex-col md:fixed md:inset-y-0 bg-white ring-1 ring-gray-200">
            <div class="px-5 py-4 text-lg font-semibold tracking-tight">DM<span class="text-indigo-600">System</span></div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                @include('components.layouts.nav-items', ['nav' => $nav, 'activeClass' => $activeClass, 'idleClass' => $idleClass])
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
