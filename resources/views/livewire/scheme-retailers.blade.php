<div>
    <a href="{{ route('schemes.index') }}" wire:navigate class="text-sm text-indigo-600">← Schemes</a>
    <h1 class="mt-1 text-xl font-semibold tracking-tight">{{ $scheme->name }} — retailers</h1>
    <p class="text-xs text-gray-500">Manually enrol retailers and choose each one's plan. Only enrolled retailers count when the achievement report is set to "enrolled only".</p>

    <div class="card mt-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Default plan (for new adds)</label>
                <select class="input" wire:model="defaultPlan">
                    @foreach ($plans as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Default category</label>
                <select class="input" wire:model="defaultCategory">
                    @foreach ($categories as $k => $l) <option value="{{ $k }}">{{ $l }} (min slab {{ $catMinSlab[$k] }})</option> @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div>
                <label class="label">Find a retailer</label>
                <input type="search" class="input" placeholder="Search RT code or name…"
                       wire:model.live.debounce.300ms="search">
                @if ($searchResults)
                    <div class="mt-1 max-h-44 overflow-y-auto rounded-lg ring-1 ring-gray-200">
                        @foreach ($searchResults as $code => $label)
                            <button wire:key="sr-{{ $code }}" wire:click="add('{{ $code }}')"
                                    class="block w-full px-3 py-1.5 text-left text-xs hover:bg-indigo-50">+ {{ $label }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                <label class="label">Bulk add — paste RT codes or names</label>
                <textarea rows="3" class="input font-mono text-xs" wire:model="paste"
                          placeholder="One per line or comma separated"></textarea>
                <button class="btn-primary mt-2 text-xs" wire:click="matchAndAdd">Match &amp; add</button>
                @if ($notMatched)
                    <p class="mt-1 text-xs text-amber-600">Not matched: {{ \Illuminate\Support\Str::limit(implode(', ', $notMatched), 100) }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">RT Code</th><th class="th">RT Name</th>
                <th class="th">Plan</th><th class="th">Category</th>
                <th class="th">Min slab</th><th class="th">Note</th><th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($enrolled as $e)
                <tr wire:key="en-{{ $e->id }}">
                    <td class="td font-mono">{{ $e->rt_code }}</td>
                    <td class="td">{{ $e->rt_name }}</td>
                    <td class="td">
                        <select class="input py-1 text-xs" wire:change="updateRow({{ $e->id }}, 'plan', $event.target.value)">
                            @foreach ($plans as $k => $l) <option value="{{ $k }}" @selected($e->plan === $k)>{{ $l }}</option> @endforeach
                        </select>
                    </td>
                    <td class="td">
                        <select class="input py-1 text-xs" wire:change="updateRow({{ $e->id }}, 'category', $event.target.value)">
                            @foreach ($categories as $k => $l) <option value="{{ $k }}" @selected($e->category === $k)>{{ $l }}</option> @endforeach
                        </select>
                    </td>
                    <td class="td">
                        <input type="number" min="1" class="input w-16 py-1 text-xs"
                               value="{{ $e->min_slab }}"
                               placeholder="{{ $catMinSlab[$e->category] ?? 1 }}"
                               wire:change="updateMinSlab({{ $e->id }}, $event.target.value)">
                    </td>
                    <td class="td">
                        <input class="input py-1 text-xs" value="{{ $e->note }}"
                               wire:change="updateRow({{ $e->id }}, 'note', $event.target.value)">
                    </td>
                    <td class="td"><button class="text-xs text-red-500" wire:click="remove({{ $e->id }})">remove</button></td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="7">No retailers enrolled yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-gray-500">{{ $enrolled->count() }} retailer(s) enrolled.</p>
</div>
