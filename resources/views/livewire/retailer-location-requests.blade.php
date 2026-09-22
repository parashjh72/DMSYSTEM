<div class="space-y-6">
    {{-- Header --}}
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Retailer Relocation Requests</h1>
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                Audit Approval
            </span>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            Field-submitted location change requests requiring management audit and approval before updating canonical GPS coordinates.
        </p>
    </div>

    {{-- Tabs --}}
    @php
        $tabs = $this->canReview()
            ? ['pending' => 'Pending Review'.($pendingCount ? " ({$pendingCount})" : ''), 'approved' => 'Approved Changes', 'rejected' => 'Rejected', 'mine' => 'My Submitted Requests']
            : ['mine' => 'My Submitted Requests'];
    @endphp

    @if (count($tabs) > 1)
        <div class="flex flex-wrap gap-2">
            @foreach ($tabs as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="rounded-xl px-3.5 py-2 text-xs font-semibold transition-all duration-150 shadow-2xs {{ $tab === $key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:text-slate-900' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Requests Table --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th">Retail Store</th>
                        <th class="th">Proposed New GPS</th>
                        <th class="th">Previous Location</th>
                        <th class="th">Requested By</th>
                        <th class="th">Status</th>
                        @if (! $mine)
                            <th class="th text-right">Audit Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr wire:key="lr-{{ $r->id }}" class="hover:bg-slate-50/70 transition-colors">
                            <td class="td">
                                <span class="font-mono font-bold text-slate-900">{{ $r->rt_code }}</span>
                                <div class="text-xs text-slate-600 font-medium">{{ $r->retailer->name ?? '—' }}</div>
                                <div class="text-[11px] text-slate-400">RD: {{ $r->retailer->rd_code ?? '—' }}</div>
                            </td>
                            <td class="td">
                                <a class="inline-flex items-center gap-1 font-semibold text-indigo-600 hover:underline" target="_blank"
                                   href="https://www.google.com/maps?q={{ $r->proposed_latitude }},{{ $r->proposed_longitude }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span>{{ $r->proposed_latitude }}, {{ $r->proposed_longitude }}</span>
                                </a>
                                @if ($r->reason)
                                    <p class="text-[11px] text-slate-500 mt-1 italic">&ldquo;{{ $r->reason }}&rdquo;</p>
                                @endif
                            </td>
                            <td class="td text-slate-500 font-mono text-[11px]">
                                @if ($r->previous_latitude !== null)
                                    <a class="hover:underline text-slate-600" target="_blank" href="https://www.google.com/maps?q={{ $r->previous_latitude }},{{ $r->previous_longitude }}">
                                        {{ $r->previous_latitude }}, {{ $r->previous_longitude }}
                                    </a>
                                @else
                                    <span class="text-slate-300">— (First registration)</span>
                                @endif
                            </td>
                            <td class="td">
                                <div class="font-semibold text-slate-800">{{ $r->requester->name ?? '—' }}</div>
                                <span class="text-[11px] text-slate-400">{{ $r->created_at->format('d M, H:i') }}</span>
                            </td>
                            <td class="td">
                                <span class="badge {{ match($r->status) {
                                    'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                    'rejected' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
                                    default => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                } }} text-[11px] font-semibold">
                                    {{ ucfirst($r->status) }}
                                </span>
                                @if ($r->reviewed_by)
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ $r->reviewer->name ?? 'Admin' }}
                                        @if ($r->review_note) &bull; {{ $r->review_note }} @endif
                                    </div>
                                @endif
                            </td>
                            @if (! $mine)
                                <td class="td text-right">
                                    @if ($r->status === 'pending')
                                        <div class="flex flex-col items-end gap-1.5">
                                            <input class="input text-[11px] !py-1 w-48" placeholder="Audit note (optional)" wire:model="noteFor.{{ $r->id }}">
                                            <div class="flex items-center gap-1.5">
                                                <button class="btn-primary !py-1 !px-2.5 text-xs font-semibold" wire:click="approve({{ $r->id }})"
                                                        wire:confirm="Apply this GPS location to {{ $r->rt_code }}?">
                                                    Approve
                                                </button>
                                                <button class="btn-ghost !py-1 !px-2.5 text-xs font-semibold text-rose-600 hover:bg-rose-50" wire:click="reject({{ $r->id }})">
                                                    Reject
                                                </button>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-400 text-xs">Audited</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $mine ? 5 : 6 }}" class="py-12 text-center text-slate-400 text-xs">
                                No location change requests found in this queue.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
