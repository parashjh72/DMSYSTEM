<?php

namespace App\Services\Reporting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Standard reports. Every method returns a *paginated* aggregation — the DB does
 * COUNT / SUM / CASE / GROUP BY over indexed columns and returns one page of
 * grouped rows. Raw rows are never pulled into PHP for grouping (§13).
 *
 * When no filter is applied, the dimension reports read the pre-aggregated
 * summary tables instead (instant regardless of raw-table size).
 */
class ReportService
{
    /** Shared activated / not-activated / lag-bucket projection over the raw table. */
    private const RAW_BUCKETS = '
        COUNT(*) AS total_imei,
        SUM(is_activated) AS activated,
        SUM(is_activated = 0) AS not_activated,
        SUM(activation_days = 0) AS lag_d0,
        SUM(activation_days BETWEEN 1 AND 7) AS lag_d1_7,
        SUM(activation_days BETWEEN 8 AND 15) AS lag_d8_15,
        SUM(activation_days BETWEEN 16 AND 30) AS lag_d16_30,
        SUM(activation_days >= 31) AS lag_d31_plus
    ';

    public function rdWise(ReportFilters $f, int $perPage = 50): LengthAwarePaginator
    {
        if ($f->isEmpty()) {
            return DB::table('rd_summary')
                ->select('rd_code', 'rd_name', 'total_imei', 'activated', 'not_activated')
                ->orderByDesc('total_imei')
                ->paginate($perPage);
        }

        return $this->groupedRaw($f, ['rd_code', 'rd_name'], $perPage);
    }

    public function rtWise(ReportFilters $f, int $perPage = 50): LengthAwarePaginator
    {
        if ($f->isEmpty()) {
            return DB::table('rt_summary')
                ->select('rt_code', 'rt_name', 'rd_code', 'rd_name', 'total_imei', 'activated', 'not_activated')
                ->orderByDesc('total_imei')
                ->paginate($perPage);
        }

        return $this->groupedRaw($f, ['rt_code', 'rt_name', 'rd_code', 'rd_name'], $perPage);
    }

    public function tsoWise(ReportFilters $f, int $perPage = 50): LengthAwarePaginator
    {
        if ($f->isEmpty()) {
            return DB::table('tso_summary')
                ->select('tso', 'total_imei', 'activated', 'not_activated')
                ->orderByDesc('total_imei')
                ->paginate($perPage);
        }

        return $this->groupedRaw($f, ['tso'], $perPage);
    }

    public function modelWise(ReportFilters $f, int $perPage = 50): LengthAwarePaginator
    {
        if ($f->isEmpty()) {
            return DB::table('model_summary')
                ->select('model', 'total_imei', 'activated', 'not_activated')
                ->orderByDesc('total_imei')
                ->paginate($perPage);
        }

        return $this->groupedRaw($f, ['model'], $perPage);
    }

    /** Date-wise sell-through report. */
    public function dateWise(ReportFilters $f, int $perPage = 50): LengthAwarePaginator
    {
        if ($f->isEmpty()) {
            // Unfiltered: roll up the (st_date, model)-grain summary instead of
            // scanning the raw table — instant regardless of raw-table size.
            return DB::table('daily_activation_summary')
                ->selectRaw('st_date,
                    SUM(total_imei) AS total_imei,
                    SUM(activated) AS activated,
                    SUM(not_activated) AS not_activated,
                    SUM(lag_d0) AS lag_d0,
                    SUM(lag_d1_7) AS lag_d1_7,
                    SUM(lag_d8_15) AS lag_d8_15,
                    SUM(lag_d16_30) AS lag_d16_30,
                    SUM(lag_d31_plus) AS lag_d31_plus')
                ->whereNotNull('st_date')
                ->groupBy('st_date')
                ->orderByDesc('st_date')
                ->paginate($perPage);
        }

        $q = DB::table('sales_activation_records')
            ->selectRaw('st_date, '.self::RAW_BUCKETS)
            ->whereNotNull('st_date')
            ->groupBy('st_date')
            ->orderByDesc('st_date');

        $f->apply($q);

        return $q->paginate($perPage);
    }

    /** Activation report — grouped by activation_date, with model breakdown available on drill-in. */
    public function activationWise(ReportFilters $f, int $perPage = 50): LengthAwarePaginator
    {
        $q = DB::table('sales_activation_records')
            ->selectRaw('activation_date, COUNT(*) AS total_activations,
                         COUNT(DISTINCT model) AS models,
                         AVG(activation_days) AS avg_lag_days')
            ->whereNotNull('activation_date')
            ->groupBy('activation_date')
            ->orderByDesc('activation_date');

        $f->apply($q);

        return $q->paginate($perPage);
    }

    /** RD + RT + Model matrix. */
    public function rdRtModel(ReportFilters $f, int $perPage = 100): LengthAwarePaginator
    {
        return $this->groupedRaw($f, ['rd_code', 'rd_name', 'rt_code', 'rt_name', 'model'], $perPage);
    }

    /** @param list<string> $groupBy */
    private function groupedRaw(ReportFilters $f, array $groupBy, int $perPage): LengthAwarePaginator
    {
        $cols = implode(', ', $groupBy);

        $q = DB::table('sales_activation_records')
            ->selectRaw("{$cols}, ".self::RAW_BUCKETS)
            ->groupByRaw($cols)
            ->orderByRaw('total_imei DESC');

        // Grouping keys that are also filter columns keep the leading index usable.
        $f->apply($q);

        return $q->paginate($perPage);
    }

    /** ST -> activation lag distribution for the current filter. One row, all buckets. */
    public function lagDistribution(ReportFilters $f): object
    {
        if ($f->isEmpty()) {
            // Unfiltered: sum the lag buckets from rd_summary instead of
            // aggregating the whole raw table on every Reports page load.
            return DB::table('rd_summary')->selectRaw('
                COALESCE(SUM(total_imei), 0) AS total_imei,
                COALESCE(SUM(activated), 0) AS activated,
                COALESCE(SUM(not_activated), 0) AS not_activated,
                COALESCE(SUM(lag_d0), 0) AS lag_d0,
                COALESCE(SUM(lag_d1_7), 0) AS lag_d1_7,
                COALESCE(SUM(lag_d8_15), 0) AS lag_d8_15,
                COALESCE(SUM(lag_d16_30), 0) AS lag_d16_30,
                COALESCE(SUM(lag_d31_plus), 0) AS lag_d31_plus
            ')->first() ?? (object) [];
        }

        $q = DB::table('sales_activation_records')->selectRaw(self::RAW_BUCKETS);
        $f->apply($q);

        return $q->first() ?? (object) [];
    }
}
