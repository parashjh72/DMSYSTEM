<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Check-in Points</h1>
        <p class="mt-1 text-xs text-slate-500">Places where field officers may punch attendance. A distributor's point applies to every officer assigned to that distributor; an office point applies to its Area (or everyone if no Area is set).</p>
    </div>

    @include('livewire.field-sales.partials.setup-tabs')

    @if ($flash)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-medium text-emerald-800">{{ $flash }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Form --}}
        <form wire:submit="save" class="card space-y-4 lg:col-span-2">
            <h2 class="text-sm font-bold text-slate-900">{{ $editId ? 'Edit check-in point' : 'Add check-in point' }}</h2>
            <div class="flex gap-2 text-xs">
                @foreach (['distributor' => 'Distributor point', 'office' => 'Office / other'] as $value => $label)
                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-2 font-semibold {{ $kind === $value ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600' }}">
                        <input type="radio" class="sr-only" wire:model.live="kind" value="{{ $value }}"> {{ $label }}
                    </label>
                @endforeach
            </div>
            @if ($kind === 'distributor')
                <div>
                    <label class="label">Distributor</label>
                    <select class="input text-xs" wire:model.live="rdCode">
                        <option value="">Choose…</option>
                        @foreach ($distributors as $rd) <option value="{{ $rd->code }}">{{ $rd->code }} — {{ $rd->name }}</option> @endforeach
                    </select>
                    @error('rdCode') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <label class="label">Area <span class="font-normal text-slate-400">(empty = everyone)</span></label>
                    <select class="input text-xs" wire:model="areaId">
                        <option value="">All areas</option>
                        @foreach ($areas as $id => $areaName) <option value="{{ $id }}">{{ $areaName }}</option> @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="label">Name</label>
                <input class="input text-xs" wire:model="name" placeholder="e.g. Head office, Butwal">
                @error('name') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Location</label>
                <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                    <span class="font-mono text-slate-700">{{ $latitude !== null ? number_format($latitude, 6).', '.number_format($longitude, 6) : 'Not set' }}</span>
                    <x-map-picker :save="'setFormLatLng'" id="geofence-form" :lat="$latitude" :lng="$longitude" label="Pin on map" title="Pin Check-in Point" class="text-xs font-semibold text-indigo-600" />
                </div>
                @error('latitude') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Radius (metres)</label>
                    <input type="number" min="25" max="5000" class="input text-xs" wire:model="radius">
                    @error('radius') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <label class="mt-6 flex items-center gap-2 text-xs text-slate-700"><input type="checkbox" class="rounded border-slate-300 text-indigo-600" wire:model="active"> Active</label>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary text-xs">{{ $editId ? 'Update point' : 'Add point' }}</button>
                @if ($editId)<button type="button" class="btn-ghost text-xs" wire:click="cancelEdit">Cancel</button>@endif
            </div>
        </form>

        {{-- List --}}
        <div class="space-y-4 lg:col-span-3">
            @if ($missing->isNotEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                    <span class="font-semibold">{{ $missing->count() }} distributor(s) have no check-in point yet:</span>
                    {{ $missing->take(12)->pluck('code')->implode(', ') }}{{ $missing->count() > 12 ? '…' : '' }}
                </div>
            @endif
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                <div class="border-b border-slate-100 p-3">
                    <input class="input text-xs" wire:model.live.debounce.300ms="search" placeholder="Search name or distributor code…">
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500">
                                <th class="th">Name</th>
                                <th class="th">Applies to</th>
                                <th class="th">Radius</th>
                                <th class="th">Status</th>
                                <th class="th text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($points as $point)
                                <tr wire:key="point-{{ $point->id }}" class="hover:bg-slate-50/70">
                                    <td class="td">
                                        <div class="font-semibold text-slate-900">{{ $point->name }}</div>
                                        <a class="font-mono text-[10px] text-indigo-600" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $point->latitude }},{{ $point->longitude }}">{{ number_format($point->latitude, 5) }}, {{ number_format($point->longitude, 5) }}</a>
                                    </td>
                                    <td class="td text-slate-600">
                                        {{ $point->distributor ? $point->distributor->code.' — '.$point->distributor->name : ($point->area ? 'Office · '.$point->area->name : 'Office · everyone') }}
                                    </td>
                                    <td class="td">{{ number_format($point->radius_metres) }} m</td>
                                    <td class="td"><span class="badge {{ $point->active ? 'badge-emerald' : 'badge-slate' }}">{{ $point->active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="td text-right whitespace-nowrap">
                                        <button class="font-semibold text-indigo-600" wire:click="edit({{ $point->id }})">Edit</button>
                                        <button class="ml-3 font-semibold text-rose-600" wire:click="delete({{ $point->id }})" wire:confirm="Delete this check-in point?">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-xs text-slate-400">No check-in points yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-4 py-3">{{ $points->links() }}</div>
            </div>
        </div>
    </div>
</div>
