<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Duty Rules &amp; Holidays</h1>
        <p class="mt-1 text-xs text-slate-500">How attendance is judged (late, half day, weekly off), how check-in points are enforced, and how often the field app sends location.</p>
    </div>

    @include('livewire.field-sales.partials.setup-tabs')

    @if ($flash)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-medium text-emerald-800">{{ $flash }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Policy form --}}
        <form wire:submit="savePolicy" class="card space-y-4 lg:col-span-3">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-900">Duty rules</h2>
                <div class="w-56">
                    <label class="label">Applies to</label>
                    <select class="input text-xs" wire:model.live="scope">
                        <option value="">Company default</option>
                        @foreach ($areas as $id => $name) <option value="{{ $id }}">Area: {{ $name }}</option> @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Duty starts</label>
                    <input type="time" class="input text-xs" wire:model="dutyStart">
                    @error('dutyStart') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Duty ends</label>
                    <input type="time" class="input text-xs" wire:model="dutyEnd">
                    @error('dutyEnd') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Late after (minutes of grace)</label>
                    <input type="number" min="0" class="input text-xs" wire:model="lateGraceMinutes">
                    @error('lateGraceMinutes') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Half day if worked less than (minutes)</label>
                    <input type="number" min="0" class="input text-xs" wire:model="halfDayBelowMinutes">
                    @error('halfDayBelowMinutes') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="label">Weekly off</label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($weekdays as $index => $day)
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 has-[:checked]:border-indigo-300 has-[:checked]:bg-indigo-50 has-[:checked]:text-indigo-700">
                            <input type="checkbox" class="sr-only" wire:model="weeklyOff" value="{{ $index }}"> {{ $day }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Check-in point rule</label>
                    <select class="input text-xs" wire:model="geofenceMode">
                        <option value="off">Off — don't check</option>
                        <option value="flag">Flag — allow, but mark outside punches</option>
                        <option value="block">Block — refuse punches outside a point</option>
                    </select>
                </div>
                <div>
                    <label class="label">Location every (minutes)</label>
                    <input type="number" min="1" max="60" class="input text-xs" wire:model="pingIntervalMinutes">
                    @error('pingIntervalMinutes') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" class="btn-primary text-xs">Save {{ $scope === '' ? 'company' : 'area' }} rules</button>

            @if ($overrides->isNotEmpty())
                <div class="border-t border-slate-100 pt-4">
                    <div class="label">Areas with their own rules</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($overrides as $override)
                            <span wire:key="override-{{ $override->id }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs">
                                {{ $override->area?->name }} · {{ $override->dutyStartTime() }}–{{ $override->dutyEndTime() }}
                                <button type="button" class="text-slate-400 hover:text-rose-600" wire:click="removeOverride({{ $override->area_id }})" wire:confirm="Remove this area's own rules?" title="Remove">&times;</button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </form>

        {{-- Daily summary --}}
        <form wire:submit="saveSummary" class="card h-fit space-y-4 lg:col-span-2">
            <h2 class="text-sm font-bold text-slate-900">Daily summary email</h2>
            <p class="text-xs text-slate-500">Each ASM gets an email with their team's attendance and km for the day.</p>
            <label class="flex items-center gap-2 text-xs font-medium text-slate-700">
                <input type="checkbox" class="rounded border-slate-300 text-indigo-600" wire:model="summaryEnabled"> Send daily summary
            </label>
            <div>
                <label class="label">Send at (Nepal time)</label>
                <input type="time" class="input text-xs" wire:model="summaryTime">
                @error('summaryTime') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary text-xs">Save</button>
        </form>
    </div>

    {{-- Holidays --}}
    <div class="card space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-sm font-bold text-slate-900">Holidays</h2>
            <div class="w-28">
                <label class="label">Year (AD)</label>
                <input type="number" class="input text-xs" wire:model.live.debounce.400ms="holidayYear">
            </div>
        </div>
        <form wire:submit="addHoliday" class="grid gap-3 sm:grid-cols-4">
            <div>
                <label class="label">Date <span class="font-normal text-slate-400">{{ $holidayDate ? $bs($holidayDate).' BS' : '' }}</span></label>
                <input type="date" class="input text-xs" wire:model.live="holidayDate">
                @error('holidayDate') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Name</label>
                <input class="input text-xs" wire:model="holidayName" placeholder="e.g. Dashain (Vijaya Dashami)">
                @error('holidayName') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Region</label>
                <select class="input text-xs" wire:model="holidayRegionId">
                    <option value="">All regions</option>
                    @foreach ($regions as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                </select>
            </div>
            <div class="flex items-end"><button type="submit" class="btn-primary text-xs">Add holiday</button></div>
        </form>
        <ul class="divide-y divide-slate-100 rounded-xl border border-slate-100">
            @forelse ($holidays as $holiday)
                <li wire:key="holiday-{{ $holiday->id }}" class="flex items-center justify-between px-3 py-2 text-xs">
                    <div>
                        <span class="font-semibold text-slate-900">{{ $holiday->holiday_date->format('D, d M Y') }}</span>
                        <span class="text-slate-400">· {{ $bs($holiday->holiday_date) }} BS</span>
                        <span class="ml-2 text-slate-700">{{ $holiday->name }}</span>
                        <span class="badge badge-slate ml-1">{{ $holiday->region?->name ?? 'All regions' }}</span>
                    </div>
                    <button class="font-semibold text-rose-600" wire:click="deleteHoliday({{ $holiday->id }})" wire:confirm="Remove this holiday?">Remove</button>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-xs text-slate-400">No holidays for this year.</li>
            @endforelse
        </ul>
    </div>
</div>
