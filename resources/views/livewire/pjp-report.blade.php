<div class="mt-4">
    <div class="card">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div>
                <label class="label">Month</label>
                <select class="input" wire:model.live="month">
                    <option value="">All</option>
                    @foreach (range(1, 12) as $m) <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('M') }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Year</label>
                <select class="input" wire:model.live="year">
                    @foreach (range(now()->year - 1, now()->year + 1) as $y) <option value="{{ $y }}">{{ $y }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">TSO</label>
                <select class="input" wire:model.live="tsoId">
                    <option value="">All</option>
                    @foreach ($tsoOptions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">ASM</label>
                <select class="input" wire:model.live="asmId">
                    <option value="">All</option>
                    @foreach ($asmOptions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Status</label>
                <select class="input" wire:model.live="status">
                    <option value="">All</option>
                    @foreach ($statuses as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">TSO</th><th class="th">ASM</th><th class="th">Month</th>
                <th class="th text-right">Planned days</th><th class="th text-right">Planned visits</th>
                <th class="th text-right">Actual</th><th class="th text-right">Achievement</th>
                <th class="th">Status</th><th class="th">Revisions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($pjps as $p)
                <tr wire:key="pr-{{ $p->id }}">
                    <td class="td">{{ $p->tso?->name ?? '—' }}</td>
                    <td class="td text-xs text-gray-500">{{ $p->asm?->name ?? '—' }}</td>
                    <td class="td">{{ $p->monthLabel() }}</td>
                    <td class="td text-right">{{ $p->planned_days }}</td>
                    <td class="td text-right">{{ $p->planned_visits }}</td>
                    <td class="td text-right">{{ $p->visits_count }}</td>
                    <td class="td text-right font-medium">{{ $p->achievement !== null ? $p->achievement.'%' : '—' }}</td>
                    <td class="td">
                        <span class="badge {{ $p->status === 'final_approved' ? 'bg-green-100 text-green-800' : ($p->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                            {{ $p->statusLabel() }}
                        </span>
                    </td>
                    <td class="td text-xs text-gray-500">{{ $p->revision_count }}</td>
                </tr>
            @empty
                <tr><td class="td text-sm text-gray-400" colspan="9">No PJPs for this selection.</td></tr>
            @endforelse
            </tbody>
            @if ($pjps->isNotEmpty())
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="td" colspan="3">Total ({{ $totals['plans'] }} plans)</td>
                        <td class="td text-right">{{ $totals['days'] }}</td>
                        <td class="td text-right">{{ $totals['visits'] }}</td>
                        <td class="td text-right">{{ $totals['actual'] }}</td>
                        <td class="td text-right">{{ $totals['visits'] > 0 ? round($totals['actual'] / $totals['visits'] * 100, 1).'%' : '—' }}</td>
                        <td class="td" colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
