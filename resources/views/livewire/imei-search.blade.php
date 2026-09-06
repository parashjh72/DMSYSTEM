<div>
    <h1 class="text-xl font-semibold tracking-tight">IMEI Search</h1>
    <p class="mt-1 text-sm text-gray-500">Exact match on the unique IMEI index.</p>

    <div class="card mt-6">
        <label class="label">IMEI</label>
        <input class="input font-mono" wire:model.live.debounce.400ms="q" placeholder="863222207290410" autofocus>
    </div>

    @if ($searched && ! $record)
        <div class="card mt-4 text-sm text-gray-500">No record found for that IMEI.</div>
    @elseif ($record)
        <div class="card mt-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'IMEI' => $record->imei, 'Model' => $record->model, 'TSO' => $record->tso,
                    'RD' => $record->rd_code.' — '.$record->rd_name,
                    'RT' => $record->rt_code.' — '.$record->rt_name,
                    'Source' => $record->source,
                    'ST date' => $record->st_date?->toDateString(),
                    'Activation date' => $record->activation_date?->toDateString() ?? 'Not activated',
                    'Sell-In date' => $record->sell_in_date?->toDateString() ?? '—',
                    'Activation lag' => $record->activation_days !== null ? $record->activation_days.' days' : '—',
                ] as $label => $value)
                    <div>
                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</div>
                        <div class="mt-0.5 text-sm font-medium">{{ $value ?: '—' }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                First imported by batch
                <a class="text-indigo-600" href="{{ $record->first_import_batch_id ? route('imports.show', $record->firstImportBatch->uuid ?? '') : '#' }}">#{{ $record->first_import_batch_id ?? '—' }}</a>,
                last updated by batch
                <a class="text-indigo-600" href="{{ $record->last_import_batch_id ? route('imports.show', $record->lastImportBatch->uuid ?? '') : '#' }}">#{{ $record->last_import_batch_id ?? '—' }}</a>
                on {{ $record->updated_at?->format('Y-m-d H:i') }}.
            </div>
        </div>
    @endif
</div>
