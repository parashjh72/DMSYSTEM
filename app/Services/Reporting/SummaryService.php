<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\DB;

/**
 * Builds and maintains the pre-aggregated reporting tables. All aggregation is
 * done in SQL (COUNT / SUM / CASE) over indexed columns — never in PHP (§13).
 *
 * The dashboard and standard reports read only these tables, so their response
 * time is independent of the raw table's size.
 */
class SummaryService
{
    /** Threshold above which an "incremental" key set triggers a full rebuild instead. */
    private const INCREMENTAL_KEY_CAP = 2000;

    private const BUCKETS = '
        COUNT(*) AS total_imei,
        COALESCE(SUM(is_activated), 0) AS activated,
        COALESCE(SUM(is_activated = 0), 0) AS not_activated,
        COALESCE(SUM(activation_days = 0), 0) AS lag_d0,
        COALESCE(SUM(activation_days BETWEEN 1 AND 7), 0) AS lag_d1_7,
        COALESCE(SUM(activation_days BETWEEN 8 AND 15), 0) AS lag_d8_15,
        COALESCE(SUM(activation_days BETWEEN 16 AND 30), 0) AS lag_d16_30,
        COALESCE(SUM(activation_days >= 31), 0) AS lag_d31_plus
    ';

    private const UPSERT_BUCKETS = '
        total_imei = VALUES(total_imei),
        activated = VALUES(activated),
        not_activated = VALUES(not_activated),
        lag_d0 = VALUES(lag_d0),
        lag_d1_7 = VALUES(lag_d1_7),
        lag_d8_15 = VALUES(lag_d8_15),
        lag_d16_30 = VALUES(lag_d16_30),
        lag_d31_plus = VALUES(lag_d31_plus),
        recalculated_at = NOW()
    ';

    public function rebuildAll(?string $from = null, ?string $to = null): void
    {
        $this->rebuildDaily($from, $to);
        $this->rebuildDimension('rd_summary', 'rd_code', ['rd_name' => 'MAX(rd_name)']);
        $this->rebuildDimension('rt_summary', 'rt_code', [
            'rt_name' => 'MAX(rt_name)', 'rd_code' => 'MAX(rd_code)', 'rd_name' => 'MAX(rd_name)',
        ]);
        $this->rebuildDimension('model_summary', 'model', []);
        $this->rebuildDimension('tso_summary', 'tso', []);
    }

    public function rebuildForBatch(int $batchId): void
    {
        $dates = $this->affectedValues('st_date', $batchId);
        if ($dates === null || count($dates) > self::INCREMENTAL_KEY_CAP) {
            $this->rebuildDaily(null, null);
        } else {
            $this->rebuildDaily(null, null, restrictDates: $dates);
        }

        $this->rebuildDimensionForBatch('rd_summary', 'rd_code', ['rd_name' => 'MAX(rd_name)'], $batchId);
        $this->rebuildDimensionForBatch('rt_summary', 'rt_code', [
            'rt_name' => 'MAX(rt_name)', 'rd_code' => 'MAX(rd_code)', 'rd_name' => 'MAX(rd_name)',
        ], $batchId);
        $this->rebuildDimensionForBatch('model_summary', 'model', [], $batchId);
        $this->rebuildDimensionForBatch('tso_summary', 'tso', [], $batchId);
    }

    // ---- daily_activation_summary (st_date x model) -------------------------

    private function rebuildDaily(?string $from, ?string $to, ?array $restrictDates = null): void
    {
        $where = ['st_date IS NOT NULL'];
        $bindings = [];

        if ($from) {
            $where[] = 'st_date >= ?';
            $bindings[] = $from;
        }
        if ($to) {
            $where[] = 'st_date <= ?';
            $bindings[] = $to;
        }
        if ($restrictDates !== null) {
            if ($restrictDates === []) {
                return;
            }
            $where[] = 'st_date IN ('.implode(',', array_fill(0, count($restrictDates), '?')).')';
            $bindings = array_merge($bindings, $restrictDates);
            DB::table('daily_activation_summary')->whereIn('st_date', $restrictDates)->delete();
        } elseif (! $from && ! $to) {
            DB::table('daily_activation_summary')->delete(); // DELETE, not TRUNCATE: no implicit commit
        }

        DB::statement(sprintf('
            INSERT INTO daily_activation_summary
                (st_date, model, total_imei, activated, not_activated,
                 lag_d0, lag_d1_7, lag_d8_15, lag_d16_30, lag_d31_plus, recalculated_at)
            SELECT st_date, COALESCE(model, ""), %s, NOW()
              FROM sales_activation_records
             WHERE %s
             GROUP BY st_date, COALESCE(model, "")
            ON DUPLICATE KEY UPDATE %s
        ', self::BUCKETS, implode(' AND ', $where), self::UPSERT_BUCKETS), $bindings);
    }

    // ---- dimension summaries ----------------------------------------------

    /** @param array<string,string> $extraCols  column => SQL aggregate expression */
    private function rebuildDimension(string $table, string $key, array $extraCols): void
    {
        DB::table($table)->delete(); // DELETE, not TRUNCATE: TRUNCATE implicit-commits any open transaction
        $this->insertDimension($table, $key, $extraCols, whereSql: "{$key} IS NOT NULL AND {$key} <> ''", bindings: []);
    }

    private function rebuildDimensionForBatch(string $table, string $key, array $extraCols, int $batchId): void
    {
        $keys = $this->affectedValues($key, $batchId);

        if ($keys === null || count($keys) > self::INCREMENTAL_KEY_CAP) {
            $this->rebuildDimension($table, $key, $extraCols);

            return;
        }
        if ($keys === []) {
            return;
        }

        DB::table($table)->whereIn($key, $keys)->delete();

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $this->insertDimension(
            $table, $key, $extraCols,
            whereSql: "{$key} IN ({$placeholders})",
            bindings: $keys,
        );
    }

    /** @param array<string,string> $extraCols */
    private function insertDimension(string $table, string $key, array $extraCols, string $whereSql, array $bindings): void
    {
        $extraNames = array_keys($extraCols);
        $extraExprs = array_values($extraCols);

        $cols = array_merge([$key], $extraNames, [
            'total_imei', 'activated', 'not_activated',
            'lag_d0', 'lag_d1_7', 'lag_d8_15', 'lag_d16_30', 'lag_d31_plus', 'recalculated_at',
        ]);

        $selects = array_merge([$key], $extraExprs);
        $selectSql = implode(', ', $selects).', '.self::BUCKETS.', NOW()';

        $updates = array_merge(
            array_map(fn ($c) => "{$c} = VALUES({$c})", $extraNames),
            [self::UPSERT_BUCKETS],
        );

        DB::statement(sprintf('
            INSERT INTO %s (%s)
            SELECT %s
              FROM sales_activation_records
             WHERE %s
             GROUP BY %s
            ON DUPLICATE KEY UPDATE %s
        ',
            $table,
            implode(', ', $cols),
            $selectSql,
            $whereSql,
            $key,
            implode(', ', $updates),
        ), $bindings);
    }

    /**
     * Distinct values of $column among rows last touched by $batchId.
     * Returns null when the count is impractically large (caller does a full rebuild).
     *
     * @return list<string>|null
     */
    private function affectedValues(string $column, int $batchId): ?array
    {
        $count = DB::table('sales_activation_records')
            ->where('last_import_batch_id', $batchId)
            ->whereNotNull($column)
            ->distinct()
            ->count($column);

        if ($count > self::INCREMENTAL_KEY_CAP) {
            return null;
        }

        return DB::table('sales_activation_records')
            ->where('last_import_batch_id', $batchId)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->pluck($column)
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
