@foreach ($navSections as $section)
    @php
        // Filter items in section by permissions
        $visibleItems = collect($section['items'])->filter(function ($item) {
            return empty($item['perm']) || auth()->user()?->can($item['perm']);
        });
    @endphp

    @if ($visibleItems->isNotEmpty())
        <div class="pt-4 first:pt-1">
            @if (! empty($section['title']))
                <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    {{ $section['title'] }}
                </div>
            @endif
            <div class="space-y-1">
                @foreach ($visibleItems as $item)
                    @if (! empty($item['children']))
                        @php
                            $groupActive = collect($item['children'])->contains(fn ($c) => request()->routeIs($c['route']) || request()->routeIs($c['route'].'.*'));
                        @endphp
                        <div x-data="{ open: @js($groupActive) }" class="space-y-1">
                            <button type="button" @click="open = !open"
                                    class="group flex w-full items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold transition-all duration-150 {{ $groupActive ? 'bg-indigo-50/80 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900' }}">
                                <span class="flex items-center gap-2.5">
                                    @if (! empty($item['icon']))
                                        <span class="{{ $groupActive ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600' }}">
                                            {!! $item['icon'] !!}
                                        </span>
                                    @endif
                                    <span>{{ $item['label'] }}</span>
                                </span>
                                <svg class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200" :class="open && 'rotate-90 text-indigo-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak x-collapse class="space-y-1 pl-7 pr-1">
                                @foreach ($item['children'] as $child)
                                    @continue(! empty($child['perm']) && ! auth()->user()?->can($child['perm']))
                                    @php $childActive = request()->routeIs($child['route']) || request()->routeIs($child['route'].'.*'); @endphp
                                    <a href="{{ route($child['route']) }}"
                                       class="flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs font-medium transition-all duration-150 {{ $childActive ? 'bg-indigo-600 text-white shadow-xs font-semibold' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' }}">
                                        <span>{{ $child['label'] }}</span>
                                        @if ($childActive)
                                            <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        @php
                            $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*');
                        @endphp
                        <a href="{{ route($item['route']) }}"
                           class="group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-medium transition-all duration-150 {{ $isActive ? 'bg-indigo-50 text-indigo-700 font-semibold shadow-xs ring-1 ring-indigo-200/60' : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900' }}">
                            <span class="flex items-center gap-2.5 truncate">
                                @if (! empty($item['icon']))
                                    <span class="{{ $isActive ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600' }}">
                                        {!! $item['icon'] !!}
                                    </span>
                                @endif
                                <span class="truncate">{{ $item['label'] }}</span>
                            </span>
                            @if (! empty($item['badge']))
                                <span class="badge {{ $isActive ? 'bg-indigo-600 text-white ring-0' : 'bg-slate-100 text-slate-600 ring-slate-300/50' }}">
                                    {{ $item['badge'] }}
                                </span>
                            @elseif ($isActive)
                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
@endforeach
