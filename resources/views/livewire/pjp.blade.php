<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Planned Journey Plan (PJP)</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                    Route Scheduling
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Monthly territory route planning: TSO creates &rarr; Area Manager (ASM) reviews &rarr; <strong>National Sales Manager (NSM) approves</strong>.
            </p>
        </div>
    </div>

    {{-- Tabs --}}
    @php
        $tabs = array_filter([
            'plan' => $this->canPlan() ? 'My Monthly Plan' : null,
            'asm' => $this->canAsm() ? 'ASM Review Queue' : null,
            'nsm' => $this->canNsm() ? 'NSM Approval Queue' : null,
            'report' => $this->canReport() ? 'PJP Adherence Reports' : null,
        ]);
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

    @error('day')
        <div class="rounded-2xl bg-rose-50 p-4 text-xs font-semibold text-rose-800 border border-rose-200">
            {{ $message }}
        </div>
    @enderror

    {{-- ============ MY PLAN (TSO) ============ --}}
    @if ($tab === 'plan' && $this->canPlan())
        <div class="card flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <label class="label">Plan Month</label>
                    <select class="input !w-auto text-xs" wire:model.live="month">
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Year</label>
                    <select class="input !w-auto text-xs" wire:model.live="year">
                        @foreach (range(now()->year - 1, now()->year + 1) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="badge {{ [
                    'draft' => 'bg-slate-100 text-slate-700 ring-slate-400/20',
                    'revision_required' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
                    'final_approved' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
                    'rejected' => 'bg-rose-50 text-rose-800 ring-rose-600/20',
                ][$pjp->status] ?? 'bg-indigo-50 text-indigo-800 ring-indigo-600/20' }} font-bold text-xs py-1 px-3">
                    {{ $pjp->statusLabel() }}
                </span>

                <span class="text-xs font-semibold text-slate-600 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
                    {{ $pjp->planned_days }} Working Days &bull; {{ $pjp->planned_visits }} Target Visits
                </span>

                @if ($pjp->isEditableByTso())
                    <button class="btn-primary text-xs" wire:click="submit"
                            wire:confirm="Submit this PJP for management approval? You will not be able to edit while it is under review.">
                        Submit for Approval
                    </button>
                @endif
            </div>
        </div>

        {{-- Monthly Calendar Grid --}}
        <div class="card !p-4">
            @php
                $first = \Illuminate\Support\Carbon::create($year, $month, 1);
                $lead = ($first->dayOfWeekIso - 1); // Mon=0
                $byDate = $days->keyBy(fn ($d) => $d->plan_date->toDateString());
            @endphp
            <div class="grid grid-cols-7 gap-1.5 text-center text-xs font-bold text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">
                @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                    <div>{{ $d }}</div>
                @endforeach
            </div>

            <div class="mt-2 grid grid-cols-7 gap-1.5">
                @for ($i = 0; $i < $lead; $i++)
                    <div class="min-h-[72px] rounded-xl bg-slate-50/40 border border-transparent"></div>
                @endfor
                @foreach ($days as $d)
                    @php
                        $planned = $d->retailers_count;
                        $visited = $d->visits_count;
                        $isOpen = $openDayId === $d->id;
                        $isToday = $d->plan_date->isToday();
                    @endphp
                    <button type="button" wire:key="cal-{{ $d->id }}" wire:click="openDay({{ $d->id }})"
                            class="min-h-[72px] rounded-xl border p-2 text-left text-xs transition-all duration-150 flex flex-col justify-between
                            {{ $isOpen ? 'border-indigo-600 ring-2 ring-indigo-500/20 bg-indigo-50/60 shadow-xs' : 'border-slate-200/80 bg-white hover:border-slate-300 hover:shadow-xs' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold {{ $isToday ? 'flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-white text-[10px]' : 'text-slate-800' }}">
                                {{ $d->plan_date->format('j') }}
                            </span>
                            @if ($d->day_status === 'planned' && $planned > 0)
                                <span class="badge-indigo text-[10px] font-bold px-1.5 py-0">{{ $planned }} RT</span>
                            @endif
                        </div>

                        <div>
                            @if ($d->day_status === 'planned')
                                @if ($visited)
                                    <div class="text-[10px] font-bold text-emerald-600 flex items-center gap-1">
                                        <span>✓ {{ $visited }} Visited</span>
                                    </div>
                                @endif
                            @elseif ($d->day_status !== 'no_plan')
                                <div class="text-[10px] font-medium text-slate-400">{{ $d->statusLabel() }}</div>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Selected Day Route Editor --}}
        @if ($openDay)
            <div class="card space-y-4 border-indigo-200 bg-indigo-50/20">
                <div class="flex items-center justify-between border-b border-indigo-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-xs">
                            {{ $openDay->plan_date->format('d') }}
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">{{ $openDay->plan_date->format('l, d F Y') }}</h2>
                            <p class="text-xs text-slate-500">{{ $openDay->statusLabel() }}</p>
                        </div>
                    </div>
                    <button class="btn-ghost !py-1 !px-2.5 text-xs text-slate-500" wire:click="closeDay">Close</button>
                </div>

                @if ($pjp->isEditableByTso())
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="label">Day Type</label>
                            <select class="input text-xs" wire:model="dayStatus">
                                @foreach ($dayStatuses as $k => $l)
                                    <option value="{{ $k }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Daily Route Remarks / Notes</label>
                            <input class="input text-xs" wire:model="dayNotes" placeholder="e.g. Market focus, new stock roll-out...">
                        </div>
                    </div>

                    @if ($dayStatus === 'planned')
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="label !mb-0">Selected Retailers ({{ count($dayRetailers) }})</label>
                                <span class="text-xs text-slate-400">Search and check retailers in your territory</span>
                            </div>

                            <input class="input text-xs" wire:model.live.debounce.300ms="rtSearch" placeholder="Filter by RT code, shop name, phone or area…">

                            <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100 bg-white">
                                @forelse ($retailerOptions as $rt)
                                    <label wire:key="rt-{{ $rt->code }}"
                                           class="flex cursor-pointer items-start gap-2.5 px-3 py-2 text-xs hover:bg-indigo-50/60 transition {{ in_array($rt->code, $dayRetailers, true) ? 'bg-indigo-50/80 font-semibold text-indigo-900' : 'text-slate-700' }}">
                                        <input type="checkbox" class="mt-0.5 h-4 w-4 rounded-md border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                               wire:click="toggleRt('{{ $rt->code }}')" @checked(in_array($rt->code, $dayRetailers, true))>
                                        <div class="min-w-0 flex-1">
                                            <span class="font-mono font-bold">{{ $rt->code }}</span> &mdash; {{ $rt->name }}
                                            <div class="text-[11px] text-slate-400 font-normal">
                                                RD: {{ $rt->rd_code ?: '—' }}@if ($rt->area) &bull; {{ $rt->area }} @endif@if ($rt->phone) &bull; {{ $rt->phone }} @endif
                                            </div>
                                        </div>
                                    </label>
                                @empty
                                    <div class="px-3 py-4 text-xs text-slate-400 text-center">No matching retailers in your assigned territory.</div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <div class="pt-2">
                        <button class="btn-primary text-xs" wire:click="saveDay">Save Route for this Day</button>
                    </div>
                @else
                    <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 border border-slate-200">
                        🔒 This plan is currently locked for review: <strong class="text-slate-800">{{ $pjp->statusLabel() }}</strong>.
                        @if ($openDay->notes)
                            <div class="mt-1 text-slate-500">Remarks: {{ $openDay->notes }}</div>
                        @endif
                    </div>
                @endif

                {{-- Planned Retailers List with Visit Marker --}}
                @if ($openDay->retailers->isNotEmpty())
                    <div class="border-t border-indigo-100 pt-3">
                        <label class="label">Scheduled Store Visits</label>
                        <div class="space-y-1.5 mt-2">
                            @foreach ($openDay->retailers as $r)
                                @php $v = $openDay->visits->firstWhere('rt_code', $r->rt_code); @endphp
                                <div class="flex items-center justify-between rounded-xl p-2.5 text-xs {{ $v ? 'bg-emerald-50/80 border border-emerald-200/60' : 'bg-white border border-slate-200/80' }}">
                                    <div>
                                        <span class="font-mono font-bold text-slate-900">{{ $r->rt_code }}</span>
                                        <span class="text-slate-700 ml-1 font-medium">{{ $r->rt_name }}</span>
                                    </div>

                                    @if ($v)
                                        <span class="badge-emerald text-[10px] font-bold">
                                            Visited at {{ $v->visited_at->timezone(config('pjp.timezone'))->format('d M H:i') }}
                                        </span>
                                    @elseif ($pjp->status === 'final_approved')
                                        <button class="btn-primary !py-1 !px-2 text-[11px] font-bold"
                                                x-data
                                                @click="navigator.geolocation.getCurrentPosition(
                                                    p => $wire.logVisit({{ $openDay->id }}, '{{ $r->rt_code }}', {latitude:p.coords.latitude, longitude:p.coords.longitude, accuracy:p.coords.accuracy}),
                                                    () => $wire.logVisit({{ $openDay->id }}, '{{ $r->rt_code }}', {}))">
                                            Mark Visited (GPS)
                                        </button>
                                    @else
                                        <span class="badge-slate text-[10px]">Pending Approval</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif

    {{-- ============ ASM REVIEW ============ --}}
    @if ($tab === 'asm' && $this->canAsm())
        @include('livewire.partials.pjp-queue', ['queue' => $asmQueue ?? collect(), 'stage' => 'asm'])
    @endif

    {{-- ============ NSM FINAL APPROVAL ============ --}}
    @if ($tab === 'nsm' && $this->canNsm())
        @include('livewire.partials.pjp-queue', ['queue' => $nsmQueue ?? collect(), 'stage' => 'nsm'])
    @endif

    {{-- ============ REPORTS ============ --}}
    @if ($tab === 'report' && $this->canReport())
        @livewire('pjp-report', key('pjp-report'))
    @endif

    {{-- ============ REVIEW PANEL (Modal) ============ --}}
    @if ($reviewUuid && isset($reviewPjp) && $reviewPjp)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 backdrop-blur-xs p-4" wire:click.self="cancelReview">
            <div class="my-6 w-full max-w-3xl rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-sm font-bold text-slate-900">
                            PJP Plan Review &bull; {{ $reviewPjp->tso?->name }} ({{ $reviewPjp->monthLabel() }})
                        </h2>
                        <span class="badge-indigo text-[10px] font-bold">{{ $reviewPjp->statusLabel() }}</span>
                    </div>
                    <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" wire:click="cancelReview">&times;</button>
                </div>

                <div class="max-h-[70vh] space-y-5 overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">TSO Officer</span>
                            <div class="mt-1 font-bold text-slate-900">{{ $reviewPjp->tso?->name }}</div>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">ASM Manager</span>
                            <div class="mt-1 font-bold text-slate-900">{{ $reviewPjp->asm?->name ?? '—' }}</div>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Planned Days</span>
                            <div class="mt-1 font-extrabold text-indigo-700">{{ $reviewPjp->planned_days }}</div>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Visits</span>
                            <div class="mt-1 font-extrabold text-slate-900">{{ $reviewPjp->planned_visits }}</div>
                        </div>
                    </div>

                    <div>
                        <h3 class="label">Scheduled Day Breakdown</h3>
                        <div class="rounded-xl border border-slate-200/80 overflow-hidden">
                            <table class="min-w-full text-xs">
                                <thead class="bg-slate-50 text-slate-500 border-b border-slate-200/80">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-left">Day Status</th>
                                        <th class="px-3 py-2 text-left">Planned Retailers</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($reviewPjp->days->where('day_status', '!=', 'no_plan') as $d)
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="px-3 py-2 font-bold text-slate-900">{{ $d->plan_date->format('d M (D)') }}</td>
                                            <td class="px-3 py-2">
                                                <span class="badge-slate text-[10px]">{{ $d->statusLabel() }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-slate-600 font-mono">{{ $d->retailers->pluck('rt_code')->join(', ') ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="label">Audit Trail &amp; Revisions</h3>
                        <div class="space-y-2 rounded-xl bg-slate-50 p-3 text-xs border border-slate-100">
                            @foreach ($reviewPjp->events as $e)
                                <div class="flex items-start gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500 mt-1.5"></span>
                                    <div class="text-slate-700">
                                        <span class="font-bold">{{ ucwords(str_replace('_', ' ', $e->action)) }}</span>
                                        by <span class="font-semibold text-slate-900">{{ $e->actor?->name ?? 'System' }}</span>
                                        <span class="text-slate-400 text-[11px]">({{ $e->created_at->timezone(config('pjp.timezone'))->format('d M H:i') }})</span>
                                        @if ($e->comment)
                                            <div class="mt-0.5 text-slate-600 bg-white p-2 rounded-lg border border-slate-200 italic">&ldquo;{{ $e->comment }}&rdquo;</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="label">Workflow Comment / Feedback Note</label>
                        <textarea class="input text-xs" rows="2" wire:model="reviewNote" placeholder="Enter comments or revision requests..."></textarea>
                        @error('reviewNote') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-6 py-4 bg-slate-50/50">
                    @if (in_array($reviewPjp->status, ['submitted', 'resubmitted', 'asm_review']) && $this->canAsm())
                        <button class="btn-primary text-xs" wire:click="act('asm_approve')">Approve &amp; Forward to NSM</button>
                        <button class="btn-ghost text-xs text-amber-700 hover:bg-amber-50" wire:click="act('asm_revision')">Request Revision</button>
                    @elseif (in_array($reviewPjp->status, ['forwarded_to_nsm', 'nsm_review']) && $this->canNsm())
                        <button class="btn-primary text-xs" wire:click="act('nsm_final')">Final Approve PJP</button>
                        <button class="btn-ghost text-xs text-amber-700 hover:bg-amber-50" wire:click="act('nsm_revision')">Request Revision</button>
                        <button class="btn-ghost text-xs text-rose-600 hover:bg-rose-50" wire:click="act('nsm_reject')">Reject</button>
                    @endif
                    <button class="btn-ghost text-xs" wire:click="cancelReview">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
