<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Promoters (RA)</h1>
            <p class="mt-1 text-sm text-gray-500">
                Each promoter is attached to a retailer with a monthly unit target. Achievement is that
                retailer's {{ config('promoters.achievement_basis') === 'st_date' ? 'sell-through' : 'activations' }} for the month.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="btn-primary" wire:click="newRow">+ Add promoter</button>
            <button class="btn-ghost" wire:click="export('xlsx')">Export → Excel</button>
            <button class="btn-ghost" wire:click="export('csv')">CSV</button>
        </div>
    </div>

    @if ($showForm)
        <div class="card mt-4 space-y-3">
            <h2 class="text-sm font-semibold">{{ $editingId ? 'Edit' : 'Add' }} promoter</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label">Name</label>
                    <input class="input" wire:model="fName">
                    @error('fName') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Phone</label>
                    <input class="input" wire:model="fPhone">
                </div>
                <div>
                    <label class="label">Type</label>
                    <select class="input" wire:model="fType">
                        @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
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
                    <label class="label">Monthly target (units)</label>
                    <input type="number" min="0" class="input" wire:model="fTarget">
                    @error('fTarget') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" wire:model="fActive" class="rounded border-gray-300"> Active
                </label>
            </div>
            <div class="flex gap-3">
                <button class="btn-primary" wire:click="save">Save</button>
                <button class="btn-ghost" wire:click="cancelForm">Cancel</button>
            </div>
        </div>
    @endif

    <div class="card mt-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="label">Month</label>
                <input type="month" class="input w-auto" wire:model.live="month">
            </div>
            <div>
                <label class="label">Type</label>
                <select class="input w-auto" wire:model.live="typeFilter">
                    <option value="">All types</option>
                    @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Distributor</label>
                <select class="input w-auto" wire:model.live="rdFilter">
                    <option value="">All distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">Promoter</th><th class="th">Type</th><th class="th">Retailer</th>
                <th class="th text-right">Target</th><th class="th text-right">Achieved</th>
                <th class="th text-right">Attainment</th><th class="th">Status</th><th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $r)
                <tr wire:key="p-{{ $r['id'] }}">
                    <td class="td font-medium">{{ $r['name'] }}<div class="text-xs text-gray-400">{{ $r['phone'] }}</div></td>
                    <td class="td text-xs">{{ $r['type_label'] }}</td>
                    <td class="td text-xs">{{ $r['rt_code'] }}<div class="text-gray-400">{{ $r['rt_name'] }}</div></td>
                    <td class="td text-right">{{ number_format($r['target']) }}</td>
                    <td class="td text-right font-medium">{{ number_format($r['achieved']) }}</td>
                    <td class="td text-right">{{ $r['pct'] !== null ? $r['pct'].'%' : '—' }}</td>
                    <td class="td">
                        <span class="badge {{ [
                            'hit' => 'bg-green-100 text-green-800',
                            'on-track' => 'bg-blue-100 text-blue-800',
                            'behind' => 'bg-red-100 text-red-800',
                            'no-target' => 'bg-gray-100 text-gray-600',
                        ][$r['status']] }}">
                            {{ ['hit' => 'Target hit', 'on-track' => 'On track', 'behind' => 'Behind', 'no-target' => 'No target'][$r['status']] }}
                        </span>
                    </td>
                    <td class="td whitespace-nowrap text-right text-xs">
                        <button class="text-indigo-600" wire:click="editRow({{ $r['id'] }})">Edit</button>
                        <button class="ml-2 text-red-600" wire:click="delete({{ $r['id'] }})"
                                wire:confirm="Remove this promoter?">Delete</button>
                    </td>
                </tr>
            @empty
                <tr><td class="td text-sm text-gray-400" colspan="8">No promoters yet — add one above.</td></tr>
            @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="td" colspan="3">Total ({{ $rows->count() }} promoters) · {{ \Illuminate\Support\Carbon::parse($month.'-01')->format('M Y') }}</td>
                        <td class="td text-right">{{ number_format($totals['target']) }}</td>
                        <td class="td text-right">{{ number_format($totals['achieved']) }}</td>
                        <td class="td text-right">{{ $totals['pct'] !== null ? $totals['pct'].'%' : '—' }}</td>
                        <td class="td" colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
