<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Stock Report</h1>
            <p class="mt-1 text-sm text-gray-500">Unsold inventory — devices that are not yet activated.</p>
        </div>
        @can('exports.create')
            <button class="btn-ghost" wire:click="export">Export → CSV</button>
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2" wire:loading.class="opacity-50" wire:target="type">
        @foreach (\App\Livewire\StockReport::TYPES as $key => $label)
            <button type="button" wire:click="$set('type', '{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition
                    {{ $type === $key
                        ? 'bg-indigo-600 text-white ring-indigo-600 shadow-sm'
                        : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50 hover:text-gray-900' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="card mt-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input" wire:model.live="rdCode">
                    <option value="">All distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            @if ($type === 'rt')
                <div>
                    <label class="label">Retailer (RT)</label>
                    <select class="input" wire:model.live="rtCode">
                        <option value="">{{ $rdCode ? 'All retailers for this RD' : 'All retailers' }}</option>
                        @foreach ($rtOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['Total stock', $summary->total_stock],
            ['RD stock (no RT)', $summary->rd_stock],
            ['RT stock (with RT)', $summary->rt_stock],
            ['Models in stock', $summary->models],
        ] as [$l, $v])
            <div class="card p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $l }}</div>
                <div class="mt-1 text-lg font-semibold">{{ number_format((int) $v) }}</div>
            </div>
        @endforeach
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @switch($type)
                        @case('rd')
                            <th class="th">RD Code</th><th class="th">RD Name</th>
                            <th class="th">Model</th><th class="th text-right">Qty in stock</th>
                            @break
                        @case('rt')
                            <th class="th">RD Code</th><th class="th">RD Name</th>
                            <th class="th">RT Code</th><th class="th">RT Name</th>
                            <th class="th">Model</th><th class="th text-right">Qty in stock</th>
                            @break
                        @case('model')
                            <th class="th">Model</th>
                            <th class="th text-right">RD stock</th>
                            <th class="th text-right">RT stock</th>
                            <th class="th text-right">Total stock</th>
                            @break
                    @endswitch
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $r)
                <tr wire:key="row-{{ $loop->index }}">
                    @switch($type)
                        @case('rd')
                            <td class="td">{{ $r->rd_code }}</td>
                            <td class="td">{{ $r->rd_name }}</td>
                            <td class="td">{{ $r->model ?: '—' }}</td>
                            <td class="td text-right font-medium">{{ number_format($r->qty) }}</td>
                            @break
                        @case('rt')
                            <td class="td">{{ $r->rd_code }}</td>
                            <td class="td">{{ $r->rd_name }}</td>
                            <td class="td">{{ $r->rt_code }}</td>
                            <td class="td">{{ $r->rt_name }}</td>
                            <td class="td">{{ $r->model ?: '—' }}</td>
                            <td class="td text-right font-medium">{{ number_format($r->qty) }}</td>
                            @break
                        @case('model')
                            <td class="td">{{ $r->model ?: '—' }}</td>
                            <td class="td text-right">{{ number_format($r->rd_stock) }}</td>
                            <td class="td text-right">{{ number_format($r->rt_stock) }}</td>
                            <td class="td text-right font-medium">{{ number_format($r->total_stock) }}</td>
                            @break
                    @endswitch
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="6">No stock for this selection.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                    @switch($type)
                        @case('rd')
                            <tr>
                                <td class="td" colspan="3">Total (all rows)</td>
                                <td class="td text-right">{{ number_format((int) $summary->rd_stock) }}</td>
                            </tr>
                            @break
                        @case('rt')
                            <tr>
                                <td class="td" colspan="5">Total (all rows)</td>
                                <td class="td text-right">{{ number_format((int) $summary->rt_stock) }}</td>
                            </tr>
                            @break
                        @case('model')
                            <tr>
                                <td class="td">Total (all models)</td>
                                <td class="td text-right">{{ number_format((int) $summary->rd_stock) }}</td>
                                <td class="td text-right">{{ number_format((int) $summary->rt_stock) }}</td>
                                <td class="td text-right">{{ number_format((int) $summary->total_stock) }}</td>
                            </tr>
                            @break
                    @endswitch
                </tfoot>
            @endif
        </table>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
