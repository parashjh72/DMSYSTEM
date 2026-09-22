<div>
    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Annual Volume Contracts</h1>
                <p class="text-xs text-gray-500">Committed sales-volume target contracts with retailer partners and milestone attainment tracking.</p>
            </div>
        </div>
        <button class="btn-primary text-xs flex items-center gap-1.5" wire:click="newRow">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Contract
        </button>
    </div>

    @if ($showForm)
        <div class="card mt-6 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900">{{ $editingId ? 'Edit Contract' : 'Create New Annual Contract' }}</h2>
                <button class="text-gray-400 hover:text-gray-600 text-xs" wire:click="cancelForm">✕ Close</button>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="label text-xs font-semibold text-gray-700">Retail Partner</label>
                    <div class="relative">
                        <input class="input text-xs pl-8" wire:model.live.debounce.300ms="fRtSearch" placeholder="Type RT code or name to search…">
                        <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    @if (count($rtOptions))
                        <select class="input mt-1.5 text-xs" size="4" wire:change="pickRetailer($event.target.value)">
                            @foreach ($rtOptions as $code => $label)
                                <option value="{{ $code }}" @selected($fRtCode === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    <p class="mt-1 text-xs {{ $fRtCode ? 'text-emerald-700 font-medium' : 'text-gray-400' }}">
                        {{ $fRtCode ? '✓ Selected Retailer: '.$fRtCode : 'Please select a retailer from above' }}
                    </p>
                    @error('fRtCode') <p class="text-xs text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Committed Volume (Units)</label>
                    <input type="number" min="1" class="input text-xs font-mono" placeholder="e.g. 500" wire:model="fTargetVolume">
                    @error('fTargetVolume') <p class="text-xs text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Incentive %</label>
                    <input type="number" step="0.01" min="0" max="100" class="input text-xs font-mono" placeholder="e.g. 3.5" wire:model="fIncentivePct">
                    @error('fIncentivePct') <p class="text-xs text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Status</label>
                    <select class="input text-xs" wire:model="fStatus">
                        @foreach ($statuses as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Starts On</label>
                    <input type="date" class="input text-xs" wire:model="fStartsOn">
                    @error('fStartsOn') <p class="text-xs text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Ends On</label>
                    <input type="date" class="input text-xs" wire:model="fEndsOn">
                    @error('fEndsOn') <p class="text-xs text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="label text-xs font-semibold text-gray-700">Contract Notes & Clauses</label>
                    <input class="input text-xs" placeholder="Special payment terms, model restrictions..." wire:model="fNotes">
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3 border-t border-gray-100 pt-4">
                <button class="btn-primary text-xs" wire:click="save">Save Contract</button>
                <button class="btn-ghost text-xs" wire:click="cancelForm">Cancel</button>
            </div>
        </div>
    @endif

    <!-- Filters Bar -->
    <div class="card mt-6 border border-gray-100 shadow-sm">
        <div class="flex flex-wrap items-center gap-4">
            <div class="w-48">
                <label class="label text-xs font-semibold text-gray-700">Status</label>
                <select class="input text-xs" wire:model.live="statusFilter">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                </select>
            </div>
            <div class="w-64">
                <label class="label text-xs font-semibold text-gray-700">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdFilter">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Contracts Table -->
    <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/75">
                    <tr>
                        <th class="th">Retail Partner</th>
                        <th class="th">Duration</th>
                        <th class="th text-right">Target</th>
                        <th class="th text-right">Achieved</th>
                        <th class="th text-center w-36">Attainment</th>
                        <th class="th text-right">Incentive %</th>
                        <th class="th">Status</th>
                        <th class="th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($rows as $c)
                    @php $pct = $c->target_volume > 0 ? round($c->achieved / $c->target_volume * 100, 1) : null; @endphp
                    <tr wire:key="ac-{{ $c->id }}" class="hover:bg-gray-50/75 transition">
                        <td class="td">
                            <div class="font-bold text-gray-900 font-mono">{{ $c->rt_code }}</div>
                            <div class="text-[11px] text-gray-500 truncate max-w-xs">{{ $c->rt_name }} · <span class="font-mono text-gray-400">{{ $c->rd_code ?: '—' }}</span></div>
                        </td>
                        <td class="td text-gray-600">
                            <div>{{ $c->starts_on->format('d M Y') }} – {{ $c->ends_on->format('d M Y') }}</div>
                            <div class="text-[11px] text-gray-400">{{ $c->starts_on->diffInDays($c->ends_on) + 1 }} days duration</div>
                        </td>
                        <td class="td text-right font-mono font-semibold text-gray-900">{{ number_format($c->target_volume) }}</td>
                        <td class="td text-right font-mono font-semibold text-indigo-700">{{ number_format($c->achieved) }}</td>
                        <td class="td text-center">
                            @if ($pct !== null)
                                <div class="flex items-center gap-2 justify-center">
                                    <div class="w-16 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                        <div class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-emerald-500' : 'bg-indigo-600' }}" style="width: {{ min($pct, 100) }}%"></div>
                                    </div>
                                    <span class="font-mono font-semibold {{ $pct >= 100 ? 'text-emerald-700' : 'text-gray-700' }}">{{ $pct }}%</span>
                                </div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="td text-right font-mono font-bold text-gray-900">{{ rtrim(rtrim(number_format($c->incentive_pct, 2), '0'), '.') }}%</td>
                        <td class="td">
                            <span class="badge {{ match($c->status) {
                                'active' => 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200',
                                'closed' => 'bg-gray-100 text-gray-700',
                                'cancelled' => 'bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-200',
                                default => 'bg-gray-100 text-gray-700',
                            } }}">
                                {{ $statuses[$c->status] ?? $c->status }}
                            </span>
                            @if ($c->notes) <div class="text-[10px] text-gray-400 mt-0.5 truncate max-w-xs">{{ $c->notes }}</div> @endif
                        </td>
                        <td class="td text-right whitespace-nowrap space-x-2">
                            <button class="font-semibold text-indigo-600 hover:text-indigo-800 text-xs" wire:click="editRow({{ $c->id }})">Edit</button>
                            @if ($c->status === 'active')
                                <span class="text-gray-300">·</span>
                                <button class="font-semibold text-gray-600 hover:text-gray-800 text-xs" wire:click="close({{ $c->id }})" wire:confirm="Close this contract?">Close</button>
                            @endif
                            <span class="text-gray-300">·</span>
                            <button class="font-semibold text-rose-600 hover:text-rose-800 text-xs" wire:click="delete({{ $c->id }})" wire:confirm="Delete this contract?">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="td text-center text-gray-400 py-10" colspan="8">
                            <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <p class="mt-2 text-xs">No annual volume contracts found.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</div>
