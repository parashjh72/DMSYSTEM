<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Data Explorer</h1>
        @can('exports.create')
            <button class="btn-ghost" wire:click="export">Export filtered → CSV</button>
        @endcan
    </div>

    <div class="card mt-6">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div><label class="label">IMEI (exact)</label><input class="input" wire:model="f.imei"></div>
            <div><label class="label">Model</label><input class="input" wire:model="f.model"></div>
            <div><label class="label">TSO</label><input class="input" wire:model="f.tso"></div>
            <div><label class="label">Source</label><input class="input" wire:model="f.source"></div>
            <div><label class="label">RD code</label><input class="input" wire:model="f.rd_code"></div>
            <div><label class="label">RT code</label><input class="input" wire:model="f.rt_code"></div>
            <div><label class="label">ST date from</label><input type="date" class="input" wire:model="f.st_date_from"></div>
            <div><label class="label">ST date to</label><input type="date" class="input" wire:model="f.st_date_to"></div>
            <div><label class="label">Activation from</label><input type="date" class="input" wire:model="f.activation_date_from"></div>
            <div><label class="label">Activation to</label><input type="date" class="input" wire:model="f.activation_date_to"></div>
            <div><label class="label">Sell-In from</label><input type="date" class="input" wire:model="f.sell_in_date_from"></div>
            <div><label class="label">Sell-In to</label><input type="date" class="input" wire:model="f.sell_in_date_to"></div>
            <div>
                <label class="label">Activation status</label>
                <select class="input" wire:model="f.activation_status">
                    <option value="">Any</option>
                    <option value="activated">Activated</option>
                    <option value="not_activated">Not activated</option>
                </select>
            </div>
            <div>
                <label class="label">Page size</label>
                <select class="input" wire:model.live="perPage">
                    <option>25</option><option>50</option><option>100</option><option>250</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex gap-3">
            <button class="btn-primary" wire:click="applyFilters">Apply</button>
            <button class="btn-ghost" wire:click="resetFilters">Reset</button>
        </div>
    </div>

    <div class="card mt-6 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @foreach (['imei' => 'IMEI', 'model' => 'Model', 'tso' => 'TSO', 'rd_code' => 'RD', 'rt_code' => 'RT', 'st_date' => 'ST Date', 'activation_date' => 'Activation', 'sell_in_date' => 'Sell-In', 'activation_days' => 'Days', 'source' => 'Source'] as $col => $label)
                        <th class="th cursor-pointer select-none" wire:click="sortBy('{{ $col }}')">
                            {{ $label }}
                            @if ($sort === $col) <span class="text-gray-400">{{ $dir === 'asc' ? '▲' : '▼' }}</span> @endif
                        </th>
                    @endforeach
                    <th class="th">Batch</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($records as $r)
                <tr wire:key="r-{{ $r->id }}">
                    <td class="td font-mono">{{ $r->imei }}</td>
                    <td class="td">{{ $r->model }}</td>
                    <td class="td">{{ $r->tso }}</td>
                    <td class="td">{{ $r->rd_code }}</td>
                    <td class="td">{{ $r->rt_code }}</td>
                    <td class="td">{{ $r->st_date }}</td>
                    <td class="td">{{ $r->activation_date ?? '—' }}</td>
                    <td class="td">{{ $r->sell_in_date ?? '—' }}</td>
                    <td class="td">{{ $r->activation_days ?? '—' }}</td>
                    <td class="td">{{ $r->source }}</td>
                    <td class="td text-gray-400">#{{ $r->last_import_batch_id }}</td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="10">No records match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $records->links() }}</div>
</div>
