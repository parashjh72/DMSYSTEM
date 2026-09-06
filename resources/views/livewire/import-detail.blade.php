<div @if ($polling) wire:poll.3s @endif>
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('imports.index') }}" wire:navigate class="text-sm text-indigo-600">← Imports</a>
            <h1 class="mt-1 text-xl font-semibold tracking-tight">{{ $batch->original_filename }}</h1>
            <p class="text-xs text-gray-500">Batch {{ $batch->uuid }} · {{ number_format($batch->file_size / 1048576, 2) }} MB · {{ strtoupper($batch->file_type) }}</p>
        </div>
        @can('imports.create')
            @if (in_array($batch->status->value, ['failed', 'completed_with_errors']))
                <button class="btn-ghost" wire:click="retry">Retry failed chunks</button>
            @endif
        @endcan
    </div>

    @php $pct = $batch->progressPercent(); @endphp
    <div class="card mt-6">
        <div class="flex justify-between text-sm">
            <span class="font-medium">{{ $batch->status->label() }}</span>
            <span class="text-gray-500">{{ number_format($batch->processed_rows) }} / {{ number_format($batch->total_rows) }} ({{ $pct }}%)</span>
        </div>
        <div class="mt-2 h-2.5 rounded-full bg-gray-100">
            <div class="h-2.5 rounded-full bg-indigo-600 transition-all" style="width:{{ $pct }}%"></div>
        </div>
        <div class="mt-1 text-xs text-gray-400">
            {{ $batch->completed_chunks }} / {{ $batch->total_chunks }} chunks
            @if ($batch->duration_seconds) · {{ $batch->duration_seconds }}s @endif
        </div>
        @if ($batch->error_message)
            <p class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ $batch->error_message }}</p>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        @foreach ([
            ['Total', $batch->total_rows], ['Valid', $batch->valid_rows], ['Inserted', $batch->inserted_rows],
            ['Updated', $batch->updated_rows], ['Skipped', $batch->skipped_rows],
            ['Invalid', $batch->invalid_rows], ['In-file dup', $batch->duplicate_rows],
        ] as [$l, $v])
            <div class="card p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $l }}</div>
                <div class="mt-1 text-lg font-semibold">{{ number_format($v) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="card">
            <h2 class="text-sm font-semibold">Config</h2>
            <dl class="mt-2 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Mode</dt><dd>{{ $batch->import_mode->label() }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Chunk size</dt><dd>{{ number_format($batch->chunk_size) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Imported by</dt><dd>{{ $batch->creator?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Started</dt><dd>{{ $batch->started_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Completed</dt><dd>{{ $batch->completed_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="card lg:col-span-2">
            <h2 class="text-sm font-semibold">Errors by type</h2>
            @if ($errorCounts->isEmpty())
                <p class="mt-2 text-sm text-gray-400">No row errors recorded.</p>
            @else
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($errorCounts as $type => $count)
                        <span class="badge bg-amber-100 text-amber-800">{{ $type }}: {{ number_format($count) }}</span>
                    @endforeach
                </div>
                <div class="mt-3 max-h-72 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr><th class="th">Row</th><th class="th">Type</th><th class="th">Message</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                        @foreach ($errorSample as $e)
                            <tr><td class="td">{{ $e->row_number }}</td><td class="td">{{ $e->error_type }}</td><td class="td whitespace-normal">{{ $e->error_message }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-400">Showing latest 50. Use Exports for the full error list.</p>
            @endif
        </div>
    </div>
</div>
