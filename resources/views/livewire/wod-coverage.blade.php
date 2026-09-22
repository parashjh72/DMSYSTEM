<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Width of Distribution (WOD)</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                    Retail Penetration
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Measures market depth: count of distinct retail stores holding active inventory per model across territories.
            </p>
        </div>
        @can('exports.view')
            <button class="btn-primary text-xs" wire:click="export">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span>Export CSV</span>
            </button>
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
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdCode">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Model Filter</label>
                <select class="input text-xs" wire:model.live="model">
                    <option value="">All Models</option>
                    @foreach ($modelOptions as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Model Lifecycle Status</label>
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
    </div>

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        @foreach ([
            ['Retail Stores With Stock', $summary->retailers, 'text-emerald-700'],
            ['Active Distributors (RD)', $summary->distributors, 'text-indigo-700'],
            ['Models Distributed', $summary->models, 'text-slate-900'],
        ] as [$l, $v, $colorCls])
            <div class="card">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $l }}</div>
                <div class="mt-1 text-2xl font-extrabold {{ $colorCls }}">{{ number_format((int) $v) }}</div>
            </div>
        @endforeach
    </div>

    @php $keyCount = count($labelHeaders); @endphp

    {{-- Coverage Matrix --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs text-right">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        @foreach ($labelHeaders as $i => $h)
                            <th class="th text-left {{ $i === 0 ? 'sticky left-0 bg-slate-50/95 z-10' : '' }}">{{ $h }}</th>
                        @endforeach
                        @foreach ($columns['models'] as $m)
                            <th class="th text-right font-mono">{{ $m }}</th>
                        @endforeach
                        @if ($columns['hasOther'])
                            <th class="th text-right font-mono">Other</th>
                        @endif
                        <th class="th text-right font-bold text-slate-900">Total RTs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr wire:key="w-{{ $loop->index }}" class="hover:bg-slate-50/70 transition-colors">
                            @foreach ($r->labels as $i => $val)
                                <td class="td text-left {{ $i === 0 ? 'font-mono font-bold sticky left-0 bg-white z-10' : 'text-slate-700' }}">{{ $val ?: '—' }}</td>
                            @endforeach
                            @foreach ($columns['models'] as $m)
                                <td class="td text-right font-mono {{ ($r->cells[$m] ?? 0) ? 'font-semibold text-slate-800' : 'text-slate-300' }}">
                                    {{ number_format($r->cells[$m] ?? 0) }}
                                </td>
                            @endforeach
                            @if ($columns['hasOther'])
                                <td class="td text-right font-mono {{ $r->other ? 'text-slate-800' : 'text-slate-300' }}">
                                    {{ number_format($r->other) }}
                                </td>
                            @endif
                            <td class="td text-right font-mono font-bold text-indigo-700 bg-indigo-50/30">
                                {{ number_format($r->total_rts) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $keyCount + count($columns['models']) + ($columns['hasOther'] ? 2 : 1) }}" class="py-12 text-center text-slate-400 text-xs">No distribution records found.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-slate-900">
                        <tr>
                            <td class="td text-left sticky left-0 bg-slate-50/95 z-10" colspan="{{ $keyCount }}">Network Total (Distinct RTs)</td>
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

    <div>{{ $rows->links() }}</div>
</div>
