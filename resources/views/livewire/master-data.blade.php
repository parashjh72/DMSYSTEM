<div>
    <h1 class="text-xl font-semibold tracking-tight">Master Data</h1>
    <p class="mt-1 text-sm text-gray-500">Lookup tables kept in sync by every import.</p>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => [$label])
            <button wire:click="$set('tab', '{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-300' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="card mt-4">
        <input class="input" placeholder="Search…" wire:model.live.debounce.300ms="search">
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                @foreach ($columns as $c) <th class="th">{{ str_replace('_', ' ', $c) }}</th> @endforeach
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $c) <td class="td">{{ $row->$c }}</td> @endforeach
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="4">Nothing here yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
