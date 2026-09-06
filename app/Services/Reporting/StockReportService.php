<?php

namespace App\Services\Reporting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Stock = unsold inventory: a device that is NOT activated is still "in stock".
 *
 *   RD stock  – device still sits with the distributor: not activated AND the row
 *               carries no retailer name (RT not yet assigned).
 *   RT stock  – device is with a retailer but not activated yet.
 *   Total     – RD stock + RT stock (every not-activated unit).
 *
 * All aggregation is SQL (COUNT / SUM / GROUP BY) over indexed columns.
 */
class StockReportService
{
    private const NO_RT = "(rt_name IS NULL OR rt_name = '')";

    private const HAS_RT = "(rt_name IS NOT NULL AND rt_name <> '')";

    /** RD-wise: model-wise quantity of stock still held at the distributor. */
    public function rdWise(?string $rdCode, int $perPage = 50): LengthAwarePaginator
    {
        return DB::table('sales_activation_records')
            ->selectRaw('rd_code, MAX(rd_name) AS rd_name, model, COUNT(*) AS qty')
            ->where('is_activated', 0)
            ->whereRaw(self::NO_RT)
            ->whereNotNull('rd_code')->where('rd_code', '<>', '')
            ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->groupBy('rd_code', 'model')
            ->orderBy('rd_code')->orderByDesc('qty')
            ->paginate($perPage);
    }

    /** RT-wise: which RD → which RT holds which model, and how many (not activated). */
    public function rtWise(?string $rdCode, ?string $rtCode, int $perPage = 50): LengthAwarePaginator
    {
        return DB::table('sales_activation_records')
            ->selectRaw('rd_code, MAX(rd_name) AS rd_name, rt_code, MAX(rt_name) AS rt_name, model, COUNT(*) AS qty')
            ->where('is_activated', 0)
            ->whereRaw(self::HAS_RT)
            ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->when($rtCode, fn ($q, $v) => $q->where('rt_code', $v))
            ->groupBy('rd_code', 'rt_code', 'model')
            ->orderBy('rd_code')->orderBy('rt_code')->orderByDesc('qty')
            ->paginate($perPage);
    }

    /** Model-wise: RD stock + RT stock for each model, combined in one row. */
    public function modelWise(?string $rdCode, int $perPage = 50): LengthAwarePaginator
    {
        return DB::table('sales_activation_records')
            ->selectRaw('
                model,
                SUM(is_activated = 0 AND '.self::NO_RT.') AS rd_stock,
                SUM(is_activated = 0 AND '.self::HAS_RT.') AS rt_stock,
                SUM(is_activated = 0) AS total_stock
            ')
            ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->groupBy('model')
            ->havingRaw('total_stock > 0')
            ->orderByDesc('total_stock')
            ->paginate($perPage);
    }

    /** Headline totals for the current RD / RT scope. */
    public function summary(?string $rdCode, ?string $rtCode = null): object
    {
        return DB::table('sales_activation_records')
            ->selectRaw('
                SUM(is_activated = 0 AND '.self::NO_RT.') AS rd_stock,
                SUM(is_activated = 0 AND '.self::HAS_RT.') AS rt_stock,
                SUM(is_activated = 0) AS total_stock,
                COUNT(DISTINCT CASE WHEN is_activated = 0 THEN model END) AS models
            ')
            ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->when($rtCode, fn ($q, $v) => $q->where('rt_code', $v))
            ->first() ?? (object) ['rd_stock' => 0, 'rt_stock' => 0, 'total_stock' => 0, 'models' => 0];
    }
}
