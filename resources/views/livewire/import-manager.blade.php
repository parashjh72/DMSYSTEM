<div>
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Imports</h1>
            <p class="mt-1 text-sm text-gray-500">CSV / XLSX · streamed and processed in background chunks.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('imports.template') }}" class="btn-ghost">Model template</a>
            <a href="{{ route('imports.template', ['kind' => 'sell_through']) }}" class="btn-ghost">Sell-thru template</a>
            <a href="{{ route('imports.template', ['kind' => 'activation']) }}" class="btn-ghost">Activation template</a>
        </div>
    </div>

    @can('imports.create')
    <div class="card mt-6">
        @if (! $review)
            <label class="label">Import type</label>
            <div class="mb-3 flex flex-wrap gap-2">
                <button wire:click="$set('kind', 'records')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition
                        {{ $kind === 'records' ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                    Model data (IMEI, model, TSO, RD, RT, dates…)
                </button>
                <button wire:click="$set('kind', 'sell_through')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition
                        {{ $kind === 'sell_through' ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                    Sell-through (RD → RT)
                </button>
                <button wire:click="$set('kind', 'activation')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition
                        {{ $kind === 'activation' ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                    Activation
                </button>
            </div>
            @if ($kind === 'sell_through')
                <p class="mb-2 rounded bg-indigo-50 px-3 py-2 text-xs text-indigo-800">
                    Assigns retailers to existing IMEIs. File columns: <strong>IMEI, Model, RD Code, RTCode, ST Date</strong>
                    (ST Date = invoice date). <strong>Only IMEI, RTCode and ST Date are applied</strong> — Model and RD Code
                    are informational (the device's model/RD come from the model import). RT name is filled from Master
                    Data. <strong>Only devices that have no RT code, RT name and ST date yet are updated</strong> — a device
                    already assigned to a retailer is left as-is and counted as skipped. IMEIs not already in the system
                    are reported as errors.
                </p>
            @elseif ($kind === 'activation')
                <p class="mb-2 rounded bg-indigo-50 px-3 py-2 text-xs text-indigo-800">
                    Marks existing IMEIs activated. File columns: <strong>IMEI, Activation Date</strong>.
                    <strong>Only devices with no activation date yet are updated</strong> — one that already has an
                    activation date is left as-is and counted as skipped. IMEIs not already in the system are reported
                    as errors.
                </p>
            @endif

            <label class="label">Upload a file</label>
            <input type="file" wire:model="file" accept=".csv,.txt,.xlsx"
                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-indigo-700">
            <div wire:loading wire:target="file" class="mt-2 text-sm text-gray-500">Uploading &amp; sniffing headers…</div>
            <p class="mt-2 text-xs text-gray-400">
                Columns are matched by header name, so order does not matter.
                <a href="{{ route('imports.template') }}" class="text-indigo-600">Download the template</a> for the expected format.
            </p>
            @error('file') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @else
            @php $rk = $review['kind'] ?? 'records'; @endphp
            <h2 class="text-sm font-semibold">
                Review column mapping
                <span class="badge {{ $rk === 'records' ? 'bg-gray-100 text-gray-600' : 'bg-indigo-100 text-indigo-800' }}">
                    {{ ['records' => 'Model data', 'sell_through' => 'Sell-through (RD → RT)', 'activation' => 'Activation'][$rk] }}
                </span>
            </h2>
            <p class="mt-1 text-xs text-gray-500">Detected headers: {{ implode(', ', $review['headers']) }}</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($fields as $field)
                    <div class="flex items-center gap-3">
                        <span class="w-32 text-sm font-medium text-gray-700">{{ $field }}</span>
                        <select class="input"
                                wire:change="setMapping('{{ $field }}', $event.target.value)">
                            <option value="">— not mapped —</option>
                            @foreach ($review['headers'] as $i => $h)
                                <option value="{{ $i }}" @selected(($review['map'][$field] ?? null) === $i)>{{ $h ?: "(column $i)" }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            @if ($review['missing'])
                <p class="mt-3 text-sm text-red-600">Unmapped required field(s): {{ implode(', ', $review['missing']) }}</p>
            @endif

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @if (($review['kind'] ?? 'records') === 'records')
                <div>
                    <label class="label">Import mode</label>
                    <select wire:model="mode" class="input">
                        <option value="upsert">Insert new & update existing</option>
                        <option value="insert_new">Insert new only</option>
                        <option value="skip_existing">Skip existing IMEIs</option>
                        <option value="update_existing">Update existing only</option>
                    </select>
                </div>
                @endif
                <div>
                    <label class="label">Rows per chunk</label>
                    <select wire:model="chunkSize" class="input">
                        <option value="2000">2,000 (shared / low memory)</option>
                        <option value="5000">5,000 (default)</option>
                        <option value="10000">10,000</option>
                        <option value="25000">25,000 (dedicated server)</option>
                        <option value="50000">50,000 (import box)</option>
                    </select>
                </div>
            </div>

            <div class="mt-5 flex gap-3">
                <button class="btn-primary" wire:click="startImport"
                        @disabled(!empty($review['missing']))>Start import</button>
                <button class="btn-ghost" wire:click="cancelReview">Cancel</button>
            </div>
        @endif
    </div>
    @endcan

    <div class="card mt-6 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th">File</th><th class="th">Type</th><th class="th">Status</th><th class="th">Rows</th>
                    <th class="th">New / Upd / Skip</th><th class="th">Invalid / Dup</th>
                    <th class="th">By</th><th class="th">Started</th><th class="th"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($batches as $b)
                <tr wire:key="b-{{ $b->id }}">
                    <td class="td max-w-[220px] truncate">{{ $b->original_filename }}</td>
                    <td class="td"><span class="badge {{ $b->kind === 'records' ? 'bg-gray-100 text-gray-600' : 'bg-indigo-100 text-indigo-800' }}">{{ ['records' => 'Model', 'sell_through' => 'Sell-thru', 'activation' => 'Activation'][$b->kind] ?? $b->kind }}</span></td>
                    <td class="td">
                        <span class="badge {{ match($b->status->value) {
                            'completed' => 'bg-green-100 text-green-800',
                            'completed_with_errors' => 'bg-amber-100 text-amber-800',
                            'failed' => 'bg-red-100 text-red-800',
                            'processing','queued' => 'bg-blue-100 text-blue-800',
                            default => 'bg-gray-100 text-gray-700',
                        } }}">{{ $b->status->label() }}</span>
                    </td>
                    <td class="td">{{ number_format($b->total_rows) }}</td>
                    <td class="td">{{ number_format($b->inserted_rows) }} / {{ number_format($b->updated_rows) }} / {{ number_format($b->skipped_rows) }}</td>
                    <td class="td">{{ number_format($b->invalid_rows) }} / {{ number_format($b->duplicate_rows) }}</td>
                    <td class="td">{{ $b->creator?->name ?? '—' }}</td>
                    <td class="td text-gray-400">{{ $b->started_at?->diffForHumans() ?? '—' }}</td>
                    <td class="td"><a class="text-indigo-600" href="{{ route('imports.show', $b->uuid) }}" wire:navigate>Details</a></td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="9">No imports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $batches->links() }}</div>
</div>
