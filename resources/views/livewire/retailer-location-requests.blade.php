<div>
    <h1 class="text-xl font-semibold tracking-tight">Retailer Location Requests</h1>
    <p class="mt-1 text-sm text-gray-500">
        A TSO's first visit check-in sets a retailer's location automatically. Any change after that is requested here
        and applied only when an Admin approves it.
    </p>

    @php
        $tabs = $this->canReview()
            ? ['pending' => 'Pending'.($pendingCount ? " ({$pendingCount})" : ''), 'approved' => 'Approved', 'rejected' => 'Rejected', 'mine' => 'My requests']
            : ['mine' => 'My requests'];
    @endphp
    @if (count($tabs) > 1)
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($tabs as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset
                        {{ $tab === $key ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="th">Retailer</th>
                <th class="th">Proposed location</th>
                <th class="th">Previous</th>
                <th class="th">Requested by</th>
                <th class="th">Status</th>
                @if (! $mine) <th class="th"></th> @endif
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $r)
                <tr wire:key="lr-{{ $r->id }}">
                    <td class="td">
                        <span class="font-mono">{{ $r->rt_code }}</span>
                        <span class="block text-xs text-gray-400">{{ $r->retailer->name ?? '' }} · {{ $r->retailer->rd_code ?? '—' }}</span>
                    </td>
                    <td class="td">
                        <a class="text-indigo-600 underline" target="_blank"
                           href="https://www.google.com/maps?q={{ $r->proposed_latitude }},{{ $r->proposed_longitude }}">
                            {{ $r->proposed_latitude }}, {{ $r->proposed_longitude }}
                        </a>
                        @if ($r->reason) <span class="block text-xs text-gray-400">{{ $r->reason }}</span> @endif
                    </td>
                    <td class="td text-xs text-gray-500">
                        @if ($r->previous_latitude !== null)
                            <a class="underline" target="_blank" href="https://www.google.com/maps?q={{ $r->previous_latitude }},{{ $r->previous_longitude }}">{{ $r->previous_latitude }}, {{ $r->previous_longitude }}</a>
                        @else — @endif
                    </td>
                    <td class="td text-xs">
                        {{ $r->requester->name ?? '—' }}
                        <span class="block text-gray-400">{{ $r->created_at->format('d M, H:i') }}</span>
                    </td>
                    <td class="td">
                        <span class="badge {{ ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$r->status] }}">
                            {{ ucfirst($r->status) }}
                        </span>
                        @if ($r->reviewed_by)
                            <span class="block text-xs text-gray-400">{{ $r->reviewer->name ?? '' }}{{ $r->review_note ? ' — '.$r->review_note : '' }}</span>
                        @endif
                    </td>
                    @if (! $mine)
                        <td class="td">
                            @if ($r->status === 'pending')
                                <div class="flex flex-col gap-1">
                                    <input class="input text-xs" placeholder="note (optional)" wire:model="noteFor.{{ $r->id }}">
                                    <div class="flex gap-2">
                                        <button class="text-xs font-medium text-green-700" wire:click="approve({{ $r->id }})"
                                                wire:confirm="Apply this location to {{ $r->rt_code }}?">Approve</button>
                                        <button class="text-xs font-medium text-red-600" wire:click="reject({{ $r->id }})">Reject</button>
                                    </div>
                                </div>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td class="td text-gray-400" colspan="{{ $mine ? 5 : 6 }}">No requests.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
