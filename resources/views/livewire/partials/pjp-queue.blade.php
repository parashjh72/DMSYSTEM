<div class="mt-4">
    <h2 class="text-sm font-semibold">
        {{ $stage === 'asm' ? 'PJPs awaiting your review' : 'PJPs awaiting your final approval' }}
        ({{ $queue->count() }})
    </h2>
    <div class="card mt-2 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">TSO</th><th class="th">Month</th><th class="th">Planned days</th>
                <th class="th">Planned visits</th><th class="th">Submitted</th>
                @if ($stage === 'nsm') <th class="th">ASM</th> @endif
                <th class="th">Status</th><th class="th"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($queue as $p)
                <tr wire:key="q-{{ $stage }}-{{ $p->id }}">
                    <td class="td">{{ $p->tso?->name ?? '—' }}</td>
                    <td class="td">{{ $p->monthLabel() }}</td>
                    <td class="td">{{ $p->planned_days }}</td>
                    <td class="td">{{ $p->planned_visits }}</td>
                    <td class="td text-xs text-gray-500">{{ $p->submitted_at?->diffForHumans() ?? '—' }}</td>
                    @if ($stage === 'nsm')
                        <td class="td text-xs">{{ $p->asm?->name ?? '—' }} <span class="text-green-600">✓</span></td>
                    @endif
                    <td class="td"><span class="badge bg-blue-100 text-blue-800">{{ $p->statusLabel() }}</span></td>
                    <td class="td text-right">
                        <button class="text-indigo-600 text-xs" wire:click="startReview('{{ $p->uuid }}')">Review</button>
                    </td>
                </tr>
            @empty
                <tr><td class="td text-sm text-gray-400" colspan="{{ $stage === 'nsm' ? 8 : 7 }}">Nothing waiting.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
