<div class="mt-8">
    <h2 class="text-sm font-semibold">{{ $title }} ({{ $queue->count() }})</h2>
    <div class="card mt-2 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">Submitted</th><th class="th">Type</th><th class="th">Retailer</th><th class="th">RD</th>
                <th class="th">Proposed</th><th class="th">By</th><th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($queue as $r)
                <tr wire:key="q-{{ $stage }}-{{ $r->id }}">
                    <td class="td text-xs text-gray-500">{{ $r->created_at->diffForHumans() }}</td>
                    <td class="td text-xs">{{ $r->typeLabel() }}</td>
                    <td class="td text-xs">{{ $r->rt_code }}<div class="text-gray-400">{{ $r->rt_name }}</div></td>
                    <td class="td text-xs">{{ $r->rd_code }}</td>
                    <td class="td text-xs">
                        {{ $r->promoter_name ?: '—' }} · target {{ $r->proposed_target }}
                    </td>
                    <td class="td text-xs">
                        {{ $r->requester?->name ?? '—' }}
                        @if ($stage === 'nsm' && $r->asmReviewer)
                            <div class="text-gray-400">ASM ✓ {{ $r->asmReviewer->name }}</div>
                        @endif
                    </td>
                    <td class="td text-right">
                        <button class="text-indigo-600 text-xs" wire:click="startReview('{{ $r->uuid }}')">Review</button>
                    </td>
                </tr>
                @if ($reviewUuid === $r->uuid)
                    <tr wire:key="rev-{{ $stage }}-{{ $r->id }}">
                        <td class="td bg-indigo-50/50" colspan="7">
                            @if (!empty($r->sales_snapshot['months']))
                                <div class="mb-2 flex flex-wrap gap-4 text-xs text-gray-600">
                                    @foreach ($r->sales_snapshot['months'] as $m)
                                        <span>{{ $m['label'] }}: <strong>{{ $m['activations'] }}</strong> act / {{ $m['sell_through'] }} sell-thru</span>
                                    @endforeach
                                </div>
                            @endif
                            @if ($r->note) <p class="mb-2 text-xs text-gray-500">Note: {{ $r->note }}</p> @endif
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="flex-1 min-w-[220px]">
                                    <label class="label">Note (optional)</label>
                                    <input class="input" wire:model="reviewNote">
                                    @error('reviewNote') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <button class="btn-primary" wire:click="approve" wire:loading.attr="disabled">
                                    {{ $stage === 'asm' ? 'Approve → NSM' : 'Approve (final)' }}
                                </button>
                                <button class="btn-ghost text-red-600" wire:click="rejectRequest" wire:loading.attr="disabled">Reject</button>
                                <button class="btn-ghost" wire:click="cancelReview">Close</button>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td class="td text-sm text-gray-400" colspan="7">Nothing awaiting approval.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
