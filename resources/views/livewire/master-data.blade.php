<div>
    @php
        $noun = ['rd' => 'Distributor', 'rt' => 'Retailer', 'model' => 'Model', 'tso' => 'TSO'][$tab] ?? 'Entry';
    @endphp

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Master Data Management</h1>
                <p class="text-xs text-gray-500">Core organizational lookup entities kept synchronized across ingestion pipelines.</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($editable)
                <button class="btn-primary text-xs flex items-center gap-1.5" wire:click="newRow">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add {{ $noun }}
                </button>
            @endif
            <button class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200" wire:click="export('xlsx')">
                <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Excel
            </button>
            <button class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200" wire:click="export('csv')">
                CSV
            </button>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="mt-6 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
        @foreach ($tabs as $key => [$label])
            <button wire:click="$set('tab', '{{ $key }}')"
                    class="rounded-xl px-4 py-2 text-xs font-semibold transition {{ $tab === $key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <!-- Edit / Create Form -->
    @if ($showForm && $editable)
        <div class="card mt-6 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900">{{ $editingId ? 'Edit' : 'Create' }} {{ $noun }}</h2>
                <button class="text-gray-400 hover:text-gray-600 text-xs" wire:click="cancelForm">✕ Cancel</button>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @if ($tab === 'rd' || $tab === 'rt')
                    <div>
                        <label class="label text-xs font-semibold text-gray-700">Code</label>
                        <input class="input text-xs font-mono uppercase" wire:model="formCode" @disabled($editingId)>
                        @if ($editingId) <p class="text-[11px] text-gray-400 mt-0.5">Code cannot be modified once created.</p> @endif
                        @error('formCode') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div class="{{ $tab === 'rd' ? 'sm:col-span-2' : '' }}">
                        <label class="label text-xs font-semibold text-gray-700">Entity Name</label>
                        <input class="input text-xs" wire:model="formName">
                        @error('formName') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    @if ($tab === 'rt')
                        <div>
                            <label class="label text-xs font-semibold text-gray-700">Distributor (RD Code)</label>
                            <select class="input text-xs" wire:model="formRdCode">
                                <option value="">— None —</option>
                                @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                            </select>
                            @error('formRdCode') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label text-xs font-semibold text-gray-700">Geographic Area</label>
                            <input class="input text-xs" placeholder="e.g. Kathmandu Valley" wire:model="formArea">
                            @error('formArea') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label text-xs font-semibold text-gray-700">Contact Phone</label>
                            <input class="input text-xs font-mono" placeholder="98XXXXXXXX" wire:model="formPhone">
                        </div>
                        <div class="sm:col-span-3">
                            <label class="label text-xs font-semibold text-gray-700">Street Address</label>
                            <input class="input text-xs" placeholder="Shop #, Street, City" wire:model="formAddress">
                        </div>
                        <div>
                            <label class="label text-xs font-semibold text-gray-700">Latitude</label>
                            <input class="input text-xs font-mono" wire:model="formLat" placeholder="e.g. 27.7172">
                            @error('formLat') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label text-xs font-semibold text-gray-700">Longitude</label>
                            <input class="input text-xs font-mono" wire:model="formLng" placeholder="e.g. 85.3240">
                            @error('formLng') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-3 flex items-end">
                            <x-map-picker :save="'setFormLatLng'" id="rt-form"
                                          :lat="$formLat !== '' ? $formLat : null" :lng="$formLng !== '' ? $formLng : null"
                                          label="📍 Pin Location on Google Map" class="btn-ghost text-xs border border-gray-200" />
                        </div>
                    @endif
                @elseif ($tab === 'model')
                    <div>
                        <label class="label text-xs font-semibold text-gray-700">Model Name</label>
                        <input class="input text-xs" wire:model="formName" @disabled($editingId)>
                        @if ($editingId) <p class="text-[11px] text-gray-400 mt-0.5">Model name is locked.</p> @endif
                        @error('formName') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label text-xs font-semibold text-gray-700">Product Code</label>
                        <input class="input text-xs font-mono" wire:model="formProductCode" placeholder="e.g. RMX3830">
                        @error('formProductCode') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label text-xs font-semibold text-gray-700">Lifecycle Status</label>
                        <select class="input text-xs" wire:model="formStatus">
                            <option value="running">Running Series</option>
                            <option value="out">Out of Production / Phase-out</option>
                        </select>
                    </div>
                @else
                    <div class="sm:col-span-3">
                        <label class="label text-xs font-semibold text-gray-700">TSO Representative Name</label>
                        <input class="input text-xs" wire:model="formName">
                        @error('formName') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            @if ($tab === 'rd' && ! $editingId && $canCreateLogin)
                <div class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-900 cursor-pointer">
                        <input type="checkbox" wire:model.live="createLogin" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Automatically create a portal login for this distributor (scoped exclusively to this RD Code)
                    </label>
                    @if ($createLogin)
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="label text-xs font-medium text-gray-700">Account Name</label>
                                <input class="input text-xs" wire:model="loginName">
                                @error('loginName') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label text-xs font-medium text-gray-700">Login Email</label>
                                <input class="input text-xs" type="email" wire:model="loginEmail">
                                @error('loginEmail') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label text-xs font-medium text-gray-700">Initial Password</label>
                                <input class="input text-xs font-mono" type="password" wire:model="loginPassword">
                                @error('loginPassword') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-5 flex items-center gap-3 border-t border-gray-100 pt-4">
                <button class="btn-primary text-xs" wire:click="saveRow">Save {{ $noun }}</button>
                <button class="btn-ghost text-xs" wire:click="cancelForm">Cancel</button>
            </div>
        </div>
    @endif

    <!-- Search & Filter Controls -->
    <div class="card mt-6 border border-gray-100 shadow-sm">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <input class="input pl-8 text-xs" placeholder="Search by name, code, or area…" wire:model.live.debounce.300ms="search">
                <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            @if ($isRetailers)
                <select class="input w-auto text-xs" wire:model.live="rdFilter">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            @endif

            @if ($isModels)
                <select class="input w-auto text-xs" wire:model.live="modelStatus">
                    <option value="">All Lifecycle ({{ number_format($counts->total) }})</option>
                    <option value="running">Running Series ({{ number_format($counts->running) }})</option>
                    <option value="out">Out of Production ({{ number_format($counts->out) }})</option>
                </select>
                <button class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200" wire:click="reclassify"
                        title="Re-apply the running-series rules from configuration">
                    <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Re-classify
                </button>
            @endif
        </div>
    </div>

    <!-- Data Table -->
    <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/75">
                    <tr>
                        @foreach ($columns as $c)
                            <th class="th">{{ ucwords(str_replace('_', ' ', $c)) }}</th>
                        @endforeach
                        @if ($isModels) <th class="th">Lifecycle</th> @endif
                        @if ($editable) <th class="th text-right">Actions</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($rows as $row)
                    <tr wire:key="md-{{ $row->id }}" class="hover:bg-gray-50/75 transition">
                        @foreach ($columns as $c)
                            <td class="td {{ in_array($c, ['code', 'rd_code', 'phone', 'product_code']) ? 'font-mono text-gray-700' : ($c === 'name' ? 'font-semibold text-gray-900' : 'text-gray-600') }}">
                                {{ $row->$c ?: '—' }}
                            </td>
                        @endforeach
                        @if ($isModels)
                            <td class="td">
                                <button wire:click="toggleModelStatus({{ $row->id }})"
                                        title="Click to toggle lifecycle"
                                        class="badge cursor-pointer transition {{ $row->status === 'running'
                                            ? 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200 hover:bg-emerald-200'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                    {{ $row->status === 'running' ? '● Running' : '○ Out' }}
                                </button>
                            </td>
                        @endif
                        @if ($editable)
                            <td class="td whitespace-nowrap text-right space-x-2">
                                <button class="font-semibold text-indigo-600 hover:text-indigo-800 text-xs" wire:click="editRow({{ $row->id }})">Edit</button>
                                <span class="text-gray-300">·</span>
                                <button class="font-semibold text-rose-600 hover:text-rose-800 text-xs" wire:click="deleteRow({{ $row->id }})"
                                        wire:confirm="Delete this record? Note: if present in future imports, it will reappear.">Delete</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td class="td text-center text-gray-400 py-10" colspan="8">No {{ strtolower($noun) }} entries found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</div>
