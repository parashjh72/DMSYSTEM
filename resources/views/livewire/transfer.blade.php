<div>
    <a href="{{ route('settings.index') }}" wire:navigate class="text-sm text-indigo-600">← Settings</a>
    <h1 class="mt-1 text-xl font-semibold tracking-tight">Transfer records</h1>
    <p class="mt-1 text-sm text-gray-500">Reassign devices to a different retailer. Every transfer is logged below.</p>

    <div class="mt-4 flex gap-2">
        @foreach (['imei_list' => 'By IMEI list', 'retailer' => 'By retailer'] as $key => $label)
            <button wire:click="$set('mode', '{{ $key }}')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition
                    {{ $mode === $key ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($result)
        <div class="card mt-4 ring-green-200">
            <p class="text-sm font-medium text-green-800">
                {{ number_format($result['affected']) }} record(s) transferred.
                @if ($result['requested']) ({{ number_format($result['requested']) }} requested) @endif
            </p>
            @if ($result['notFound'])
                <p class="mt-1 text-xs text-amber-600">
                    Not found ({{ count($result['notFound']) }}):
                    {{ \Illuminate\Support\Str::limit(implode(', ', $result['notFound']), 120) }}
                </p>
            @endif
        </div>
    @endif

    <div class="card mt-4">
        {{-- Target retailer (shared) --}}
        <label class="label">Target retailer</label>
        <input type="search" class="input mb-1 text-sm" placeholder="Search RT code or name…"
               wire:model.live.debounce.300ms="targetSearch">
        <select class="input" wire:model="targetRt">
            <option value="">— choose target retailer —</option>
            @foreach ($targetOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
        </select>
        @error('targetRt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" class="rounded border-gray-300" wire:model="moveDistributor">
            Also set the distributor (RD) to the target retailer's RD
        </label>

        <div class="my-4 border-t border-gray-100"></div>

        @if ($mode === 'imei_list')
            <label class="label">IMEIs to move</label>
            <textarea rows="6" wire:model="imeis" class="input font-mono text-xs leading-5"
                      placeholder="Paste IMEI numbers — one per line, or space / comma separated"></textarea>
            @error('imeis') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <button class="btn-primary mt-3"
                    wire:click="transferImeis"
                    wire:confirm="Move the pasted IMEIs to the selected retailer?">
                Transfer IMEIs
            </button>
        @else
            <label class="label">Source retailer (move everything from)</label>
            <input type="search" class="input mb-1 text-sm" placeholder="Search RT code or name…"
                   wire:model.live.debounce.300ms="sourceSearch">
            <select class="input" wire:model="sourceRt">
                <option value="">— choose source retailer —</option>
                @foreach ($sourceOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
            </select>
            @error('sourceRt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" class="rounded border-gray-300" wire:model="onlyInStock">
                Only move in-stock units (not activated)
            </label>
            <button class="btn-primary mt-3"
                    wire:click="transferRetailer"
                    wire:confirm="Move this retailer's records to the target retailer?">
                Transfer retailer stock
            </button>
        @endif
    </div>

    <h2 class="mt-8 text-sm font-semibold">Recent transfers</h2>
    <div class="card mt-2 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">When</th><th class="th">Mode</th><th class="th">From</th><th class="th">To</th>
                <th class="th text-right">Affected</th><th class="th">By</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($recent as $t)
                <tr>
                    <td class="td text-gray-400">{{ $t->created_at?->diffForHumans() }}</td>
                    <td class="td">{{ $t->mode === 'retailer' ? 'Retailer'.($t->only_in_stock ? ' (stock)' : '') : 'IMEI list' }}</td>
                    <td class="td">{{ $t->from_rt_code ?? '—' }}</td>
                    <td class="td">{{ $t->to_rt_code }}{{ $t->move_distributor ? ' / '.$t->to_rd_code : '' }}</td>
                    <td class="td text-right font-medium">{{ number_format($t->affected_count) }}</td>
                    <td class="td">{{ $t->performer?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="6">No transfers yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
