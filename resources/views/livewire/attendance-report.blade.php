<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Attendance Report</h1>
            <p class="mt-1 text-sm text-gray-500">Field check-in / check-out with GPS location.</p>
        </div>
        @can('exports.create')
            <button class="btn-ghost" wire:click="export">Export → Excel / CSV</button>
        @endcan
    </div>

    <div class="card mt-4">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div><label class="label">From</label><input type="date" class="input" wire:model.live="from"></div>
            <div><label class="label">To</label><input type="date" class="input" wire:model.live="to"></div>
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
                <label class="label">Distributor</label>
                <select class="input" wire:model.live="rdCode">
                    <option value="">All</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Status</label>
                <select class="input" wire:model.live="status">
                    <option value="">All</option>
                    <option value="checked_in">Checked in</option>
                    <option value="checked_out">Checked out</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">Date</th><th class="th">TSO</th><th class="th">ASM</th>
                <th class="th">Check in</th><th class="th">In location</th>
                <th class="th">Check out</th><th class="th">Out location</th>
                <th class="th">Duration</th><th class="th">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $r)
                <tr wire:key="att-{{ $r->id }}">
                    <td class="td whitespace-nowrap">{{ $r->attendance_date->format('d M Y') }}</td>
                    <td class="td">{{ $r->user?->name ?? '—' }}</td>
                    <td class="td text-xs text-gray-500">{{ $r->user?->reportsTo?->name ?? '—' }}</td>
                    <td class="td whitespace-nowrap">{{ $r->check_in_at?->timezone($tz)->format('h:i A') ?? '—' }}</td>
                    <td class="td">
                        @if ($r->check_in_latitude)
                            <a class="text-indigo-600 text-xs" target="_blank"
                               href="https://www.google.com/maps?q={{ $r->check_in_latitude }},{{ $r->check_in_longitude }}">map</a>
                            <span class="text-xs text-gray-400">±{{ round($r->check_in_accuracy ?? 0) }}m</span>
                        @else — @endif
                    </td>
                    <td class="td whitespace-nowrap">{{ $r->check_out_at?->timezone($tz)->format('h:i A') ?? '—' }}</td>
                    <td class="td">
                        @if ($r->check_out_latitude)
                            <a class="text-indigo-600 text-xs" target="_blank"
                               href="https://www.google.com/maps?q={{ $r->check_out_latitude }},{{ $r->check_out_longitude }}">map</a>
                        @else — @endif
                    </td>
                    <td class="td">{{ $r->workingLabel() ?? '—' }}</td>
                    <td class="td">
                        <span class="badge {{ $r->isCheckedOut() ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ $r->isCheckedOut() ? 'Checked out' : 'Checked in' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td class="td text-sm text-gray-400" colspan="9">No attendance for this selection.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
