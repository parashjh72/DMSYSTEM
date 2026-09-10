<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Annual Contracts</h1>
            <p class="mt-1 text-sm text-gray-500">
                A retailer committed to a sales-volume target over a period, for an incentive percentage.
                Achievement is that retailer's activations within the contract dates.
            </p>
        </div>
        <button class="btn-primary" wire:click="newRow">New contract</button>
    </div>

    @if ($showForm)
        <div class="card mt-4 space-y-3">
            <h2 class="text-sm font-semibold">{{ $editingId ? 'Edit' : 'New' }} contract</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="label">Retailer</label>
                    <input class="input" wire:model.live.debounce.300ms="fRtSearch" placeholder="Search RT code or name…">
                    @if (count($rtOptions))
                        <select class="input mt-1" size="4" wire:change="pickRetailer($event.target.value)">
                            @foreach ($rtOptions as $code => $label)
                                <option value="{{ $code }}" @selected($fRtCode === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    <p class="mt-1 text-xs {{ $fRtCode ? 'text-emerald-600' : 'text-gray-400' }}">
                        {{ $fRtCode ? 'Selected: '.$fRtCode : 'No retailer selected yet' }}
                    </p>
                    @error('fRtCode') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Sales volume target (units)</label>
                    <input type="number" min="1" class="input" wire:model="fTargetVolume">
                    @error('fTargetVolume') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Incentive %</label>
                    <input type="number" step="0.01" min="0" max="100" class="input" wire:model="fIncentivePct">
                    @error('fIncentivePct') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Status</label>
                    <select class="input" wire:model="fStatus">
                        @foreach ($statuses as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Starts on</label>
                    <input type="date" class="input" wire:model="fStartsOn">
                    @error('fStartsOn') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Ends on</label>
                    <input type="date" class="input" wire:model="fEndsOn">
                    @error('fEndsOn') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="label">Notes</label>
                    <input class="input" wire:model="fNotes">
                </div>
            </div>
            <div class="flex gap-3">
                <button class="btn-primary" wire:click="save">Save</button>
                <button class="btn-ghost" wire:click="cancelForm">Cancel</button>
            </div>
        </div>
    @endif

    <div class="card mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label">Status</label>
            <select class="input w-auto" wire:model.live="statusFilter">
                <option value="">All</option>
                @foreach ($statuses as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
            </select>
        </div>
        <div>
            <label class="label">Distributor (RD)</label>
            <select class="input w-auto" wire:model.live="rdFilter">
                <option value="">All</option>
                @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
            </select>
        </div>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">Retailer</th>
                <th class="th">Duration</th>
                <th class="th text-right">Target</th>
                <th class="th text-right">Achieved</th>
                <th class="th text-right">Attainment</th>
                <th class="th text-right">Incentive %</th>
                <th class="th">Status</th>
                <th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $c)
                @php $pct = $c->target_volume > 0 ? round($c->achieved / $c->target_volume * 100, 1) : null; @endphp
                <tr wire:key="ac-{{ $c->id }}">
                    <td class="td">
                        <span class="font-mono">{{ $c->rt_code }}</span>
                        <span class="block text-xs text-gray-400">{{ $c->rt_name }} · {{ $c->rd_code ?: '—' }}</span>
                    </td>
                    <td class="td text-xs">
                        {{ $c->starts_on->format('d M Y') }} — {{ $c->ends_on->format('d M Y') }}
                        <span class="block text-gray-400">{{ $c->starts_on->diffInDays($c->ends_on) + 1 }} days</span>
                    </td>
                    <td class="td text-right">{{ number_format($c->target_volume) }}</td>
                    <td class="td text-right">{{ number_format($c->achieved) }}</td>
                    <td class="td text-right {{ $pct !== null && $pct >= 100 ? 'font-semibold text-emerald-600' : '' }}">
                        {{ $pct === null ? '—' : $pct.'%' }}
                    </td>
                    <td class="td text-right">{{ rtrim(rtrim(number_format($c->incentive_pct, 2), '0'), '.') }}%</td>
                    <td class="td">
                        <span class="badge {{ ['active' => 'bg-green-100 text-green-800', 'closed' => 'bg-gray-100 text-gray-700', 'cancelled' => 'bg-red-100 text-red-800'][$c->status] }}">
                            {{ $statuses[$c->status] ?? $c->status }}
                        </span>
                        @if ($c->notes) <span class="block text-xs text-gray-400">{{ $c->notes }}</span> @endif
                    </td>
                    <td class="td text-right whitespace-nowrap">
                        <button class="text-xs text-indigo-600" wire:click="editRow({{ $c->id }})">Edit</button>
                        @if ($c->status === 'active')
                            <button class="ml-2 text-xs text-gray-500" wire:click="close({{ $c->id }})" wire:confirm="Close this contract?">Close</button>
                        @endif
                        <button class="ml-2 text-xs text-red-600" wire:click="delete({{ $c->id }})" wire:confirm="Delete this contract?">Delete</button>
                    </td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="8">No contracts.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
