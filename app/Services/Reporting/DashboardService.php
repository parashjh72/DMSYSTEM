<?php

namespace App\Services\Reporting;

use App\Support\RecordScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard KPIs. Reads the pre-aggregated summary tables, never the raw table
 * for totals (§8). Results are cached briefly so a burst of viewers doesn't
 * repeat the same aggregation.
 *
 * Row-level scoping: users with scoped RD codes (RD / ASM / TSO roles) see
 * ONLY their own distributors' numbers. Unscoped roles (Super Admin, Admin,
 * NSM) see company-wide figures. Scoped cache keys are namespaced per user so
 * numbers can never leak across accounts via the shared cache.
 */
class DashboardService
{
    private const TTL = 120; // seconds

    /** @return list<string>|null  null = unrestricted (company-wide) */
    private function scopedCodes(): ?array
    {
        return RecordScope::rdCodes();
    }

    private function cacheKey(string $base): string
    {
        return $this->scopedCodes() === null ? $base : $base.':scoped:'.auth()->id();
    }

    public function kpis(): array
    {
        return Cache::remember($this->cacheKey('dashboard:kpis'), self::TTL, function () {
            $codes = $this->scopedCodes();
            $scoped = $codes !== null;

            $totals = DB::table('rd_summary')
                ->when($scoped, fn ($q) => $q->whereIn('rd_code', $codes))
                ->selectRaw('
                    COALESCE(SUM(total_imei), 0)  AS total_records,
                    COALESCE(SUM(activated), 0)   AS total_activated,
                    COALESCE(SUM(not_activated), 0) AS total_not_activated
                ')->first();

            $total = (int) $totals->total_records;
            $activated = (int) $totals->total_activated;

            $rdRows = DB::table('rd_summary')
                ->when($scoped, fn ($q) => $q->whereIn('rd_code', $codes));

            if ($scoped) {
                // Bounded by the user's RD codes; (rd_code, …) indexes keep
                // these cheap even at 10M+ raw rows.
                $distinctRt = (int) DB::table('rt_summary')->whereIn('rd_code', $codes)->count();
                $distinctModel = (int) DB::table('sales_activation_records')->whereIn('rd_code', $codes)->distinct()->count('model');
                $distinctTso = (int) DB::table('sales_activation_records')->whereIn('rd_code', $codes)->distinct()->count('tso');
            } else {
                $distinctRt = (int) DB::table('rt_summary')->count();
                $distinctModel = (int) DB::table('model_summary')->count();
                $distinctTso = (int) DB::table('tso_summary')->count();
            }

            return [
                'total_records' => $total,
                'total_activated' => $activated,
                'total_not_activated' => (int) $totals->total_not_activated,
                'activation_rate' => $total > 0 ? round($activated / $total * 100, 2) : 0.0,
                'distinct_rd' => (int) (clone $rdRows)->count(),
                'distinct_rt' => $distinctRt,
                'distinct_model' => $distinctModel,
                'distinct_tso' => $distinctTso,
            ];
        });
    }

    /** Daily sell-through vs activation series for the last N days. */
    public function dailySeries(int $days = 30): array
    {
        // Cached values are plain arrays only — config/cache.php sets
        // serializable_classes => false, so cached objects come back incomplete.
        return Cache::remember($this->cacheKey("dashboard:series:{$days}"), self::TTL, function () use ($days) {
            $codes = $this->scopedCodes();

            if ($codes === null) {
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
            }

            // Scoped: bounded raw query via the (rd_code, st_date) index.
            $anchor = DB::table('sales_activation_records')->whereIn('rd_code', $codes)->max('st_date')
                ?? now()->toDateString();
            $from = Carbon::parse($anchor)->subDays($days)->toDateString();

            return DB::table('sales_activation_records')
                ->selectRaw('st_date,
                    COUNT(*) AS sell_through,
                    COALESCE(SUM(is_activated), 0) AS activated,
                    COALESCE(SUM(1 - is_activated), 0) AS not_activated')
                ->whereIn('rd_code', $codes)
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
        return Cache::remember($this->cacheKey("dashboard:top:{$dimension}:{$limit}"), self::TTL, function () use ($dimension, $limit) {
            $codes = $this->scopedCodes();

            if ($dimension === 'rd') {
                return DB::table('rd_summary')
                    ->when($codes !== null, fn ($q) => $q->whereIn('rd_code', $codes))
                    ->orderByDesc('total_imei')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
            }

            if ($dimension === 'rt') {
                // rt_summary carries rd_code, so it scopes cleanly.
                return DB::table('rt_summary')
                    ->when($codes !== null, fn ($q) => $q->whereIn('rd_code', $codes))
                    ->orderByDesc('total_imei')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
            }

            if ($codes === null) {
                $table = match ($dimension) {
                    'model' => 'model_summary',
                    'tso' => 'tso_summary',
                };

                return DB::table($table)
                    ->orderByDesc('total_imei')
                    ->limit($limit)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
            }

            // Scoped model/tso leaderboards: no per-RD grain exists in the
            // dimension summaries, so aggregate raw bounded by the user's codes.
            $column = $dimension === 'model' ? 'model' : 'tso';

            return DB::table('sales_activation_records')
                ->selectRaw("{$column}, COUNT(*) AS total_imei, COALESCE(SUM(is_activated), 0) AS activated")
                ->whereIn('rd_code', $codes)
                ->groupBy($column)
                ->orderByDesc('total_imei')
                ->limit($limit)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        });
    }

    public function lagBuckets(): array
    {
        return Cache::remember($this->cacheKey('dashboard:lag'), self::TTL, function () {
            $codes = $this->scopedCodes();

            $row = DB::table('rd_summary')
                ->when($codes !== null, fn ($q) => $q->whereIn('rd_code', $codes))
                ->selectRaw('
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

    /**
     * Scheduler + queue-worker health, plus pending / failed / stuck counts.
     * NOT cached — it's a live status check.
     */
    public function systemHealth(): array
    {
        $now = now()->timestamp;
        $schedulerAt = (int) Cache::get('heartbeat:scheduler', 0);
        $queueAt = (int) Cache::get('heartbeat:queue', 0);

        $pending = 0;
        $oldestPendingMin = null;
        if (config('queue.default') === 'database') {
            $pending = (int) DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $oldestPendingMin = $oldest ? (int) round(($now - $oldest) / 60) : null;
        }

        $failed = DB::getSchemaBuilder()->hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : 0;

        $stuckImports = (int) DB::table('import_batches')
            ->whereIn('status', ['queued', 'processing'])
            ->where('updated_at', '<', now()->subMinutes(10))
            ->count();

        $mk = fn (int $at, int $staleAfter) => [
            'seen' => $at > 0,
            'age_seconds' => $at > 0 ? $now - $at : null,
            'ok' => $at > 0 && ($now - $at) <= $staleAfter,
        ];

        return [
            'scheduler' => $mk($schedulerAt, 180),   // cron every minute → 3 min grace
            'queue' => $mk($queueAt, 300),            // worker heartbeat → 5 min grace
            'pending_jobs' => $pending,
            'oldest_pending_min' => $oldestPendingMin,
            'failed_jobs' => $failed,
            'stuck_imports' => $stuckImports,
            'queue_driver' => config('queue.default'),
        ];
    }

    public function forget(): void
    {
        // Global keys. Scoped per-user keys (dashboard:*:scoped:{id}) can't be
        // enumerated without cache tags (unsupported by the file/database
        // drivers), but their 120s TTL bounds staleness the same as globals.
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
