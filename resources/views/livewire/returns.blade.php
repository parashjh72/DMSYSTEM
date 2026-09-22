<div class="space-y-6">
    {{-- Header --}}
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Device Returns &amp; Recalls</h1>
            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                Stock Reversal
            </span>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            Reassign devices from retail shelves back into unassigned distributor warehouse stock. RD initiates recall &rarr; National Admin approves.
        </p>
    </div>

    {{-- RD: Request a Return --}}
    @if ($this->canRequest())
        <div class="card space-y-4 border-indigo-200 bg-indigo-50/20">
            <div class="border-b border-indigo-100 pb-3">
                <h2 class="text-sm font-bold text-slate-900">Initiate Device Return from Retailer</h2>
                <p class="text-xs text-slate-500">
                    Paste IMEIs currently assigned to a retailer under your distributor code. On admin approval, retailer linkage and ST dates are reset.
                </p>
            </div>

            <div>
                <label class="label" for="imeis">IMEI Numbers to Recall</label>
                <textarea id="imeis" rows="4" wire:model="imeis"
                          class="input font-mono text-xs leading-5 bg-white"
                          placeholder="Paste IMEIs (one per line, comma or space separated)..."></textarea>
                @error('imeis') <p class="text-xs text-rose-600 mt-1 font-semibold">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="note">Reason / Stock Reversal Rationale <span class="text-slate-400 font-normal">(Optional)</span></label>
                <input id="note" class="input text-xs bg-white" wire:model="note" placeholder="e.g. Wrong retailer dispatch, dealer stock rebalance, defective batch…">
            </div>

            <div class="pt-1">
                <button class="btn-primary text-xs" wire:click="submit" wire:loading.attr="disabled">
                    Submit Return Request
                </button>
            </div>

            {{-- Submission Result Banner --}}
            @if ($result)
                <div class="rounded-xl bg-white p-4 border border-indigo-100 shadow-2xs space-y-2 text-xs">
                    @if ($result['request'])
                        <div class="flex items-center gap-2 text-emerald-800 font-bold">
                            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>
                                Request <span class="font-mono text-indigo-700">#{{ \Illuminate\Support\Str::limit($result['request']['uuid'], 8, '') }}</span>
                                successfully submitted for {{ $result['request']['count'] }} device(s) under {{ $result['request']['rd_code'] }}.
                            </span>
                        </div>
                    @endif

                    @if (count($result['rejected']))
                        <div class="pt-2 border-t border-slate-100">
                            <p class="font-bold text-amber-800">Skipped Entries ({{ count($result['rejected']) }}):</p>
                            <ul class="mt-1 space-y-1 font-mono text-[11px] text-slate-600 max-h-32 overflow-y-auto">
                                @foreach ($result['rejected'] as $imei => $reason)
                                    <li class="bg-amber-50/70 px-2 py-1 rounded">
                                        <strong>{{ $imei }}</strong> &mdash; {{ $reason }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- My Submitted Returns --}}
        @if ($mine->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-sm font-bold text-slate-900">Your Submitted Return Requests</h2>
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-xs">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-500">
                                    <th class="th">Submitted</th>
                                    <th class="th">RD</th>
                                    <th class="th text-right">Device Count</th>
                                    <th class="th">Status</th>
                                    <th class="th">Reviewed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($mine as $r)
                                    <tr wire:key="mine-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                                        <td class="td text-slate-500">{{ $r->created_at->diffForHumans() }}</td>
                                        <td class="td font-mono font-bold text-slate-900">{{ $r->rd_code }}</td>
                                        <td class="td text-right font-mono font-bold text-slate-800">
                                            {{ $r->requested_count }}{{ $r->status === 'approved' ? " ({$r->approved_count} cleared)" : '' }}
                                        </td>
                                        <td class="td">
                                            <span class="badge {{ match($r->status) {
                                                'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                                'rejected' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
                                                default => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                            } }} text-[10px] font-semibold">
                                                {{ ucfirst($r->status) }}
                                            </span>
                                        </td>
                                        <td class="td text-slate-500">
                                            <div>{{ $r->reviewed_at?->diffForHumans() ?? 'Pending Review' }}</div>
                                            @if ($r->review_note)
                                                <div class="text-[11px] text-slate-400 italic">&ldquo;{{ $r->review_note }}&rdquo;</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Review Queue (Admin / Super Admin) --}}
    @if ($this->canReview())
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900">
                    Awaiting National Distributor Approval
                    <span class="badge-amber ml-1 text-xs">{{ $pending->count() }}</span>
                </h2>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500">
                                <th class="th">Submitted</th>
                                <th class="th">Distributor (RD)</th>
                                <th class="th text-right">Units</th>
                                <th class="th">Requested By</th>
                                <th class="th">Reason</th>
                                <th class="th text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($pending as $r)
                                <tr wire:key="pend-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                                    <td class="td text-slate-500">{{ $r->created_at->diffForHumans() }}</td>
                                    <td class="td font-mono font-bold text-slate-900">{{ $r->rd_code }}</td>
                                    <td class="td text-right font-mono font-bold text-indigo-700">{{ $r->requested_count }}</td>
                                    <td class="td font-medium text-slate-800">{{ $r->requester?->name ?? '—' }}</td>
                                    <td class="td text-slate-600 max-w-xs truncate">{{ $r->note ?: '—' }}</td>
                                    <td class="td text-right">
                                        <button class="btn-primary !py-1 !px-2.5 text-xs font-semibold" wire:click="startReview('{{ $r->uuid }}')">
                                            Review Request
                                        </button>
                                    </td>
                                </tr>

                                @if ($reviewUuid === $r->uuid)
                                    <tr wire:key="rev-{{ $r->id }}">
                                        <td class="td bg-indigo-50/50 border-y border-indigo-100" colspan="6">
                                            <div class="space-y-3 p-2">
                                                <div>
                                                    <span class="label !mb-1">Included IMEIs ({{ count($r->imeis) }} devices)</span>
                                                    <div class="max-h-28 overflow-y-auto rounded-xl bg-white p-2.5 border border-indigo-100 font-mono text-[11px] text-slate-700 break-all leading-5">
                                                        {{ implode(', ', $r->imeis) }}
                                                    </div>
                                                </div>

                                                <div class="flex flex-wrap items-end gap-3 pt-1">
                                                    <div class="flex-1 min-w-[220px]">
                                                        <label class="label">Approval Remarks / Audit Note</label>
                                                        <input class="input text-xs" wire:model="reviewNote" placeholder="Enter note...">
                                                        @error('reviewNote') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                                    </div>
                                                    <button class="btn-primary text-xs" wire:click="approve" wire:loading.attr="disabled">
                                                        Approve Return (Clear Retailer)
                                                    </button>
                                                    <button class="btn-ghost text-xs text-rose-600 hover:bg-rose-50" wire:click="reject" wire:loading.attr="disabled">
                                                        Reject Request
                                                    </button>
                                                    <button class="btn-ghost text-xs" wire:click="cancelReview">
                                                        Close
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td class="py-12 text-center text-slate-400 text-xs" colspan="6">
                                        No pending return requests awaiting review.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- History Table --}}
            @if ($history->isNotEmpty())
                <div class="space-y-3 pt-4">
                    <h2 class="text-sm font-bold text-slate-900">Recently Processed Returns</h2>
                    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-xs">
                                <thead class="bg-slate-50/80 text-slate-500">
                                    <tr>
                                        <th class="th">Processed</th>
                                        <th class="th">RD</th>
                                        <th class="th text-right">Cleared Units</th>
                                        <th class="th">Decision</th>
                                        <th class="th">Auditor</th>
                                        <th class="th">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($history as $r)
                                        <tr wire:key="hist-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                                            <td class="td text-slate-500">{{ $r->reviewed_at?->diffForHumans() }}</td>
                                            <td class="td font-mono font-bold text-slate-900">{{ $r->rd_code }}</td>
                                            <td class="td text-right font-mono font-bold">{{ $r->status === 'approved' ? $r->approved_count : $r->requested_count }}</td>
                                            <td class="td">
                                                <span class="badge {{ $r->status === 'approved' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20' }} text-[10px] font-semibold">
                                                    {{ ucfirst($r->status) }}
                                                </span>
                                            </td>
                                            <td class="td font-medium text-slate-800">{{ $r->reviewer?->name ?? '—' }}</td>
                                            <td class="td text-slate-500 max-w-xs truncate">{{ $r->review_note ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
