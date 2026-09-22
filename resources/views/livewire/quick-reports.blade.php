<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Quick Reports</h1>
            <p class="mt-1 text-xs text-slate-500">Fast tactical reports for inventory distribution, activation run-rates, and financial values.</p>
        </div>
        <div>
            @can('exports.create')
                @if (in_array($type, ['zero_stock', 'act_value']))
                    <button class="btn-primary text-xs" wire:click="export">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        <span>Export CSV</span>
                    </button>
                @endif
            @endcan
        </div>
    </div>

    {{-- Report Type Selector --}}
    <div class="flex flex-wrap gap-2">
        @foreach (\App\Livewire\QuickReports::TYPES as $key => $label)
            <button wire:click="$set('type', '{{ $key }}')"
                    class="rounded-xl px-3.5 py-2 text-xs font-semibold transition-all duration-150 shadow-2xs {{ $type === $key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:text-slate-900' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Filter Card --}}
    <div class="card space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdCode">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            @if (in_array($type, ['act_vs_st', 'act_value']))
                <div>
                    <label class="label">Date From</label>
                    <input type="date" class="input text-xs" wire:model.live="dateFrom">
                </div>
                <div>
                    <label class="label">Date To</label>
                    <input type="date" class="input text-xs" wire:model.live="dateTo">
                </div>
            @endif
            @if ($type === 'act_value')
                <div>
                    <label class="label">Price Baseline</label>
                    <select class="input text-xs" wire:model.live="valueBasis">
                        <option value="activation_date">Activation Date (Sell-Out Value)</option>
                        <option value="st_date">Sell-Through Date (Invoice Value)</option>
                    </select>
                </div>
            @endif
        </div>

        @if ($type === 'act_value')
            <p class="text-xs text-slate-400 border-t border-slate-100 pt-2">
                Each device is valued at the active price list in effect on its {{ $valueBasis === 'st_date' ? 'sell-through' : 'activation' }} date.
                <a href="{{ route('model-prices') }}" wire:navigate class="text-indigo-600 font-medium hover:underline">Manage model prices &rarr;</a>
            </p>
        @endif
    </div>

    {{-- 1. Activation vs Sell-Through --}}
    @if ($type === 'act_vs_st')
        @php
            $tSt = $series->sum('sell_through');
            $tAct = $series->sum('activated');
            $gap = max(0, $tSt - $tAct);
            $actRate = $tSt > 0 ? round($tAct / $tSt * 100, 1) : 0;
        @endphp
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Sell-Through</div>
                <div class="mt-1 text-2xl font-extrabold text-indigo-700">{{ number_format($tSt) }}</div>
            </div>
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Activated</div>
                <div class="mt-1 text-2xl font-extrabold text-emerald-700">{{ number_format($tAct) }}</div>
            </div>
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Unactivated Pipeline (Gap)</div>
                <div class="mt-1 text-2xl font-extrabold text-amber-700">{{ number_format($gap) }}</div>
            </div>
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Conversion Rate</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $actRate }}%</div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500">
                            <th class="th">Date</th>
                            <th class="th text-right">Daily ST</th>
                            <th class="th text-right">Daily Act.</th>
                            <th class="th text-right">Cum. ST</th>
                            <th class="th text-right">Cum. Act.</th>
                            <th class="th text-right">Gap (ST &minus; Act)</th>
                            <th class="th text-right">Conversion %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($series as $r)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="td font-medium text-slate-900">{{ $r['date'] }}</td>
                                <td class="td text-right font-mono font-semibold text-indigo-600">{{ number_format($r['sell_through']) }}</td>
                                <td class="td text-right font-mono font-semibold text-emerald-600">{{ number_format($r['activated']) }}</td>
                                <td class="td text-right font-mono text-slate-500">{{ number_format($r['cum_sell_through']) }}</td>
                                <td class="td text-right font-mono text-slate-500">{{ number_format($r['cum_activated']) }}</td>
                                <td class="td text-right font-mono font-bold text-amber-700">{{ number_format($r['gap']) }}</td>
                                <td class="td text-right">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-bold {{ (float)$r['activation_pct'] >= 70 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $r['activation_pct'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-12 text-center text-slate-400 text-xs">No transaction records in date range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 2. Activation Value by Model --}}
    @if ($type === 'act_value')
        @php
            $vQty = $value->sum('qty');
            $vVal = $value->sum('total_value');
            $vUnpriced = $value->sum('unpriced_qty');
        @endphp
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Units</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ number_format($vQty) }}</div>
            </div>
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Estimated Turnover Value</div>
                <div class="mt-1 text-2xl font-extrabold text-emerald-700">NPR {{ number_format($vVal, 2) }}</div>
            </div>
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Unpriced Units</div>
                <div class="mt-1 text-2xl font-extrabold {{ $vUnpriced > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ number_format($vUnpriced) }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500">
                            <th class="th">Model</th>
                            <th class="th text-right">Units</th>
                            <th class="th text-right">Effective Unit Price</th>
                            <th class="th text-right">Total Realized Value</th>
                            <th class="th text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($value as $v)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="td font-bold text-slate-900">{{ $v['model'] }}</td>
                                <td class="td text-right font-mono">{{ number_format($v['qty']) }}</td>
                                <td class="td text-right font-mono text-slate-600">NPR {{ number_format($v['avg_price'], 2) }}</td>
                                <td class="td text-right font-mono font-bold text-emerald-700">NPR {{ number_format($v['total_value'], 2) }}</td>
                                <td class="td text-right">
                                    @if ($v['unpriced_qty'] > 0)
                                        <span class="badge-amber text-[10px]">{{ $v['unpriced_qty'] }} unpriced</span>
                                    @else
                                        <span class="badge-emerald text-[10px]">Priced</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-12 text-center text-slate-400 text-xs">No valuation records available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
