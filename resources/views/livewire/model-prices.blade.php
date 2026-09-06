<div>
    <h1 class="text-xl font-semibold tracking-tight">Model Prices</h1>
    <p class="mt-1 text-sm text-gray-500">
        Effective-dated price per model. Value reports price each device by the rate in force on its date,
        so a report spanning a price change blends old and new rates.
    </p>

    <div class="card mt-4">
        <input class="input" placeholder="Search model…" wire:model.live.debounce.300ms="search">
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">Model</th>
                <th class="th text-right">Current price</th>
                <th class="th text-right">Price points</th>
                <th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($models as $m)
                <tr wire:key="mp-{{ $m->id }}">
                    <td class="td">{{ $m->name }}</td>
                    <td class="td text-right font-medium">
                        {{ isset($current[$m->name]) ? $symbol.' '.number_format($current[$m->name], 2) : '—' }}
                    </td>
                    <td class="td text-right text-gray-500">{{ $counts[$m->name] ?? 0 }}</td>
                    <td class="td text-right">
                        <button class="text-indigo-600" wire:click="open('{{ $m->name }}')">
                            {{ $editing === $m->name ? 'Close' : 'Manage' }}
                        </button>
                    </td>
                </tr>
                @if ($editing === $m->name)
                    <tr wire:key="mp-panel-{{ $m->id }}">
                        <td colspan="4" class="bg-gray-50 px-4 py-4">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div>
                                    <h3 class="text-xs font-semibold uppercase text-gray-500">Price history</h3>
                                    <table class="mt-2 w-full text-sm">
                                        <thead><tr class="text-left text-xs text-gray-400">
                                            <th class="py-1">Effective from</th><th>Price</th><th>Note</th><th></th>
                                        </tr></thead>
                                        <tbody>
                                        @forelse ($history as $h)
                                            <tr class="border-t border-gray-100">
                                                <td class="py-1">{{ $h->effective_from->toDateString() }}</td>
                                                <td>{{ $symbol }} {{ number_format($h->price, 2) }}</td>
                                                <td class="text-gray-500">{{ $h->note }}</td>
                                                <td class="text-right">
                                                    <button class="text-xs text-red-500" wire:click="deletePrice({{ $h->id }})"
                                                            wire:confirm="Delete this price point?">delete</button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="py-2 text-gray-400">No prices set yet.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div>
                                    <h3 class="text-xs font-semibold uppercase text-gray-500">Add / change price</h3>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <div>
                                            <label class="label">Effective from</label>
                                            <input type="date" class="input" wire:model="newFrom">
                                            @error('newFrom') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="label">Price ({{ $symbol }})</label>
                                            <input type="number" step="0.01" class="input" wire:model="newPrice">
                                            @error('newPrice') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="label">Note (optional)</label>
                                            <input class="input" wire:model="newNote" placeholder="e.g. Sept price revision">
                                        </div>
                                    </div>
                                    <button class="btn-primary mt-3" wire:click="addPrice">Save price</button>
                                    <p class="mt-1 text-xs text-gray-400">
                                        Setting a date that already exists updates that row.
                                    </p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td class="td text-gray-400" colspan="4">No models.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $models->links() }}</div>
</div>
