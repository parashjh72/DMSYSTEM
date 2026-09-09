<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Users</h1>
        <button class="btn-primary" wire:click="$set('showForm', true)">New user</button>
    </div>

    @if ($showForm)
        <div class="card mt-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">Name</label><input class="input" wire:model="name">@error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label">Email</label><input class="input" wire:model="email">@error('email')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label">Password {{ $editingId ? '(leave blank to keep)' : '' }}</label><input type="password" class="input" wire:model="password">@error('password')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="label">Role</label>
                    <select class="input" wire:model.live="role">
                        @foreach ($roles as $r) <option>{{ $r }}</option> @endforeach
                    </select>
                </div>
            </div>

            @if (in_array($role, $scopedRoles, true))
                <div class="mt-4">
                    <label class="label">Assigned distributors (RD)
                        <span class="text-gray-400">— this {{ $role }} sees only rows for the ticked RD codes</span>
                    </label>
                    @if ($rdOptions->isEmpty())
                        <p class="text-xs text-amber-600">No distributors in the system yet. Import ND → RD data or add one in Master Data first.</p>
                    @else
                        <div class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 p-2">
                            @foreach ($rdOptions as $rd)
                                <label wire:key="rd-{{ $rd['code'] }}" class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50">
                                    <input type="checkbox" value="{{ $rd['code'] }}" wire:model="scopedRdCodes" class="rounded border-gray-300">
                                    {{ $rd['label'] }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('scopedRdCodes')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="mt-4 flex gap-3">
                <button class="btn-primary" wire:click="save">Save</button>
                <button class="btn-ghost" wire:click="$set('showForm', false)">Cancel</button>
            </div>
        </div>
    @endif

    <div class="card mt-6 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="th">Name</th><th class="th">Email</th><th class="th">Role</th><th class="th">Scope</th><th class="th"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            @foreach ($users as $u)
                <tr>
                    <td class="td">{{ $u->name }}</td>
                    <td class="td">{{ $u->email }}</td>
                    <td class="td">{{ $u->roles->pluck('name')->join(', ') ?: '—' }}</td>
                    <td class="td text-xs text-gray-500">{{ $u->scopedRdCodes() ? implode(', ', $u->scopedRdCodes()) : 'All data' }}</td>
                    <td class="td"><button class="text-indigo-600" wire:click="edit({{ $u->id }})">Edit</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
