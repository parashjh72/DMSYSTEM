<div>
    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Imports & Data Ingestion</h1>
                <p class="text-xs text-gray-500">Streamed processing for CSV / XLSX datasets in background chunks.</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @unless ($sellThroughOnly)
                <a href="{{ route('imports.template') }}" class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200">
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    ND → RD Template
                </a>
            @endunless
            <a href="{{ route('imports.template', ['kind' => 'sell_through']) }}" class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200">
                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Sell-thru Template
            </a>
            @unless ($sellThroughOnly)
                <a href="{{ route('imports.template', ['kind' => 'activation']) }}" class="btn-ghost text-xs flex items-center gap-1.5 border border-gray-200">
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Activation Template
                </a>
            @endunless
        </div>
    </div>

    @if (auth()->user()?->can('imports.create') || $sellThroughOnly)
    <div class="card mt-6 border border-gray-100 shadow-sm transition hover:shadow-md">
        @if (! $review)
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                    <span class="flex h-2 w-2 rounded-full bg-indigo-600"></span>
                    Upload Dataset
                </h2>
                @if ($sellThroughOnly)
                    <span class="badge bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200">Scoped Distributor Import</span>
                @endif
            </div>

            @if ($sellThroughOnly)
                <div class="mt-4 rounded-xl bg-indigo-50/70 p-3.5 border border-indigo-100 text-xs text-indigo-900 leading-relaxed">
                    <strong>Distributor Scope:</strong> Only devices assigned to <strong>your distributor code(s)</strong> will be updated. IMEIs from other distributors will be safely flagged as errors.
                </div>
            @else
                <div class="mt-4">
                    <label class="label mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Select Ingestion Pipeline</label>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                        <button type="button" wire:click="$set('kind', 'records')"
                                class="flex items-center gap-3 rounded-xl border p-3.5 text-left transition {{ $kind === 'records' ? 'border-indigo-600 bg-indigo-50/40 text-indigo-900 shadow-sm ring-1 ring-indigo-600' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50' }}">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $kind === 'records' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500' }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold">ND → RD Inflow</div>
                                <div class="text-xs text-gray-500">Primary distributor stock</div>
                            </div>
                        </button>
                        <button type="button" wire:click="$set('kind', 'sell_through')"
                                class="flex items-center gap-3 rounded-xl border p-3.5 text-left transition {{ $kind === 'sell_through' ? 'border-indigo-600 bg-indigo-50/40 text-indigo-900 shadow-sm ring-1 ring-indigo-600' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50' }}">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $kind === 'sell_through' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500' }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold">Sell-thru (RD → RT)</div>
                                <div class="text-xs text-gray-500">Assign retailer invoices</div>
                            </div>
                        </button>
                        <button type="button" wire:click="$set('kind', 'activation')"
                                class="flex items-center gap-3 rounded-xl border p-3.5 text-left transition {{ $kind === 'activation' ? 'border-indigo-600 bg-indigo-50/40 text-indigo-900 shadow-sm ring-1 ring-indigo-600' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50' }}">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $kind === 'activation' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500' }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold">Activation</div>
                                <div class="text-xs text-gray-500">Mark consumer sellout</div>
                            </div>
                        </button>
                    </div>
                </div>
            @endif

            @if ($kind === 'sell_through')
                <div class="mt-3 rounded-xl bg-blue-50/60 p-3.5 border border-blue-100 text-xs text-blue-900 leading-relaxed">
                    <strong>Rule:</strong> File requires columns <code>IMEI</code>, <code>RD Code</code>, <code>RTCode</code>, <code>ST Date</code>. Only devices without an existing retailer assignment will be updated. Existing retailer assignments are safely preserved and reported as skipped.
                </div>
            @elseif ($kind === 'activation')
                <div class="mt-3 rounded-xl bg-blue-50/60 p-3.5 border border-blue-100 text-xs text-blue-900 leading-relaxed">
                    <strong>Rule:</strong> File requires columns <code>IMEI</code> and <code>Activation Date</code>. Only devices without an activation date will be activated. Already-activated devices are skipped.
                </div>
            @endif

            <div class="mt-5">
                <label class="label text-xs font-semibold text-gray-700">Choose File (.csv, .xlsx, .txt)</label>
                <div class="mt-1 flex justify-center rounded-2xl border-2 border-dashed border-gray-300 px-6 pt-5 pb-6 transition hover:border-indigo-400 bg-gray-50/50">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-10 w-10 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600 justify-center">
                            <label class="relative cursor-pointer rounded-md font-semibold text-indigo-600 focus-within:outline-none hover:text-indigo-500">
                                <span>Upload a file</span>
                                <input type="file" wire:model="file" accept=".csv,.txt,.xlsx" class="sr-only">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">CSV, XLSX, or plain text up to 100MB</p>
                    </div>
                </div>

                <div wire:loading wire:target="file" class="mt-3 flex items-center gap-2 text-xs font-medium text-indigo-600">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Uploading file & auto-sniffing column headers…
                </div>

                @error('file') <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
            </div>
        @else
            @php $rk = $review['kind'] ?? 'records'; @endphp
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">Review & Map Columns</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Matched file headers to system attributes.</p>
                </div>
                <span class="badge bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-200">
                    {{ ['records' => 'ND → RD', 'sell_through' => 'Sell-through (RD → RT)', 'activation' => 'Activation'][$rk] }}
                </span>
            </div>

            <div class="mt-4 rounded-lg bg-gray-50 p-2.5 text-xs text-gray-600">
                <span class="font-medium text-gray-900">Detected Headers:</span> {{ implode(', ', $review['headers']) }}
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach ($fields as $field)
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-3 shadow-xs">
                        <span class="text-xs font-semibold text-gray-800">{{ $field }}</span>
                        <select class="input w-48 py-1 text-xs"
                                wire:change="setMapping('{{ $field }}', $event.target.value)">
                            <option value="">— unmapped —</option>
                            @foreach ($review['headers'] as $i => $h)
                                <option value="{{ $i }}" @selected(($review['map'][$field] ?? null) === $i)>{{ $h ?: "(col $i)" }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            @if ($review['missing'])
                <div class="mt-4 rounded-xl bg-rose-50 p-3 border border-rose-100 text-xs font-medium text-rose-800">
                    Missing required field mapping(s): {{ implode(', ', $review['missing']) }}
                </div>
            @endif

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                @if (($review['kind'] ?? 'records') === 'records')
                <div>
                    <label class="label text-xs font-medium text-gray-700">Import Mode</label>
                    <select wire:model="mode" class="input text-xs">
                        <option value="upsert">Insert new & update existing</option>
                        <option value="insert_new">Insert new only</option>
                        <option value="skip_existing">Skip existing IMEIs</option>
                        <option value="update_existing">Update existing only</option>
                    </select>
                </div>
                @endif
                <div>
                    <label class="label text-xs font-medium text-gray-700">Batch Chunk Size</label>
                    <select wire:model="chunkSize" class="input text-xs">
                        <option value="2000">2,000 (Low memory / shared)</option>
                        <option value="5000">5,000 (Default recommended)</option>
                        <option value="10000">10,000 (Fast)</option>
                        <option value="25000">25,000 (Dedicated instance)</option>
                        <option value="50000">50,000 (Bulk turbo)</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4">
                <button class="btn-primary text-xs" wire:click="startImport" @disabled(!empty($review['missing']))>
                    Launch Background Import
                </button>
                <button class="btn-ghost text-xs" wire:click="cancelReview">Cancel & Upload Another</button>
            </div>
        @endif
    </div>
    @endif

    <!-- Import Batch History Table -->
    <div class="card mt-6 overflow-hidden p-0 border border-gray-200/80 shadow-xs">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5 bg-gray-50/50">
            <h2 class="text-sm font-bold text-gray-900">Ingestion Logs & Batch Runs</h2>
            <span class="text-xs text-gray-400 font-mono">Streamed Records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/75">
                    <tr>
                        <th class="th">File / Batch</th>
                        <th class="th">Type</th>
                        <th class="th">Status</th>
                        <th class="th text-right">Total Rows</th>
                        <th class="th">New / Upd / Skip</th>
                        <th class="th">Invalid / Dup</th>
                        <th class="th">Imported By</th>
                        <th class="th">Timestamp</th>
                        <th class="th text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($batches as $b)
                    <tr wire:key="b-{{ $b->id }}" class="hover:bg-gray-50/75 transition">
                        <td class="td max-w-[220px]">
                            <div class="font-semibold text-gray-900 truncate" title="{{ $b->original_filename }}">{{ $b->original_filename }}</div>
                            <div class="text-[10px] text-gray-400 font-mono">{{ substr($b->uuid, 0, 8) }}…</div>
                        </td>
                        <td class="td">
                            <span class="badge {{ $b->kind === 'records' ? 'bg-slate-100 text-slate-700' : ($b->kind === 'sell_through' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800') }}">
                                {{ ['records' => 'ND-RD', 'sell_through' => 'Sell-thru', 'activation' => 'Activation'][$b->kind] ?? $b->kind }}
                            </span>
                        </td>
                        <td class="td">
                            <span class="badge {{ match($b->status->value) {
                                'completed' => 'bg-emerald-100 text-emerald-800 ring-1 ring-inset ring-emerald-200',
                                'completed_with_errors' => 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200',
                                'failed' => 'bg-rose-100 text-rose-800 ring-1 ring-inset ring-rose-200',
                                'processing','queued' => 'bg-indigo-100 text-indigo-800 ring-1 ring-inset ring-indigo-200 animate-pulse',
                                default => 'bg-gray-100 text-gray-700',
                            } }}">
                                {{ $b->status->label() }}
                            </span>
                        </td>
                        <td class="td text-right font-semibold text-gray-900">{{ number_format($b->total_rows) }}</td>
                        <td class="td font-mono text-gray-600">
                            <span class="text-emerald-700 font-semibold">{{ number_format($b->inserted_rows) }}</span> /
                            <span class="text-blue-700">{{ number_format($b->updated_rows) }}</span> /
                            <span class="text-gray-400">{{ number_format($b->skipped_rows) }}</span>
                        </td>
                        <td class="td font-mono">
                            <span class="{{ $b->invalid_rows > 0 ? 'text-rose-600 font-semibold' : 'text-gray-400' }}">{{ number_format($b->invalid_rows) }}</span> /
                            <span class="{{ $b->duplicate_rows > 0 ? 'text-amber-600' : 'text-gray-400' }}">{{ number_format($b->duplicate_rows) }}</span>
                        </td>
                        <td class="td text-gray-600">{{ $b->creator?->name ?? '—' }}</td>
                        <td class="td text-gray-400">{{ $b->started_at?->diffForHumans() ?? '—' }}</td>
                        <td class="td text-right">
                            <a class="inline-flex items-center gap-1 font-semibold text-indigo-600 hover:text-indigo-800" href="{{ route('imports.show', $b->uuid) }}" wire:navigate>
                                Details
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="td text-center text-gray-400 py-10" colspan="9">
                            <svg class="mx-auto h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <p class="mt-2 text-xs">No import batches recorded yet.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $batches->links() }}</div>
</div>
