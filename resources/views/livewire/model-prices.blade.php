<div>
    <!-- Page Header -->
    <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-bold tracking-tight text-gray-900">Model Price Matrix</h1>
            <p class="text-xs text-gray-500">Effective-dated price books. Financial valuation uses the exact rate active on each device's event date.</p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card mt-6 border border-gray-100 shadow-sm">
        <div class="relative max-w-md">
            <input class="input pl-8 text-xs" placeholder="Search device models…" wire:model.live.debounce.300ms="search">
            <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    <!-- Model Prices Table -->
    <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/75">
                    <tr>
                        <th class="th">Device Model</th>
                        <th class="th text-right">Current Active Price</th>
                        <th class="th text-center">Historical Points</th>
                        <th class="th text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($models as $m)
                    <tr wire:key="mp-{{ $m->id }}" class="hover:bg-gray-50/75 transition {{ $editing === $m->name ? 'bg-indigo-50/20' : '' }}">
                        <td class="td font-bold text-gray-900">{{ $m->name }}</td>
                        <td class="td text-right font-mono font-bold text-emerald-700">
                            {{ isset($current[$m->name]) ? $symbol.' '.number_format($current[$m->name], 2) : '—' }}
                        </td>
                        <td class="td text-center font-mono text-gray-500">{{ $counts[$m->name] ?? 0 }} rates</td>
                        <td class="td text-right">
                            <button class="inline-flex items-center gap-1 font-semibold text-xs {{ $editing === $m->name ? 'text-gray-600 hover:text-gray-800' : 'text-indigo-600 hover:text-indigo-800' }}"
                                    wire:click="open('{{ $m->name }}')">
                                {{ $editing === $m->name ? 'Hide History ▲' : 'Manage Rates ▼' }}
                            </button>
                        </td>
                    </tr>
                    @if ($editing === $m->name)
                        <tr wire:key="mp-panel-{{ $m->id }}" class="bg-gray-50/80">
                            <td colspan="4" class="px-6 py-5">
                                <div class="grid gap-6 lg:grid-cols-2">
                                    <!-- Price History Table -->
                                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 pb-2 border-b border-gray-100 flex items-center justify-between">
                                            <span>Price History Log</span>
                                            <span class="text-[10px] text-gray-400 font-normal font-sans">{{ $m->name }}</span>
                                        </h3>
                                        <div class="mt-2 overflow-x-auto">
                                            <table class="w-full text-xs">
                                                <thead>
                                                    <tr class="text-left text-[11px] font-semibold text-gray-400 border-b border-gray-100">
                                                        <th class="py-2">Effective Date</th>
                                                        <th class="text-right">Unit Price</th>
                                                        <th class="pl-4">Revision Note</th>
                                                        <th class="text-right"></th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-50">
                                                @forelse ($history as $h)
                                                    <tr class="hover:bg-gray-50/50">
                                                        <td class="py-2 font-mono text-gray-700">{{ $h->effective_from->toDateString() }}</td>
                                                        <td class="text-right font-mono font-bold text-gray-900">{{ $symbol }} {{ number_format($h->price, 2) }}</td>
                                                        <td class="pl-4 text-gray-500 truncate max-w-xs">{{ $h->note ?: '—' }}</td>
                                                        <td class="text-right">
                                                            <button class="text-[11px] font-semibold text-rose-600 hover:text-rose-800" wire:click="deletePrice({{ $h->id }})"
                                                                    wire:confirm="Delete this price record?">Delete</button>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="4" class="py-4 text-center text-gray-400">No prices set yet.</td></tr>
                                                @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Add / Change Price Form -->
                                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 pb-2 border-b border-gray-100">
                                            Set / Revise Rate
                                        </h3>
                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="label text-xs font-semibold text-gray-700">Effective Date</label>
                                                <input type="date" class="input text-xs" wire:model="newFrom">
                                                @error('newFrom') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="label text-xs font-semibold text-gray-700">Price ({{ $symbol }})</label>
                                                <input type="number" step="0.01" class="input text-xs font-mono" placeholder="0.00" wire:model="newPrice">
                                                @error('newPrice') <p class="text-[11px] text-rose-600 mt-0.5">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="label text-xs font-semibold text-gray-700">Reason / Note (Optional)</label>
                                                <input class="input text-xs" placeholder="e.g. Festival price cut, MRP update" wire:model="newNote">
                                            </div>
                                        </div>
                                        <div class="mt-4 flex items-center justify-between">
                                            <p class="text-[11px] text-gray-400">Overwrites existing row if date matches.</p>
                                            <button class="btn-primary text-xs" wire:click="addPrice">Save Price</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td class="td text-center text-gray-400 py-10" colspan="4">No device models registered in master data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $models->links() }}</div>
</div>
