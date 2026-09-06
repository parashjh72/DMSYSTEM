<div @if ($polling) wire:poll.3s @endif>
    <h1 class="text-xl font-semibold tracking-tight">Exports</h1>
    <p class="mt-1 text-sm text-gray-500">Queued CSV jobs. Start one from Data Explorer or Reports.</p>

    <div class="card mt-6 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">Type</th><th class="th">Status</th><th class="th">Rows</th>
                <th class="th">Size</th><th class="th">By</th><th class="th">Created</th><th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($exports as $e)
                <tr wire:key="e-{{ $e->id }}">
                    <td class="td">{{ str_replace('_', ' ', $e->type) }}</td>
                    <td class="td">
                        <span class="badge {{ match($e->status) {
                            'completed' => 'bg-green-100 text-green-800',
                            'failed' => 'bg-red-100 text-red-800',
                            'expired' => 'bg-gray-100 text-gray-600',
                            default => 'bg-blue-100 text-blue-800',
                        } }}">{{ $e->status }}</span>
                        @if ($e->status === 'processing')
                            <span class="text-xs text-gray-400">{{ number_format($e->processed_rows) }} rows</span>
                        @endif
                    </td>
                    <td class="td">{{ $e->total_rows !== null ? number_format($e->total_rows) : '—' }}</td>
                    <td class="td">{{ $e->file_size ? number_format($e->file_size / 1048576, 2).' MB' : '—' }}</td>
                    <td class="td">{{ $e->creator?->name ?? '—' }}</td>
                    <td class="td text-gray-400">{{ $e->created_at->diffForHumans() }}</td>
                    <td class="td">
                        @if ($e->status === 'completed')
                            <button class="text-indigo-600" wire:click="download('{{ $e->uuid }}')">Download</button>
                        @elseif ($e->status === 'failed')
                            <span class="text-xs text-red-500" title="{{ $e->error_message }}">error</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="7">No exports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $exports->links() }}</div>
</div>
