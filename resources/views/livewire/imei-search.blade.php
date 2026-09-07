<div>
    <h1 class="text-xl font-semibold tracking-tight">IMEI Search</h1>
    <p class="mt-1 text-sm text-gray-500">
        Paste one or many IMEI numbers — one per line (straight from an Excel column), or space / comma separated.
        Exact match on the unique IMEI index.
    </p>

    <div class="card mt-6">
        <label class="label" for="imeis">IMEI numbers</label>
        <textarea id="imeis" rows="5" wire:model.blur="imeis"
                  class="input font-mono text-xs leading-5"
                  placeholder="860821081439232&#10;860821081439737&#10;860821081439794"></textarea>
        <div class="mt-3 flex items-center gap-3">
            <button class="btn-primary" wire:click="$refresh">Search</button>
            <button class="btn-ghost" wire:click="clear">Clear</button>
            @if ($searched)
                <span class="text-sm text-gray-500">
                    {{ number_format($totalWanted) }} IMEI{{ $totalWanted === 1 ? '' : 's' }} ·
                    <span class="font-medium text-emerald-600">{{ number_format($foundCount) }} found</span> ·
                    <span class="font-medium text-amber-600">{{ number_format(count($unmatched)) }} not found</span>
                </span>
            @endif
        </div>
        @if ($capped)
            <p class="mt-2 text-xs text-amber-600">Limited to the first {{ number_format($maxImeis) }} IMEIs per search.</p>
        @endif
    </div>

    @if ($searched && count($unmatched))
        <div class="card mt-4" x-data="{
                copied: false,
                copy() {
                    navigator.clipboard.writeText($refs.miss.value).then(() => {
                        this.copied = true; setTimeout(() => this.copied = false, 1500);
                    });
                }
             }">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-amber-700">Not found ({{ number_format(count($unmatched)) }})</h2>
                <button class="btn-ghost text-xs" @click="copy()">
                    <span x-show="!copied">Copy for Excel</span>
                    <span x-show="copied" x-cloak>Copied ✓</span>
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">One per line — paste straight into an Excel column.</p>
            <textarea x-ref="miss" readonly rows="{{ min(12, max(3, count($unmatched))) }}"
                      class="input mt-2 font-mono text-xs leading-5 bg-gray-50">{{ implode("\n", $unmatched) }}</textarea>
        </div>
    @endif

    @if ($searched && $records && $records->isNotEmpty())
        <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
            <span>Showing {{ number_format($records->firstItem()) }}–{{ number_format($records->lastItem()) }} of {{ number_format($records->total()) }} matches</span>
            <select class="input w-auto text-xs" wire:model.live="perPage">
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
                <option value="250">250 / page</option>
                <option value="500">500 / page</option>
            </select>
        </div>
        <div class="card mt-2 overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="th">IMEI</th><th class="th">Model</th><th class="th">TSO</th>
                        <th class="th">RD</th><th class="th">RT</th>
                        <th class="th">ST Date</th><th class="th">Activation</th><th class="th">Sell-In</th>
                        <th class="th">Status</th><th class="th">Batch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach ($records as $r)
                    <tr wire:key="rec-{{ $r->id }}">
                        <td class="td font-mono">{{ $r->imei }}</td>
                        <td class="td">{{ $r->model }}</td>
                        <td class="td">{{ $r->tso }}</td>
                        <td class="td">{{ $r->rd_code }}</td>
                        <td class="td">{{ $r->rt_code }}</td>
                        <td class="td">{{ $r->st_date?->toDateString() ?? '—' }}</td>
                        <td class="td">{{ $r->activation_date?->toDateString() ?? '—' }}</td>
                        <td class="td">{{ $r->sell_in_date?->toDateString() ?? '—' }}</td>
                        <td class="td">
                            <span class="badge {{ $r->is_activated ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $r->is_activated ? 'Activated' : 'Not activated' }}
                            </span>
                        </td>
                        <td class="td text-gray-400">#{{ $r->last_import_batch_id }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $records->links() }}</div>
    @elseif ($searched && $records && $records->isEmpty())
        <div class="card mt-4 text-sm text-gray-500">None of the {{ number_format($totalWanted) }} IMEIs were found.</div>
    @endif
</div>
