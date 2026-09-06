<div>
    <a href="{{ route('schemes.index') }}" wire:navigate class="text-sm text-indigo-600">← Schemes</a>
    <div class="mt-1 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ $scheme->name }} — achievement</h1>
            <p class="text-xs text-gray-500">
                {{ $scheme->effective_from->format('d M Y') }} – {{ $scheme->effective_to->format('d M Y') }} ·
                sell-out = {{ $scheme->sellout_basis === 'st_date' ? 'sell-thru date' : 'activation date' }} ·
                {{ ucfirst($scheme->qualified_models) }} models
            </p>
        </div>
        @can('exports.create')
            <button class="btn-ghost" wire:click="export">Export → Excel</button>
        @endcan
    </div>

    <div class="card mt-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input" wire:model.live="rdCode">
                    <option value="">All distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <label class="flex items-end gap-2 pb-2 text-sm text-gray-600">
                <input type="checkbox" class="rounded border-gray-300" wire:model.live="qualifiedOnly">
                Only retailers who reached a slab
            </label>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card p-3"><div class="text-xs uppercase text-gray-500">Retailers (qualified)</div><div class="mt-1 text-lg font-semibold">{{ number_format($qualifiedCount) }}</div></div>
        <div class="card p-3"><div class="text-xs uppercase text-gray-500">Qualified value</div><div class="mt-1 text-lg font-semibold">{{ config('pricing.symbol') }} {{ number_format($totalValue, 2) }}</div></div>
        <div class="card p-3"><div class="text-xs uppercase text-gray-500">Total payout</div><div class="mt-1 text-lg font-semibold">{{ config('pricing.symbol') }} {{ number_format($totalPayout, 2) }}</div></div>
        <div class="card p-3"><div class="text-xs uppercase text-gray-500">Slabs</div><div class="mt-1 text-lg font-semibold">{{ $scheme->slabs->count() }}</div></div>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200 text-right">
            <thead class="bg-gray-50"><tr>
                <th class="th text-left">RT Code</th><th class="th text-left">RT Name</th><th class="th text-left">RD</th>
                <th class="th text-right">Qualified Qty</th>
                <th class="th text-right">Qualified Value</th>
                <th class="th text-right">Slab</th>
                <th class="th text-right">Payout %</th>
                <th class="th text-right">Payout Amount</th>
                <th class="th text-left">Reward</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $r)
                <tr wire:key="ach-{{ $r['rt_code'] }}">
                    <td class="td text-left font-mono">{{ $r['rt_code'] }}</td>
                    <td class="td text-left">{{ $r['rt_name'] }}</td>
                    <td class="td text-left">{{ $r['rd_code'] }}</td>
                    <td class="td text-right">{{ number_format($r['qty']) }}</td>
                    <td class="td text-right font-medium">{{ number_format($r['qualified_value'], 2) }}</td>
                    <td class="td text-right">
                        @if ($r['slab_no']) <span class="badge bg-indigo-100 text-indigo-800">{{ $r['slab_no'] }}</span>
                        @else <span class="text-gray-300">—</span> @endif
                    </td>
                    <td class="td text-right">{{ $r['payout_percent'] ? $r['payout_percent'].'%' : '—' }}</td>
                    <td class="td text-right font-semibold">{{ $r['payout_amount'] ? number_format($r['payout_amount'], 2) : '—' }}</td>
                    <td class="td text-left text-xs text-gray-500">{{ $r['reward'] }}</td>
                </tr>
            @empty
                <tr><td class="td text-left text-gray-400" colspan="9">No retailers.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                    <tr>
                        <td class="td text-left" colspan="4">Total ({{ $rows->count() }} retailers)</td>
                        <td class="td text-right">{{ number_format($totalValue, 2) }}</td>
                        <td class="td"></td><td class="td"></td>
                        <td class="td text-right">{{ number_format($totalPayout, 2) }}</td>
                        <td class="td"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="card mt-6">
        <h2 class="text-sm font-semibold">Slabs</h2>
        <table class="mt-2 w-full text-sm">
            <thead><tr class="text-left text-xs text-gray-400"><th class="py-1">#</th><th>Target</th><th>Payout %</th><th>Reward</th></tr></thead>
            <tbody>
            @foreach ($scheme->slabs as $s)
                <tr class="border-t border-gray-100">
                    <td class="py-1">{{ $s->slab_no }}</td>
                    <td>{{ $s->label ?: number_format($s->min_value).' – '.($s->max_value ? number_format($s->max_value) : '& above') }}</td>
                    <td>{{ rtrim(rtrim(number_format($s->payout_percent, 2), '0'), '.') }}%</td>
                    <td class="text-gray-500">{{ $s->reward }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
