<div class="space-y-4">
    {{-- Sub-views + export --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap gap-1.5">
            @foreach (['visits' => 'Party visits', 'frequency' => 'Visit frequency', 'reasons' => 'Non-effective reasons'] as $key => $label)
                <button wire:click="$set('view', '{{ $key }}')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $view === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        @can('exports.view')
            <button class="btn-ghost text-xs" wire:click="export">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span>Export CSV</span>
            </button>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="card !p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="label">From</label>
            <input type="date" class="input text-xs" wire:model.live="from">
        </div>
        <div>
            <label class="label">To</label>
            <input type="date" class="input text-xs" wire:model.live="to">
        </div>
        <div>
            <label class="label">Field Officer (TSO)</label>
            <select class="input text-xs" wire:model.live="tsoId">
                <option value="">All TSOs</option>
                @foreach ($tsoOptions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
            </select>
        </div>
        @if ($view === 'visits')
            <div>
                <label class="label">Outcome</label>
                <select class="input text-xs" wire:model.live="outcome">
                    <option value="">All visits</option>
                    <option value="effective">Effective (order)</option>
                    <option value="non_effective">Non-effective (no order)</option>
                    <option value="unrecorded">Outcome not recorded</option>
                </select>
            </div>
        @endif
        @if ($view !== 'reasons')
            <div>
                <label class="label">Party</label>
                <input class="input text-xs" wire:model.live.debounce.300ms="search" placeholder="RT code or name…">
            </div>
        @endif
    </div>

    {{-- Totals --}}
    <div class="grid gap-3 grid-cols-2 lg:grid-cols-6">
        @foreach ([
            ['Planned calls', number_format($totals['planned']), 'text-slate-800'],
            ['Visits', number_format($totals['visits']), 'text-indigo-600'],
            ['Coverage', $totals['coverage'] !== null ? $totals['coverage'].'%' : '—', 'text-sky-600'],
            ['Effective', number_format($totals['effective']), 'text-emerald-600'],
            ['Non-effective', number_format($totals['non_effective']), 'text-rose-600'],
            ['Order value', number_format($totals['order_value'], 2), 'text-emerald-700'],
        ] as [$label, $value, $colour])
            <div class="card !p-4">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</div>
                <div class="mt-1 text-lg font-extrabold {{ $colour }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>
    @if ($totals['strike_rate'] !== null)
        <p class="text-xs text-slate-500">Strike rate (effective ÷ visits): <strong class="text-slate-800">{{ $totals['strike_rate'] }}%</strong></p>
    @endif

    @if ($view === 'visits')
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="th">Visited</th>
                            <th class="th">TSO</th>
                            <th class="th">Party</th>
                            <th class="th">Outcome</th>
                            <th class="th">Order / Reason</th>
                            <th class="th">Remarks</th>
                            <th class="th text-right">GPS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr wire:key="bv-{{ $r->id }}">
                                <td class="td font-mono">{{ \Illuminate\Support\Carbon::parse($r->visited_at, 'UTC')->timezone($tz)->format('d M H:i') }}</td>
                                <td class="td">{{ $r->tso_name }}</td>
                                <td class="td">
                                    <div class="font-semibold text-slate-900">{{ $r->retailer_name ?: '—' }}</div>
                                    <div class="font-mono text-[11px] text-indigo-700">{{ $r->rt_code }} @if ($r->area) <span class="font-sans text-slate-400">· {{ $r->area }}</span> @endif</div>
                                </td>
                                <td class="td">
                                    @if ($r->outcome === 'effective') <span class="badge-emerald">Effective</span>
                                    @elseif ($r->outcome === 'non_effective') <span class="badge-rose">Non-effective</span>
                                    @else <span class="badge-slate">Not recorded</span>
                                    @endif
                                </td>
                                <td class="td whitespace-normal max-w-xs">
                                    @if ($r->outcome === 'effective')
                                        <div class="font-semibold text-emerald-700">{{ number_format((float) $r->order_value, 2) }}</div>
                                        <div class="text-[11px] text-slate-500">
                                            {{ collect(json_decode((string) $r->order_items, true) ?: [])->map(fn ($l) => $l['model'].' ×'.$l['qty'])->implode(', ') }}
                                        </div>
                                    @elseif ($r->outcome === 'non_effective')
                                        {{ $reasons[$r->no_order_reason] ?? $r->no_order_reason }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="td whitespace-normal max-w-xs text-slate-500">{{ $r->note ?: '—' }}</td>
                                <td class="td text-right">
                                    @if ($r->latitude !== null)
                                        <a class="text-indigo-600 hover:underline" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $r->latitude }},{{ $r->longitude }}">Map &nearr;</a>
                                    @else — @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="td text-center text-slate-400 py-10">No party visits in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $rows->links() }}</div>
        </div>
    @elseif ($view === 'frequency')
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="th">Party</th>
                            <th class="th text-right">Planned</th>
                            <th class="th text-right">Visited</th>
                            <th class="th text-right">Missed</th>
                            <th class="th text-right">Effective</th>
                            <th class="th text-right">No order</th>
                            <th class="th text-right">Order value</th>
                            <th class="th">Last visit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            @php $missed = $r->planned - $r->visited; @endphp
                            <tr wire:key="bf-{{ $r->rt_code }}">
                                <td class="td">
                                    <div class="font-semibold text-slate-900">{{ $r->retailer_name ?: '—' }}</div>
                                    <div class="font-mono text-[11px] text-indigo-700">{{ $r->rt_code }} @if ($r->area) <span class="font-sans text-slate-400">· {{ $r->area }}</span> @endif</div>
                                </td>
                                <td class="td text-right">{{ $r->planned }}</td>
                                <td class="td text-right font-semibold">{{ $r->visited }}</td>
                                <td class="td text-right {{ $missed > 0 ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">{{ $missed }}</td>
                                <td class="td text-right text-emerald-700">{{ $r->effective }}</td>
                                <td class="td text-right text-rose-600">{{ $r->non_effective }}</td>
                                <td class="td text-right">{{ number_format((float) $r->order_value, 2) }}</td>
                                <td class="td text-slate-500">{{ $r->last_visit ? \Illuminate\Support\Carbon::parse($r->last_visit, 'UTC')->timezone($tz)->format('d M Y') : 'Never' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="td text-center text-slate-400 py-10">No parties planned in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $rows->links() }}</div>
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="card">
                <h3 class="text-sm font-bold text-slate-900 mb-3">Why no order was placed</h3>
                <div class="space-y-2.5">
                    @forelse ($reasonRows as $r)
                        <div>
                            <div class="flex justify-between text-xs"><span class="font-medium text-slate-700">{{ $r->reason }}</span><span class="text-slate-500">{{ $r->visits }} · {{ $r->share }}%</span></div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-rose-500" style="width: {{ $r->share }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">No non-effective visits in this period.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                <div class="px-4 pt-4 pb-2"><h3 class="text-sm font-bold text-slate-900">Visit productivity by TSO</h3></div>
                <table class="min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="th">TSO</th>
                            <th class="th text-right">Visits</th>
                            <th class="th text-right">Effective</th>
                            <th class="th text-right">No order</th>
                            <th class="th text-right">Strike %</th>
                            <th class="th text-right">Order value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tsoRows as $r)
                            <tr>
                                <td class="td font-semibold">{{ $r->tso_name }}</td>
                                <td class="td text-right">{{ $r->visits }}</td>
                                <td class="td text-right text-emerald-700">{{ $r->effective }}</td>
                                <td class="td text-right text-rose-600">{{ $r->non_effective }}</td>
                                <td class="td text-right">{{ $r->visits > 0 ? round($r->effective / $r->visits * 100, 1) : 0 }}%</td>
                                <td class="td text-right">{{ number_format((float) $r->order_value, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="td text-center text-slate-400 py-8">No visits in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
