<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Standard Reports</h1>
            <p class="mt-1 text-xs text-slate-500">Aggregated sell-through, sell-in, and customer activation metrics.</p>
        </div>
        <div>
            @can('exports.create')
                @if (\App\Livewire\Reports::TYPES[$type][2] ?? null)
                    <button class="btn-primary text-xs" wire:click="export">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        <span>Export CSV</span>
                    </button>
                @endif
            @endcan
        </div>
    </div>

    {{-- Report View Selector --}}
    <div class="flex flex-wrap items-center gap-2" wire:loading.class="opacity-60" wire:target="type">
        @foreach (\App\Livewire\Reports::TYPES as $key => [$label])
            <button type="button" wire:click="$set('type', '{{ $key }}')" wire:loading.attr="disabled" wire:target="type"
                    aria-pressed="{{ $type === $key ? 'true' : 'false' }}"
                    class="rounded-xl px-3.5 py-2 text-xs font-semibold transition-all duration-150 shadow-2xs {{ $type === $key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:text-slate-900' }}">
                {{ $label }}
            </button>
        @endforeach
        <span wire:loading wire:target="type" class="text-xs text-indigo-600 font-medium animate-pulse">Loading dataset…</span>
    </div>

    @php
        $basisNames = ['st' => 'ST (Sell-Through)', 'activation' => 'Activation', 'sell_in' => 'Sell-In'];
    @endphp

    {{-- Filter Panel --}}
    <div class="card space-y-4">
        {{-- Basis & Presets --}}
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Date Basis:</span>
                @foreach ($bases as $b)
                    <button wire:click="setDateBasis('{{ $b }}')"
                            @disabled(count($bases) === 1)
                            class="rounded-lg px-2.5 py-1 text-xs font-semibold transition-all duration-150 {{ $dateBasis === $b ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $basisNames[$b] }}
                    </button>
                @endforeach

                <span class="mx-1 h-4 w-px bg-slate-200"></span>

                <button wire:click="toggleInactive"
                        title="Show only devices sold through but not yet activated"
                        class="rounded-lg px-2.5 py-1 text-xs font-semibold transition-all duration-150 {{ ($f['activation_status'] ?? '') === 'not_activated' ? 'bg-amber-600 text-white ring-1 ring-amber-600 shadow-xs' : 'bg-amber-50 text-amber-800 border border-amber-200/80 hover:bg-amber-100' }}">
                    Pending Activation Only
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs font-semibold text-slate-400">Presets:</span>
                @foreach ([
                    'today' => 'Today', 'yesterday' => 'Yesterday', 'last7' => '7D',
                    'this_month' => 'This Month', 'last_month' => 'Last Month',
                ] as $key => $label)
                    <button wire:click="datePreset('{{ $key }}')"
                            class="rounded-lg px-2.5 py-1 text-xs font-semibold transition-all duration-150 {{ $activePreset === $key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200/60' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Filter Grid --}}
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="label">{{ $basisLabel }} From</label>
                <input type="date" class="input text-xs" wire:model="f.{{ $basisPrefix }}_from">
            </div>
            <div>
                <label class="label">{{ $basisLabel }} To</label>
                <input type="date" class="input text-xs" wire:model="f.{{ $basisPrefix }}_to">
            </div>
            <div>
                <label class="label">Field Officer (TSO)</label>
                <select class="input text-xs" wire:model="f.tso">
                    <option value="">All TSOs</option>
                    @foreach ($tsoOptions as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Model</label>
                <select class="input text-xs" wire:model="f.model">
                    <option value="">All Models</option>
                    @foreach ($modelOptions as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="f.rd_code">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                <label class="label">Retailer (RT)</label>
                <div class="relative">
                    <input type="text" autocomplete="off" class="input pr-8 text-xs"
                           placeholder="{{ ($f['rd_code'] ?? '') !== '' ? 'Type retailer...' : 'Code or name...' }}"
                           wire:model.live.debounce.300ms="rtSearch"
                           @focus="open = true" @click="open = true" @keydown.escape="open = false"
                           x-on:input="open = true">
                    @if (($f['rt_code'] ?? '') !== '')
                        <button type="button" title="Clear"
                                class="absolute inset-y-0 right-2 my-auto h-4 w-4 text-slate-400 hover:text-slate-600"
                                wire:click="selectRt('')" @click="open = false">&times;</button>
                    @endif
                </div>

                <div x-show="open" x-transition.opacity x-cloak
                     class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl bg-white py-1 text-xs shadow-xl ring-1 ring-slate-200">
                    <button type="button" wire:click="selectRt('')" @click="open = false"
                            class="block w-full px-3 py-2 text-left text-slate-500 hover:bg-slate-50 border-b border-slate-100">
                        All retailers{{ ($f['rd_code'] ?? '') !== '' ? ' for this RD' : '' }}
                    </button>
                    @forelse ($rtOptions as $code => $label)
                        <button type="button" wire:key="rt-{{ $code }}"
                                wire:click="selectRt('{{ $code }}')" @click="open = false"
                                class="block w-full px-3 py-1.5 text-left hover:bg-indigo-50/70 {{ ($f['rt_code'] ?? '') === (string) $code ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-slate-700' }}">
                            {{ $label }}
                        </button>
                    @empty
                        <div class="px-3 py-2 text-slate-400">No match for “{{ $rtSearch }}”.</div>
                    @endforelse
                    @if ($rtTruncated)
                        <div class="px-3 py-1.5 text-[11px] text-amber-600 bg-amber-50">Showing top results — type to narrow.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2 border-t border-slate-100">
            <button class="btn-primary text-xs" wire:click="applyFilters">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <span>Apply Filters</span>
            </button>
            <button class="btn-ghost text-xs" wire:click="resetFilters">
                Reset All
            </button>
        </div>
    </div>

    {{-- Summary KPIs for Activation Lag & Totals --}}
    @php
        $lt = (int) ($lag->total_imei ?? 0);
        $la = (int) ($lag->activated ?? 0);
    @endphp
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        @foreach ([
            ['Total Devices', $lt, 'bg-slate-50 text-slate-800'],
            ['Activated', $la, 'bg-emerald-50 text-emerald-800'],
            ['In Channel', $lt - $la, 'bg-amber-50 text-amber-800'],
            ['Same Day (0d)', $lag->lag_d0 ?? 0, 'bg-indigo-50 text-indigo-800'],
            ['1–7 Days', $lag->lag_d1_7 ?? 0, 'bg-blue-50 text-blue-800'],
            ['8–30 Days', ($lag->lag_d8_15 ?? 0) + ($lag->lag_d16_30 ?? 0), 'bg-purple-50 text-purple-800'],
            ['31+ Days', $lag->lag_d31_plus ?? 0, 'bg-rose-50 text-rose-800'],
        ] as [$l, $v, $cls])
            <div class="card !p-3.5 border border-slate-200/80">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $l }}</div>
                <div class="mt-1 text-lg font-extrabold text-slate-900">{{ number_format((int) $v) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Results Table --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        @foreach (array_keys((array) ($rows->first() ?? [])) as $col)
                            <th class="th">{{ ucwords(str_replace('_', ' ', $col)) }}</th>
                        @endforeach
                        <th class="th text-right">Activation Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        @php
                            $row = (array) $row;
                            $tot = (int) ($row['total_imei'] ?? $row['total_activations'] ?? 0);
                            $act = (int) ($row['activated'] ?? 0);
                            $pct = $tot > 0 ? round($act / $tot * 100, 1) : null;
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            @foreach ($row as $k => $v)
                                <td class="td {{ is_numeric($v) && ! str_contains((string) $v, '-') ? 'font-mono' : '' }}">
                                    {{ is_numeric($v) && ! str_contains((string) $v, '-') ? number_format((float) $v) : $v }}
                                </td>
                            @endforeach
                            <td class="td text-right">
                                @if ($pct !== null)
                                    <span class="inline-flex rounded-lg px-2 py-0.5 text-xs font-bold {{ $pct >= 70 ? 'bg-emerald-50 text-emerald-700' : ($pct >= 40 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $pct }}%
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-12 text-center text-slate-400 text-xs" colspan="15">
                                No records found for the selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
