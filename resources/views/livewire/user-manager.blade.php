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
                    <select class="input" wire:model="role">
                        @foreach ($roles as $r) <option>{{ $r }}</option> @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button class="btn-primary" wire:click="save">Save</button>
                <button class="btn-ghost" wire:click="$set('showForm', false)">Cancel</button>
            </div>
        </div>
    @endif

    <div class="card mt-6 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr><th class="th">Name</th><th class="th">Email</th><th class="th">Role</th><th class="th"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            @foreach ($users as $u)
                <tr>
                    <td class="td">{{ $u->name }}</td>
                    <td class="td">{{ $u->email }}</td>
                    <td class="td">{{ $u->roles->pluck('name')->join(', ') ?: '—' }}</td>
                    <td class="td"><button class="text-indigo-600" wire:click="edit({{ $u->id }})">Edit</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
