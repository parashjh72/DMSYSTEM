<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">WOD Coverage</h1>
            <p class="mt-1 text-sm text-gray-500">
                Width of distribution — for each RD / TSO and model, how many distinct retailers currently hold unsold stock.
            </p>
        </div>
        @can('exports.view')
            <button class="btn-ghost" wire:click="export">Export CSV</button>
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
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input" wire:model.live="rdCode">
                    <option value="">All distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Model</label>
                <select class="input" wire:model.live="model">
                    <option value="">All models</option>
                    @foreach ($modelOptions as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Model status</label>
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
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach ([
            ['Retailers with stock', $summary->retailers],
            ['Distributors', $summary->distributors],
            ['Models in stock', $summary->models],
        ] as [$l, $v])
            <div class="card p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $l }}</div>
                <div class="mt-1 text-lg font-semibold">{{ number_format((int) $v) }}</div>
            </div>
        @endforeach
    </div>

    @php $keyCount = count($labelHeaders); @endphp

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200 text-right">
            <thead class="bg-gray-50">
                <tr>
                    @foreach ($labelHeaders as $i => $h)
                        <th class="th text-left {{ $i === 0 ? 'sticky left-0 bg-gray-50' : '' }}">{{ $h }}</th>
                    @endforeach
                    @foreach ($columns['models'] as $m)
                        <th class="th text-right whitespace-nowrap">{{ $m }}</th>
                    @endforeach
                    @if ($columns['hasOther'])
                        <th class="th text-right">Other</th>
                    @endif
                    <th class="th text-right">Total RTs</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $r)
                <tr wire:key="w-{{ $loop->index }}">
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
                <tr><td class="td text-left text-gray-400" colspan="{{ $keyCount + count($columns['models']) + ($columns['hasOther'] ? 2 : 1) }}">No retailer stock for this selection.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                    <tr>
                        <td class="td text-left sticky left-0 bg-gray-50" colspan="{{ $keyCount }}">All {{ $type === 'tso' ? 'TSOs' : 'RDs' }} (distinct retailers)</td>
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
    <p class="mt-2 text-xs text-gray-400">
        Each cell counts retailers holding at least one unsold unit of that model. Column and total figures are
        distinct-retailer counts, so they are not the sum of the row above them.
    </p>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
