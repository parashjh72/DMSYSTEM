<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Quick Reports</h1>
        @can('exports.create')
            @if (in_array($type, ['zero_stock', 'act_value']))
                <button class="btn-ghost" wire:click="export">Export → CSV</button>
            @endif
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach (\App\Livewire\QuickReports::TYPES as $key => $label)
            <button wire:click="$set('type', '{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition
                    {{ $type === $key ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="card mt-4">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input" wire:model.live="rdCode">
                    <option value="">All distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            @if (in_array($type, ['act_vs_st', 'act_value']))
                <div><label class="label">Date from</label><input type="date" class="input" wire:model.live="dateFrom"></div>
                <div><label class="label">Date to</label><input type="date" class="input" wire:model.live="dateTo"></div>
            @endif
            @if ($type === 'act_value')
                <div>
                    <label class="label">Price against</label>
                    <select class="input" wire:model.live="valueBasis">
                        <option value="activation_date">Activation date (sell-out value)</option>
                        <option value="st_date">Sell-thru date</option>
                    </select>
                </div>
            @endif
        </div>
        @if ($type === 'act_value')
            <p class="mt-2 text-xs text-gray-400">
                Each device is valued at the price in force on its {{ $valueBasis === 'st_date' ? 'sell-thru' : 'activation' }} date,
                so a range that spans a price change blends the old and new rates.
                <a href="{{ route('model-prices') }}" wire:navigate class="text-indigo-600">Manage prices</a>.
            </p>
        @endif
    </div>

    {{-- 1. Activation vs Sell-thru --}}
    @if ($type === 'act_vs_st')
        @php
            $tSt = $series->sum('sell_through'); $tAct = $series->sum('activated');
        @endphp
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">Total sell-thru</div><div class="mt-1 text-lg font-semibold">{{ number_format($tSt) }}</div></div>
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">Total activated</div><div class="mt-1 text-lg font-semibold">{{ number_format($tAct) }}</div></div>
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">Sold-thru, not activated</div><div class="mt-1 text-lg font-semibold">{{ number_format(max(0, $tSt - $tAct)) }}</div></div>
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">Activation rate</div><div class="mt-1 text-lg font-semibold">{{ $tSt > 0 ? round($tAct / $tSt * 100, 1) : 0 }}%</div></div>
        </div>

        <div class="card mt-4 overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-gray-200 text-right">
                <thead class="bg-gray-50"><tr>
                    <th class="th text-left">Date</th>
                    <th class="th text-right">Sell-thru</th>
                    <th class="th text-right">Activated</th>
                    <th class="th text-right">Cum. sell-thru</th>
                    <th class="th text-right">Cum. activated</th>
                    <th class="th text-right">Gap (ST − Act)</th>
                    <th class="th text-right">Activation %</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($series as $r)
                    <tr>
                        <td class="td text-left">{{ $r['date'] }}</td>
                        <td class="td text-right">{{ number_format($r['sell_through']) }}</td>
                        <td class="td text-right">{{ number_format($r['activated']) }}</td>
                        <td class="td text-right text-gray-500">{{ number_format($r['cum_sell_through']) }}</td>
                        <td class="td text-right text-gray-500">{{ number_format($r['cum_activated']) }}</td>
                        <td class="td text-right font-medium">{{ number_format($r['gap']) }}</td>
                        <td class="td text-right">{{ $r['activation_pct'] }}%</td>
                    </tr>
                @empty
                    <tr><td class="td text-left text-gray-400" colspan="7">No data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- 3. Activation value by model --}}
    @if ($type === 'act_value')
        @php
            $vQty = $value->sum('qty'); $vVal = $value->sum('total_value'); $vUnpriced = $value->sum('unpriced_qty');
        @endphp
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">Devices</div><div class="mt-1 text-lg font-semibold">{{ number_format($vQty) }}</div></div>
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">Total value</div><div class="mt-1 text-lg font-semibold">{{ $symbol }} {{ number_format($vVal, 2) }}</div></div>
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">Models</div><div class="mt-1 text-lg font-semibold">{{ number_format($value->count()) }}</div></div>
            <div class="card p-3"><div class="text-xs uppercase text-gray-500">Unpriced devices</div><div class="mt-1 text-lg font-semibold {{ $vUnpriced ? 'text-amber-600' : '' }}">{{ number_format($vUnpriced) }}</div></div>
        </div>

        <div class="card mt-4 overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-gray-200 text-right">
                <thead class="bg-gray-50"><tr>
                    <th class="th text-left">Model</th>
                    <th class="th text-right">Qty</th>
                    <th class="th text-right">Total value</th>
                    <th class="th text-right">Avg price</th>
                    <th class="th text-right">Price range in period</th>
                    <th class="th text-right">Unpriced</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($value as $r)
                    <tr>
                        <td class="td text-left">{{ $r['model'] }}</td>
                        <td class="td text-right">{{ number_format($r['qty']) }}</td>
                        <td class="td text-right font-medium">{{ $symbol }} {{ number_format($r['total_value'], 2) }}</td>
                        <td class="td text-right text-gray-500">{{ $symbol }} {{ number_format($r['avg_price'], 2) }}</td>
                        <td class="td text-right text-gray-500">{{ $r['price_range'] }}</td>
                        <td class="td text-right {{ $r['unpriced_qty'] ? 'text-amber-600' : 'text-gray-300' }}">{{ number_format($r['unpriced_qty']) }}</td>
                    </tr>
                @empty
                    <tr><td class="td text-left text-gray-400" colspan="6">No activations in this range.</td></tr>
                @endforelse
                </tbody>
                @if ($value->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                        <tr>
                            <td class="td text-left">Total</td>
                            <td class="td text-right">{{ number_format($vQty) }}</td>
                            <td class="td text-right">{{ $symbol }} {{ number_format($vVal, 2) }}</td>
                            <td class="td"></td><td class="td"></td>
                            <td class="td text-right">{{ number_format($vUnpriced) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif

    {{-- 2. Zero-stock RT, sold-out but no sell-thru --}}
    @if ($type === 'zero_stock')
        <p class="mt-3 text-sm text-gray-500">
            Retailers with no stock left (everything activated) whose devices have <em>no</em> sell-through date —
            they sold-out but were never recorded as sold-through to.
        </p>
        <div class="card mt-4 overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="th">RT Code</th><th class="th">RT Name</th>
                    <th class="th">RD Code</th><th class="th">RD Name</th>
                    <th class="th text-right">Activated (sold-out)</th>
                    <th class="th text-right">In stock</th>
                    <th class="th text-right">Sell-thru</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr wire:key="z-{{ $r->rt_code }}">
                        <td class="td font-mono">{{ $r->rt_code }}</td>
                        <td class="td">{{ $r->rt_name }}</td>
                        <td class="td">{{ $r->rd_code }}</td>
                        <td class="td">{{ $r->rd_name }}</td>
                        <td class="td text-right font-medium">{{ number_format($r->activated) }}</td>
                        <td class="td text-right text-gray-400">{{ number_format($r->in_stock) }}</td>
                        <td class="td text-right text-gray-400">{{ number_format($r->sell_through) }}</td>
                    </tr>
                @empty
                    <tr><td class="td text-gray-400" colspan="7">No retailers match — good, no anomalies.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $rows?->links() }}</div>
    @endif
</div>
