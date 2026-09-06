<div>
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Master Data</h1>
            <p class="mt-1 text-sm text-gray-500">Lookup tables kept in sync by every import.</p>
        </div>
        <div class="flex gap-2">
            <button class="btn-ghost" wire:click="export('xlsx')">Export → Excel</button>
            <button class="btn-ghost" wire:click="export('csv')">CSV</button>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => [$label])
            <button wire:click="$set('tab', '{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-300' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="card mt-4">
        <div class="flex flex-wrap items-center gap-3">
            <input class="input flex-1 min-w-[200px]" placeholder="Search…" wire:model.live.debounce.300ms="search">

            @if ($isRetailers)
                <select class="input w-auto" wire:model.live="rdFilter">
                    <option value="">All distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            @endif

            @if ($isModels)
                <select class="input w-auto" wire:model.live="modelStatus">
                    <option value="">All ({{ number_format($counts->total) }})</option>
                    <option value="running">Running ({{ number_format($counts->running) }})</option>
                    <option value="out">Out ({{ number_format($counts->out) }})</option>
                </select>
                <button class="btn-ghost" wire:click="reclassify"
                        title="Re-apply the running-series rules from config/models.php">
                    Re-classify from list
                </button>
            @endif
        </div>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                @foreach ($columns as $c) <th class="th">{{ str_replace('_', ' ', $c) }}</th> @endforeach
                @if ($isModels) <th class="th">Type</th> @endif
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $row)
                <tr wire:key="md-{{ $row->id }}">
                    @foreach ($columns as $c) <td class="td">{{ $row->$c }}</td> @endforeach
                    @if ($isModels)
                        <td class="td">
                            <button wire:click="toggleModelStatus({{ $row->id }})"
                                    title="Click to toggle running / out"
                                    class="badge {{ $row->status === 'running'
                                        ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                {{ $row->status === 'running' ? 'Running' : 'Out' }}
                            </button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="5">Nothing here yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
