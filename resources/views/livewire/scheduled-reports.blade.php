<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Scheduled Email Reports</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                    Automated
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Automated recurring distribution exports dispatched via email. Configured timezone: <strong class="text-slate-700">{{ config('reports.timezone') }}</strong>.
            </p>
        </div>
        <button class="btn-primary text-xs" wire:click="$set('showForm', true)">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>Create Scheduled Job</span>
        </button>
    </div>

    {{-- Form Card --}}
    @if ($showForm)
        <div class="card space-y-4 border-indigo-200 bg-indigo-50/20">
            <div class="flex items-center justify-between border-b border-indigo-100 pb-3">
                <h2 class="text-sm font-bold text-slate-900">{{ $editingId ? 'Edit Scheduled Dispatch' : 'New Scheduled Dispatch' }}</h2>
                <button type="button" wire:click="resetForm" class="text-slate-400 hover:text-slate-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">Job Name</label>
                    <input class="input text-xs" wire:model="name" placeholder="e.g. Daily Regional Sell-Through Digest">
                    @error('name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label">Report Template</label>
                    <select class="input text-xs" wire:model.live="export_type">
                        @foreach ($typeOptions as $key => $meta)
                            <option value="{{ $key }}">{{ $meta[0] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label">Attachment Format</label>
                    <select class="input text-xs" wire:model="format">
                        <option value="xlsx">Excel Workbook (.xlsx)</option>
                        <option value="csv">Standard CSV (.csv)</option>
                    </select>
                </div>

                @if ($currentDateable)
                    <div>
                        <label class="label">Data Timeframe Window</label>
                        <select class="input text-xs" wire:model="period">
                            @foreach ($periodOptions as $key => $label)
                                @if ($key !== 'none')
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Date Basis</label>
                        <select class="input text-xs" wire:model="date_basis">
                            @foreach ($basisOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="sm:col-span-2 rounded-xl bg-slate-50 p-3 text-xs text-slate-500 border border-slate-200">
                        ℹ️ This report is a point-in-time snapshot reflecting current channel stock at dispatch time.
                    </div>
                @endif

                <div>
                    <label class="label">Dispatch Frequency</label>
                    <select class="input text-xs" wire:model.live="frequency">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>

                <div>
                    <label class="label">Time ({{ config('reports.timezone') }})</label>
                    <input type="time" class="input text-xs" wire:model="time">
                    @error('time')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>

                @if ($frequency === 'weekly')
                    <div>
                        <label class="label">Day of Week</label>
                        <select class="input text-xs" wire:model="day_of_week">
                            @foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $d)
                                <option value="{{ $i }}">{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($frequency === 'monthly')
                    <div>
                        <label class="label">Day of Month (1–28)</label>
                        <input type="number" min="1" max="28" class="input text-xs" wire:model="day_of_month">
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <label class="label">Email Recipients <span class="text-slate-400 font-normal">(Comma or newline separated)</span></label>
                    <textarea class="input font-mono text-xs leading-5" rows="2" wire:model="recipients" placeholder="asm@example.com, nsm@example.com"></textarea>
                    @error('recipients')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer select-none">
                        <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Active (Ready for scheduled cron trigger)</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-3 border-t border-indigo-100">
                <button class="btn-primary text-xs" wire:click="save">Save Schedule</button>
                <button class="btn-ghost text-xs" wire:click="resetForm">Cancel</button>
            </div>
        </div>
    @endif

    {{-- Scheduled Jobs Table --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">Schedule Name</th>
                        <th class="th">Report Type</th>
                        <th class="th">Frequency</th>
                        <th class="th">Window</th>
                        <th class="th">Recipients</th>
                        <th class="th">Last Execution</th>
                        <th class="th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($reports as $r)
                        <tr class="hover:bg-slate-50/70 transition-colors {{ $r->is_active ? '' : 'opacity-60 bg-slate-50/30' }}">
                            <td class="td font-bold text-slate-900">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full {{ $r->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    <span>{{ $r->name }}</span>
                                </div>
                            </td>
                            <td class="td">
                                <span class="font-medium text-slate-800">{{ $typeOptions[$r->export_type][0] ?? $r->export_type }}</span>
                                <span class="badge-slate font-mono uppercase text-[10px] ml-1">{{ $r->format }}</span>
                            </td>
                            <td class="td font-medium text-slate-700">{{ $r->scheduleLabel() }}</td>
                            <td class="td text-slate-600">{{ $periodOptions[$r->period] ?? $r->period }}</td>
                            <td class="td">
                                <span class="badge-indigo text-[10px]">
                                    {{ count($r->recipients ?? []) }} email(s)
                                </span>
                            </td>
                            <td class="td">
                                @if ($r->last_run_at)
                                    <div class="flex items-center gap-1.5">
                                        <span class="badge {{ $r->last_status === 'ok' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }} text-[10px]">
                                            {{ $r->last_status === 'ok' ? 'Sent' : 'Failed' }}
                                        </span>
                                        <span class="text-slate-500">{{ $r->last_run_at->timezone(config('reports.timezone'))->format('d M H:i') }}</span>
                                    </div>
                                    @if ($r->last_status === 'failed' && $r->last_error)
                                        <div class="text-[11px] text-rose-600 truncate max-w-xs mt-0.5">{{ $r->last_error }}</div>
                                    @endif
                                @else
                                    <span class="text-slate-400">Never executed</span>
                                @endif
                            </td>
                            <td class="td text-right whitespace-nowrap space-x-1.5">
                                <button class="btn-ghost !py-1 !px-2 text-[11px] text-indigo-600 font-semibold" wire:click="runNow({{ $r->id }})">Run Now</button>
                                <button class="btn-ghost !py-1 !px-2 text-[11px] text-slate-600 font-medium" wire:click="toggle({{ $r->id }})">{{ $r->is_active ? 'Pause' : 'Resume' }}</button>
                                <button class="btn-ghost !py-1 !px-2 text-[11px] text-slate-700 font-medium" wire:click="edit({{ $r->id }})">Edit</button>
                                <button class="btn-ghost !py-1 !px-2 text-[11px] text-rose-600 font-medium hover:bg-rose-50" wire:click="delete({{ $r->id }})" wire:confirm="Delete this scheduled report?">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 text-xs">
                                No recurring email reports scheduled.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
