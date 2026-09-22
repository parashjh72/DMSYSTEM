<div>
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col gap-2">
        <a href="{{ route('settings.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Settings
        </a>
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Inventory Stock Reassignment & Transfer</h1>
                <p class="text-xs text-gray-500">Reallocate device serials between retail channels with complete audit trailing.</p>
            </div>
        </div>
    </div>

    <!-- Mode Selector Tabs -->
    <div class="mt-6 flex gap-2">
        @foreach (['imei_list' => 'Reassign by IMEI List', 'retailer' => 'Transfer Entire Retailer Stock'] as $key => $label)
            <button wire:click="$set('mode', '{{ $key }}')"
                    class="rounded-xl px-4 py-2 text-xs font-semibold transition {{ $mode === $key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <!-- Result Feedback Banner -->
    @if ($result)
        <div class="mt-4 rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-xs text-emerald-950">
            <div class="flex items-center gap-2 font-bold text-emerald-900">
                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Transfer Completed Successfully: {{ number_format($result['affected']) }} device(s) transferred
                @if ($result['requested']) (out of {{ number_format($result['requested']) }} requested) @endif
            </div>
            @if ($result['notFound'])
                <div class="mt-2 text-amber-800 bg-amber-50/70 p-2.5 rounded-lg border border-amber-200 font-mono text-[11px]">
                    <strong>Unmatched IMEIs ({{ count($result['notFound']) }}):</strong>
                    {{ \Illuminate\Support\Str::limit(implode(', ', $result['notFound']), 160) }}
                </div>
            @endif
        </div>
    @endif

    <!-- Transfer Execution Card -->
    <div class="card mt-6 border border-gray-100 shadow-sm max-w-3xl">
        <h2 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100 flex items-center gap-2">
            <span class="flex h-2 w-2 rounded-full bg-indigo-600"></span>
            Destination (Target Retailer)
        </h2>

        <div class="mt-4 space-y-3">
            <div>
                <label class="label text-xs font-semibold text-gray-700">Target Retailer Partner</label>
                <input type="search" class="input mb-1 text-xs" placeholder="Search target RT code or name…"
                       wire:model.live.debounce.300ms="targetSearch">
                <select class="input text-xs" wire:model="targetRt">
                    <option value="">— Select Target Retailer —</option>
                    @foreach ($targetOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
                @error('targetRt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer pt-1">
                <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" wire:model="moveDistributor">
                Also synchronize distributor (RD) assignment to match target retailer's primary RD
            </label>
        </div>

        <div class="my-6 border-t border-gray-100"></div>

        <h2 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100 flex items-center gap-2">
            <span class="flex h-2 w-2 rounded-full bg-indigo-600"></span>
            Source Payload
        </h2>

        @if ($mode === 'imei_list')
            <div class="mt-4 space-y-3">
                <label class="label text-xs font-semibold text-gray-700">IMEI Numbers to Transfer</label>
                <textarea rows="6" wire:model="imeis" class="input font-mono text-xs leading-relaxed"
                          placeholder="Paste serials or IMEI numbers — one per line, space-delimited, or comma-separated"></textarea>
                @error('imeis') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                <div class="flex justify-end pt-2">
                    <button class="btn-primary text-xs flex items-center gap-1.5"
                            wire:click="transferImeis"
                            wire:confirm="Confirm moving the pasted IMEIs to the selected retailer?">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Execute IMEI Transfer
                    </button>
                </div>
            </div>
        @else
            <div class="mt-4 space-y-3">
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Source Retailer Partner (Transfer from)</label>
                    <input type="search" class="input mb-1 text-xs" placeholder="Search source RT code or name…"
                           wire:model.live.debounce.300ms="sourceSearch">
                    <select class="input text-xs" wire:model="sourceRt">
                        <option value="">— Select Source Retailer —</option>
                        @foreach ($sourceOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                    </select>
                    @error('sourceRt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer pt-1">
                    <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" wire:model="onlyInStock">
                    Transfer in-stock units only (safely exclude already activated consumer sell-outs)
                </label>

                <div class="flex justify-end pt-2">
                    <button class="btn-primary text-xs flex items-center gap-1.5"
                            wire:click="transferRetailer"
                            wire:confirm="Are you sure you want to move all inventory from the source retailer to the target retailer?">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Transfer Entire Retailer Stock
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Recent Transfer Audit Log -->
    <div class="mt-8">
        <h2 class="text-sm font-bold text-gray-900 mb-3 flex items-center justify-between">
            <span>Recent Transfer Audit Trail</span>
            <span class="text-xs text-gray-400 font-normal">Last executions</span>
        </h2>
        <div class="card overflow-hidden p-0 border border-gray-200/80 shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50/75">
                        <tr>
                            <th class="th">Timestamp</th>
                            <th class="th">Transfer Mode</th>
                            <th class="th">Source Entity</th>
                            <th class="th">Destination Entity</th>
                            <th class="th text-right">Affected Units</th>
                            <th class="th">Authorized By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($recent as $t)
                        <tr class="hover:bg-gray-50/75 transition">
                            <td class="td text-gray-400 font-mono text-[11px]">{{ $t->created_at?->diffForHumans() }}</td>
                            <td class="td">
                                <span class="badge {{ $t->mode === 'retailer' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
                                    {{ $t->mode === 'retailer' ? 'Retailer Bulk'.($t->only_in_stock ? ' (Stock only)' : '') : 'IMEI List' }}
                                </span>
                            </td>
                            <td class="td font-mono font-medium text-gray-800">{{ $t->from_rt_code ?? 'Manual Batch' }}</td>
                            <td class="td font-mono font-medium text-gray-800">
                                {{ $t->to_rt_code }}{{ $t->move_distributor ? ' (RD: '.$t->to_rd_code.')' : '' }}
                            </td>
                            <td class="td text-right font-mono font-bold text-emerald-700">{{ number_format($t->affected_count) }}</td>
                            <td class="td text-gray-600">{{ $t->performer?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr><td class="td text-center text-gray-400 py-8" colspan="6">No inventory reassignments recorded yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
