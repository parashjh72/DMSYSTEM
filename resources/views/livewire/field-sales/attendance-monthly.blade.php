@php
    $cellStyles = [
        'present' => 'bg-emerald-50 text-emerald-700',
        'late' => 'bg-amber-50 text-amber-700',
        'half_day' => 'bg-orange-50 text-orange-700',
        'absent' => 'bg-rose-50 text-rose-700',
        'leave' => 'bg-sky-50 text-sky-700',
        'weekly_off' => 'bg-slate-100 text-slate-500',
        'holiday' => 'bg-violet-50 text-violet-700',
    ];
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Monthly Attendance</h1>
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">{{ $bsLabel }} BS</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">Daily status per field officer from check-in/out, leave, holidays and duty rules. Updated hourly.</p>
        </div>
        <div class="flex gap-2">
            <button class="btn-secondary text-xs" wire:click="recalculate" wire:loading.attr="disabled" wire:target="recalculate">
                <span wire:loading.remove wire:target="recalculate">Recalculate</span>
                <span wire:loading wire:target="recalculate">Working…</span>
            </button>
            <button class="btn-primary text-xs" wire:click="export">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span>Export CSV</span>
            </button>
        </div>
    </div>

    @if ($flash)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-medium text-emerald-800">{{ $flash }}</div>
    @endif

    {{-- Filters --}}
    <div class="card">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Month</label>
                <input type="month" class="input text-xs" wire:model.live="month">
            </div>
            <div>
                <label class="label">Region</label>
                <select class="input text-xs" wire:model.live="regionId">
                    <option value="">All regions</option>
                    @foreach ($options['regions'] as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Area</label>
                <select class="input text-xs" wire:model.live="areaId">
                    <option value="">All areas</option>
                    @foreach ($options['areas'] as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="label">Distributor (RD)</label>
                <select class="input text-xs" wire:model.live="rdCode">
                    <option value="">All distributors</option>
                    @foreach ($options['distributors'] as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-[11px]">
            @foreach (\App\Models\FieldSales\AttendanceDay::STATUSES as $key => [$code, $label])
                <span class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 font-semibold {{ $cellStyles[$key] }}">{{ $code }} <span class="font-medium">{{ $label }}</span></span>
            @endforeach
        </div>
    </div>

    {{-- Grid --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-[11px]">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-500">
                        <th class="th sticky left-0 z-10 min-w-44 bg-slate-50 text-left">Field officer <span class="block text-[9px] font-medium normal-case tracking-normal text-indigo-400">AD date · BS day</span></th>
                        @foreach ($dates as $d)
                            <th class="min-w-7 whitespace-nowrap px-0.5 py-2 text-center font-semibold leading-tight {{ $d->toDateString() === $today ? 'text-indigo-700' : ($d->isSaturday() ? 'text-slate-400' : '') }}"
                                title="{{ $d->format('D, d M Y') }} · {{ \App\Support\NepaliDate::formatLong($d) }} BS">
                                <div class="text-[9px] font-medium uppercase text-slate-400">{{ substr($d->format('D'), 0, 2) }}</div>
                                <div>{{ $d->format('j') }}</div>
                                <div class="text-[9px] font-medium text-indigo-400">{{ \App\Support\NepaliDate::fromAd($d)['day'] ?? '' }}</div>
                            </th>
                        @endforeach
                        <th class="th text-center">P</th>
                        <th class="th text-center">L</th>
                        <th class="th text-center">H</th>
                        <th class="th text-center">A</th>
                        <th class="th text-center">LV</th>
                        <th class="th text-right">Km</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        @php $days = $grid[$user->id] ?? []; $t = $totalsFor($days); @endphp
                        <tr wire:key="month-{{ $user->id }}" class="hover:bg-slate-50/70">
                            <td class="td sticky left-0 z-10 bg-white">
                                <div class="font-bold text-slate-900">{{ $user->name }}</div>
                                <div class="text-[10px] text-slate-400">{{ implode(', ', $user->scopedRdCodes()) }}</div>
                            </td>
                            @foreach ($dates as $d)
                                @php $day = $days[$d->toDateString()] ?? null; @endphp
                                <td class="px-0.5 py-1 text-center">
                                    @if ($day?->status)
                                        <span title="{{ \App\Models\FieldSales\AttendanceDay::STATUSES[$day->status][1] }}{{ $day->late_minutes ? ' · '.$day->late_minutes.' min late' : '' }}{{ $day->missed_checkout ? ' · missed check-out' : '' }}{{ $day->check_in_geofence === 'outside' ? ' · outside geofence' : '' }}"
                                              class="relative inline-flex h-6 min-w-6 items-center justify-center rounded-md px-1 font-bold {{ $cellStyles[$day->status] }}">
                                            {{ \App\Models\FieldSales\AttendanceDay::STATUSES[$day->status][0] }}
                                            @if ($day->check_in_geofence === 'outside' || $day->missed_checkout)
                                                <span class="absolute -right-0.5 -top-0.5 h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-slate-200">·</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="td text-center font-semibold text-emerald-700">{{ $t['present'] }}</td>
                            <td class="td text-center font-semibold text-amber-700">{{ $t['late'] }}</td>
                            <td class="td text-center font-semibold text-orange-700">{{ $t['half_day'] }}</td>
                            <td class="td text-center font-semibold text-rose-700">{{ $t['absent'] }}</td>
                            <td class="td text-center font-semibold text-sky-700">{{ $t['leave'] }}</td>
                            <td class="td text-right font-mono">{{ number_format((float) ($km[$user->id] ?? 0), 1) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($dates) + 7 }}" class="px-4 py-10 text-center text-xs text-slate-400">No field staff match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-4 py-3">{{ $users->links() }}</div>
    </div>
    <p class="text-[11px] text-slate-400">A red dot marks a check-in outside the assigned geofence or a missed check-out. Hover a cell for details.</p>
</div>
