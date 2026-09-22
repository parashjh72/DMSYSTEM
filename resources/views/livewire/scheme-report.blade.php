<div>
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col gap-2">
        <a href="{{ route('schemes.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Schemes
        </a>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">{{ $scheme->name }} — Achievement</h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $scheme->effective_from->format('d M Y') }} – {{ $scheme->effective_to->format('d M Y') }} ·
                    Basis: {{ $scheme->sellout_basis === 'st_date' ? 'Sell-through date' : 'Activation date' }} ·
                    {{ ucfirst($scheme->qualified_models) }} models
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('settings.manage')
                    <a class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200" href="{{ route('schemes.retailers', $scheme->uuid) }}" wire:navigate>
                        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        Manage Retailers
                    </a>
                @endcan
                @can('exports.create')
                    <button class="btn-primary text-xs flex items-center gap-1.5" wire:click="export">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Excel
                    </button>
                @endcan
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card mt-6 border border-gray-100 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label text-xs font-semibold text-gray-700">Filter Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdCode">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" wire:model.live="qualifiedOnly">
                    Only retailers who reached a slab
                </label>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" wire:model.live="enrolledOnly">
                    Only enrolled retailers ({{ $enrolledCount }})
                </label>
            </div>
        </div>
    </div>

    <!-- 4 KPI Stat Cards -->
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="card p-4 border border-indigo-100 bg-white shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Reached a Slab</div>
            <div class="mt-1 text-2xl font-extrabold text-indigo-900 font-mono">{{ number_format($qualifiedCount) }}</div>
            <div class="text-[11px] text-indigo-600 mt-0.5">Qualified retailers</div>
        </div>
        <div class="card p-4 border border-emerald-100 bg-white shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Eligible (Enrolled + Min)</div>
            <div class="mt-1 text-2xl font-extrabold text-emerald-800 font-mono">{{ number_format($eligibleCount) }}</div>
            <div class="text-[11px] text-emerald-600 mt-0.5">Passed threshold</div>
        </div>
        <div class="card p-4 border border-blue-100 bg-white shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Qualified Value</div>
            <div class="mt-1 text-2xl font-extrabold text-blue-900 font-mono">{{ config('pricing.symbol') }} {{ number_format($totalValue, 2) }}</div>
            <div class="text-[11px] text-blue-600 mt-0.5">Total eligible revenue</div>
        </div>
        <div class="card p-4 border border-amber-100 bg-white shadow-2xs">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Payout (Option 1)</div>
            <div class="mt-1 text-2xl font-extrabold text-amber-900 font-mono">{{ config('pricing.symbol') }} {{ number_format($totalPayout, 2) }}</div>
            <div class="text-[11px] text-amber-600 mt-0.5">Calculated cash incentive</div>
        </div>
    </div>

    <!-- Achievement Matrix Table -->
    <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50/75">
                    <tr>
                        <th class="th text-left">RT Code</th>
                        <th class="th text-left">Retailer Name</th>
                        <th class="th text-left">RD</th>
                        <th class="th text-left">Plan</th>
                        <th class="th text-left">Category</th>
                        <th class="th text-right">Units Sold</th>
                        <th class="th text-right">Qualified Value</th>
                        <th class="th text-right">Slab</th>
                        <th class="th text-center">Eligible</th>
                        <th class="th text-right">Payout %</th>
                        <th class="th text-right">Entitlement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($rows as $r)
                    <tr wire:key="ach-{{ $r['rt_code'] }}" class="hover:bg-gray-50/75 transition {{ $r['enrolled'] ? '' : 'text-gray-400 bg-gray-50/30' }}">
                        <td class="td text-left font-mono font-semibold text-gray-900">{{ $r['rt_code'] }}</td>
                        <td class="td text-left font-medium text-gray-800">{{ $r['rt_name'] }}</td>
                        <td class="td text-left text-gray-600">{{ $r['rd_code'] }}</td>
                        <td class="td text-left">
                            @if ($r['enrolled'])
                                <span class="badge {{ $r['plan_key'] === 'option_two' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
                                    {{ $r['plan_key'] === 'option_two' ? 'Option 2 (Gift)' : 'Option 1 (%)' }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="td text-left text-gray-600">{{ $r['category'] ?? '—' }}</td>
                        <td class="td text-right font-mono font-medium">{{ number_format($r['qty']) }}</td>
                        <td class="td text-right font-mono font-semibold text-gray-900">{{ number_format($r['qualified_value'], 2) }}</td>
                        <td class="td text-right">
                            @if ($r['slab_no'])
                                <span class="badge bg-indigo-50 text-indigo-700 font-mono">Slab #{{ $r['slab_no'] }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                            @if ($r['enrolled'] && $r['min_slab'])
                                <span class="text-[10px] text-gray-400 font-mono"> / min {{ $r['min_slab'] }}</span>
                            @endif
                        </td>
                        <td class="td text-center">
                            @if (! $r['enrolled'])
                                <span class="badge bg-gray-100 text-gray-500">Not Enrolled</span>
                            @elseif ($r['eligible'])
                                <span class="badge bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200 font-semibold">Yes</span>
                            @else
                                <span class="badge bg-rose-50 text-rose-700">No</span>
                            @endif
                        </td>
                        <td class="td text-right font-mono">{{ $r['payout_percent'] ? $r['payout_percent'].'%' : '—' }}</td>
                        <td class="td text-right font-mono font-bold text-gray-900">
                            @if (is_numeric($r['entitlement']))
                                <span class="text-emerald-700">{{ config('pricing.symbol') }} {{ number_format($r['entitlement'], 2) }}</span>
                            @elseif ($r['entitlement'])
                                <span class="text-purple-700 font-medium">{{ $r['entitlement'] }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="td text-center text-gray-400 py-10" colspan="11">
                            <p class="text-xs">No retailer achievement records found matching the active filters.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50/90 font-bold text-gray-900">
                        <tr>
                            <td class="td text-left" colspan="6">Summary Total ({{ $rows->count() }} retailers)</td>
                            <td class="td text-right font-mono">{{ number_format($totalValue, 2) }}</td>
                            <td class="td" colspan="3"></td>
                            <td class="td text-right font-mono text-emerald-700">{{ config('pricing.symbol') }} {{ number_format($totalPayout, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
