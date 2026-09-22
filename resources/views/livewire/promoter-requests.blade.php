<div class="space-y-6">
    <div>
        <p class="text-xs text-slate-500">
            Promoter (RA) deployment requests: Field Officer (TSO) initiates &rarr; <strong>ASM endorses &rarr; NSM grants final authorization</strong>.
        </p>
    </div>

    {{-- TSO Submission Form --}}
    @if ($this->canRequest())
        <div class="card space-y-4 border-indigo-200 bg-indigo-50/20">
            <div class="border-b border-indigo-100 pb-3">
                <h2 class="text-sm font-bold text-slate-900">Request Retail Promoter (RA) Deployment</h2>
                <p class="text-xs text-slate-500">Submit justification and retailer sales performance for management review.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label">Deployment Classification</label>
                    <select class="input text-xs" wire:model="type">
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Target Retailer Store</label>
                    <input class="input text-xs" wire:model.live.debounce.300ms="rtSearch" placeholder="Type RT code or store name…">
                    @if ($retailerOptions->isNotEmpty())
                        <select class="input mt-1.5 text-xs" size="4" wire:change="pickRetailer($event.target.value)">
                            @foreach ($retailerOptions as $rt)
                                <option value="{{ $rt->code }}" @selected($rtCode === $rt->code)>{{ trim($rt->code.' — '.$rt->name, ' —') }}</option>
                            @endforeach
                        </select>
                    @endif
                    <p class="mt-1 text-xs {{ $rtCode ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">
                        {{ $rtCode ? 'Selected Account: '.$rtCode : 'Please pick a store' }}
                    </p>
                    @error('rtCode') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- 3-Month Sales Snapshot --}}
            @if (count($preview))
                <div class="rounded-xl border border-indigo-100 bg-white p-3.5 space-y-2">
                    <p class="label !mb-0">Recent 3-Month Sales Run-Rate</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="text-slate-400 border-b border-slate-100">
                                <tr>
                                    <th class="py-1 text-left font-semibold">Month</th>
                                    <th class="py-1 text-right font-semibold">Customer Activations</th>
                                    <th class="py-1 text-right font-semibold">Sell-Through (Invoiced)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($preview as $m)
                                    <tr>
                                        <td class="py-1.5 font-bold text-slate-800">{{ $m['label'] }}</td>
                                        <td class="py-1.5 text-right font-mono font-bold text-emerald-700">{{ number_format($m['activations']) }}</td>
                                        <td class="py-1.5 text-right font-mono text-slate-600">{{ number_format($m['sell_through']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Candidate Name <span class="text-slate-400 font-normal">(Optional)</span></label>
                    <input class="input text-xs" wire:model="promoterName" placeholder="Proposed staff name">
                </div>
                <div>
                    <label class="label">Proposed Monthly Target (Units)</label>
                    <input type="number" min="0" class="input text-xs font-mono font-bold" wire:model="proposedTarget" placeholder="e.g. 60">
                    @error('proposedTarget') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="label">Business Justification Note</label>
                <input class="input text-xs" wire:model="note" placeholder="Explain retailer traffic, counter share, or competition presence…">
            </div>

            <div class="pt-2">
                <button class="btn-primary text-xs" wire:click="submit" wire:loading.attr="disabled">
                    Submit Request for Approval
                </button>
            </div>
        </div>

        {{-- My Submitted Requests --}}
        @if ($mine->isNotEmpty())
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500">
                                <th class="th">Submitted</th>
                                <th class="th">Type</th>
                                <th class="th">Retailer</th>
                                <th class="th text-right">Target</th>
                                <th class="th">Status</th>
                                <th class="th">Management Feedback</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($mine as $r)
                                <tr wire:key="mine-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                                    <td class="td text-slate-500">{{ $r->created_at->diffForHumans() }}</td>
                                    <td class="td">
                                        <span class="badge-indigo text-[10px]">{{ $r->typeLabel() }}</span>
                                    </td>
                                    <td class="td">
                                        <span class="font-mono font-bold text-slate-900">{{ $r->rt_code }}</span>
                                        <div class="text-[11px] text-slate-500">{{ $r->rt_name }}</div>
                                    </td>
                                    <td class="td text-right font-mono font-bold">{{ $r->proposed_target }}</td>
                                    <td class="td">
                                        <span class="badge {{ match($r->status) {
                                            'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                            'rejected' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
                                            default => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                                        } }} text-[10px] font-semibold">
                                            {{ $r->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="td text-slate-600 text-[11px]">
                                        @if ($r->asm_note)
                                            <div><strong class="text-slate-800">ASM:</strong> {{ $r->asm_note }}</div>
                                        @endif
                                        @if ($r->nsm_note)
                                            <div><strong class="text-slate-800">NSM:</strong> {{ $r->nsm_note }}</div>
                                        @endif
                                        @if (! $r->asm_note && ! $r->nsm_note)
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    {{-- ASM Queue --}}
    @if ($this->canAsm())
        @include('livewire.partials.ra-queue', ['title' => 'Awaiting Area Manager (ASM) Review', 'queue' => $asmQueue, 'stage' => 'asm'])
    @endif

    {{-- NSM Queue --}}
    @if ($this->canNsm())
        @include('livewire.partials.ra-queue', ['title' => 'Awaiting National Sales Manager (NSM) Final Approval', 'queue' => $nsmQueue, 'stage' => 'nsm'])
    @endif
</div>
