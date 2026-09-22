<div class="space-y-6">
    {{-- Filter Panel --}}
    <div class="card space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
            <div>
                <label class="label">Month</label>
                <select class="input text-xs" wire:model.live="month">
                    <option value="">All Months</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Year</label>
                <select class="input text-xs" wire:model.live="year">
                    @foreach (range(now()->year - 1, now()->year + 1) as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Field Officer (TSO)</label>
                <select class="input text-xs" wire:model.live="tsoId">
                    <option value="">All TSOs</option>
                    @foreach ($tsoOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Area Manager (ASM)</label>
                <select class="input text-xs" wire:model.live="asmId">
                    <option value="">All ASMs</option>
                    @foreach ($asmOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Approval Status</label>
                <select class="input text-xs" wire:model.live="status">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $k => $l)
                        <option value="{{ $k }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Report Table --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">TSO Officer</th>
                        <th class="th">ASM Manager</th>
                        <th class="th">Month</th>
                        <th class="th text-right">Planned Days</th>
                        <th class="th text-right">Planned Visits</th>
                        <th class="th text-right">Actual Visits</th>
                        <th class="th text-right">Visit Adherence</th>
                        <th class="th">Approval State</th>
                        <th class="th text-right">Revisions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($pjps as $p)
                        <tr wire:key="pr-{{ $p->id }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="td font-bold text-slate-900">{{ $p->tso?->name ?? '—' }}</td>
                            <td class="td text-slate-500">{{ $p->asm?->name ?? '—' }}</td>
                            <td class="td font-semibold text-indigo-700">{{ $p->monthLabel() }}</td>
                            <td class="td text-right font-mono">{{ $p->planned_days }}</td>
                            <td class="td text-right font-mono">{{ $p->planned_visits }}</td>
                            <td class="td text-right font-mono font-bold text-slate-800">{{ $p->visits_count }}</td>
                            <td class="td text-right">
                                @if ($p->achievement !== null)
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-bold {{ (float)$p->achievement >= 80 ? 'bg-emerald-50 text-emerald-700' : ((float)$p->achievement >= 50 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">
                                        {{ $p->achievement }}%
                                    </span>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="td">
                                <span class="badge {{ match($p->status) {
                                    'final_approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                    'rejected' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
                                    'revision_required' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                    default => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                                } }} text-[11px] font-semibold">
                                    {{ $p->statusLabel() }}
                                </span>
                            </td>
                            <td class="td text-right font-mono text-slate-500">{{ $p->revision_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400 text-xs">
                                No PJP reports found for the selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $pjps->links() }}</div>
</div>
