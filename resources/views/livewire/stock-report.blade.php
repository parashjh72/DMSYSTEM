<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ ucfirst($noun) }} Report</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $noun === 'sellout'
                    ? 'Sold-out devices — activated over the counter.'
                    : 'Unsold inventory — devices that are not yet activated.' }}
            </p>
        </div>
        @can('exports.create')
            <div class="flex gap-2">
                <button class="btn-ghost" wire:click="export('xlsx')">Export → Excel</button>
                <button class="btn-ghost" wire:click="export('csv')">CSV</button>
            </div>
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2" wire:loading.class="opacity-50" wire:target="type">
        @foreach ($types as $key => $label)
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
            @if ($noun === 'sellout')
                <div><label class="label">Sellout date from</label><input type="date" class="input" wire:model.live="dateFrom"></div>
                <div><label class="label">Sellout date to</label><input type="date" class="input" wire:model.live="dateTo"></div>
            @endif
            <div>
                <label class="label">Models</label>
                <div class="flex gap-1">
                    @foreach (['' => 'Both', 'running' => 'Running', 'out' => 'Out'] as $val => $lbl)
                        <button wire:click="$set('lifecycle', '{{ $val }}')"
                                class="flex-1 rounded-lg px-2 py-2 text-xs font-semibold ring-1 ring-inset transition
                                {{ $lifecycle === $val
                                    ? 'bg-indigo-600 text-white ring-indigo-600'
                                    : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($type === 'rt')
            <div class="mt-4 border-t border-gray-100 pt-4">
                <label class="label">Retailers ({{ count($rtCodes) }} selected)</label>
                <div class="grid gap-3 lg:grid-cols-2">
                    <div>
                        <textarea rows="3" wire:model="rtPaste"
                                  class="input font-mono text-xs leading-5"
                                  placeholder="Paste RT codes or names — one per line, or comma separated"></textarea>
                        <div class="mt-2 flex items-center gap-2">
                            <button class="btn-primary text-xs" wire:click="matchRetailers">Match &amp; tick</button>
                            <button class="btn-ghost text-xs" wire:click="clearRts">Clear all</button>
                            @if ($rtUnmatched)
                                <span class="text-xs text-amber-600">{{ count($rtUnmatched) }} not matched:
                                    {{ \Illuminate\Support\Str::limit(implode(', ', $rtUnmatched), 80) }}</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <input type="search" class="input mb-1 text-xs" placeholder="Filter list…"
                               wire:model.live.debounce.300ms="rtSearch">
                        <div class="max-h-48 overflow-y-auto rounded-lg ring-1 ring-gray-200">
                            @forelse ($rtOptions as $code => $label)
                                <label wire:key="rtc-{{ $code }}"
                                       class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-xs hover:bg-indigo-50
                                       {{ in_array($code, $rtCodes, true) ? 'bg-indigo-50' : '' }}">
                                    <input type="checkbox" class="rounded border-gray-300"
                                           wire:click="toggleRt('{{ $code }}')"
                                           @checked(in_array($code, $rtCodes, true))>
                                    <span class="truncate">{{ $label }}</span>
                                </label>
                            @empty
                                <div class="px-3 py-2 text-xs text-gray-400">No retailers.</div>
                            @endforelse
                            @if ($rtTruncated)
                                <div class="px-3 py-1 text-xs text-amber-600">List truncated — filter to narrow.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($noun === 'sellout' ? [
            ['Total sold out', $summary->total_stock],
            ['With retailer', $summary->rt_stock],
            ['Models', $summary->models],
            ['', null],
        ] : [
            ['Total stock', $summary->total_stock],
            ['RD stock (no RT)', $summary->rd_stock],
            ['RT stock (with RT)', $summary->rt_stock],
            ['Models in stock', $summary->models],
        ] as [$l, $v])
            @if ($l === '') @continue @endif
            <div class="card p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $l }}</div>
                <div class="mt-1 text-lg font-semibold">{{ number_format((int) $v) }}</div>
            </div>
        @endforeach
    </div>

    @php
        $keyCount = $type === 'model' ? 1 : count($labelHeaders);
    @endphp

    @if ($type === 'model')
        <div class="card mt-4 overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr>
                    <th class="th">Model</th>
                    @if ($noun !== 'sellout')
                        <th class="th text-right">RD stock</th>
                        <th class="th text-right">RT stock</th>
                    @endif
                    <th class="th text-right">{{ $noun === 'sellout' ? 'Sold-out qty' : 'Total stock' }}</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr wire:key="m-{{ $loop->index }}">
                        <td class="td">{{ $r->model ?: '—' }}</td>
                        @if ($noun !== 'sellout')
                            <td class="td text-right">{{ number_format($r->rd_stock) }}</td>
                            <td class="td text-right">{{ number_format($r->rt_stock) }}</td>
                        @endif
                        <td class="td text-right font-medium">{{ number_format($r->total_stock) }}</td>
                    </tr>
                @empty
                    <tr><td class="td text-gray-400" colspan="4">No {{ $noun }} for this selection.</td></tr>
                @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                        <tr>
                            <td class="td">Total (all models)</td>
                            @if ($noun !== 'sellout')
                                <td class="td text-right">{{ number_format((int) $summary->rd_stock) }}</td>
                                <td class="td text-right">{{ number_format((int) $summary->rt_stock) }}</td>
                            @endif
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
                        @foreach ($labelHeaders as $i => $h)
                            <th class="th text-left {{ $i === 0 ? 'sticky left-0 bg-gray-50' : '' }}">{{ $h }}</th>
                        @endforeach
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
                        @foreach ($r->labels as $i => $val)
                            <td class="td text-left {{ $i === 0 ? 'font-mono sticky left-0 bg-white' : '' }}">{{ $val ?: '—' }}</td>
                        @endforeach
                        @foreach ($columns['models'] as $m)
                            <td class="td text-right {{ ($r->cells[$m] ?? 0) ? '' : 'text-gray-300' }}">{{ number_format($r->cells[$m] ?? 0) }}</td>
                        @endforeach
                        @if ($columns['hasOther'])
                            <td class="td text-right {{ $r->other ? '' : 'text-gray-300' }}">{{ number_format($r->other) }}</td>
                        @endif
                        <td class="td text-right font-semibold">{{ number_format($r->total_qty) }}</td>
                    </tr>
                @empty
                    <tr><td class="td text-left text-gray-400" colspan="{{ $keyCount + count($columns['models']) + ($columns['hasOther'] ? 2 : 1) }}">No {{ $noun }} for this selection.</td></tr>
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
