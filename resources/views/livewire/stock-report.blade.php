<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ ucfirst($noun) }} Analysis</h1>
                <span class="inline-flex items-center gap-1 rounded-full {{ $noun === 'sellout' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-indigo-50 text-indigo-700 ring-indigo-600/20' }} px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset">
                    {{ $noun === 'sellout' ? 'Over The Counter' : 'Channel Inventory' }}
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                {{ $noun === 'sellout'
                    ? 'Customer retail activations verified over the counter.'
                    : 'Unactivated channel stock currently sitting with distributors or retailers.' }}
            </p>
        </div>

        @can('exports.create')
            <div class="flex items-center gap-2">
                <button class="btn-primary text-xs" wire:click="export('xlsx')">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span>Excel (.xlsx)</span>
                </button>
                <button class="btn-ghost text-xs" wire:click="export('csv')">
                    CSV
                </button>
            </div>
        @endcan
    </div>

    {{-- Type Tabs --}}
    <div class="flex flex-wrap items-center gap-2" wire:loading.class="opacity-60" wire:target="type">
        @foreach ($types as $key => $label)
            <button type="button" wire:click="$set('type', '{{ $key }}')"
                    class="rounded-xl px-3.5 py-2 text-xs font-semibold transition-all duration-150 shadow-2xs {{ $type === $key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:text-slate-900' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Filter Panel --}}
    <div class="card space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdCode">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>

            @if ($noun === 'sellout')
                <div>
                    <label class="label">Sellout Date From</label>
                    <input type="date" class="input text-xs" wire:model.live="dateFrom">
                </div>
                <div>
                    <label class="label">Sellout Date To</label>
                    <input type="date" class="input text-xs" wire:model.live="dateTo">
                </div>
            @endif

            <div>
                <label class="label">Model Lifecycle</label>
                <div class="flex gap-1">
                    @foreach (['' => 'Both', 'running' => 'Running', 'out' => 'Phased Out'] as $val => $lbl)
                        <button wire:click="$set('lifecycle', '{{ $val }}')"
                                class="flex-1 rounded-xl px-2 py-2 text-xs font-semibold transition-all {{ $lifecycle === $val ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Retailer Multi-Matcher (if in RT mode) --}}
        @if ($type === 'rt')
            <div class="border-t border-slate-100 pt-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="label !mb-0">Selected Retailers ({{ count($rtCodes) }})</label>
                    <span class="text-xs text-slate-400">Filter specific accounts or paste from Excel</span>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <textarea rows="3" wire:model="rtPaste"
                                  class="input font-mono text-xs leading-5 bg-slate-50/50"
                                  placeholder="Paste RT codes or names — one per line, or comma separated..."></textarea>
                        <div class="mt-2 flex items-center gap-2">
                            <button class="btn-primary !py-1.5 !px-3 text-xs" wire:click="matchRetailers">
                                Match &amp; Select
                            </button>
                            <button class="btn-ghost !py-1.5 !px-3 text-xs" wire:click="clearRts">
                                Clear Selection
                            </button>
                            @if ($rtUnmatched)
                                <span class="text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-200">
                                    {{ count($rtUnmatched) }} not matched
                                </span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <input type="search" class="input mb-2 text-xs" placeholder="Search retailer list…"
                               wire:model.live.debounce.300ms="rtSearch">
                        <div class="max-h-40 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100 bg-white">
                            @forelse ($rtOptions as $code => $label)
                                <label wire:key="rtc-{{ $code }}"
                                       class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-xs hover:bg-indigo-50/60 transition {{ in_array($code, $rtCodes, true) ? 'bg-indigo-50/80 font-semibold text-indigo-900' : 'text-slate-700' }}">
                                    <input type="checkbox" class="h-4 w-4 rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                           wire:click="toggleRt('{{ $code }}')"
                                           @checked(in_array($code, $rtCodes, true))>
                                    <span class="truncate">{{ $label }}</span>
                                </label>
                            @empty
                                <div class="px-3 py-3 text-xs text-slate-400 text-center">No retailers found matching criteria.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($noun === 'sellout' ? [
            ['Total Sold-Out Units', $summary->total_stock, 'text-emerald-700'],
            ['Assigned to Retailer', $summary->rt_stock, 'text-indigo-700'],
            ['Active Device Models', $summary->models, 'text-slate-900'],
        ] : [
            ['Total Unsold Stock', $summary->total_stock, 'text-indigo-700'],
            ['Distributor Warehouse Stock', $summary->rd_stock, 'text-amber-700'],
            ['Retailer Shelf Stock', $summary->rt_stock, 'text-blue-700'],
            ['Distinct Models in Stock', $summary->models, 'text-slate-900'],
        ] as [$l, $v, $colorCls])
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $l }}</div>
                <div class="mt-1 text-2xl font-extrabold {{ $colorCls }}">{{ number_format((int) $v) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Tabular Display --}}
    @php
        $keyCount = $type === 'model' ? 1 : count($labelHeaders);
    @endphp

    @if ($type === 'model')
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500">
                            <th class="th">Model Name</th>
                            @if ($noun !== 'sellout')
                                <th class="th text-right">RD Warehouse</th>
                                <th class="th text-right">Retailer Shelf</th>
                            @endif
                            <th class="th text-right font-bold text-slate-700">{{ $noun === 'sellout' ? 'Sold-Out Qty' : 'Total Inventory' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr wire:key="m-{{ $loop->index }}" class="hover:bg-slate-50/70 transition-colors">
                                <td class="td font-bold text-slate-900">{{ $r->model ?: 'Unassigned Model' }}</td>
                                @if ($noun !== 'sellout')
                                    <td class="td text-right font-mono text-slate-600">{{ number_format($r->rd_stock) }}</td>
                                    <td class="td text-right font-mono text-slate-600">{{ number_format($r->rt_stock) }}</td>
                                @endif
                                <td class="td text-right font-mono font-bold text-indigo-700">{{ number_format($r->total_stock) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-12 text-center text-slate-400 text-xs">No records available for the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-slate-900">
                            <tr>
                                <td class="td">Total Summary (All Models)</td>
                                @if ($noun !== 'sellout')
                                    <td class="td text-right font-mono">{{ number_format((int) $summary->rd_stock) }}</td>
                                    <td class="td text-right font-mono">{{ number_format((int) $summary->rt_stock) }}</td>
                                @endif
                                <td class="td text-right font-mono font-extrabold text-indigo-700">{{ number_format((int) $summary->total_stock) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @else
        {{-- Matrix / Pivot View --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500">
                            @foreach ($labelHeaders as $i => $h)
                                <th class="th {{ $i === 0 ? 'sticky left-0 bg-slate-50/95 z-10' : '' }}">{{ $h }}</th>
                            @endforeach
                            @foreach ($columns['models'] as $m)
                                <th class="th text-right font-mono">{{ $m }}</th>
                            @endforeach
                            @if ($columns['hasOther'])
                                <th class="th text-right font-mono">Other</th>
                            @endif
                            <th class="th text-right font-bold text-slate-900">Total Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr wire:key="p-{{ $loop->index }}" class="hover:bg-slate-50/70 transition-colors">
                                @foreach ($r->labels as $i => $val)
                                    <td class="td {{ $i === 0 ? 'font-mono font-bold sticky left-0 bg-white z-10' : 'text-slate-700' }}">{{ $val ?: '—' }}</td>
                                @endforeach
                                @foreach ($columns['models'] as $m)
                                    <td class="td text-right font-mono {{ ($r->cells[$m] ?? 0) ? 'text-slate-800' : 'text-slate-300' }}">
                                        {{ number_format($r->cells[$m] ?? 0) }}
                                    </td>
                                @endforeach
                                @if ($columns['hasOther'])
                                    <td class="td text-right font-mono {{ $r->other ? 'text-slate-800' : 'text-slate-300' }}">
                                        {{ number_format($r->other) }}
                                    </td>
                                @endif
                                <td class="td text-right font-mono font-bold text-indigo-700 bg-indigo-50/20">
                                    {{ number_format($r->total_qty) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $keyCount + count($columns['models']) + ($columns['hasOther'] ? 2 : 1) }}" class="py-12 text-center text-slate-400 text-xs">No records available for this selection.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-slate-900">
                            <tr>
                                <td class="td sticky left-0 bg-slate-50/95 z-10" colspan="{{ $keyCount }}">Total (All Rows)</td>
                                @foreach ($columns['models'] as $m)
                                    <td class="td text-right font-mono">{{ number_format($columns['totals'][$m] ?? 0) }}</td>
                                @endforeach
                                @if ($columns['hasOther'])
                                    <td class="td text-right font-mono">{{ number_format($columns['otherTotal']) }}</td>
                                @endif
                                <td class="td text-right font-mono font-extrabold text-indigo-700 bg-indigo-50/50">
                                    {{ number_format($columns['grandTotal']) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

    <div>{{ $rows->links() }}</div>
</div>
