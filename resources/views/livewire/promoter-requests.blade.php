<div>
    <h1 class="text-xl font-semibold tracking-tight">RA Requests</h1>
    <p class="mt-1 text-sm text-gray-500">
        A TSO requests a promoter (RA) for a retailer. Approval: <strong>ASM → NSM</strong>. On final approval the promoter is created.
    </p>

    {{-- ---- TSO: raise a request -------------------------------------- --}}
    @if ($this->canRequest())
        <div class="card mt-6 space-y-3">
            <h2 class="text-sm font-semibold">Request an RA</h2>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label">RA type</label>
                    <select class="input" wire:model="type">
                        @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Retailer (in your territory)</label>
                    <input class="input" wire:model.live.debounce.300ms="rtSearch" placeholder="Search RT code or name…">
                    @if ($retailerOptions->isNotEmpty())
                        <select class="input mt-1" size="4" wire:change="pickRetailer($event.target.value)">
                            @foreach ($retailerOptions as $rt)
                                <option value="{{ $rt->code }}" @selected($rtCode === $rt->code)>{{ trim($rt->code.' — '.$rt->name, ' —') }}</option>
                            @endforeach
                        </select>
                    @endif
                    <p class="mt-1 text-xs {{ $rtCode ? 'text-emerald-600' : 'text-gray-400' }}">
                        {{ $rtCode ? 'Selected: '.$rtCode : 'No retailer selected' }}
                    </p>
                    @error('rtCode') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if (count($preview))
                <div>
                    <p class="label">Last 3 months at this retailer</p>
                    <div class="mt-1 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-xs text-gray-500">
                                <tr><th class="px-3 py-1 text-left">Month</th><th class="px-3 py-1 text-right">Activations</th><th class="px-3 py-1 text-right">Sell-through</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($preview as $m)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-1">{{ $m['label'] }}</td>
                                        <td class="px-3 py-1 text-right font-medium">{{ number_format($m['activations']) }}</td>
                                        <td class="px-3 py-1 text-right text-gray-500">{{ number_format($m['sell_through']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label">Proposed RA name <span class="text-gray-400">(optional)</span></label>
                    <input class="input" wire:model="promoterName">
                </div>
                <div>
                    <label class="label">Proposed monthly target</label>
                    <input type="number" min="0" class="input" wire:model="proposedTarget">
                    @error('proposedTarget') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="label">Note <span class="text-gray-400">(optional)</span></label>
                <input class="input" wire:model="note" placeholder="why this retailer needs an RA">
            </div>

            <button class="btn-primary" wire:click="submit" wire:loading.attr="disabled">Submit request</button>
        </div>

        @if ($mine->isNotEmpty())
            <div class="card mt-4 overflow-x-auto p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="th">Submitted</th><th class="th">Type</th><th class="th">Retailer</th>
                        <th class="th">Target</th><th class="th">Status</th><th class="th">Notes</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach ($mine as $r)
                        <tr wire:key="mine-{{ $r->id }}">
                            <td class="td text-xs text-gray-500">{{ $r->created_at->diffForHumans() }}</td>
                            <td class="td text-xs">{{ $r->typeLabel() }}</td>
                            <td class="td text-xs">{{ $r->rt_code }}<div class="text-gray-400">{{ $r->rt_name }}</div></td>
                            <td class="td">{{ $r->proposed_target }}</td>
                            <td class="td">
                                <span class="badge {{ ['pending_asm' => 'bg-blue-100 text-blue-800', 'pending_nsm' => 'bg-indigo-100 text-indigo-800', 'approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$r->status] }}">
                                    {{ $r->statusLabel() }}
                                </span>
                            </td>
                            <td class="td text-xs text-gray-500">
                                @if ($r->asm_note) ASM: {{ $r->asm_note }}<br> @endif
                                @if ($r->nsm_note) NSM: {{ $r->nsm_note }} @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- ---- ASM queue ---------------------------------------------------- --}}
    @if ($this->canAsm())
        @include('livewire.partials.ra-queue', ['title' => 'Awaiting your (ASM) approval', 'queue' => $asmQueue, 'stage' => 'asm'])
    @endif

    {{-- ---- NSM queue ---------------------------------------------------- --}}
    @if ($this->canNsm())
        @include('livewire.partials.ra-queue', ['title' => 'Awaiting your (NSM) final approval', 'queue' => $nsmQueue, 'stage' => 'nsm'])
    @endif
</div>
