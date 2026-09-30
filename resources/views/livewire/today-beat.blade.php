<div class="space-y-3"
     x-data="{
        here: null,
        saving: false,
        init() {
            navigator.geolocation?.getCurrentPosition(
                (p) => { this.here = { lat: p.coords.latitude, lng: p.coords.longitude }; },
                () => {},
                { enableHighAccuracy: true, maximumAge: 60000, timeout: 20000 },
            );
        },
        km(lat, lng) {
            if (! this.here || lat === null || lng === null) return '';
            const r = 6371, rad = (d) => d * Math.PI / 180;
            const dLat = rad(lat - this.here.lat), dLng = rad(lng - this.here.lng);
            const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(this.here.lat)) * Math.cos(rad(lat)) * Math.sin(dLng / 2) ** 2;
            const d = 2 * r * Math.asin(Math.sqrt(h));
            return d < 1 ? Math.round(d * 1000) + ' m' : d.toFixed(1) + ' km';
        },
        save() {
            if (this.saving) return;
            this.saving = true;
            const done = (gps) => $wire.saveVisit(gps).finally(() => { this.saving = false; });
            if (! navigator.geolocation) { done({}); return; }
            navigator.geolocation.getCurrentPosition(
                (p) => done({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy }),
                () => done({}),
                { enableHighAccuracy: true, maximumAge: 30000, timeout: 15000 },
            );
        },
     }">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <h2 class="text-sm font-bold text-slate-900">Today's Beat</h2>
            <span class="badge-indigo text-[10px] font-bold">{{ now(config('pjp.timezone'))->format('D, d M') }}</span>
        </div>
        <a href="{{ route('pjp') }}" wire:navigate class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">Beat Plan &rarr;</a>
    </div>

    @if (session()->has('beat-status'))
        <div class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 border border-emerald-200">{{ session('beat-status') }}</div>
    @endif

    @if (! $day || $planned === 0)
        <div class="card !p-4 text-xs text-slate-500">
            No parties are planned for today. Add them to today's date in your <a href="{{ route('pjp') }}" wire:navigate class="font-semibold text-indigo-600 hover:underline">Beat Plan</a>.
        </div>
    @else
        {{-- Call status --}}
        @php
            $pct = $planned > 0 ? $visited / $planned : 0;
            $circumference = 2 * M_PI * 26;
            $mapped = $parties->filter(fn ($p) => $p['lat'] !== null)->values();
        @endphp
        <div class="card !p-4 flex items-center justify-between gap-4">
            <div class="min-w-0 space-y-1 text-xs">
                <div class="font-semibold text-slate-900">{{ $day->pjp->monthLabel() }} beat · {{ $planned }} {{ \Illuminate\Support\Str::plural('party', $planned) }}</div>
                <div class="text-slate-500">{{ $effective }} effective · {{ $visited - $effective }} no order · {{ $planned - $visited }} pending</div>
                @if ($orderValue > 0)
                    <div class="font-semibold text-emerald-700">Orders today: {{ number_format($orderValue, 2) }}</div>
                @endif
                @if (! $canRecord)
                    <div class="text-amber-700 font-semibold">Plan status: {{ $day->pjp->statusLabel() }} — visits open after final approval.</div>
                @endif
                @if ($day->notes)
                    <div class="text-slate-400">{{ $day->notes }}</div>
                @endif
            </div>
            <div class="shrink-0 text-center">
                <div class="relative h-16 w-16">
                    <svg viewBox="0 0 64 64" class="h-16 w-16 -rotate-90">
                        <circle cx="32" cy="32" r="26" fill="none" stroke="#e2e8f0" stroke-width="6"/>
                        <circle cx="32" cy="32" r="26" fill="none" stroke="#f59e0b" stroke-width="6" stroke-linecap="round"
                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - $pct) }}"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center text-sm font-extrabold text-slate-800">{{ $visited }}/{{ $planned }}</div>
                </div>
                <div class="text-[10px] font-semibold text-teal-700">Call Status</div>
            </div>
        </div>

        {{-- Search + route --}}
        <div class="flex items-center gap-2">
            <input class="input text-xs" wire:model.live.debounce.300ms="search" placeholder="Search party…">
            @if ($mapped->isNotEmpty())
                @php
                    $last = $mapped->last();
                    $waypoints = $mapped->slice(0, -1)->take(9)->map(fn ($p) => $p['lat'].','.$p['lng'])->implode('|');
                @endphp
                <a class="btn-ghost !px-3 text-xs shrink-0" target="_blank" rel="noopener" title="Open today's route in Google Maps"
                   href="https://www.google.com/maps/dir/?api=1&travelmode=driving&destination={{ $last['lat'] }},{{ $last['lng'] }}{{ $waypoints !== '' ? '&waypoints='.urlencode($waypoints) : '' }}">
                    <svg class="h-4 w-4 text-orange-600" fill="currentColor" viewBox="0 0 24 24"><path d="M9 3 3 5.5v15.5l6-2.5 6 2.5 6-2.5V3l-6 2.5L9 3zm0 2.2 6 2.5v11.1l-6-2.5V5.2z"/></svg>
                    <span>Route</span>
                </a>
            @endif
        </div>

        {{-- Parties --}}
        <div class="space-y-2">
            @forelse ($parties as $p)
                @php $v = $p['visit']; @endphp
                <div wire:key="beat-{{ $p['code'] }}" class="card !p-3.5 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900 truncate">{{ $p['name'] ?: $p['code'] }}</div>
                            <div class="text-[11px] text-slate-500 truncate">
                                <span class="font-mono text-indigo-700">{{ $p['code'] }}</span>@if ($p['place']) · {{ $p['place'] }} @endif
                            </div>
                        </div>
                        @if ($p['lat'] !== null)
                            <span class="shrink-0 text-[11px] font-semibold text-slate-500" x-text="km({{ $p['lat'] }}, {{ $p['lng'] }})"></span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-2">
                        <div class="text-[11px] font-semibold min-w-0 truncate">
                            @if ($v?->isEffective())
                                <span class="text-emerald-700">✓ Order received · {{ number_format((float) $v->order_value, 2) }}</span>
                            @elseif ($v?->isNonEffective())
                                <span class="text-rose-600">✕ No order · {{ $v->reasonLabel() }}</span>
                            @elseif ($v)
                                <span class="text-emerald-700">Visited {{ $v->visited_at->timezone(config('pjp.timezone'))->format('h:i A') }}</span>
                            @else
                                <span class="text-slate-400">Not visited yet</span>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($p['phone'])
                                <a href="tel:{{ $p['phone'] }}" class="rounded-full p-1.5 text-teal-700 hover:bg-teal-50" title="Call {{ $p['phone'] }}">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6.6 10.8a15.1 15.1 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11.4 11.4 0 003.6.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1l-2.2 2.2z"/></svg>
                                </a>
                            @endif
                            @if ($canRecord)
                                <button type="button" wire:click="openVisit('{{ $p['code'] }}')"
                                        class="{{ $v ? 'btn-ghost' : 'btn-primary' }} !py-1 !px-2.5 text-[11px] font-bold">
                                    {{ $v ? 'Edit visit' : 'Record visit' }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="card !p-4 text-xs text-slate-400 text-center">No parties match your search.</div>
            @endforelse
        </div>
    @endif

    {{-- Visit form --}}
    @if ($visitRt)
        @php $party = $parties->firstWhere('code', $visitRt); @endphp
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/60 p-0 sm:p-4" wire:key="visit-form-{{ $visitRt }}">
            <div class="w-full max-w-md max-h-[92vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl bg-white p-5 shadow-2xl space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">{{ $party['name'] ?? $visitRt }}</h3>
                        <p class="text-[11px] text-slate-500 font-mono">{{ $visitRt }}</p>
                    </div>
                    <button type="button" wire:click="closeVisit" class="text-xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="$set('outcome', 'effective')"
                            class="rounded-xl py-2.5 text-xs font-bold border transition {{ $outcome === 'effective' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200' }}">
                        Order received
                    </button>
                    <button type="button" wire:click="$set('outcome', 'non_effective')"
                            class="rounded-xl py-2.5 text-xs font-bold border transition {{ $outcome === 'non_effective' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white text-slate-600 border-slate-200' }}">
                        No order
                    </button>
                </div>

                @if ($outcome === 'effective')
                    <div class="space-y-2">
                        <label class="label !mb-0">Order details</label>
                        @php $total = 0; @endphp
                        @foreach ($lines as $i => $line)
                            @php
                                $price = $prices[$line['model']] ?? null;
                                $amount = $price !== null ? $price * (int) $line['qty'] : null;
                                $total += $amount ?? 0;
                            @endphp
                            <div class="flex items-center gap-2" wire:key="line-{{ $i }}">
                                <select class="input text-xs flex-1" wire:model.live="lines.{{ $i }}.model">
                                    <option value="">Select product…</option>
                                    @foreach ($modelOptions as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                                </select>
                                <input type="number" min="1" inputmode="numeric" class="input text-xs !w-20" wire:model.live.debounce.400ms="lines.{{ $i }}.qty">
                                <button type="button" wire:click="removeLine({{ $i }})" class="text-slate-400 hover:text-rose-600 px-1" title="Remove">&times;</button>
                            </div>
                            @if ($line['model'] !== '')
                                <div class="text-[11px] text-slate-500 -mt-1 pl-1">
                                    {{ $price !== null ? number_format($price, 2).' × '.(int) $line['qty'].' = '.number_format($amount, 2) : 'No list price set' }}
                                </div>
                            @endif
                        @endforeach
                        <div class="flex items-center justify-between">
                            <button type="button" wire:click="addLine" class="text-xs font-semibold text-indigo-600 hover:underline">+ Add product</button>
                            <div class="text-xs font-bold text-slate-900">Total {{ number_format($total, 2) }}</div>
                        </div>
                    </div>
                @else
                    <div>
                        <label class="label">Why was no order placed?</label>
                        <select class="input text-xs" wire:model.live="reason">
                            <option value="">Select a reason…</option>
                            @foreach ($reasons as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="label">Remarks {{ $outcome === 'non_effective' && $reason === 'other' ? '(required)' : '(optional)' }}</label>
                    <textarea class="input text-xs" rows="2" wire:model="note" placeholder="Meeting notes, follow-up, competitor info…"></textarea>
                </div>

                @error('visit') <p class="text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                @error('lines.*.qty') <p class="text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="closeVisit" class="btn-secondary text-xs">Cancel</button>
                    <button type="button" @click="save()" :disabled="saving" class="btn-primary text-xs">
                        <span x-text="saving ? 'Saving…' : 'Save visit (GPS)'">Save visit (GPS)</span>
                    </button>
                </div>
                <p class="text-[10px] text-slate-400 text-center">Your current GPS location is saved with the visit.</p>
            </div>
        </div>
    @endif
</div>
