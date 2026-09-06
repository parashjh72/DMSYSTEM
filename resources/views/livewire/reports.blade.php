<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Reports</h1>
        @can('exports.create')
            @if (\App\Livewire\Reports::TYPES[$type][2] ?? null)
                <button class="btn-ghost" wire:click="export">Export → CSV</button>
            @endif
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach (\App\Livewire\Reports::TYPES as $key => [$label])
            <button wire:click="$set('type', '{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-300' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="card mt-4">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div><label class="label">ST date from</label><input type="date" class="input" wire:model="f.st_date_from"></div>
            <div><label class="label">ST date to</label><input type="date" class="input" wire:model="f.st_date_to"></div>
            <div><label class="label">Activation from</label><input type="date" class="input" wire:model="f.activation_date_from"></div>
            <div><label class="label">Activation to</label><input type="date" class="input" wire:model="f.activation_date_to"></div>
            <div><label class="label">TSO</label><input class="input" wire:model="f.tso"></div>
            <div><label class="label">RD code</label><input class="input" wire:model="f.rd_code"></div>
            <div><label class="label">RT code</label><input class="input" wire:model="f.rt_code"></div>
            <div><label class="label">Model</label><input class="input" wire:model="f.model"></div>
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
