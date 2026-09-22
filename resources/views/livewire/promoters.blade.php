<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Promoter Management (RA)</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                    Retail Associates
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Track dedicated promoter personnel deployed at key retail shops and monitor monthly unit sales targets.
            </p>
        </div>

        @if ($tab === 'roster' && $this->canManage())
            <div class="flex items-center gap-2">
                <button class="btn-primary text-xs" wire:click="newRow">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Add Promoter</span>
                </button>
                <button class="btn-ghost text-xs" wire:click="export('xlsx')">Excel</button>
                <button class="btn-ghost text-xs" wire:click="export('csv')">CSV</button>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    @if ($this->canManage() && $this->canRequests())
        <div class="flex flex-wrap gap-2">
            <button wire:click="$set('tab', 'roster')"
                    class="rounded-xl px-3.5 py-2 text-xs font-semibold transition-all duration-150 shadow-2xs {{ $tab === 'roster' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:text-slate-900' }}">
                Active Roster &amp; Achievement
            </button>
            <button wire:click="$set('tab', 'requests')"
                    class="rounded-xl px-3.5 py-2 text-xs font-semibold transition-all duration-150 shadow-2xs {{ $tab === 'requests' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:text-slate-900' }}">
                Deployment Requests Queue
            </button>
        </div>
    @endif

    {{-- Request Component Sub-View --}}
    @if ($tab === 'requests' && $this->canRequests())
        <livewire:promoter-requests wire:key="ra-requests" />
    @endif

    {{-- Roster View --}}
    @if ($tab === 'roster' && $this->canManage())
        {{-- Promoter Editor Form --}}
        @if ($showForm)
            <div class="card space-y-4 border-indigo-200 bg-indigo-50/20">
                <div class="flex items-center justify-between border-b border-indigo-100 pb-3">
                    <h2 class="text-sm font-bold text-slate-900">{{ $editingId ? 'Edit Promoter Record' : 'Enroll New Retail Promoter' }}</h2>
                    <button class="text-slate-400 hover:text-slate-600" wire:click="cancelForm">&times;</button>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label">Full Name</label>
                        <input class="input text-xs" wire:model="fName" placeholder="Promoter full name">
                        @error('fName') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Mobile Contact</label>
                        <input class="input text-xs" wire:model="fPhone" placeholder="98XXXXXXXX">
                    </div>
                    <div>
                        <label class="label">Classification</label>
                        <select class="input text-xs" wire:model="fType">
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label">Assigned Retailer Store</label>
                        <input class="input text-xs" wire:model.live.debounce.300ms="fRtSearch" placeholder="Type RT code or store name to search…">
                        @if (count($rtOptions))
                            <select class="input mt-1.5 text-xs" size="4" wire:change="pickRetailer($event.target.value)">
                                @foreach ($rtOptions as $code => $label)
                                    <option value="{{ $code }}" @selected($fRtCode === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                        <p class="mt-1 text-xs {{ $fRtCode ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">
                            {{ $fRtCode ? 'Selected Account: '.$fRtCode : 'Please select a retailer from the list' }}
                        </p>
                        @error('fRtCode') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label">Monthly Target (Units)</label>
                        <input type="number" min="0" class="input text-xs font-mono font-bold" wire:model="fTarget" placeholder="e.g. 50">
                        @error('fTarget') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer select-none">
                            <input type="checkbox" wire:model="fActive" class="h-4 w-4 rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span>Active Status (Currently deployed at store)</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-3 border-t border-indigo-100">
                    <button class="btn-primary text-xs" wire:click="save">Save Promoter</button>
                    <button class="btn-ghost text-xs" wire:click="cancelForm">Cancel</button>
                </div>
            </div>
        @endif

        {{-- Filter Bar --}}
        <div class="card space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label">Reporting Month</label>
                    <input type="month" class="input text-xs" wire:model.live="month">
                </div>
                <div>
                    <label class="label">Promoter Tier</label>
                    <select class="input text-xs" wire:model.live="typeFilter">
                        <option value="">All Tiers</option>
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Distributor (RD)</label>
                    <select class="input text-xs" wire:model.live="rdFilter">
                        <option value="">All Distributors</option>
                        @foreach ($rdOptions as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Roster Table --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500">
                            <th class="th">Promoter Profile</th>
                            <th class="th">Role Type</th>
                            <th class="th">Assigned Store</th>
                            <th class="th text-right">Target</th>
                            <th class="th text-right">Achieved</th>
                            <th class="th text-right">Attainment %</th>
                            <th class="th">Performance</th>
                            <th class="th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr wire:key="p-{{ $r['id'] }}" class="hover:bg-slate-50/70 transition-colors">
                                <td class="td">
                                    <div class="font-bold text-slate-900">{{ $r['name'] }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $r['phone'] ?: 'No phone' }}</div>
                                </td>
                                <td class="td">
                                    <span class="badge-indigo text-[10px]">{{ $r['type_label'] }}</span>
                                </td>
                                <td class="td">
                                    <div class="font-mono font-bold text-slate-800">{{ $r['rt_code'] }}</div>
                                    <div class="text-[11px] text-slate-500 max-w-xs truncate">{{ $r['rt_name'] }}</div>
                                </td>
                                <td class="td text-right font-mono font-semibold">{{ number_format($r['target']) }}</td>
                                <td class="td text-right font-mono font-bold text-indigo-700">{{ number_format($r['achieved']) }}</td>
                                <td class="td text-right">
                                    <span class="font-mono font-bold {{ $r['pct'] >= 100 ? 'text-emerald-700' : ($r['pct'] >= 60 ? 'text-blue-700' : 'text-slate-600') }}">
                                        {{ $r['pct'] !== null ? $r['pct'].'%' : '—' }}
                                    </span>
                                </td>
                                <td class="td">
                                    <span class="badge {{ [
                                        'hit' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                        'on-track' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
                                        'behind' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
                                        'no-target' => 'bg-slate-100 text-slate-600 ring-slate-400/20',
                                    ][$r['status']] }} text-[10px] font-semibold">
                                        {{ ['hit' => 'Target Hit 🏆', 'on-track' => 'On Track', 'behind' => 'Behind Pace', 'no-target' => 'Unset'][$r['status']] }}
                                    </span>
                                </td>
                                <td class="td text-right whitespace-nowrap space-x-1.5">
                                    <button class="btn-ghost !py-1 !px-2 text-xs text-indigo-600 font-semibold" wire:click="editRow({{ $r['id'] }})">Edit</button>
                                    <button class="btn-ghost !py-1 !px-2 text-xs text-rose-600 font-medium hover:bg-rose-50" wire:click="delete({{ $r['id'] }})"
                                            wire:confirm="Remove this promoter from the active roster?">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400 text-xs">
                                    No promoters enrolled for this reporting period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-slate-900 text-xs">
                            <tr>
                                <td class="td" colspan="3">Network Total ({{ $rows->count() }} Promoters &bull; {{ \Illuminate\Support\Carbon::parse($month.'-01')->format('M Y') }})</td>
                                <td class="td text-right font-mono">{{ number_format($totals['target']) }}</td>
                                <td class="td text-right font-mono text-indigo-700 font-extrabold">{{ number_format($totals['achieved']) }}</td>
                                <td class="td text-right font-mono font-bold">{{ $totals['pct'] !== null ? $totals['pct'].'%' : '—' }}</td>
                                <td class="td" colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif
</div>
