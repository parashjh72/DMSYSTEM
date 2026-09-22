<div class="space-y-6">
    {{-- Header --}}
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">IMEI Search &amp; Audit</h1>
            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                Bulk Lookup
            </span>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            Paste one or hundreds of device IMEI numbers (space, comma, or newline separated straight from Excel) to audit activation status and history.
        </p>
    </div>

    {{-- Input Box --}}
    <div class="card space-y-3">
        <div class="flex items-center justify-between">
            <label class="label !mb-0" for="imeis">Paste IMEIs (up to {{ number_format($maxImeis) }})</label>
            <span class="text-[11px] text-slate-400">Indexed exact match</span>
        </div>

        <textarea id="imeis" rows="5" wire:model.blur="imeis"
                  class="input font-mono text-xs leading-6 bg-slate-50/50 focus:bg-white text-slate-900 border-slate-200"
                  placeholder="860821081439232&#10;860821081439737&#10;860821081439794"></textarea>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
            <div class="flex items-center gap-2">
                <button class="btn-primary text-xs" wire:click="$refresh">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span>Run Search</span>
                </button>
                <button class="btn-ghost text-xs" wire:click="clear">
                    Clear Input
                </button>
            </div>

            @if ($searched)
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <span class="text-slate-600">{{ number_format($totalWanted) }} requested</span>
                    <span class="text-slate-300">&bull;</span>
                    <span class="badge-emerald">{{ number_format($foundCount) }} found</span>
                    <span class="text-slate-300">&bull;</span>
                    <span class="badge-amber">{{ number_format(count($unmatched)) }} not found</span>
                </div>
            @endif
        </div>

        @if ($capped)
            <p class="text-xs text-amber-700 bg-amber-50 p-2.5 rounded-xl border border-amber-200">
                ⚠️ Search is capped at the first {{ number_format($maxImeis) }} entries to maintain instant database response times.
            </p>
        @endif
    </div>

    {{-- Unmatched IMEIs Box --}}
    @if ($searched && count($unmatched))
        <div class="card border-amber-200 bg-amber-50/30" x-data="{
                copied: false,
                copy() {
                    navigator.clipboard.writeText($refs.miss.value).then(() => {
                        this.copied = true; setTimeout(() => this.copied = false, 2000);
                    });
                }
             }">
            <div class="flex items-center justify-between border-b border-amber-200/60 pb-3">
                <div class="flex items-center gap-2">
                    <span class="flex h-2 w-2 rounded-full bg-amber-500"></span>
                    <h2 class="text-sm font-bold text-amber-900">Unmatched IMEIs ({{ number_format(count($unmatched)) }})</h2>
                </div>
                <button class="btn-ghost text-xs bg-white" @click="copy()">
                    <span x-show="!copied" class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>Copy for Excel</span>
                    </span>
                    <span x-show="copied" x-cloak class="text-emerald-700 font-bold flex items-center gap-1">
                        <span>Copied to Clipboard ✓</span>
                    </span>
                </button>
            </div>
            <p class="mt-2 text-xs text-slate-500">These IMEIs were not found in the national distributor imports:</p>
            <textarea x-ref="miss" readonly rows="{{ min(8, max(3, count($unmatched))) }}"
                      class="input mt-2 font-mono text-xs leading-5 bg-white border-amber-200 text-slate-700">{{ implode("\n", $unmatched) }}</textarea>
        </div>
    @endif

    {{-- Found Records --}}
    @if ($searched && $records && $records->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span class="font-medium">
                    Showing {{ number_format($records->firstItem()) }}–{{ number_format($records->lastItem()) }} of {{ number_format($records->total()) }} matched records
                </span>
                <div class="flex items-center gap-2">
                    <span>Rows per page:</span>
                    <select class="input !w-auto text-xs py-1 px-2.5" wire:model.live="perPage">
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                        <option value="500">500</option>
                    </select>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500">
                                <th class="th">IMEI</th>
                                <th class="th">Model</th>
                                <th class="th">Product Code</th>
                                <th class="th">TSO</th>
                                <th class="th">RD</th>
                                <th class="th">RD Name</th>
                                <th class="th">RT</th>
                                <th class="th">RT Name</th>
                                <th class="th">ST Date</th>
                                <th class="th">Activation</th>
                                <th class="th">Sell-In</th>
                                <th class="th">Status</th>
                                <th class="th">Batch</th>
                                <th class="th text-right">Audit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($records as $r)
                                <tr wire:key="rec-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                                    <td class="td font-mono font-bold text-slate-900">{{ $r->imei }}</td>
                                    <td class="td font-medium text-slate-800">{{ $r->model }}</td>
                                    <td class="td text-slate-500">{{ $r->product_code ?? '—' }}</td>
                                    <td class="td text-slate-600">{{ $r->tso ?: '—' }}</td>
                                    <td class="td font-mono font-medium text-indigo-700 bg-indigo-50/30 px-2 rounded">{{ $r->rd_code ?: '—' }}</td>
                                    <td class="td text-slate-600 max-w-[130px] truncate" title="{{ $r->rd_name }}">{{ $r->rd_name ?? '—' }}</td>
                                    <td class="td font-mono text-slate-600">{{ $r->rt_code ?? '—' }}</td>
                                    <td class="td text-slate-600 max-w-[130px] truncate" title="{{ $r->rt_name }}">{{ $r->rt_name ?? '—' }}</td>
                                    <td class="td text-slate-600">{{ $r->st_date?->toDateString() ?? '—' }}</td>
                                    <td class="td">{{ $r->activation_date?->toDateString() ?? '—' }}</td>
                                    <td class="td text-slate-500">{{ $r->sell_in_date?->toDateString() ?? '—' }}</td>
                                    <td class="td">
                                        @if ($r->is_activated)
                                            <span class="badge-emerald font-semibold">Activated</span>
                                        @else
                                            <span class="badge-amber font-medium">In Channel</span>
                                        @endif
                                    </td>
                                    <td class="td text-slate-400 font-mono text-[11px]">#{{ $r->last_import_batch_id }}</td>
                                    <td class="td text-right">
                                        <button class="btn-ghost !py-1 !px-2.5 text-xs text-indigo-600 hover:text-indigo-700 font-semibold"
                                                wire:click="showTimeline('{{ $r->imei }}')">
                                            Timeline
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $records->links() }}</div>
        </div>
    @elseif ($searched && $records && $records->isEmpty())
        <div class="card text-center py-12">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-500 mb-3">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="text-sm font-bold text-slate-800">None of the requested IMEIs were found</h3>
            <p class="text-xs text-slate-400 mt-1">Please verify the input format or confirm the devices were imported by the National Distributor.</p>
        </div>
    @endif

    {{-- Timeline Modal --}}
    @if ($timelineImei !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
             wire:click.self="closeTimeline">
            <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Device Audit Lifecycle</h2>
                            <p class="font-mono text-xs text-indigo-600 font-semibold">{{ $timelineImei }}</p>
                        </div>
                    </div>
                    <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition" wire:click="closeTimeline">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="max-h-[60vh] overflow-y-auto px-6 py-5">
                    @forelse ($timeline as $e)
                        <div class="relative flex gap-4 pb-6 last:pb-0">
                            <div class="relative flex flex-col items-center">
                                <div class="h-3 w-3 shrink-0 rounded-full ring-4 ring-white
                                    {{ match ($e['kind']) {
                                        'return_approved' => 'bg-emerald-500 ring-emerald-100',
                                        'return_requested' => 'bg-amber-500 ring-amber-100',
                                        'return_rejected' => 'bg-rose-500 ring-rose-100',
                                        'activated' => 'bg-emerald-500 ring-emerald-100',
                                        'assigned' => 'bg-indigo-500 ring-indigo-100',
                                        default => 'bg-slate-400 ring-slate-100',
                                    } }}"></div>
                                <div class="h-full w-0.5 bg-slate-200 mt-1"></div>
                            </div>
                            <div class="min-w-0 pb-1">
                                <p class="text-xs font-bold text-slate-900">{{ $e['title'] }}
                                    @if ($e['detail'])
                                        <span class="font-normal text-slate-600">— {{ $e['detail'] }}</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-slate-400 mt-0.5">
                                    {{ $e['at'] ? \Illuminate\Support\Carbon::parse($e['at'])->format('d M Y, H:i') : \Illuminate\Support\Carbon::parse($e['when'])->format('d M Y') }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 py-6 text-center">No audit history recorded for this IMEI.</p>
                    @endforelse
                </div>

                <div class="border-t border-slate-100 px-6 py-3 bg-slate-50/50 flex justify-end">
                    <button class="btn-ghost text-xs" wire:click="closeTimeline">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
