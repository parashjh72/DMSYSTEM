<div>
    <h1 class="text-xl font-semibold tracking-tight">Returns</h1>
    <p class="mt-1 text-sm text-gray-500">
        Pull devices back from a retailer into distributor stock. An RD raises the request; Admin approves it.
    </p>

    {{-- ---- Request a return (RD) ---------------------------------------- --}}
    @if ($this->canRequest())
        <div class="card mt-6 space-y-3">
            <h2 class="text-sm font-semibold">Request a return</h2>
            <p class="text-xs text-gray-500">
                Paste IMEIs currently at a retailer, in your distributor(s). On approval their retailer and
                invoice date are cleared and they go back to your unassigned stock.
            </p>
            <div>
                <label class="label" for="imeis">IMEIs to return</label>
                <textarea id="imeis" rows="4" wire:model="imeis"
                          class="input font-mono text-xs leading-5"
                          placeholder="one per line, or space / comma separated"></textarea>
                @error('imeis') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="note">Reason (optional)</label>
                <input id="note" class="input" wire:model="note" placeholder="e.g. wrong retailer / stock recall">
            </div>
            <button class="btn-primary" wire:click="submit" wire:loading.attr="disabled">Submit request</button>

            @if ($result)
                <div class="rounded-lg bg-gray-50 p-3 text-sm">
                    @if ($result['request'])
                        <p class="text-green-700">
                            Request <span class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($result['request']['uuid'], 8, '') }}</span>
                            submitted for {{ $result['request']['count'] }} device(s) under {{ $result['request']['rd_code'] }}.
                        </p>
                    @endif
                    @if (count($result['rejected']))
                        <p class="mt-2 font-medium text-amber-700">Skipped ({{ count($result['rejected']) }}):</p>
                        <ul class="mt-1 space-y-0.5 text-xs text-gray-600">
                            @foreach ($result['rejected'] as $imei => $reason)
                                <li><span class="font-mono">{{ $imei }}</span> — {{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>

        @if ($mine->isNotEmpty())
            <div class="card mt-4 overflow-x-auto p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="th">Submitted</th><th class="th">RD</th><th class="th">Devices</th>
                        <th class="th">Status</th><th class="th">Reviewed</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach ($mine as $r)
                        <tr wire:key="mine-{{ $r->id }}">
                            <td class="td text-xs text-gray-500">{{ $r->created_at->diffForHumans() }}</td>
                            <td class="td">{{ $r->rd_code }}</td>
                            <td class="td">{{ $r->requested_count }}{{ $r->status === 'approved' ? " ({$r->approved_count} cleared)" : '' }}</td>
                            <td class="td">
                                <span class="badge {{ ['pending' => 'bg-blue-100 text-blue-800', 'approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$r->status] }}">
                                    {{ ucfirst($r->status) }}
                                </span>
                            </td>
                            <td class="td text-xs text-gray-500">
                                {{ $r->reviewed_at?->diffForHumans() ?? '—' }}
                                @if ($r->review_note) <div class="text-gray-400">{{ $r->review_note }}</div> @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- ---- Review queue (Admin / Super Admin) -------------------------- --}}
    @if ($this->canReview())
        <div class="mt-8">
            <h2 class="text-sm font-semibold">Pending approval ({{ $pending->count() }})</h2>
            <div class="card mt-2 overflow-x-auto p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="th">Submitted</th><th class="th">RD</th><th class="th">Devices</th>
                        <th class="th">By</th><th class="th">Reason</th><th class="th"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse ($pending as $r)
                        <tr wire:key="pend-{{ $r->id }}">
                            <td class="td text-xs text-gray-500">{{ $r->created_at->diffForHumans() }}</td>
                            <td class="td">{{ $r->rd_code }}</td>
                            <td class="td">{{ $r->requested_count }}</td>
                            <td class="td">{{ $r->requester?->name ?? '—' }}</td>
                            <td class="td text-xs text-gray-500">{{ $r->note ?: '—' }}</td>
                            <td class="td text-right">
                                <button class="text-indigo-600 text-xs" wire:click="startReview('{{ $r->uuid }}')">Review</button>
                            </td>
                        </tr>
                        @if ($reviewUuid === $r->uuid)
                            <tr wire:key="rev-{{ $r->id }}">
                                <td class="td bg-indigo-50/50" colspan="6">
                                    <p class="text-xs text-gray-600">IMEIs:</p>
                                    <p class="mt-1 font-mono text-xs text-gray-500 break-all">{{ implode(', ', $r->imeis) }}</p>
                                    <div class="mt-2 flex flex-wrap items-end gap-3">
                                        <div class="flex-1 min-w-[220px]">
                                            <label class="label">Note (optional)</label>
                                            <input class="input" wire:model="reviewNote">
                                            @error('reviewNote') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <button class="btn-primary" wire:click="approve" wire:loading.attr="disabled">Approve</button>
                                        <button class="btn-ghost text-red-600" wire:click="reject" wire:loading.attr="disabled">Reject</button>
                                        <button class="btn-ghost" wire:click="cancelReview">Close</button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td class="td text-sm text-gray-400" colspan="6">Nothing awaiting approval.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($history->isNotEmpty())
                <h2 class="mt-6 text-sm font-semibold">Recently reviewed</h2>
                <div class="card mt-2 overflow-x-auto p-0">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr>
                            <th class="th">Reviewed</th><th class="th">RD</th><th class="th">Devices</th>
                            <th class="th">Status</th><th class="th">By</th><th class="th">Note</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                        @foreach ($history as $r)
                            <tr wire:key="hist-{{ $r->id }}">
                                <td class="td text-xs text-gray-500">{{ $r->reviewed_at?->diffForHumans() }}</td>
                                <td class="td">{{ $r->rd_code }}</td>
                                <td class="td">{{ $r->status === 'approved' ? $r->approved_count : $r->requested_count }}</td>
                                <td class="td">
                                    <span class="badge {{ $r->status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ ucfirst($r->status) }}</span>
                                </td>
                                <td class="td text-xs">{{ $r->reviewer?->name ?? '—' }}</td>
                                <td class="td text-xs text-gray-500">{{ $r->review_note ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
