<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Scheduled Reports</h1>
            <p class="mt-1 text-sm text-gray-500">
                Reports built and emailed automatically. Times are {{ config('reports.timezone') }}.
            </p>
        </div>
        <button class="btn-primary" wire:click="$set('showForm', true)">New scheduled report</button>
    </div>

    @if ($showForm)
        <div class="card mt-4 space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">Name</label>
                    <input class="input" wire:model="name" placeholder="Daily RT sell-through">
                    @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label">Report</label>
                    <select class="input" wire:model.live="export_type">
                        @foreach ($typeOptions as $key => $meta)
                            <option value="{{ $key }}">{{ $meta[0] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Format</label>
                    <select class="input" wire:model="format">
                        <option value="xlsx">Excel (.xlsx)</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>

                @if ($currentDateable)
                    <div>
                        <label class="label">Data window</label>
                        <select class="input" wire:model="period">
                            @foreach ($periodOptions as $key => $label)
                                @if ($key !== 'none')
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Date basis</label>
                        <select class="input" wire:model="date_basis">
                            @foreach ($basisOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="sm:col-span-2 rounded bg-gray-50 px-3 py-2 text-xs text-gray-500">
                        This is a point-in-time snapshot — it always reflects stock as of the send time.
                    </div>
                @endif

                <div>
                    <label class="label">Frequency</label>
                    <select class="input" wire:model.live="frequency">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="label">Time ({{ config('reports.timezone') }})</label>
                    <input type="time" class="input" wire:model="time">
                    @error('time')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                @if ($frequency === 'weekly')
                    <div>
                        <label class="label">Day of week</label>
                        <select class="input" wire:model="day_of_week">
                            @foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $d)
                                <option value="{{ $i }}">{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($frequency === 'monthly')
                    <div>
                        <label class="label">Day of month (1–28)</label>
                        <input type="number" min="1" max="28" class="input" wire:model="day_of_month">
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <label class="label">Recipients <span class="text-gray-400">— comma or newline separated</span></label>
                    <textarea class="input" rows="2" wire:model="recipients" placeholder="asm@example.com, tso1@example.com"></textarea>
                    @error('recipients')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" wire:model="is_active" class="rounded border-gray-300"> Active
                </label>
            </div>

            <div class="flex gap-3">
                <button class="btn-primary" wire:click="save">Save</button>
                <button class="btn-ghost" wire:click="resetForm">Cancel</button>
            </div>
        </div>
    @endif

    <div class="card mt-6 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th">Name</th><th class="th">Report</th><th class="th">Schedule</th>
                    <th class="th">Window</th><th class="th">Recipients</th><th class="th">Last run</th><th class="th"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($reports as $r)
                <tr class="{{ $r->is_active ? '' : 'opacity-50' }}">
                    <td class="td font-medium">{{ $r->name }}</td>
                    <td class="td">{{ $typeOptions[$r->export_type][0] ?? $r->export_type }} <span class="text-xs text-gray-400">/ {{ $r->format }}</span></td>
                    <td class="td text-xs">{{ $r->scheduleLabel() }}</td>
                    <td class="td text-xs">{{ $periodOptions[$r->period] ?? $r->period }}</td>
                    <td class="td text-xs text-gray-500">{{ count($r->recipients ?? []) }} address(es)</td>
                    <td class="td text-xs">
                        @if ($r->last_run_at)
                            <span class="{{ $r->last_status === 'ok' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $r->last_status === 'ok' ? 'Sent' : 'Failed' }}
                            </span>
                            {{ $r->last_run_at->timezone(config('reports.timezone'))->format('d M H:i') }}
                            @if ($r->last_status === 'failed' && $r->last_error)
                                <div class="text-red-500">{{ \Illuminate\Support\Str::limit($r->last_error, 80) }}</div>
                            @endif
                        @else
                            <span class="text-gray-400">never</span>
                        @endif
                    </td>
                    <td class="td whitespace-nowrap text-right text-xs">
                        <button class="text-indigo-600" wire:click="runNow({{ $r->id }})">Run now</button>
                        <button class="ml-2 text-gray-600" wire:click="toggle({{ $r->id }})">{{ $r->is_active ? 'Pause' : 'Resume' }}</button>
                        <button class="ml-2 text-indigo-600" wire:click="edit({{ $r->id }})">Edit</button>
                        <button class="ml-2 text-red-600" wire:click="delete({{ $r->id }})" wire:confirm="Delete this scheduled report?">Delete</button>
                    </td>
                </tr>
            @empty
                <tr><td class="td text-sm text-gray-400" colspan="7">No scheduled reports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
