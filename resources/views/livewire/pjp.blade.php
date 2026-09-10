<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">PJP — Planned Journey Plan</h1>
            <p class="mt-1 text-sm text-gray-500">TSO plans → ASM reviews → <strong>NSM final approval</strong>.</p>
        </div>
    </div>

    @php
        $tabs = array_filter([
            'plan' => $this->canPlan() ? 'My Plan' : null,
            'asm' => $this->canAsm() ? 'ASM Review' : null,
            'nsm' => $this->canNsm() ? 'NSM Final Approval' : null,
            'report' => $this->canReport() ? 'Reports' : null,
        ]);
    @endphp
    @if (count($tabs) > 1)
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($tabs as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $tab === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    @error('day') <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    {{-- ============ MY PLAN (TSO) ============ --}}
    @if ($tab === 'plan' && $this->canPlan())
        <div class="card mt-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label">Month</label>
                <select class="input w-auto" wire:model.live="month">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Year</label>
                <select class="input w-auto" wire:model.live="year">
                    @foreach (range(now()->year - 1, now()->year + 1) as $y) <option value="{{ $y }}">{{ $y }}</option> @endforeach
                </select>
            </div>
            <div class="ml-auto flex items-center gap-3">
                <span class="badge {{ [
                    'draft' => 'bg-gray-100 text-gray-700', 'revision_required' => 'bg-amber-100 text-amber-800',
                    'final_approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800',
                ][$pjp->status] ?? 'bg-blue-100 text-blue-800' }}">{{ $pjp->statusLabel() }}</span>
                <span class="text-sm text-gray-500">{{ $pjp->planned_days }} days · {{ $pjp->planned_visits }} visits</span>
                @if ($pjp->isEditableByTso())
                    <button class="btn-primary" wire:click="submit"
                            wire:confirm="Submit this PJP for approval? You won't be able to edit it while it's under review.">Submit PJP</button>
                @endif
            </div>
        </div>

        {{-- Calendar --}}
        <div class="card mt-4 p-3">
            @php
                $first = \Illuminate\Support\Carbon::create($year, $month, 1);
                $lead = ($first->dayOfWeekIso - 1); // Mon=0
                $byDate = $days->keyBy(fn ($d) => $d->plan_date->toDateString());
            @endphp
            <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-gray-400">
                @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d) <div class="py-1">{{ $d }}</div> @endforeach
            </div>
            <div class="mt-1 grid grid-cols-7 gap-1">
                @for ($i = 0; $i < $lead; $i++) <div></div> @endfor
                @foreach ($days as $d)
                    @php $planned = $d->retailers_count; $visited = $d->visits_count; @endphp
                    <button wire:key="cal-{{ $d->id }}" wire:click="openDay({{ $d->id }})"
                            class="min-h-[64px] rounded-lg border p-1 text-left text-xs transition hover:border-indigo-300
                            {{ $openDayId === $d->id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }}">
                        <div class="font-semibold text-gray-600">{{ $d->plan_date->format('d') }}</div>
                        @if ($d->day_status === 'planned')
                            <div class="mt-1 text-indigo-700">{{ $planned }} RT</div>
                            @if ($visited) <div class="text-emerald-600">{{ $visited }} visited</div> @endif
                        @elseif ($d->day_status !== 'no_plan')
                            <div class="mt-1 text-gray-400">{{ $d->statusLabel() }}</div>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Day editor --}}
        @if ($openDay)
            <div class="card mt-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">{{ $openDay->plan_date->format('l, d M Y') }}</h2>
                    <button class="text-xs text-gray-400" wire:click="closeDay">close</button>
                </div>

                @if ($pjp->isEditableByTso())
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="label">Day</label>
                            <select class="input" wire:model="dayStatus">
                                @foreach ($dayStatuses as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Notes</label>
                            <input class="input" wire:model="dayNotes">
                        </div>
                    </div>

                    @if ($dayStatus === 'planned')
                        <div>
                            <label class="label">Retailers ({{ count($dayRetailers) }} selected)</label>
                            <input class="input" wire:model.live.debounce.300ms="rtSearch" placeholder="Search RT code / name / phone…">
                            <div class="mt-1 max-h-56 overflow-y-auto rounded-lg ring-1 ring-gray-200">
                                @forelse ($retailerOptions as $rt)
                                    <label wire:key="rt-{{ $rt->code }}"
                                           class="flex cursor-pointer items-start gap-2 px-3 py-1.5 text-xs hover:bg-indigo-50 {{ in_array($rt->code, $dayRetailers, true) ? 'bg-indigo-50' : '' }}">
                                        <input type="checkbox" class="mt-0.5 rounded border-gray-300"
                                               wire:click="toggleRt('{{ $rt->code }}')" @checked(in_array($rt->code, $dayRetailers, true))>
                                        <span class="flex-1">
                                            <span class="font-mono">{{ $rt->code }}</span> — {{ $rt->name }}
                                            <span class="block text-gray-400">
                                                RD {{ $rt->rd_code ?: '—' }}@if ($rt->area) · {{ $rt->area }} @endif@if ($rt->phone) · {{ $rt->phone }} @endif
                                            </span>
                                        </span>
                                    </label>
                                @empty
                                    <div class="px-3 py-2 text-xs text-gray-400">No retailers in your territory match.</div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <button class="btn-primary" wire:click="saveDay">Save day</button>
                @else
                    <p class="text-xs text-gray-500">This plan is locked for editing ({{ $pjp->statusLabel() }}).</p>
                    <div class="text-sm">
                        <span class="font-medium">{{ $openDay->statusLabel() }}</span>
                        @if ($openDay->notes) — {{ $openDay->notes }} @endif
                    </div>
                @endif

                @if ($openDay->retailers->isNotEmpty())
                    <div>
                        <p class="label">Planned retailers</p>
                        <ul class="mt-1 space-y-1 text-xs">
                            @foreach ($openDay->retailers as $r)
                                @php $v = $openDay->visits->firstWhere('rt_code', $r->rt_code); @endphp
                                <li class="flex items-center justify-between rounded bg-gray-50 px-2 py-1">
                                    <span><span class="font-mono">{{ $r->rt_code }}</span> {{ $r->rt_name }}</span>
                                    @if ($v)
                                        <span class="text-emerald-600">visited {{ $v->visited_at->timezone(config('pjp.timezone'))->format('d M H:i') }}</span>
                                    @elseif ($pjp->status === 'final_approved')
                                        <button class="text-indigo-600"
                                                x-data
                                                @click="navigator.geolocation.getCurrentPosition(
                                                    p => $wire.logVisit({{ $openDay->id }}, '{{ $r->rt_code }}', {latitude:p.coords.latitude, longitude:p.coords.longitude, accuracy:p.coords.accuracy}),
                                                    () => $wire.logVisit({{ $openDay->id }}, '{{ $r->rt_code }}', {}))">mark visited</button>
                                    @else
                                        <span class="text-gray-400">not visited</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
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

    {{-- ============ REVIEW PANEL (modal) ============ --}}
    @if ($reviewUuid && isset($reviewPjp) && $reviewPjp)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4" wire:click.self="cancelReview">
            <div class="my-6 w-full max-w-3xl rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-semibold">
                        PJP · {{ $reviewPjp->tso?->name }} · {{ $reviewPjp->monthLabel() }}
                        <span class="badge ml-2 bg-blue-100 text-blue-800">{{ $reviewPjp->statusLabel() }}</span>
                    </h2>
                    <button class="text-gray-400" wire:click="cancelReview">&times;</button>
                </div>
                <div class="max-h-[70vh] space-y-4 overflow-y-auto px-5 py-4">
                    <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                        <div><span class="text-xs text-gray-500">TSO</span><div>{{ $reviewPjp->tso?->name }}</div></div>
                        <div><span class="text-xs text-gray-500">ASM</span><div>{{ $reviewPjp->asm?->name ?? '—' }}</div></div>
                        <div><span class="text-xs text-gray-500">Planned days</span><div>{{ $reviewPjp->planned_days }}</div></div>
                        <div><span class="text-xs text-gray-500">Planned visits</span><div>{{ $reviewPjp->planned_visits }}</div></div>
                    </div>

                    <div>
                        <p class="label">Days</p>
                        <div class="mt-1 overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="text-gray-500"><tr><th class="px-2 py-1 text-left">Date</th><th class="px-2 py-1 text-left">Status</th><th class="px-2 py-1 text-left">Retailers</th></tr></thead>
                                <tbody>
                                    @foreach ($reviewPjp->days->where('day_status', '!=', 'no_plan') as $d)
                                        <tr class="border-t border-gray-100">
                                            <td class="px-2 py-1">{{ $d->plan_date->format('d M') }}</td>
                                            <td class="px-2 py-1">{{ $d->statusLabel() }}</td>
                                            <td class="px-2 py-1">{{ $d->retailers->pluck('rt_code')->join(', ') ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <p class="label">History</p>
                        <ul class="mt-1 space-y-1 text-xs text-gray-600">
                            @foreach ($reviewPjp->events as $e)
                                <li>{{ $e->created_at->timezone(config('pjp.timezone'))->format('d M H:i') }} —
                                    <strong>{{ str_replace('_', ' ', $e->action) }}</strong>
                                    by {{ $e->actor?->name ?? 'system' }}{{ $e->actor_role ? " ({$e->actor_role})" : '' }}
                                    @if ($e->comment) — “{{ $e->comment }}” @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <label class="label">Comment {{ $stage ?? '' }}</label>
                        <input class="input" wire:model="reviewNote">
                        @error('reviewNote') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-gray-100 px-5 py-3">
                    @if (in_array($reviewPjp->status, ['submitted', 'resubmitted', 'asm_review']) && $this->canAsm())
                        <button class="btn-primary" wire:click="act('asm_approve')">Approve &amp; forward to NSM</button>
                        <button class="btn-ghost text-amber-600" wire:click="act('asm_revision')">Request revision</button>
                    @elseif (in_array($reviewPjp->status, ['forwarded_to_nsm', 'nsm_review']) && $this->canNsm())
                        <button class="btn-primary" wire:click="act('nsm_final')">Final approve</button>
                        <button class="btn-ghost text-amber-600" wire:click="act('nsm_revision')">Request revision</button>
                        <button class="btn-ghost text-red-600" wire:click="act('nsm_reject')">Reject</button>
                    @endif
                    <button class="btn-ghost" wire:click="cancelReview">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
