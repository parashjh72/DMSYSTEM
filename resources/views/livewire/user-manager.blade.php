<div>
    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">User Management & Permissions</h1>
                <p class="text-xs text-gray-500">Manage user accounts, RBAC roles, reporting hierarchies, and data scoping.</p>
            </div>
        </div>
        <button class="btn-primary text-xs flex items-center gap-1.5" wire:click="$set('showForm', true)">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New User
        </button>
    </div>

    @if ($showForm)
        <div class="card mt-6 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900">{{ $editingId ? 'Edit User Account' : 'Create User Account' }}</h2>
                <button class="text-gray-400 hover:text-gray-600 text-xs" wire:click="$set('showForm', false)">✕ Close</button>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Full Name</label>
                    <input class="input text-xs" placeholder="Jane Doe" wire:model="name">
                    @error('name')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Work Email Address</label>
                    <input class="input text-xs" type="email" placeholder="jane@company.com" wire:model="email">
                    @error('email')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">Password {{ $editingId ? '(leave blank to keep current)' : '' }}</label>
                    <input type="password" class="input text-xs" placeholder="••••••••" wire:model="password">
                    @error('password')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label text-xs font-semibold text-gray-700">System Role</label>
                    <select class="input text-xs" wire:model.live="role">
                        @foreach ($roles as $r) <option>{{ $r }}</option> @endforeach
                    </select>
                </div>
            </div>

            @if ($managerRole)
                <div class="mt-4">
                    <label class="label text-xs font-semibold text-gray-700">Reporting Manager ({{ $managerRole }})</label>
                    <select class="input text-xs max-w-md" wire:model="reportsToId">
                        <option value="">— None —</option>
                        @foreach ($managerOptions as $id => $mName) <option value="{{ $id }}">{{ $mName }}</option> @endforeach
                    </select>
                    @error('reportsToId')<p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p>@enderror
                </div>
            @endif

            @if (in_array($role, $scopedRoles, true))
                <div class="mt-5 rounded-xl border border-indigo-100 bg-indigo-50/40 p-4">
                    <label class="label text-xs font-bold text-gray-900 mb-1">
                        Distributor Scope Restriction
                    </label>
                    <p class="text-[11px] text-gray-500 mb-3">As a <strong>{{ $role }}</strong>, this user will only see telemetry and records for selected RD codes.</p>
                    @if ($rdOptions->isEmpty())
                        <p class="text-xs text-amber-600">No distributors registered yet in Master Data.</p>
                    @else
                        <div class="max-h-56 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2.5 divide-y divide-gray-50 grid grid-cols-1 sm:grid-cols-2 gap-1">
                            @foreach ($rdOptions as $rd)
                                <label wire:key="rd-{{ $rd['code'] }}" class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" value="{{ $rd['code'] }}" wire:model="scopedRdCodes" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="font-mono font-medium text-gray-800">{{ $rd['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('scopedRdCodes')<p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="mt-5 flex items-center gap-3 border-t border-gray-100 pt-4">
                <button class="btn-primary text-xs" wire:click="save">Save User</button>
                <button class="btn-ghost text-xs" wire:click="$set('showForm', false)">Cancel</button>
            </div>
        </div>
    @endif

    <!-- Users Table -->
    <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/75">
                    <tr>
                        <th class="th">User Account</th>
                        <th class="th">Email Address</th>
                        <th class="th">Role</th>
                        <th class="th">Reports To</th>
                        <th class="th">Data Scope</th>
                        <th class="th text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($users as $u)
                    <tr class="hover:bg-gray-50/75 transition">
                        <td class="td font-bold text-gray-900 flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-tr from-indigo-500 to-indigo-700 text-white font-bold text-[10px]">
                                {{ strtoupper(substr($u->name, 0, 1)) }}
                            </div>
                            <span>{{ $u->name }}</span>
                        </td>
                        <td class="td text-gray-600 font-mono">{{ $u->email }}</td>
                        <td class="td">
                            <span class="badge bg-indigo-50 text-indigo-700 font-medium border border-indigo-100">
                                {{ $u->roles->pluck('name')->join(', ') ?: 'No Role' }}
                            </span>
                        </td>
                        <td class="td text-gray-600">{{ $u->reportsTo?->name ?? '—' }}</td>
                        <td class="td">
                            @if ($u->scopedRdCodes())
                                <span class="badge bg-amber-50 text-amber-800 border border-amber-200 font-mono text-[10px]">
                                    Scoped: {{ implode(', ', $u->scopedRdCodes()) }}
                                </span>
                            @else
                                <span class="badge bg-emerald-50 text-emerald-800 border border-emerald-200 text-[10px]">
                                    All Data (Global)
                                </span>
                            @endif
                        </td>
                        <td class="td text-right">
                            <button class="font-semibold text-indigo-600 hover:text-indigo-800 text-xs" wire:click="edit({{ $u->id }})">
                                Edit Account
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
