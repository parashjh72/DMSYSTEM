<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Data Explorer</h1>
            <p class="mt-1 text-xs text-slate-500">Query, inspect and filter device-level records across regional distributors and retailers.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('exports.create')
                <button class="btn-primary text-xs" wire:click="export">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span>Export Filtered to CSV</span>
                </button>
            @endcan
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
                <h2 class="text-sm font-bold text-slate-800">Advanced Filter Controls</h2>
            </div>
            <span class="text-xs text-slate-400">Match across indexed fields</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <div>
                <label class="label">IMEI (Exact)</label>
                <input class="input font-mono text-xs" placeholder="e.g. 86082108..." wire:model="f.imei">
            </div>
            <div>
                <label class="label">Model Name</label>
                <input class="input text-xs" placeholder="e.g. Note 12" wire:model="f.model">
            </div>
            <div>
                <label class="label">Field Officer (TSO)</label>
                <input class="input text-xs" placeholder="TSO name..." wire:model="f.tso">
            </div>
            <div>
                <label class="label">Source Tag</label>
                <input class="input text-xs" placeholder="e.g. import-01" wire:model="f.source">
            </div>
            <div>
                <label class="label">Distributor (RD Code)</label>
                <input class="input font-mono text-xs uppercase" placeholder="RD code..." wire:model="f.rd_code">
            </div>
            <div>
                <label class="label">Retailer (RT Code)</label>
                <input class="input font-mono text-xs uppercase" placeholder="RT code..." wire:model="f.rt_code">
            </div>
            <div>
                <label class="label">ST Date From</label>
                <input type="date" class="input text-xs" wire:model="f.st_date_from">
            </div>
            <div>
                <label class="label">ST Date To</label>
                <input type="date" class="input text-xs" wire:model="f.st_date_to">
            </div>
            <div>
                <label class="label">Activation Date From</label>
                <input type="date" class="input text-xs" wire:model="f.activation_date_from">
            </div>
            <div>
                <label class="label">Activation Date To</label>
                <input type="date" class="input text-xs" wire:model="f.activation_date_to">
            </div>
            <div>
                <label class="label">Sell-In Date From</label>
                <input type="date" class="input text-xs" wire:model="f.sell_in_date_from">
            </div>
            <div>
                <label class="label">Sell-In Date To</label>
                <input type="date" class="input text-xs" wire:model="f.sell_in_date_to">
            </div>
            <div>
                <label class="label">Activation Status</label>
                <select class="input text-xs" wire:model="f.activation_status">
                    <option value="">All Devices</option>
                    <option value="activated">Activated Only</option>
                    <option value="not_activated">Unactivated / Channel Stock</option>
                </select>
            </div>
            <div>
                <label class="label">Page Size</label>
                <select class="input text-xs" wire:model.live="perPage">
                    <option value="25">25 rows</option>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="250">250 rows</option>
                </select>
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

    {{-- Records Table --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        @foreach ([
                            'imei' => 'IMEI',
                            'model' => 'Model',
                            'product_code' => 'Product Code',
                            'tso' => 'TSO',
                            'rd_code' => 'RD Code',
                            'rt_code' => 'RT Code',
                            'st_date' => 'ST Date',
                            'activation_date' => 'Activation',
                            'sell_in_date' => 'Sell-In',
                            'activation_days' => 'Days',
                            'source' => 'Source'
                        ] as $col => $label)
                            <th class="th cursor-pointer select-none hover:text-slate-900 transition" wire:click="sortBy('{{ $col }}')">
                                <span class="flex items-center gap-1">
                                    <span>{{ $label }}</span>
                                    @if ($sort === $col)
                                        <span class="text-indigo-600 font-bold">{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </span>
                            </th>
                        @endforeach
                        <th class="th text-right">Batch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($records as $r)
                        <tr wire:key="r-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="td font-mono font-semibold text-slate-900">{{ $r->imei }}</td>
                            <td class="td font-medium text-slate-800">{{ $r->model }}</td>
                            <td class="td text-slate-500">{{ $r->product_code ?? '—' }}</td>
                            <td class="td text-slate-600">{{ $r->tso }}</td>
                            <td class="td font-mono font-medium text-indigo-700 bg-indigo-50/30 px-2 rounded">{{ $r->rd_code }}</td>
                            <td class="td font-mono text-slate-600">{{ $r->rt_code ?: '—' }}</td>
                            <td class="td text-slate-600">{{ $r->st_date }}</td>
                            <td class="td">
                                @if ($r->activation_date)
                                    <span class="badge-emerald font-semibold">{{ $r->activation_date }}</span>
                                @else
                                    <span class="badge-slate font-medium">Unactivated</span>
                                @endif
                            </td>
                            <td class="td text-slate-500">{{ $r->sell_in_date ?? '—' }}</td>
                            <td class="td">
                                @if ($r->activation_days !== null)
                                    <span class="font-medium {{ $r->activation_days <= 14 ? 'text-emerald-700' : ($r->activation_days <= 45 ? 'text-amber-700' : 'text-slate-600') }}">
                                        {{ $r->activation_days }} d
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="td text-slate-500">{{ $r->source }}</td>
                            <td class="td text-right">
                                <span class="font-mono text-[11px] text-slate-400">#{{ $r->last_import_batch_id }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-12 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 mb-3">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <h3 class="text-sm font-bold text-slate-800">No records matched your criteria</h3>
                                <p class="text-xs text-slate-400 mt-1">Try broadening your search or resetting active filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div>
        {{ $records->links() }}
    </div>
</div>
