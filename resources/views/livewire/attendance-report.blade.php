<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Attendance Report</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    Field Monitoring
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">Historical check-in and check-out logs with geofenced GPS verification.</p>
        </div>
        @can('exports.create')
            <button class="btn-primary text-xs" wire:click="export">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span>Export Report</span>
            </button>
        @endcan
    </div>

    {{-- Filter Panel --}}
    <div class="card space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="label">Date From</label>
                <input type="date" class="input text-xs" wire:model.live="from">
            </div>
            <div>
                <label class="label">Date To</label>
                <input type="date" class="input text-xs" wire:model.live="to">
            </div>
            <div>
                <label class="label">Field Officer (TSO)</label>
                <select class="input text-xs" wire:model.live="tsoId">
                    <option value="">All TSOs</option>
                    @foreach ($tsoOptions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Area Manager (ASM)</label>
                <select class="input text-xs" wire:model.live="asmId">
                    <option value="">All ASMs</option>
                    @foreach ($asmOptions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdCode">
                    <option value="">All Distributors</option>
                    @foreach ($rdOptions as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Shift Status</label>
                <select class="input text-xs" wire:model.live="status">
                    <option value="">All Statuses</option>
                    <option value="checked_in">Checked In (Active)</option>
                    <option value="checked_out">Shift Completed</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Records Table --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">Date</th>
                        <th class="th">TSO Officer</th>
                        <th class="th">ASM Manager</th>
                        <th class="th">Check-In</th>
                        <th class="th">In GPS</th>
                        <th class="th">Check-Out</th>
                        <th class="th">Out GPS</th>
                        <th class="th">Shift Duration</th>
                        <th class="th text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr wire:key="att-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="td font-medium text-slate-900">{{ $r->attendance_date->format('d M Y') }}</td>
                            <td class="td font-bold text-slate-800">{{ $r->user?->name ?? '—' }}</td>
                            <td class="td text-slate-500">{{ $r->user?->reportsTo?->name ?? '—' }}</td>
                            <td class="td font-mono">{{ $r->check_in_at?->timezone($tz)->format('h:i A') ?? '—' }}</td>
                            <td class="td">
                                @if ($r->check_in_latitude)
                                    <div class="flex items-center gap-1.5">
                                        <a class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-semibold"
                                           target="_blank"
                                           href="https://www.google.com/maps?q={{ $r->check_in_latitude }},{{ $r->check_in_longitude }}">
                                            <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span>Map</span>
                                        </a>
                                        <span class="text-[10px] text-slate-400">(&plusmn;{{ round($r->check_in_accuracy ?? 0) }}m)</span>
                                    </div>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="td font-mono">{{ $r->check_out_at?->timezone($tz)->format('h:i A') ?? '—' }}</td>
                            <td class="td">
                                @if ($r->check_out_latitude)
                                    <div class="flex items-center gap-1.5">
                                        <a class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-semibold"
                                           target="_blank"
                                           href="https://www.google.com/maps?q={{ $r->check_out_latitude }},{{ $r->check_out_longitude }}">
                                            <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span>Map</span>
                                        </a>
                                        <span class="text-[10px] text-slate-400">(&plusmn;{{ round($r->check_out_accuracy ?? 0) }}m)</span>
                                    </div>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="td font-medium text-slate-700">{{ $r->workingLabel() ?: 'In Progress' }}</td>
                            <td class="td text-right">
                                @if ($r->isCheckedOut())
                                    <span class="badge-emerald font-semibold">Completed</span>
                                @else
                                    <span class="badge-amber font-semibold">Active Shift</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400 text-xs">
                                No attendance records found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
