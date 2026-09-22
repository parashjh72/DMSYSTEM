<div @if ($polling) wire:poll.3s @endif>
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('imports.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Imports
            </a>
            <div class="mt-2 flex items-center gap-3">
                <h1 class="text-xl font-bold tracking-tight text-gray-900 truncate max-w-xl">{{ $batch->original_filename }}</h1>
                <span class="badge {{ match($batch->status->value) {
                    'completed' => 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200',
                    'completed_with_errors' => 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200',
                    'failed' => 'bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-200',
                    'processing','queued' => 'bg-indigo-100 text-indigo-800 ring-1 ring-inset ring-indigo-200 animate-pulse',
                    default => 'bg-gray-100 text-gray-700',
                } }}">{{ $batch->status->label() }}</span>
            </div>
            <p class="text-xs text-gray-500 font-mono mt-1">Batch {{ $batch->uuid }} · {{ number_format($batch->file_size / 1048576, 2) }} MB · {{ strtoupper($batch->file_type) }}</p>
        </div>
        @can('imports.create')
            @if (in_array($batch->status->value, ['failed', 'completed_with_errors']))
                <button class="btn-primary text-xs flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700" wire:click="retry">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Retry Failed Chunks
                </button>
            @endif
        @endcan
    </div>

    @php $pct = $batch->progressPercent(); @endphp
    <!-- Ingestion Progress Bar Card -->
    <div class="card mt-6 border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between text-xs">
            <span class="font-semibold text-gray-700 flex items-center gap-2">
                @if ($polling)
                    <span class="flex h-2 w-2 rounded-full bg-indigo-600 animate-ping"></span>
                @endif
                Processing Stream
            </span>
            <span class="font-mono font-medium text-gray-900">{{ number_format($batch->processed_rows) }} / {{ number_format($batch->total_rows) }} rows ({{ $pct }}%)</span>
        </div>
        <div class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 transition-all duration-500" style="width:{{ $pct }}%"></div>
        </div>
        <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
            <span>{{ $batch->completed_chunks }} / {{ $batch->total_chunks }} chunks finished</span>
            <span>@if ($batch->duration_seconds) Completed in {{ $batch->duration_seconds }}s @endif</span>
        </div>
        @if ($batch->error_message)
            <div class="mt-3 rounded-xl bg-rose-50 p-3 border border-rose-100 text-xs text-rose-800">
                <strong>Execution Error:</strong> {{ $batch->error_message }}
            </div>
        @endif
    </div>

    <!-- 7 Metric Stat Cards -->
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
        @foreach ([
            ['Total', $batch->total_rows, 'text-gray-900', 'border-gray-200'],
            ['Valid', $batch->valid_rows, 'text-emerald-700', 'border-emerald-200'],
            ['Inserted', $batch->inserted_rows, 'text-indigo-700', 'border-indigo-200'],
            ['Updated', $batch->updated_rows, 'text-blue-700', 'border-blue-200'],
            ['Skipped', $batch->skipped_rows, 'text-slate-500', 'border-slate-200'],
            ['Invalid', $batch->invalid_rows, $batch->invalid_rows > 0 ? 'text-rose-600' : 'text-gray-400', $batch->invalid_rows > 0 ? 'border-rose-300' : 'border-gray-200'],
            ['In-file Dup', $batch->duplicate_rows, $batch->duplicate_rows > 0 ? 'text-amber-600' : 'text-gray-400', $batch->duplicate_rows > 0 ? 'border-amber-300' : 'border-gray-200'],
        ] as [$l, $v, $color, $border])
            <div class="card p-3 border {{ $border }} bg-white shadow-2xs">
                <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $l }}</div>
                <div class="mt-1 text-lg font-bold {{ $color }} font-mono">{{ number_format($v) }}</div>
            </div>
        @endforeach
    </div>

    <!-- Config & Error Cloud Grid -->
    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="card border border-gray-100 shadow-xs">
            <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 pb-2 border-b border-gray-100">Batch Configuration</h2>
            <dl class="mt-3 space-y-2 text-xs">
                <div class="flex justify-between py-0.5"><dt class="text-gray-500">Pipeline Type</dt><dd class="font-medium text-gray-900">{{ $batch->kindLabel() }}</dd></div>
                <div class="flex justify-between py-0.5"><dt class="text-gray-500">Import Mode</dt><dd class="font-medium text-gray-900">{{ $batch->import_mode->label() }}</dd></div>
                <div class="flex justify-between py-0.5"><dt class="text-gray-500">Chunk Size</dt><dd class="font-mono text-gray-900">{{ number_format($batch->chunk_size) }} rows</dd></div>
                <div class="flex justify-between py-0.5"><dt class="text-gray-500">Submitted By</dt><dd class="font-medium text-gray-900">{{ $batch->creator?->name ?? '—' }}</dd></div>
                <div class="flex justify-between py-0.5"><dt class="text-gray-500">Started</dt><dd class="text-gray-600">{{ $batch->started_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div class="flex justify-between py-0.5"><dt class="text-gray-500">Completed</dt><dd class="text-gray-600">{{ $batch->completed_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="card lg:col-span-2 border border-gray-100 shadow-xs">
            <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 pb-2 border-b border-gray-100">Error Breakdown</h2>
            @if ($errorCounts->isEmpty())
                <div class="flex flex-col items-center justify-center py-6 text-center text-xs text-gray-400">
                    <svg class="h-8 w-8 text-emerald-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Clean Ingestion: 0 row errors recorded in this batch.
                </div>
            @else
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($errorCounts as $type => $count)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-900 border border-amber-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            {{ $type }}: <strong class="font-mono">{{ number_format($count) }}</strong>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Data Tabs -->
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3">
        <div class="flex gap-2">
            <button wire:click="$set('tab', 'rows')"
                    class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition {{ $tab === 'rows' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                Batch Records
            </button>
            <button wire:click="$set('tab', 'errors')"
                    class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition {{ $tab === 'errors' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                Row Errors ({{ number_format($errorTotal) }})
            </button>
        </div>
        @can('exports.create')
            @if ($tab === 'rows')
                <button class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200" wire:click="exportRows">
                    <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Records (CSV)
                </button>
            @elseif (! $errorCounts->isEmpty())
                <button class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200 text-rose-600 hover:text-rose-700" wire:click="exportErrors">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Errors (CSV)
                </button>
            @endif
        @endcan
    </div>

    @if ($tab === 'rows')
        <div class="card mt-3 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50/75">
                        <tr>
                            <th class="th">IMEI</th>
                            <th class="th">Model</th>
                            <th class="th">TSO</th>
                            <th class="th">RD</th>
                            <th class="th">RT</th>
                            <th class="th">ST Date</th>
                            <th class="th">Activation</th>
                            <th class="th">Sell-In</th>
                            <th class="th">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($rows as $r)
                        <tr wire:key="row-{{ $r->id }}" class="hover:bg-gray-50/75 transition">
                            <td class="td font-mono font-medium text-gray-900">{{ $r->imei }}</td>
                            <td class="td font-medium text-gray-800">{{ $r->model }}</td>
                            <td class="td text-gray-600">{{ $r->tso }}</td>
                            <td class="td text-gray-600">{{ $r->rd_code }}</td>
                            <td class="td text-gray-600">{{ $r->rt_code ?: '—' }}</td>
                            <td class="td text-gray-500">{{ $r->st_date ?? '—' }}</td>
                            <td class="td text-gray-500">{{ $r->activation_date ?? '—' }}</td>
                            <td class="td text-gray-500">{{ $r->sell_in_date ?? '—' }}</td>
                            <td class="td"><span class="badge bg-slate-100 text-slate-700">{{ $r->source }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="td text-center text-gray-400 py-8" colspan="9">
                                No records currently attributed to this batch
                                @if ($batch->kind !== 'sell_through') (a subsequent import may have overwritten them). @endif
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($rows) <div class="mt-3">{{ $rows->links() }}</div> @endif
        <p class="mt-2 text-[11px] text-gray-400">Records where this batch is the latest attribution checkpoint.</p>
    @else
        <div class="card mt-3 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50/75">
                        <tr>
                            <th class="th">Row</th>
                            <th class="th">Chunk</th>
                            <th class="th">Type</th>
                            <th class="th">Message</th>
                            <th class="th">Payload</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($errors as $e)
                        <tr wire:key="err-{{ $e->id }}" class="hover:bg-gray-50/75 transition">
                            <td class="td font-mono font-medium text-gray-900">#{{ $e->row_number }}</td>
                            <td class="td font-mono text-gray-500">{{ $e->chunk_number }}</td>
                            <td class="td"><span class="badge bg-rose-50 text-rose-700 border border-rose-200">{{ $e->error_type }}</span></td>
                            <td class="td whitespace-normal text-rose-800 font-medium">{{ $e->error_message }}</td>
                            <td class="td max-w-xs truncate font-mono text-[11px] text-gray-400">{{ is_array($e->row_payload) ? json_encode($e->row_payload) : $e->row_payload }}</td>
                        </tr>
                    @empty
                        <tr><td class="td text-center text-gray-400 py-8" colspan="5">No errors recorded in this batch.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($errors) <div class="mt-3">{{ $errors->links() }}</div> @endif
    @endif
</div>
