<div class="space-y-3">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900">
            {{ $stage === 'asm' ? 'PJPs Awaiting Area Manager (ASM) Review' : 'PJPs Awaiting National Sales Manager (NSM) Final Approval' }}
            <span class="badge-indigo ml-1 text-xs">{{ $queue->count() }}</span>
        </h2>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">TSO Officer</th>
                        <th class="th">Plan Month</th>
                        <th class="th text-right">Planned Days</th>
                        <th class="th text-right">Target Visits</th>
                        <th class="th">Submitted</th>
                        @if ($stage === 'nsm')
                            <th class="th">ASM Endorsement</th>
                        @endif
                        <th class="th">Workflow Status</th>
                        <th class="th text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($queue as $p)
                        <tr wire:key="q-{{ $stage }}-{{ $p->id }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="td font-bold text-slate-900">{{ $p->tso?->name ?? '—' }}</td>
                            <td class="td font-semibold text-indigo-700">{{ $p->monthLabel() }}</td>
                            <td class="td text-right font-mono">{{ $p->planned_days }}</td>
                            <td class="td text-right font-mono font-bold text-slate-800">{{ $p->planned_visits }}</td>
                            <td class="td text-slate-500 text-[11px]">{{ $p->submitted_at?->diffForHumans() ?? '—' }}</td>
                            @if ($stage === 'nsm')
                                <td class="td text-[11px]">
                                    <span class="font-medium text-slate-800">{{ $p->asm?->name ?? '—' }}</span>
                                    <span class="badge-emerald text-[10px] ml-1">Endorsed ✓</span>
                                </td>
                            @endif
                            <td class="td">
                                <span class="badge-indigo text-[10px] font-semibold">{{ $p->statusLabel() }}</span>
                            </td>
                            <td class="td text-right">
                                <button class="btn-primary !py-1 !px-2.5 text-xs font-semibold" wire:click="startReview('{{ $p->uuid }}')">
                                    Review Plan
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $stage === 'nsm' ? 8 : 7 }}" class="py-12 text-center text-slate-400 text-xs">
                                No PJP plans are currently waiting in your approval queue.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
