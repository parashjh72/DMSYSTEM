<?php

namespace App\Services\Reporting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ad-hoc "quick" reports that don't fit the standard grouped report shapes.
 * SQL aggregation only.
 */
class QuickReportService
{
    /**
     * Activation vs Sell-through by date. One row per calendar date that has
     * either a sell-through or an activation, with running totals.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function activationVsSellThrough(ReportFilters $f): Collection
    {
        $st = $this->countByDate('st_date', $f);
        $act = $this->countByDate('activation_date', $f);

        $dates = collect($st->keys())->merge($act->keys())->unique()->sort()->values();

        $cumSt = 0;
        $cumAct = 0;

        return $dates->map(function ($date) use ($st, $act, &$cumSt, &$cumAct) {
            $s = (int) ($st[$date] ?? 0);
            $a = (int) ($act[$date] ?? 0);
            $cumSt += $s;
            $cumAct += $a;

            return [
                'date' => $date,
                'sell_through' => $s,
                'activated' => $a,
                'cum_sell_through' => $cumSt,
                'cum_activated' => $cumAct,
                'gap' => $cumSt - $cumAct,           // sold-through but not yet activated
                'activation_pct' => $cumSt > 0 ? round($cumAct / $cumSt * 100, 1) : 0,
            ];
        })->reverse()->values();
    }

    /** @return Collection<string,int> date => count */
    private function countByDate(string $column, ReportFilters $f): Collection
    {
        $q = DB::table('sales_activation_records')
            ->selectRaw("{$column} AS d, COUNT(*) AS c")
            ->whereNotNull($column)
            ->groupBy($column);

        $f->apply($q);

        return $q->pluck('c', 'd');
    }

    /**
     * Retailers with ZERO stock (nothing un-activated) that have activations
     * (did sell-out) but NO sell-through date on any of their devices
     * (never recorded as sold-through to them).
     */
    public function zeroStockSoldNotSellThrough(ReportFilters $f, int $perPage = 100)
    {
        $q = DB::table('sales_activation_records')
            ->selectRaw('
                rt_code,
                MAX(rt_name) AS rt_name,
                MAX(rd_code) AS rd_code,
                MAX(rd_name) AS rd_name,
                COUNT(*) AS total,
                SUM(is_activated) AS activated,
                SUM(is_activated = 0) AS in_stock,
                SUM(st_date IS NOT NULL) AS sell_through
            ')
            ->whereNotNull('rt_code')->where('rt_code', '<>', '')
            ->groupBy('rt_code')
            ->havingRaw('in_stock = 0 AND activated > 0 AND sell_through = 0')
            ->orderByDesc('activated');

        $f->apply($q);

        return $q->paginate($perPage);
    }
}
