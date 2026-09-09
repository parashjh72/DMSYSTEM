<div wire:poll.30s>
    <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
    <p class="mt-1 text-sm text-gray-500">Figures are read from pre-aggregated summary tables.</p>

    @if ($health)
        @php
            $dot = fn ($ok) => $ok ? 'bg-green-500' : 'bg-red-500';
            $ago = fn ($s) => $s === null ? 'never' : ($s < 90 ? $s.'s ago' : round($s / 60).'m ago');
            $allOk = $health['scheduler']['ok'] && $health['queue']['ok'] && $health['failed_jobs'] === 0 && $health['stuck_imports'] === 0;
        @endphp
        <div class="card mt-4 {{ $allOk ? '' : 'ring-2 ring-amber-300' }}">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <span class="font-semibold {{ $allOk ? 'text-green-700' : 'text-amber-700' }}">
                    System {{ $allOk ? 'healthy' : 'needs attention' }}
                </span>

                <span class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ $dot($health['scheduler']['ok']) }}"></span>
                    Scheduler <span class="text-gray-400">{{ $ago($health['scheduler']['age_seconds']) }}</span>
                </span>

                <span class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ $dot($health['queue']['ok']) }}"></span>
                    Queue worker <span class="text-gray-400">{{ $ago($health['queue']['age_seconds']) }}</span>
                </span>

                <span class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full {{ $dot($health['pending_jobs'] === 0 || ($health['oldest_pending_min'] ?? 0) < 5) }}"></span>
                    Pending jobs: {{ number_format($health['pending_jobs']) }}
                    @if ($health['oldest_pending_min']) <span class="text-gray-400">(oldest {{ $health['oldest_pending_min'] }}m)</span> @endif
                </span>

                @if ($health['failed_jobs'] > 0)
                    <span class="flex items-center gap-2 text-red-600">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        Failed jobs: {{ number_format($health['failed_jobs']) }}
                    </span>
                @endif

                @if ($health['stuck_imports'] > 0)
                    <span class="flex items-center gap-2 text-red-600">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        Stuck imports: {{ $health['stuck_imports'] }}
                    </span>
                @endif

                <span class="text-xs text-gray-400">queue: {{ $health['queue_driver'] }}</span>
            </div>

            @unless ($allOk)
                <p class="mt-2 text-xs text-gray-500">
                    If the scheduler or queue worker is red, the cron isn't running on the server —
                    see <span class="font-mono">docs/SETUP.md</span> → "Shared hosting".
                </p>
            @endunless
        </div>
    @endif

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Total records', number_format($kpis['total_records'])],
            ['Activated', number_format($kpis['total_activated'])],
            ['Not activated', number_format($kpis['total_not_activated'])],
            ['Activation rate', $kpis['activation_rate'].'%'],
        ] as [$label, $value])
            <div class="card">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Distributors', $kpis['distinct_rd']],
            ['Retailers', $kpis['distinct_rt']],
            ['Models', $kpis['distinct_model']],
            ['TSOs', $kpis['distinct_tso']],
        ] as [$label, $value])
            <div class="card">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">Daily sell-through vs activation</h2>
                <select wire:model.live="seriesDays" class="input w-auto text-xs">
                    <option value="14">14 days</option>
                    <option value="30">30 days</option>
                    <option value="60">60 days</option>
                    <option value="90">90 days</option>
                </select>
            </div>
            @php
                $max = collect($series)->max('sell_through') ?: 1;
            @endphp
            <div class="mt-4 flex items-end gap-1 overflow-x-auto" style="height:160px">
                @forelse ($series as $d)
                    <div class="flex flex-1 min-w-[6px] flex-col justify-end" title="{{ $d['st_date'] }}: {{ $d['sell_through'] }} ST / {{ $d['activated'] }} act">
                        <div class="w-full rounded-t bg-indigo-500" style="height:{{ (int) ($d['sell_through'] / $max * 140) }}px"></div>
                        <div class="w-full bg-emerald-400" style="height:{{ (int) ($d['activated'] / $max * 140) }}px"></div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No data in range.</p>
                @endforelse
            </div>
            <div class="mt-2 flex gap-4 text-xs text-gray-500">
                <span><span class="inline-block h-2 w-2 rounded-sm bg-indigo-500"></span> Sell-through</span>
                <span><span class="inline-block h-2 w-2 rounded-sm bg-emerald-400"></span> Activated</span>
            </div>
        </div>

        <div class="card">
            <h2 class="text-sm font-semibold">ST → activation lag</h2>
            <div class="mt-3 space-y-2">
                @php $lagMax = max(array_values($lag)) ?: 1; @endphp
                @foreach ($lag as $bucket => $count)
                    <div>
                        <div class="flex justify-between text-xs text-gray-600">
                            <span>{{ $bucket }}</span><span>{{ number_format($count) }}</span>
                        </div>
                        <div class="mt-0.5 h-2 rounded bg-gray-100">
                            <div class="h-2 rounded bg-indigo-500" style="width:{{ (int) ($count / $lagMax * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        @foreach ([['Top distributors', $topRd, 'rd_code'], ['Top models', $topModel, 'model'], ['Top TSOs', $topTso, 'tso']] as [$title, $rows, $key])
            <div class="card">
                <h2 class="text-sm font-semibold">{{ $title }}</h2>
                <table class="mt-2 w-full">
                    <tbody>
                    @foreach ($rows as $r)
                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="td max-w-[140px] truncate">{{ $r[$key] }}</td>
                            <td class="td text-right font-medium">{{ number_format($r['total_imei']) }}</td>
                            <td class="td text-right text-gray-400">{{ $r['total_imei'] ? round($r['activated'] / $r['total_imei'] * 100) : 0 }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    @can('reports.view')
        <a href="{{ route('retailer-map') }}" wire:navigate class="card mt-6 flex items-center justify-between hover:ring-indigo-300">
            <div>
                <h2 class="text-sm font-semibold">Retailer Map</h2>
                <p class="mt-1 text-xs text-gray-500">See your retailers plotted by their saved GPS location.</p>
            </div>
            <span class="text-indigo-600">Open map →</span>
        </a>
    @endcan
</div>
