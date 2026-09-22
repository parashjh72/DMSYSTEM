<div class="space-y-3">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900">
            {{ $title }}
            <span class="badge-indigo ml-1 text-xs">{{ $queue->count() }}</span>
        </h2>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">Submitted</th>
                        <th class="th">Request Type</th>
                        <th class="th">Retail Store</th>
                        <th class="th">RD</th>
                        <th class="th">Proposed Deployment</th>
                        <th class="th">Requested By</th>
                        <th class="th text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($queue as $r)
                        <tr wire:key="q-{{ $stage }}-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="td text-slate-500 text-[11px]">{{ $r->created_at->diffForHumans() }}</td>
                            <td class="td">
                                <span class="badge-indigo text-[10px]">{{ $r->typeLabel() }}</span>
                            </td>
                            <td class="td">
                                <span class="font-mono font-bold text-slate-900">{{ $r->rt_code }}</span>
                                <div class="text-[11px] text-slate-500">{{ $r->rt_name }}</div>
                            </td>
                            <td class="td font-mono text-slate-600">{{ $r->rd_code }}</td>
                            <td class="td">
                                <span class="font-bold text-slate-800">{{ $r->promoter_name ?: '—' }}</span>
                                <div class="text-[11px] text-slate-500">Target: <strong class="text-indigo-700 font-semibold">{{ $r->proposed_target }} units</strong></div>
                            </td>
                            <td class="td">
                                <span class="font-medium text-slate-800">{{ $r->requester?->name ?? '—' }}</span>
                                @if ($stage === 'nsm' && $r->asmReviewer)
                                    <div class="text-[10px] text-emerald-700 font-semibold">ASM Endorsed ✓ {{ $r->asmReviewer->name }}</div>
                                @endif
                            </td>
                            <td class="td text-right">
                                <button class="btn-primary !py-1 !px-2.5 text-xs font-semibold" wire:click="startReview('{{ $r->uuid }}')">
                                    Review
                                </button>
                            </td>
                        </tr>
                        @if ($reviewUuid === $r->uuid)
                            <tr wire:key="rev-{{ $stage }}-{{ $r->id }}">
                                <td class="td bg-indigo-50/40 border-y border-indigo-100" colspan="7">
                                    <div class="space-y-3 p-2">
                                        @if (!empty($r->sales_snapshot['months']))
                                            <div class="flex flex-wrap gap-3 text-xs bg-white p-3 rounded-xl border border-indigo-100">
                                                <span class="font-bold text-slate-700">Retailer Sales Performance:</span>
                                                @foreach ($r->sales_snapshot['months'] as $m)
                                                    <span class="bg-slate-50 px-2 py-0.5 rounded-lg border border-slate-200">
                                                        {{ $m['label'] }}: <strong class="text-emerald-700">{{ $m['activations'] }} act.</strong> / {{ $m['sell_through'] }} ST
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if ($r->note)
                                            <p class="text-xs text-slate-600 italic bg-white p-2 rounded-lg border border-indigo-100">
                                                Note from submitter: &ldquo;{{ $r->note }}&rdquo;
                                            </p>
                                        @endif

                                        <div class="flex flex-wrap items-end gap-3 pt-1">
                                            <div class="flex-1 min-w-[220px]">
                                                <label class="label">Review Decision Note</label>
                                                <input class="input text-xs" wire:model="reviewNote" placeholder="Enter comments or approval rationale...">
                                                @error('reviewNote') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                            </div>
                                            <button class="btn-primary text-xs" wire:click="approve" wire:loading.attr="disabled">
                                                {{ $stage === 'asm' ? 'Endorse & Forward to NSM' : 'Final Approve Deployment' }}
                                            </button>
                                            <button class="btn-ghost text-xs text-rose-600 hover:bg-rose-50" wire:click="rejectRequest" wire:loading.attr="disabled">
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
                            <td class="py-12 text-center text-slate-400 text-xs" colspan="7">
                                No promoter requests awaiting review in this queue.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
