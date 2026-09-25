<div wire:poll.30s class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Executive Dashboard</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Live Sync
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">Distribution pipeline performance, device activations, and field metrics.</p>
        </div>

        <div class="flex items-center gap-2">
            @can('imports.create')
                <a href="{{ route('imports.index') }}" wire:navigate class="btn-ghost text-xs">
                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Import Data</span>
                </a>
            @endcan
            @can('reports.view')
                <a href="{{ route('reports') }}" wire:navigate class="btn-primary text-xs">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    <span>View Reports</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- System Health Monitor --}}
    @if ($health)
        @php
            $dot = fn ($ok) => $ok ? 'bg-emerald-500' : 'bg-rose-500';
            $ago = fn ($s) => $s === null ? 'never' : ($s < 90 ? $s.'s ago' : round($s / 60).'m ago');
            $allOk = $health['scheduler']['ok'] && $health['queue']['ok'] && $health['failed_jobs'] === 0 && $health['stuck_imports'] === 0;
        @endphp
        <div class="rounded-2xl border p-4 shadow-xs transition {{ $allOk ? 'bg-white border-slate-200/80' : 'bg-amber-50/50 border-amber-200 ring-2 ring-amber-300/40' }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium">
                    <div class="flex items-center gap-2">
                        <span class="flex h-2.5 w-2.5 rounded-full {{ $allOk ? 'bg-emerald-500 ring-4 ring-emerald-100' : 'bg-amber-500 ring-4 ring-amber-100' }}"></span>
                        <span class="font-bold {{ $allOk ? 'text-emerald-800' : 'text-amber-800' }}">
                            {{ $allOk ? 'System Operational' : 'Action Required' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5 text-slate-600 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100">
                        <span class="h-2 w-2 rounded-full {{ $dot($health['scheduler']['ok']) }}"></span>
                        <span class="text-slate-500">Scheduler:</span>
                        <span class="font-semibold text-slate-700">{{ $ago($health['scheduler']['age_seconds']) }}</span>
                    </div>

                    <div class="flex items-center gap-1.5 text-slate-600 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100">
                        <span class="h-2 w-2 rounded-full {{ $dot($health['queue']['ok']) }}"></span>
                        <span class="text-slate-500">Queue Worker:</span>
                        <span class="font-semibold text-slate-700">{{ $ago($health['queue']['age_seconds']) }}</span>
                    </div>

                    <div class="flex items-center gap-1.5 text-slate-600 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100">
                        <span class="h-2 w-2 rounded-full {{ $dot($health['pending_jobs'] === 0 || ($health['oldest_pending_min'] ?? 0) < 5) }}"></span>
                        <span class="text-slate-500">Pending Jobs:</span>
                        <span class="font-semibold text-slate-700">{{ number_format($health['pending_jobs']) }}</span>
                        @if ($health['oldest_pending_min'])
                            <span class="text-slate-400">({{ $health['oldest_pending_min'] }}m)</span>
                        @endif
                    </div>

                    @if ($health['failed_jobs'] > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 px-2.5 py-1 text-rose-700 ring-1 ring-rose-200">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            Failed Jobs: <strong class="font-bold">{{ number_format($health['failed_jobs']) }}</strong>
                        </span>
                    @endif

                    @if ($health['stuck_imports'] > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 px-2.5 py-1 text-rose-700 ring-1 ring-rose-200">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            Stuck Imports: <strong class="font-bold">{{ $health['stuck_imports'] }}</strong>
                        </span>
                    @endif
                </div>

                <div class="text-[11px] font-medium text-slate-400">
                    Hostinger Shared / Driver: <span class="font-semibold text-slate-600">{{ $health['queue_driver'] }}</span>
                </div>
            </div>

            @unless ($allOk)
                <p class="mt-3 text-xs text-amber-700 border-t border-amber-200/60 pt-2 font-medium">
                    ⚠️ If scheduler or queue worker is stale, check crontab setup on Hostinger cPanel.
                </p>
            @endunless
        </div>
    @endif

    {{-- Primary KPI Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        {{-- Total Records --}}
        <div class="card card-hover relative overflow-hidden !p-4 sm:!p-5">
            <div class="flex items-center justify-between">
                <span class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-slate-500">Total Devices</span>
                <span class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-4 w-4 sm:h-4.5 sm:w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($kpis['total_records']) }}</span>
            </div>
            <p class="mt-1 text-[11px] sm:text-xs text-slate-400 truncate">Total tracked IMEIs</p>
        </div>

        {{-- Activated --}}
        <div class="card card-hover relative overflow-hidden !p-4 sm:!p-5">
            <div class="flex items-center justify-between">
                <span class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-slate-500">Activated</span>
                <span class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-4 w-4 sm:h-4.5 sm:w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 sm:mt-3 flex items-baseline gap-1.5 sm:gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight text-emerald-700">{{ number_format($kpis['total_activated']) }}</span>
                <span class="badge-emerald text-[10px] sm:text-[11px] font-bold">Active</span>
            </div>
            <p class="mt-1 text-[11px] sm:text-xs text-slate-400 truncate">Activated &amp; online</p>
        </div>

        {{-- In Channel (Not Activated) --}}
        <div class="card card-hover relative overflow-hidden !p-4 sm:!p-5">
            <div class="flex items-center justify-between">
                <span class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-slate-500">In Channel</span>
                <span class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-4 w-4 sm:h-4.5 sm:w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </span>
            </div>
            <div class="mt-2 sm:mt-3 flex items-baseline gap-1.5 sm:gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight text-amber-700">{{ number_format($kpis['total_not_activated']) }}</span>
                <span class="badge-amber text-[10px] sm:text-[11px] font-bold">Stock</span>
            </div>
            <p class="mt-1 text-[11px] sm:text-xs text-slate-400 truncate">Sold to retailers</p>
        </div>

        {{-- Activation Rate --}}
        <div class="card card-hover relative overflow-hidden !p-4 sm:!p-5">
            <div class="flex items-center justify-between">
                <span class="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-slate-500">Activation Rate</span>
                <span class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                    <svg class="h-4 w-4 sm:h-4.5 sm:w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
            </div>
            <div class="mt-2 sm:mt-3 flex items-baseline gap-2">
                <span class="text-2xl sm:text-3xl font-extrabold tracking-tight text-indigo-700">{{ $kpis['activation_rate'] }}%</span>
            </div>
            <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-full" style="width: {{ min(100, (float)$kpis['activation_rate']) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Secondary Network Scope Counters --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach ([
            ['Distributors (RD)', $kpis['distinct_rd'], 'bg-blue-50 text-blue-600'],
            ['Retailers (RT)', $kpis['distinct_rt'], 'bg-emerald-50 text-emerald-600'],
            ['Phone Models', $kpis['distinct_model'], 'bg-purple-50 text-purple-600'],
            ['Field Officers (TSO)', $kpis['distinct_tso'], 'bg-orange-50 text-orange-600'],
        ] as [$label, $value, $color])
            <div class="card !p-3.5 sm:!p-5 flex items-center justify-between">
                <div class="min-w-0 pr-2">
                    <div class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-400 truncate">{{ $label }}</div>
                    <div class="mt-1 text-xl sm:text-2xl font-bold text-slate-800">{{ number_format($value) }}</div>
                </div>
                <div class="h-8 w-8 sm:h-9 sm:w-9 rounded-xl flex items-center justify-center shrink-0 {{ $color }}">
                    <svg class="h-4 w-4 sm:h-4.5 sm:w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Activity Chart & Lag Breakdown --}}
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Daily Trends --}}
        <div class="card lg:col-span-2 flex flex-col justify-between">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Daily Sell-Through vs. Activation</h2>
                        <p class="text-xs text-slate-400">Distribution volume compared against retail activations</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="seriesDays" class="text-xs font-semibold text-slate-500">Timeline:</label>
                        <select id="seriesDays" wire:model.live="seriesDays" class="input !w-auto text-xs py-1.5 px-3">
                            <option value="14">Last 14 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="60">Last 60 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>
                </div>

                @php
                    $max = collect($series)->max('sell_through') ?: 1;
                @endphp
                <div class="mt-6 flex items-end gap-1.5 overflow-x-auto pb-2" style="height:190px">
                    @forelse ($series as $d)
                        <div class="group relative flex flex-1 min-w-[10px] flex-col justify-end items-center h-full">
                            {{-- Bars --}}
                            <div class="w-full max-w-[14px] flex flex-col justify-end gap-0.5 h-full">
                                <div class="w-full rounded-t-sm bg-indigo-500 group-hover:bg-indigo-600 transition"
                                     style="height:{{ max(3, (int) ($d['sell_through'] / $max * 150)) }}px"></div>
                                <div class="w-full rounded-b-sm bg-emerald-400 group-hover:bg-emerald-500 transition"
                                     style="height:{{ max(2, (int) ($d['activated'] / $max * 150)) }}px"></div>
                            </div>

                            {{-- Tooltip --}}
                            <div class="pointer-events-none absolute bottom-full mb-2 hidden group-hover:flex flex-col items-center z-20">
                                <div class="rounded-xl bg-slate-900 px-2.5 py-1.5 text-[10px] font-medium text-white shadow-xl whitespace-nowrap">
                                    <div class="font-bold text-slate-200">{{ $d['st_date'] }}</div>
                                    <div class="text-indigo-300">Sell-through: {{ number_format($d['sell_through']) }}</div>
                                    <div class="text-emerald-300">Activated: {{ number_format($d['activated']) }}</div>
                                </div>
                                <div class="h-1.5 w-1.5 bg-slate-900 rotate-45 -mt-1"></div>
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full w-full items-center justify-center text-xs text-slate-400">
                            No transaction records found for this period.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-indigo-500"></span>
                        <span class="font-medium text-slate-700">Sell-Through (RD &rarr; RT)</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-xs bg-emerald-400"></span>
                        <span class="font-medium text-slate-700">Customer Activation</span>
                    </span>
                </div>
                <span class="text-[11px] text-slate-400">Hover bars to view details</span>
            </div>
        </div>

        {{-- Activation Lag distribution --}}
        <div class="card flex flex-col justify-between">
            <div>
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-bold text-slate-900">ST &rarr; Activation Lag</h2>
                    <p class="text-xs text-slate-400">Time taken from retail delivery to consumer activation</p>
                </div>

                <div class="mt-5 space-y-3.5">
                    @php $lagMax = max(array_values($lag)) ?: 1; @endphp
                    @foreach ($lag as $bucket => $count)
                        <div>
                            <div class="flex justify-between text-xs font-semibold text-slate-700">
                                <span>{{ $bucket }}</span>
                                <span class="font-bold text-slate-900">{{ number_format($count) }}</span>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-300"
                                     style="width:{{ (int) ($count / $lagMax * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 rounded-xl bg-indigo-50/70 p-3 text-[11px] text-indigo-800 border border-indigo-100">
                <strong>Insight:</strong> Faster activation latency indicates healthy retail shelf-turnover.
            </div>
        </div>
    </div>

    {{-- Top Rankings (Distributors, Models, TSOs) --}}
    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ([
            ['Top Distributors', $topRd, 'rd_code', 'Distributor Code'],
            ['Top Device Models', $topModel, 'model', 'Model Name'],
            ['Top Field Officers (TSO)', $topTso, 'tso', 'TSO Name']
        ] as [$title, $rows, $key, $headerLabel])
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-bold text-slate-900">{{ $title }}</h2>
                    <span class="text-[11px] font-medium text-slate-400">Ranked by volume</span>
                </div>

                <div class="mt-3 overflow-hidden">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100">
                                <th class="text-left font-semibold py-2 px-1">#</th>
                                <th class="text-left font-semibold py-2 px-2">{{ $headerLabel }}</th>
                                <th class="text-right font-semibold py-2 px-2">Volume</th>
                                <th class="text-right font-semibold py-2 px-1">Act. %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($rows as $index => $r)
                                @php
                                    $rate = $r['total_imei'] ? round($r['activated'] / $r['total_imei'] * 100) : 0;
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2.5 px-1 font-bold text-slate-400">{{ $index + 1 }}</td>
                                    <td class="py-2.5 px-2 font-medium text-slate-800 max-w-[120px] truncate" title="{{ $r[$key] }}">
                                        {{ $r[$key] ?: 'Unknown' }}
                                    </td>
                                    <td class="py-2.5 px-2 text-right font-bold text-slate-900">
                                        {{ number_format($r['total_imei']) }}
                                    </td>
                                    <td class="py-2.5 px-1 text-right">
                                        <span class="inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-bold {{ $rate >= 70 ? 'bg-emerald-50 text-emerald-700' : ($rate >= 40 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                            {{ $rate }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-slate-400 text-xs">No records available</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Map Banner Link --}}
    @can('reports.view')
        <a href="{{ route('retailer-map') }}" wire:navigate
           class="group flex flex-col sm:flex-row sm:items-center sm:justify-between rounded-2xl bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 p-6 text-white shadow-md hover:shadow-lg transition-all duration-200">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20 text-white backdrop-blur-md">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Geographic Retailer Network Map</h3>
                    <p class="text-xs text-indigo-200 mt-0.5">Explore active retailers plotted by GPS coordinates, sales volume, and territory boundaries.</p>
                </div>
            </div>
            <div class="mt-4 sm:mt-0 flex items-center gap-2 font-semibold text-xs text-white group-hover:translate-x-1 transition-transform">
                <span>Launch Interactive Map</span>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </div>
        </a>
    @endcan
</div>
