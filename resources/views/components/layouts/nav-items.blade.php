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
