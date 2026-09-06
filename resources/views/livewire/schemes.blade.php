<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Schemes</h1>
            <p class="mt-1 text-sm text-gray-500">Retailer sell-out incentive schemes — target slabs and payout %.</p>
        </div>
        <button class="btn-primary" wire:click="newScheme">New scheme</button>
    </div>

    @if ($showForm)
        <div class="card mt-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="lg:col-span-2"><label class="label">Scheme name</label><input class="input" wire:model="name">@error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label">Status</label>
                    <select class="input" wire:model="status"><option value="draft">Draft</option><option value="active">Active</option><option value="closed">Closed</option></select></div>
                <div><label class="label">Effective from</label><input type="date" class="input" wire:model="effectiveFrom">@error('effectiveFrom')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label">Effective to</label><input type="date" class="input" wire:model="effectiveTo">@error('effectiveTo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label">Sell-out counts as</label>
                    <select class="input" wire:model="selloutBasis"><option value="activation_date">Activation (over-the-counter)</option><option value="st_date">Sell-thru date</option></select></div>
                <div><label class="label">Qualifying models</label>
                    <select class="input" wire:model="qualifiedModels">
                        <option value="running">Running models only</option>
                        <option value="all">All models</option>
                        <option value="out">Out models only</option>
                    </select></div>
                <div class="lg:col-span-3"><label class="label">Note / terms</label><textarea rows="2" class="input" wire:model="note"></textarea></div>
            </div>

            <h3 class="mt-5 text-sm font-semibold">Target slabs</h3>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-400">
                        <th class="py-1">#</th><th>Label</th><th>Min value</th><th>Max value (blank = & above)</th><th>Payout %</th><th>Reward (option two)</th><th></th>
                    </tr></thead>
                    <tbody>
                    @foreach ($slabs as $i => $s)
                        <tr wire:key="slab-{{ $i }}" class="border-t border-gray-100">
                            <td class="py-1 pr-2"><input class="input w-14" wire:model="slabs.{{ $i }}.slab_no"></td>
                            <td class="pr-2"><input class="input" wire:model="slabs.{{ $i }}.label"></td>
                            <td class="pr-2"><input type="number" class="input w-32" wire:model="slabs.{{ $i }}.min_value"></td>
                            <td class="pr-2"><input type="number" class="input w-32" wire:model="slabs.{{ $i }}.max_value"></td>
                            <td class="pr-2"><input type="number" step="0.01" class="input w-20" wire:model="slabs.{{ $i }}.payout_percent"></td>
                            <td class="pr-2"><input class="input" wire:model="slabs.{{ $i }}.reward"></td>
                            <td><button class="text-xs text-red-500" wire:click="removeSlab({{ $i }})">remove</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <button class="btn-ghost mt-2 text-xs" wire:click="addSlab">+ Add slab</button>

            <div class="mt-4 flex gap-3">
                <button class="btn-primary" wire:click="save">Save scheme</button>
                <button class="btn-ghost" wire:click="$set('showForm', false)">Cancel</button>
            </div>
        </div>
    @endif

    <div class="card mt-6 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">Scheme</th><th class="th">Period</th><th class="th">Sell-out</th>
                <th class="th">Models</th><th class="th">Slabs</th><th class="th">Status</th><th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($schemes as $s)
                <tr wire:key="sc-{{ $s->id }}">
                    <td class="td">{{ $s->name }}</td>
                    <td class="td">{{ $s->effective_from->format('d M Y') }} – {{ $s->effective_to->format('d M Y') }}</td>
                    <td class="td">{{ $s->sellout_basis === 'st_date' ? 'Sell-thru' : 'Activation' }}</td>
                    <td class="td">{{ ucfirst($s->qualified_models) }}</td>
                    <td class="td">{{ $s->slabs_count }}</td>
                    <td class="td"><span class="badge {{ ['active'=>'bg-green-100 text-green-800','closed'=>'bg-gray-100 text-gray-600','draft'=>'bg-amber-100 text-amber-800'][$s->status] }}">{{ ucfirst($s->status) }}</span></td>
                    <td class="td whitespace-nowrap">
                        <a class="text-indigo-600" href="{{ route('schemes.retailers', $s->uuid) }}" wire:navigate>Retailers</a>
                        <a class="ml-3 text-indigo-600" href="{{ route('schemes.report', $s->uuid) }}" wire:navigate>Achievement</a>
                        <button class="ml-3 text-indigo-600" wire:click="edit('{{ $s->uuid }}')">Edit</button>
                        <button class="ml-3 text-red-500" wire:click="delete('{{ $s->uuid }}')" wire:confirm="Delete this scheme?">Delete</button>
                    </td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="7">No schemes yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
