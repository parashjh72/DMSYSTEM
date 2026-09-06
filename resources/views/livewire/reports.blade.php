<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Reports</h1>
        @can('exports.create')
            @if (\App\Livewire\Reports::TYPES[$type][2] ?? null)
                <button class="btn-ghost" wire:click="export">Export → CSV</button>
            @endif
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2" wire:loading.class="opacity-50" wire:target="type">
        @foreach (\App\Livewire\Reports::TYPES as $key => [$label])
            <button type="button" wire:click="$set('type', '{{ $key }}')" wire:loading.attr="disabled" wire:target="type"
                    aria-pressed="{{ $type === $key ? 'true' : 'false' }}"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition
                    {{ $type === $key
                        ? 'bg-indigo-600 text-white ring-indigo-600 shadow-sm'
                        : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50 hover:text-gray-900' }}">
                {{ $label }}
            </button>
        @endforeach
        <span wire:loading wire:target="type" class="text-xs text-gray-400">loading…</span>
    </div>

    @php
        $basisNames = ['st' => 'ST', 'activation' => 'Activation', 'sell_in' => 'Sell-In'];
    @endphp
    <div class="card mt-4">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-gray-500">Report by:</span>
                @foreach ($bases as $b)
                    <button wire:click="setDateBasis('{{ $b }}')"
                            @disabled(count($bases) === 1)
                            class="rounded-lg px-2.5 py-1 text-xs font-semibold ring-1 ring-inset transition disabled:opacity-100
                            {{ $dateBasis === $b
                                ? 'bg-gray-900 text-white ring-gray-900'
                                : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                        {{ $basisNames[$b] }}
                    </button>
                @endforeach

                <span class="mx-1 h-4 w-px bg-gray-300"></span>
                <button wire:click="toggleInactive"
                        title="Show only devices that are sold but not activated"
                        class="rounded-lg px-2.5 py-1 text-xs font-semibold ring-1 ring-inset transition
                        {{ ($f['activation_status'] ?? '') === 'not_activated'
                            ? 'bg-amber-500 text-white ring-amber-500'
                            : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                    Inactive
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-gray-500">Quick range ({{ strtolower($basisLabel) }}):</span>
                @foreach ([
                    'today' => 'Today', 'yesterday' => 'Yesterday', 'last7' => 'Last 7 days',
                    'this_month' => 'This month', 'last_month' => 'Last month',
                ] as $key => $label)
                    <button wire:click="datePreset('{{ $key }}')"
                            class="rounded-lg px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition
                            {{ $activePreset === $key
                                ? 'bg-indigo-600 text-white ring-indigo-600'
                                : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div><label class="label">{{ $basisLabel }} from</label><input type="date" class="input" wire:model="f.{{ $basisPrefix }}_from"></div>
            <div><label class="label">{{ $basisLabel }} to</label><input type="date" class="input" wire:model="f.{{ $basisPrefix }}_to"></div>
            <div>
                <label class="label">TSO</label>
                <select class="input" wire:model="f.tso">
                    <option value="">All TSOs</option>
                    @foreach ($tsoOptions as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Model</label>
                <select class="input" wire:model="f.model">
                    <option value="">All models</option>
                    @foreach ($modelOptions as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input" wire:model.live="f.rd_code">
                    <option value="">All distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                <label class="label">Retailer (RT)</label>
                <div class="relative">
                    <input type="text" autocomplete="off" class="input pr-8"
                           placeholder="{{ ($f['rd_code'] ?? '') !== '' ? 'Type retailer for this RD…' : 'Type RT code or name…' }}"
                           wire:model.live.debounce.300ms="rtSearch"
                           @focus="open = true" @click="open = true" @keydown.escape="open = false"
                           x-on:input="open = true">
                    @if (($f['rt_code'] ?? '') !== '')
                        <button type="button" title="Clear"
                                class="absolute inset-y-0 right-2 my-auto h-4 w-4 text-gray-400 hover:text-gray-600"
                                wire:click="selectRt('')" @click="open = false">&times;</button>
                    @endif
                </div>

                <div x-show="open" x-transition.opacity x-cloak
                     class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-gray-200">
                    <button type="button" wire:click="selectRt('')" @click="open = false"
                            class="block w-full px-3 py-1.5 text-left text-gray-500 hover:bg-indigo-50">
                        All retailers{{ ($f['rd_code'] ?? '') !== '' ? ' for this RD' : '' }}
                    </button>
                    @forelse ($rtOptions as $code => $label)
                        <button type="button" wire:key="rt-{{ $code }}"
                                wire:click="selectRt('{{ $code }}')" @click="open = false"
                                class="block w-full px-3 py-1.5 text-left hover:bg-indigo-50 {{ ($f['rt_code'] ?? '') === (string) $code ? 'bg-indigo-50 font-medium text-indigo-700' : '' }}">
                            {{ $label }}
                        </button>
                    @empty
                        <div class="px-3 py-1.5 text-gray-400">No match for “{{ $rtSearch }}”.</div>
                    @endforelse
                    @if ($rtTruncated)
                        <div class="px-3 py-1 text-xs text-amber-600">Showing first {{ \App\Services\Reporting\FilterOptions::RT_LIMIT }} — keep typing.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="mt-4 flex gap-3">
            <button class="btn-primary" wire:click="applyFilters">Apply</button>
            <button class="btn-ghost" wire:click="resetFilters">Reset</button>
        </div>
    </div>

    @php
        $lt = (int) ($lag->total_imei ?? 0); $la = (int) ($lag->activated ?? 0);
    @endphp
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        @foreach ([
            ['Total', $lt], ['Activated', $la], ['Not activated', $lt - $la],
            ['0 d', $lag->lag_d0 ?? 0], ['1–7 d', $lag->lag_d1_7 ?? 0],
            ['8–30 d', ($lag->lag_d8_15 ?? 0) + ($lag->lag_d16_30 ?? 0)], ['31+ d', $lag->lag_d31_plus ?? 0],
        ] as [$l, $v])
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">{{ $l }}</div><div class="mt-1 text-lg font-semibold">{{ number_format((int) $v) }}</div></div>
        @endforeach
    </div>

    <div class="card mt-6 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                @foreach (array_keys((array) ($rows->first() ?? [])) as $col)
                    <th class="th">{{ str_replace('_', ' ', $col) }}</th>
                @endforeach
                <th class="th">Activation %</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $row)
                @php $row = (array) $row; $tot = (int) ($row['total_imei'] ?? $row['total_activations'] ?? 0); $act = (int) ($row['activated'] ?? 0); @endphp
                <tr>
                    @foreach ($row as $v) <td class="td">{{ is_numeric($v) && ! str_contains((string) $v, '-') ? number_format((float) $v) : $v }}</td> @endforeach
                    <td class="td font-medium">{{ $tot > 0 ? round($act / $tot * 100, 1) : '—' }}</td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="10">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
