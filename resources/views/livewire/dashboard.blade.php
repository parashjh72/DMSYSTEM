<div>
    <h1 class="text-xl font-semibold tracking-tight">Dashboard</h1>
    <p class="mt-1 text-sm text-gray-500">Figures are read from pre-aggregated summary tables.</p>

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
</div>
