<div>
    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Trade Schemes & Incentives</h1>
                <p class="text-xs text-gray-500">Retailer sell-out incentive programs, target slabs, and payout percentage structures.</p>
            </div>
        </div>
        <button class="btn-primary text-xs flex items-center gap-1.5" wire:click="newScheme">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Scheme
        </button>
    </div>

    @if ($showForm)
        <div class="card mt-6 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900">{{ $schemeId ? 'Edit Trade Scheme' : 'Create Trade Scheme' }}</h2>
                <button class="text-gray-400 hover:text-gray-600 text-xs" wire:click="$set('showForm', false)">✕ Close</button>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <label class="label text-xs font-semibold text-gray-700">Scheme Name</label>
                    <input class="input text-xs" placeholder="e.g. Q3 Monsoon Sellout Mega Booster" wire:model="name">
                    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Status</label>
                    <select class="input text-xs" wire:model="status">
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Effective From</label>
                    <input type="date" class="input text-xs" wire:model="effectiveFrom">
                    @error('effectiveFrom')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Effective To</label>
                    <input type="date" class="input text-xs" wire:model="effectiveTo">
                    @error('effectiveTo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Sell-out Basis</label>
                    <select class="input text-xs" wire:model="selloutBasis">
                        <option value="activation_date">Activation (Consumer sellout)</option>
                        <option value="st_date">Sell-through (RD → RT Invoice)</option>
                    </select>
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Qualifying Models</label>
                    <select class="input text-xs" wire:model="qualifiedModels">
                        <option value="running">Running Models Only</option>
                        <option value="all">All Models</option>
                        <option value="out">Out Models Only</option>
                    </select>
                </div>
                <div class="lg:col-span-3">
                    <label class="label text-xs font-semibold text-gray-700">Notes / Program Terms</label>
                    <textarea rows="2" class="input text-xs" placeholder="Special conditions, qualification caps, or distribution guidelines..." wire:model="note"></textarea>
                </div>
            </div>

            <!-- Target Slabs -->
            <div class="mt-6 border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Target Slabs & Reward Matrix</h3>
                        <p class="text-[11px] text-gray-500">Define milestone volume slabs and payout percentages or gifts.</p>
                    </div>
                    <button class="btn-ghost text-xs border border-gray-200" wire:click="addSlab">
                        + Add Slab
                    </button>
                </div>

                <div class="mt-3 overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                        <thead class="bg-gray-50/75 text-gray-600">
                            <tr>
                                <th class="th w-14">#</th>
                                <th class="th">Slab Label</th>
                                <th class="th">Min Value</th>
                                <th class="th">Max Value (blank = & above)</th>
                                <th class="th">Payout %</th>
                                <th class="th">Reward (Option 2)</th>
                                <th class="th w-16"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($slabs as $i => $s)
                            <tr wire:key="slab-{{ $i }}" class="hover:bg-gray-50/50">
                                <td class="td"><input class="input py-1 text-xs w-12 font-mono text-center" wire:model="slabs.{{ $i }}.slab_no"></td>
                                <td class="td"><input class="input py-1 text-xs" placeholder="e.g. Silver Tier" wire:model="slabs.{{ $i }}.label"></td>
                                <td class="td"><input type="number" class="input py-1 text-xs font-mono" wire:model="slabs.{{ $i }}.min_value"></td>
                                <td class="td"><input type="number" class="input py-1 text-xs font-mono" placeholder="Infinity" wire:model="slabs.{{ $i }}.max_value"></td>
                                <td class="td"><input type="number" step="0.01" class="input py-1 text-xs font-mono" placeholder="2.5" wire:model="slabs.{{ $i }}.payout_percent"></td>
                                <td class="td"><input class="input py-1 text-xs" placeholder="e.g. Smart LED TV" wire:model="slabs.{{ $i }}.reward"></td>
                                <td class="td text-right">
                                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-800" wire:click="removeSlab({{ $i }})">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4">
                <button class="btn-primary text-xs" wire:click="save">Save Scheme</button>
                <button class="btn-ghost text-xs" wire:click="$set('showForm', false)">Cancel</button>
            </div>
        </div>
    @endif

    <!-- Schemes Table -->
    <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/75">
                    <tr>
                        <th class="th">Scheme Program</th>
                        <th class="th">Active Window</th>
                        <th class="th">Sell-Out Basis</th>
                        <th class="th">Qualified Models</th>
                        <th class="th text-center">Slabs</th>
                        <th class="th">Status</th>
                        <th class="th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($schemes as $s)
                    <tr wire:key="sc-{{ $s->id }}" class="hover:bg-gray-50/75 transition">
                        <td class="td">
                            <div class="font-bold text-gray-900">{{ $s->name }}</div>
                            @if ($s->note)
                                <div class="text-[11px] text-gray-400 truncate max-w-xs">{{ $s->note }}</div>
                            @endif
                        </td>
                        <td class="td text-gray-600">
                            {{ $s->effective_from->format('d M Y') }} – {{ $s->effective_to->format('d M Y') }}
                        </td>
                        <td class="td">
                            <span class="badge {{ $s->sellout_basis === 'st_date' ? 'bg-blue-50 text-blue-700' : 'bg-indigo-50 text-indigo-700' }}">
                                {{ $s->sellout_basis === 'st_date' ? 'Sell-through (ST)' : 'Activation' }}
                            </span>
                        </td>
                        <td class="td text-gray-700 font-medium">{{ ucfirst($s->qualified_models) }}</td>
                        <td class="td text-center font-mono font-semibold">{{ $s->slabs_count }}</td>
                        <td class="td">
                            <span class="badge {{ match($s->status) {
                                'active' => 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200',
                                'closed' => 'bg-gray-100 text-gray-600',
                                'draft' => 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200',
                                default => 'bg-gray-100 text-gray-700',
                            } }}">
                                {{ ucfirst($s->status) }}
                            </span>
                        </td>
                        <td class="td text-right whitespace-nowrap space-x-2">
                            <a class="font-semibold text-indigo-600 hover:text-indigo-800" href="{{ route('schemes.retailers', $s->uuid) }}" wire:navigate>
                                Retailers
                            </a>
                            <span class="text-gray-300">·</span>
                            <a class="font-semibold text-indigo-600 hover:text-indigo-800" href="{{ route('schemes.report', $s->uuid) }}" wire:navigate>
                                Achievement
                            </a>
                            <span class="text-gray-300">·</span>
                            <button class="font-semibold text-gray-700 hover:text-gray-900" wire:click="edit('{{ $s->uuid }}')">Edit</button>
                            <span class="text-gray-300">·</span>
                            <button class="font-semibold text-rose-600 hover:text-rose-800" wire:click="delete('{{ $s->uuid }}')" wire:confirm="Are you sure you want to delete this scheme?">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="td text-center text-gray-400 py-10" colspan="7">
                            <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p class="mt-2 text-xs">No trade schemes defined yet. Create one to get started.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
