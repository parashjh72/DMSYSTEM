<nav class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200/80 bg-white p-1 text-xs font-semibold shadow-xs">
    @foreach (['field-sales.setup.hierarchy' => 'Regions & Areas', 'field-sales.setup.geofences' => 'Check-in Points', 'field-sales.setup.policies' => 'Duty Rules & Holidays'] as $routeName => $label)
        <a href="{{ route($routeName) }}" wire:navigate
           class="whitespace-nowrap rounded-lg px-3 py-2 {{ request()->routeIs($routeName) ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
    @endforeach
</nav>
