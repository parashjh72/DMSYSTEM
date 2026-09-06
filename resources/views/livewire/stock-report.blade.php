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

    @php
        $keyCount = $type === 'rt' ? 4 : ($type === 'model' ? 1 : 2);
    @endphp

    @if ($type === 'model')
        <div class="card mt-4 overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="th">Model</th>
                    <th class="th text-right">RD stock</th>
                    <th class="th text-right">RT stock</th>
                    <th class="th text-right">Total stock</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr wire:key="m-{{ $loop->index }}">
                        <td class="td">{{ $r->model ?: '—' }}</td>
                        <td class="td text-right">{{ number_format($r->rd_stock) }}</td>
                        <td class="td text-right">{{ number_format($r->rt_stock) }}</td>
                        <td class="td text-right font-medium">{{ number_format($r->total_stock) }}</td>
                    </tr>
                @empty
                    <tr><td class="td text-gray-400" colspan="4">No stock for this selection.</td></tr>
                @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                        <tr>
                            <td class="td">Total (all models)</td>
                            <td class="td text-right">{{ number_format((int) $summary->rd_stock) }}</td>
                            <td class="td text-right">{{ number_format((int) $summary->rt_stock) }}</td>
                            <td class="td text-right">{{ number_format((int) $summary->total_stock) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @else
        {{-- Pivot: one row per RD (or RD+RT), one column per model --}}
        <div class="card mt-4 overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-gray-200 text-right">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="th sticky left-0 bg-gray-50">RD Code</th>
                        <th class="th">RD Name</th>
                        @if ($type === 'rt')
                            <th class="th">RT Code</th><th class="th">RT Name</th>
                        @endif
                        @foreach ($columns['models'] as $m)
                            <th class="th text-right">{{ $m }}</th>
                        @endforeach
                        @if ($columns['hasOther'])
                            <th class="th text-right">Other</th>
                        @endif
                        <th class="th text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr wire:key="p-{{ $loop->index }}">
                        <td class="td text-left font-mono sticky left-0 bg-white">{{ $r->rd_code }}</td>
                        <td class="td text-left">{{ $r->rd_name }}</td>
                        @if ($type === 'rt')
                            <td class="td text-left font-mono">{{ $r->rt_code }}</td>
                            <td class="td text-left">{{ $r->rt_name }}</td>
                        @endif
                        @foreach ($columns['models'] as $m)
                            <td class="td text-right {{ ($r->cells[$m] ?? 0) ? '' : 'text-gray-300' }}">{{ number_format($r->cells[$m] ?? 0) }}</td>
                        @endforeach
                        @if ($columns['hasOther'])
                            <td class="td text-right {{ $r->other ? '' : 'text-gray-300' }}">{{ number_format($r->other) }}</td>
                        @endif
                        <td class="td text-right font-semibold">{{ number_format($r->total_qty) }}</td>
                    </tr>
                @empty
                    <tr><td class="td text-left text-gray-400" colspan="{{ $keyCount + count($columns['models']) + ($columns['hasOther'] ? 2 : 1) }}">No stock for this selection.</td></tr>
                @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                        <tr>
                            <td class="td text-left sticky left-0 bg-gray-50" colspan="{{ $keyCount }}">Total (all rows)</td>
                            @foreach ($columns['models'] as $m)
                                <td class="td text-right">{{ number_format($columns['totals'][$m] ?? 0) }}</td>
                            @endforeach
                            @if ($columns['hasOther'])
                                <td class="td text-right">{{ number_format($columns['otherTotal']) }}</td>
                            @endif
                            <td class="td text-right">{{ number_format($columns['grandTotal']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
