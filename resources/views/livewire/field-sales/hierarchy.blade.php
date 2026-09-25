<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Regions &amp; Areas</h1>
        <p class="mt-1 text-xs text-slate-500">Company → Region → Area → Distributor. Field officers belong to the Area of their distributors; reports and the live map filter by it.</p>
    </div>

    @include('livewire.field-sales.partials.setup-tabs')

    @if ($flash)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-medium text-emerald-800">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-800">{{ $error }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Regions --}}
        <div class="card space-y-4">
            <h2 class="text-sm font-bold text-slate-900">Regions</h2>
            <form wire:submit="saveRegion" class="grid gap-3 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label class="label">Code</label>
                    <input class="input text-xs uppercase" wire:model="regionCode" placeholder="EAST">
                    @error('regionCode') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-3">
                    <label class="label">Name</label>
                    <input class="input text-xs" wire:model="regionName" placeholder="Eastern Region">
                    @error('regionName') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <label class="mt-6 flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" class="rounded border-slate-300 text-indigo-600" wire:model="regionActive"> Active</label>
                <div class="flex gap-2 sm:col-span-6">
                    <button class="btn-primary text-xs" type="submit">{{ $regionEditId ? 'Update region' : 'Add region' }}</button>
                    @if ($regionEditId)<button type="button" class="btn-ghost text-xs" wire:click="$set('regionEditId', null)">Cancel</button>@endif
                </div>
            </form>
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-100">
                @forelse ($regions as $region)
                    <li wire:key="region-{{ $region->id }}" class="flex items-center justify-between px-3 py-2 text-xs">
                        <div>
                            <span class="font-mono font-semibold text-slate-500">{{ $region->code }}</span>
                            <span class="font-semibold text-slate-900">{{ $region->name }}</span>
                            <span class="text-slate-400">· {{ $region->areas_count }} area(s)</span>
                            @unless ($region->active)<span class="badge badge-slate ml-1">Inactive</span>@endunless
                        </div>
                        <div class="flex gap-3">
                            <button class="font-semibold text-indigo-600" wire:click="editRegion({{ $region->id }})">Edit</button>
                            <button class="font-semibold text-rose-600" wire:click="deleteRegion({{ $region->id }})" wire:confirm="Delete this region?">Delete</button>
                        </div>
                    </li>
                @empty
                    <li class="px-3 py-6 text-center text-xs text-slate-400">No regions yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Areas --}}
        <div class="card space-y-4">
            <h2 class="text-sm font-bold text-slate-900">Areas</h2>
            <form wire:submit="saveArea" class="grid gap-3 sm:grid-cols-6">
                <div class="sm:col-span-2">
                    <label class="label">Region</label>
                    <select class="input text-xs" wire:model="areaRegionId">
                        <option value="">Choose…</option>
                        @foreach ($regions as $region) <option value="{{ $region->id }}">{{ $region->name }}</option> @endforeach
                    </select>
                    @error('areaRegionId') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-1">
                    <label class="label">Code</label>
                    <input class="input text-xs uppercase" wire:model="areaCode" placeholder="KTM">
                    @error('areaCode') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Name</label>
                    <input class="input text-xs" wire:model="areaName" placeholder="Kathmandu Valley">
                    @error('areaName') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <label class="mt-6 flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" class="rounded border-slate-300 text-indigo-600" wire:model="areaActive"> Active</label>
                <div class="flex gap-2 sm:col-span-6">
                    <button class="btn-primary text-xs" type="submit">{{ $areaEditId ? 'Update area' : 'Add area' }}</button>
                    @if ($areaEditId)<button type="button" class="btn-ghost text-xs" wire:click="$set('areaEditId', null)">Cancel</button>@endif
                </div>
            </form>
            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-100">
                @forelse ($areas as $area)
                    <li wire:key="area-{{ $area->id }}" class="flex items-center justify-between px-3 py-2 text-xs {{ $selected?->id === $area->id ? 'bg-indigo-50/60' : '' }}">
                        <button class="text-left" wire:click="selectArea({{ $area->id }})">
                            <span class="font-mono font-semibold text-slate-500">{{ $area->code }}</span>
                            <span class="font-semibold text-slate-900">{{ $area->name }}</span>
                            <span class="text-slate-400">· {{ $area->region?->name }} · {{ $area->distributors_count }} distributor(s)</span>
                            @unless ($area->active)<span class="badge badge-slate ml-1">Inactive</span>@endunless
                        </button>
                        <div class="flex gap-3">
                            <button class="font-semibold text-indigo-600" wire:click="editArea({{ $area->id }})">Edit</button>
                            <button class="font-semibold text-rose-600" wire:click="deleteArea({{ $area->id }})" wire:confirm="Delete this area? Its distributors become unassigned.">Delete</button>
                        </div>
                    </li>
                @empty
                    <li class="px-3 py-6 text-center text-xs text-slate-400">No areas yet — add a region first.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Distributor assignment --}}
    <div class="card space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-bold text-slate-900">
                Distributors {{ $selected ? 'in '.$selected->name : '' }}
            </h2>
            <span class="badge {{ $unassignedCount > 0 ? 'badge-amber' : 'badge-emerald' }}">{{ $unassignedCount }} distributor(s) not in any area</span>
        </div>
        @if (! $selected)
            <p class="text-xs text-slate-400">Click an area above to manage its distributors.</p>
        @else
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-64 flex-1">
                    <label class="label">Add distributor</label>
                    <select class="input text-xs" wire:model="addRdCode">
                        <option value="">Choose an unassigned distributor…</option>
                        @foreach ($unassigned as $rd) <option value="{{ $rd->code }}">{{ $rd->code }} — {{ $rd->name }}</option> @endforeach
                    </select>
                </div>
                <button class="btn-primary text-xs" wire:click="assignDistributor">Add</button>
            </div>
            <div class="flex flex-wrap gap-2">
                @forelse ($selected->distributors as $rd)
                    <span wire:key="rd-{{ $rd->id }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs">
                        <span class="font-mono font-semibold">{{ $rd->code }}</span> <span class="text-slate-500">{{ $rd->name }}</span>
                        <button class="text-slate-400 hover:text-rose-600" wire:click="unassignDistributor({{ $rd->id }})" title="Remove">&times;</button>
                    </span>
                @empty
                    <span class="text-xs text-slate-400">No distributors in this area yet.</span>
                @endforelse
            </div>
        @endif
    </div>
</div>
