@php
    $statusBadge = ['pending' => 'badge-amber', 'approved' => 'badge-emerald', 'rejected' => 'badge-rose', 'cancelled' => 'badge-slate'];
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Leave</h1>
        <p class="mt-1 text-xs text-slate-500">
            @if ($canApply) Apply for leave; your manager approves it and the days are marked as leave in your PJP. @endif
            @if ($canReview) Review leave requests from your team. @endif
        </p>
    </div>

    @if ($flash)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-medium text-emerald-800">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-800">{{ $error }}</div>
    @endif

    @if ($canApply)
        <div class="grid gap-6 lg:grid-cols-5">
            <form wire:submit="apply" class="card space-y-4 lg:col-span-2">
                <h2 class="text-sm font-bold text-slate-900">Apply for leave</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">From <span class="font-normal text-slate-400">{{ $bs($fromDate) }} BS</span></label>
                        <input type="date" class="input text-xs" wire:model.live="fromDate">
                        @error('fromDate') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">To <span class="font-normal text-slate-400">{{ $bs($toDate) }} BS</span></label>
                        <input type="date" class="input text-xs" wire:model.live="toDate">
                        @error('toDate') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Type</label>
                        <select class="input text-xs" wire:model="leaveType">
                            @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </div>
                    <label class="mt-6 flex items-center gap-2 text-xs font-medium text-slate-700">
                        <input type="checkbox" class="rounded border-slate-300 text-indigo-600" wire:model="halfDay"> Half day
                    </label>
                </div>
                <div>
                    <label class="label">Reason</label>
                    <textarea rows="3" class="input text-xs" wire:model="reason" placeholder="Reason for leave"></textarea>
                    @error('reason') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary w-full justify-center text-xs" wire:loading.attr="disabled" wire:target="apply">Send request</button>
            </form>

            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs lg:col-span-3 overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 text-xs font-bold text-slate-900">My recent requests</div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($mine as $leave)
                        <li wire:key="mine-{{ $leave->id }}" class="flex items-start justify-between gap-3 px-4 py-3 text-xs">
                            <div>
                                <div class="font-semibold text-slate-900">
                                    {{ $leave->from_date->format('d M') }}@if (! $leave->from_date->equalTo($leave->to_date)) – {{ $leave->to_date->format('d M Y') }}@else {{ $leave->from_date->format('Y') }}@endif
                                    <span class="font-normal text-slate-400">· {{ $types[$leave->leave_type] ?? $leave->leave_type }}{{ $leave->half_day ? ' · half day' : '' }} · {{ $leave->days() }} day(s)</span>
                                </div>
                                <div class="mt-0.5 text-slate-500">{{ $leave->reason }}</div>
                                @if ($leave->review_note)<div class="mt-0.5 text-slate-400">Manager: {{ $leave->review_note }}</div>@endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="badge {{ $statusBadge[$leave->status] }}">{{ ucfirst($leave->status) }}</span>
                                @if ($leave->isPending())
                                    <button class="text-[11px] font-semibold text-rose-600 hover:text-rose-800" wire:click="cancel({{ $leave->id }})" wire:confirm="Cancel this leave request?">Cancel</button>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-xs text-slate-400">No leave requests yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif

    @if ($canReview)
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <h2 class="text-xs font-bold text-slate-900">Team requests</h2>
                <select class="input w-40 text-xs" wire:model.live="statusFilter">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="">All</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-500">
                            <th class="th">Field officer</th>
                            <th class="th">Dates</th>
                            <th class="th">Type</th>
                            <th class="th">Reason</th>
                            <th class="th">Status</th>
                            <th class="th text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($queue as $leave)
                            <tr wire:key="queue-{{ $leave->id }}" class="align-top hover:bg-slate-50/70">
                                <td class="td font-bold text-slate-900">{{ $leave->user?->name }}</td>
                                <td class="td">
                                    <div class="font-medium text-slate-800">{{ $leave->from_date->format('d M') }} – {{ $leave->to_date->format('d M Y') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $bs($leave->from_date) }} → {{ $bs($leave->to_date) }} BS · {{ $leave->days() }} day(s)</div>
                                </td>
                                <td class="td">{{ $types[$leave->leave_type] ?? $leave->leave_type }}{{ $leave->half_day ? ' (half)' : '' }}</td>
                                <td class="td max-w-xs text-slate-600">{{ $leave->reason }}</td>
                                <td class="td">
                                    <span class="badge {{ $statusBadge[$leave->status] }}">{{ ucfirst($leave->status) }}</span>
                                    @if ($leave->reviewer)<div class="mt-1 text-[10px] text-slate-400">by {{ $leave->reviewer->name }}</div>@endif
                                </td>
                                <td class="td text-right">
                                    @if ($leave->isPending())
                                        <div class="flex flex-col items-end gap-2">
                                            <input type="text" class="input w-52 text-xs" placeholder="Note (required to reject)" wire:model="notes.{{ $leave->id }}">
                                            <div class="flex gap-2">
                                                <button class="btn-danger text-xs" wire:click="reject({{ $leave->id }})">Reject</button>
                                                <button class="btn-success text-xs" wire:click="approve({{ $leave->id }})">Approve</button>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-[11px] text-slate-400">{{ $leave->review_note }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-xs text-slate-400">No requests.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3">{{ $queue->links() }}</div>
        </div>
    @endif
</div>
