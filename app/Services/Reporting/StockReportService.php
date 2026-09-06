<?php

namespace App\Services\Reporting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock = unsold inventory: a device that is NOT activated is still "in stock".
 *
 *   RD stock  – device still sits with the distributor: not activated AND the row
 *               carries no retailer name (RT not yet assigned).
 *   RT stock  – device is with a retailer but not activated yet.
 *   Total     – RD stock + RT stock (every not-activated unit).
 *
 * RD-wise / RT-wise are pivoted: one row per RD (or RD+RT), one column per model,
 * quantity in the cell. Model-wise keeps models in rows (RD stock / RT stock / total).
 *
 * All aggregation is SQL (COUNT / SUM / GROUP BY) over indexed columns.
 */
class StockReportService
{
    private const NO_RT = "(rt_name IS NULL OR rt_name = '')";

    private const HAS_RT = "(rt_name IS NOT NULL AND rt_name <> '')";

    /** Max model columns before the tail is folded into an "Other" column. */
    public const MODEL_COLUMNS = 60;

    /**
     * Model columns for the current scope, ordered by quantity (largest first),
     * capped at MODEL_COLUMNS, plus the column totals for the table footer.
     *
     * @return array{
     *   models: list<string>, hasOther: bool,
     *   totals: array<string,int>, otherTotal: int, grandTotal: int
     * }
     */
    public function modelColumns(string $scope, ?string $rdCode, ?string $rtCode = null): array
    {
        $ranked = $this->stockQuery($scope, $rdCode, $rtCode)
            ->selectRaw('model, COUNT(*) AS qty')
            ->whereNotNull('model')->where('model', '<>', '')
            ->groupBy('model')
            ->orderByDesc('qty')
            ->pluck('qty', 'model');

        $models = $ranked->keys()->take(self::MODEL_COLUMNS)->all();
        $totals = $ranked->only($models)->map(fn ($q) => (int) $q)->all();
        $grand = (int) $ranked->sum();

        return [
            'models' => $models,
            'hasOther' => $ranked->count() > self::MODEL_COLUMNS,
            'totals' => $totals,
            'otherTotal' => $grand - array_sum($totals),
            'grandTotal' => $grand,
        ];
    }

    /** RD-wise, pivoted: row per RD, column per model. */
    public function rdWise(?string $rdCode, array $modelColumns, int $perPage = 50): LengthAwarePaginator
    {
        $rows = $this->stockQuery('rd', $rdCode)
            ->selectRaw('rd_code, MAX(rd_name) AS rd_name, COUNT(*) AS total_qty')
            ->whereNotNull('rd_code')->where('rd_code', '<>', '')
            ->groupBy('rd_code')
            ->orderByDesc('total_qty')
            ->paginate($perPage);

        $this->attachModelCells($rows, 'rd', ['rd_code'], $modelColumns);

        return $rows;
    }

    /** RT-wise, pivoted: row per RD+RT, column per model. */
    public function rtWise(?string $rdCode, ?string $rtCode, array $modelColumns, int $perPage = 50): LengthAwarePaginator
    {
        $rows = $this->stockQuery('rt', $rdCode, $rtCode)
            ->selectRaw('rd_code, MAX(rd_name) AS rd_name, rt_code, MAX(rt_name) AS rt_name, COUNT(*) AS total_qty')
            ->groupBy('rd_code', 'rt_code')
            ->orderByDesc('total_qty')
            ->paginate($perPage);

        $this->attachModelCells($rows, 'rt', ['rd_code', 'rt_code'], $modelColumns);

        return $rows;
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

    /** Every stock row, pivoted, for export (no pagination). @return list<array<string,mixed>> */
    public function exportRows(string $scope, ?string $rdCode, ?string $rtCode, array $modelColumns, bool $hasOther): array
    {
        $keyCols = $scope === 'rt' ? ['rd_code', 'rt_code'] : ['rd_code'];

        $groups = $this->stockQuery($scope, $rdCode, $rtCode)
            ->selectRaw(implode(', ', $keyCols).', MAX(rd_name) AS rd_name'
                .($scope === 'rt' ? ', MAX(rt_name) AS rt_name' : '')
                .', model, COUNT(*) AS qty')
            ->groupBy(...array_merge($keyCols, ['model']))
            ->get()
            ->groupBy(fn ($r) => implode('|', array_map(fn ($c) => $r->$c, $keyCols)));

        $out = [];
        foreach ($groups as $rows) {
            $first = $rows->first();
            $line = ['rd_code' => $first->rd_code, 'rd_name' => $first->rd_name];
            if ($scope === 'rt') {
                $line['rt_code'] = $first->rt_code;
                $line['rt_name'] = $first->rt_name;
            }
            $byModel = $rows->pluck('qty', 'model');
            $total = (int) $rows->sum('qty');
            $accounted = 0;
            foreach ($modelColumns as $m) {
                $q = (int) ($byModel[$m] ?? 0);
                $line[$m] = $q;
                $accounted += $q;
            }
            if ($hasOther) {
                $line['Other'] = $total - $accounted;
            }
            $line['Total'] = $total;
            $out[] = $line;
        }

        usort($out, fn ($a, $b) => $b['Total'] <=> $a['Total']);

        return $out;
    }

    // ------------------------------------------------------------------

    private function stockQuery(string $scope, ?string $rdCode, ?string $rtCode = null)
    {
        return DB::table('sales_activation_records')
            ->where('is_activated', 0)
            ->whereRaw($scope === 'rt' ? self::HAS_RT : self::NO_RT)
            ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->when($rtCode && $scope === 'rt', fn ($q) => $q->where('rt_code', $rtCode));
    }

    /**
     * Fill each paginated row with ->cells (model => qty), ->other and keep ->total_qty.
     *
     * @param  list<string>  $keyCols
     * @param  list<string>  $modelColumns
     */
    private function attachModelCells(LengthAwarePaginator $rows, string $scope, array $keyCols, array $modelColumns): void
    {
        $items = collect($rows->items());
        if ($items->isEmpty()) {
            return;
        }

        $breakdown = $this->stockQuery($scope, null)
            ->selectRaw(implode(', ', $keyCols).', model, COUNT(*) AS qty')
            ->where(function ($q) use ($items, $keyCols) {
                foreach ($items as $row) {
                    $q->orWhere(function ($w) use ($row, $keyCols) {
                        foreach ($keyCols as $c) {
                            $w->where($c, $row->$c);
                        }
                    });
                }
            })
            ->groupBy(...array_merge($keyCols, ['model']))
            ->get()
            ->groupBy(fn ($r) => implode('|', array_map(fn ($c) => $r->$c, $keyCols)));

        $modelSet = array_flip($modelColumns);

        foreach ($items as $row) {
            $key = implode('|', array_map(fn ($c) => $row->$c, $keyCols));
            $rowModels = $breakdown[$key] ?? new Collection;

            $cells = [];
            $accounted = 0;
            foreach ($rowModels as $rm) {
                if (isset($modelSet[$rm->model])) {
                    $cells[$rm->model] = (int) $rm->qty;
                    $accounted += (int) $rm->qty;
                }
            }
            $row->cells = $cells;
            $row->other = max(0, (int) $row->total_qty - $accounted);
        }
    }
}
