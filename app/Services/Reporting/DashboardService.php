<?php

namespace App\Services\Reporting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard KPIs. Reads the pre-aggregated summary tables, never the raw table
 * for totals (§8). Results are cached briefly so a burst of viewers doesn't
 * repeat the same aggregation.
 */
class DashboardService
{
    private const TTL = 120; // seconds

    public function kpis(): array
    {
        return Cache::remember('dashboard:kpis', self::TTL, function () {
            $totals = DB::table('model_summary')->selectRaw('
                COALESCE(SUM(total_imei), 0)  AS total_records,
                COALESCE(SUM(activated), 0)   AS total_activated,
                COALESCE(SUM(not_activated), 0) AS total_not_activated
            ')->first();

            $total = (int) $totals->total_records;
            $activated = (int) $totals->total_activated;

            return [
                'total_records' => $total,
                'total_activated' => $activated,
                'total_not_activated' => (int) $totals->total_not_activated,
                'activation_rate' => $total > 0 ? round($activated / $total * 100, 2) : 0.0,
                'distinct_rd' => (int) DB::table('rd_summary')->count(),
                'distinct_rt' => (int) DB::table('rt_summary')->count(),
                'distinct_model' => (int) DB::table('model_summary')->count(),
                'distinct_tso' => (int) DB::table('tso_summary')->count(),
            ];
        });
    }

    /** Daily sell-through vs activation series for the last N days. */
    public function dailySeries(int $days = 30): array
    {
        // Cached values are plain arrays only — config/cache.php sets
        // serializable_classes => false, so cached objects come back incomplete.
        return Cache::remember("dashboard:series:{$days}", self::TTL, function () use ($days) {
            // Anchor the window to the most recent ST date present (historical
            // imports may not reach "today"), falling back to today.
            $anchor = DB::table('daily_activation_summary')->max('st_date') ?? now()->toDateString();
            $from = Carbon::parse($anchor)->subDays($days)->toDateString();

            return DB::table('daily_activation_summary')
                ->selectRaw('st_date,
                    SUM(total_imei) AS sell_through,
                    SUM(activated) AS activated,
                    SUM(not_activated) AS not_activated')
                ->where('st_date', '>=', $from)
                ->groupBy('st_date')
                ->orderBy('st_date')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        });
    }

    /** @return array<int,array<string,mixed>> top N by volume */
    public function top(string $dimension, int $limit = 10): array
    {
        $table = match ($dimension) {
            'rd' => 'rd_summary',
            'rt' => 'rt_summary',
            'model' => 'model_summary',
            'tso' => 'tso_summary',
        };

        return Cache::remember("dashboard:top:{$dimension}:{$limit}", self::TTL, fn () => DB::table($table)
            ->orderByDesc('total_imei')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all());
    }

    public function lagBuckets(): array
    {
        return Cache::remember('dashboard:lag', self::TTL, function () {
            $row = DB::table('model_summary')->selectRaw('
                COALESCE(SUM(lag_d0),0) d0, COALESCE(SUM(lag_d1_7),0) d1_7,
                COALESCE(SUM(lag_d8_15),0) d8_15, COALESCE(SUM(lag_d16_30),0) d16_30,
                COALESCE(SUM(lag_d31_plus),0) d31_plus
            ')->first();

            return [
                '0 days' => (int) $row->d0,
                '1-7 days' => (int) $row->d1_7,
                '8-15 days' => (int) $row->d8_15,
                '16-30 days' => (int) $row->d16_30,
                '31+ days' => (int) $row->d31_plus,
            ];
        });
    }

    public function forget(): void
    {
        $keys = ['dashboard:kpis', 'dashboard:lag'];
        foreach ([14, 30, 60, 90] as $d) {
            $keys[] = "dashboard:series:{$d}";
        }
        foreach (['rd', 'rt', 'model', 'tso'] as $dim) {
            foreach ([8, 10] as $n) {
                $keys[] = "dashboard:top:{$dim}:{$n}";
            }
        }
        Cache::deleteMultiple($keys);
    }
}
