<?php

namespace App\Services\Reporting;

use App\Support\RecordScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * WOD (Width of Distribution) Coverage.
 *
 * For each RD (or TSO territory) and model, the number of *distinct retailers*
 * that currently hold unsold stock of that model. A retailer that once stocked
 * the model but has since activated every unit no longer counts — only live
 * shelf stock (is_activated = 0, sitting at a retailer) is measured.
 *
 * Example: model M under RD R sits at 5 retailers with quantities 1, 2, 5, 0, 0
 * → WOD coverage for R × M is 3.
 *
 * Row / column totals are their own COUNT(DISTINCT rt_code) queries, never a sum
 * of the cells: a retailer stocking three models is one retailer, not three.
 */
class WodCoverageService
{
    /** Max model columns before the tail folds into an "Other" column. */
    public const MODEL_COLUMNS = 60;

    /** null = both, otherwise 'running' | 'out' — restricts by device_models.status. */
    private ?string $lifecycle = null;

    public function forLifecycle(?string $lifecycle): static
    {
        $this->lifecycle = in_array($lifecycle, ['running', 'out'], true) ? $lifecycle : null;

        return $this;
    }

    /** Live retailer stock, already narrowed to the caller's RD scope. */
    private function base()
    {
        return RecordScope::apply(DB::table('sales_activation_records'))
            ->where('is_activated', 0)
            ->whereNotNull('rt_code')->where('rt_code', '<>', '');
    }

    private function filtered(?string $rdCode, ?string $model)
    {
        return $this->base()
            ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->when($model, fn ($q, $v) => $q->where('model', $v))
            ->when($this->lifecycle, fn ($q, $status) => $q->whereIn(
                'model',
                fn ($sub) => $sub->select('name')->from('device_models')->where('status', $status),
            ));
    }

    /**
     * Model columns for the current scope, ranked by coverage (widest first),
     * capped at MODEL_COLUMNS, plus the column totals for the footer.
     *
     * @return array{
     *   models: list<string>, hasOther: bool,
     *   totals: array<string,int>, otherTotal: int, grandTotal: int
     * }
     */
    public function modelColumns(?string $rdCode, ?string $model): array
    {
        $ranked = $this->filtered($rdCode, $model)
            ->selectRaw('model, COUNT(DISTINCT rt_code) AS wod')
            ->whereNotNull('model')->where('model', '<>', '')
            ->groupBy('model')
            ->orderByDesc('wod')
            ->pluck('wod', 'model');

        $models = $ranked->keys()->take(self::MODEL_COLUMNS)->all();
        $hasOther = $ranked->count() > self::MODEL_COLUMNS;

        $grand = (int) $this->filtered($rdCode, $model)->distinct()->count('rt_code');
        $otherTotal = $hasOther
            ? (int) $this->filtered($rdCode, $model)->whereNotIn('model', $models)->distinct()->count('rt_code')
            : 0;

        return [
            'models' => $models,
            'hasOther' => $hasOther,
            'totals' => $ranked->only($models)->map(fn ($n) => (int) $n)->all(),
            'otherTotal' => $otherTotal,
            'grandTotal' => $grand,
        ];
    }

    /** RD-wise, pivoted: row per RD, column per model, cell = distinct stocked retailers. */
    public function rdWise(?string $rdCode, ?string $model, array $modelColumns, bool $hasOther, int $perPage = 50): LengthAwarePaginator
    {
        $rows = $this->filtered($rdCode, $model)
            ->selectRaw('rd_code, MAX(rd_name) AS rd_name, COUNT(DISTINCT rt_code) AS total_qty')
            ->whereNotNull('rd_code')->where('rd_code', '<>', '')
            ->groupBy('rd_code')
            ->orderByDesc('total_qty')
            ->paginate($perPage);

        $this->attachCells($rows, 'rd_code', $rdCode, $model, $modelColumns, $hasOther);
        $rows->getCollection()->each(fn ($r) => $r->labels = [$r->rd_code, $r->rd_name]);

        return $rows;
    }

    /** TSO-wise, pivoted: row per TSO. */
    public function tsoWise(?string $rdCode, ?string $model, array $modelColumns, bool $hasOther, int $perPage = 50): LengthAwarePaginator
    {
        $rows = $this->filtered($rdCode, $model)
            ->selectRaw('tso, COUNT(DISTINCT rt_code) AS total_qty')
            ->whereNotNull('tso')->where('tso', '<>', '')
            ->groupBy('tso')
            ->orderByDesc('total_qty')
            ->paginate($perPage);

        $this->attachCells($rows, 'tso', $rdCode, $model, $modelColumns, $hasOther);
        $rows->getCollection()->each(fn ($r) => $r->labels = [$r->tso]);

        return $rows;
    }

    /**
     * Every pivot row for export (no pagination), uniform shape.
     *
     * @return list<array{labels: list<string>, cells: array<string,int>, other: int, total: int}>
     */
    public function exportRows(string $scope, ?string $rdCode, ?string $model, array $modelColumns, bool $hasOther): array
    {
        [$keyCol, $nameSelect] = $scope === 'tso'
            ? ['tso', '']
            : ['rd_code', ', MAX(rd_name) AS rd_name'];

        $totals = $this->filtered($rdCode, $model)
            ->selectRaw($keyCol.$nameSelect.', COUNT(DISTINCT rt_code) AS total_qty')
            ->whereNotNull($keyCol)->where($keyCol, '<>', '')
            ->groupBy($keyCol)
            ->orderByDesc('total_qty')
            ->get();

        $cells = $this->filtered($rdCode, $model)
            ->selectRaw($keyCol.', model, COUNT(DISTINCT rt_code) AS wod')
            ->whereNotNull($keyCol)->where($keyCol, '<>', '')
            ->groupBy($keyCol, 'model')
            ->get()
            ->groupBy($keyCol);

        $other = $hasOther
            ? $this->filtered($rdCode, $model)->whereNotIn('model', $modelColumns)
                ->selectRaw($keyCol.', COUNT(DISTINCT rt_code) AS wod')
                ->whereNotNull($keyCol)->where($keyCol, '<>', '')
                ->groupBy($keyCol)->pluck('wod', $keyCol)
            : collect();

        return $totals->map(function ($row) use ($keyCol, $scope, $cells, $other, $modelColumns, $hasOther) {
            $byModel = ($cells[$row->$keyCol] ?? collect())->pluck('wod', 'model');

            return [
                'labels' => $scope === 'tso' ? [$row->tso] : [$row->rd_code, $row->rd_name],
                'cells' => collect($modelColumns)->mapWithKeys(fn ($m) => [$m => (int) ($byModel[$m] ?? 0)])->all(),
                'other' => $hasOther ? (int) ($other[$row->$keyCol] ?? 0) : 0,
                'total' => (int) $row->total_qty,
            ];
        })->all();
    }

    /** Headline totals for the current scope. */
    public function summary(?string $rdCode, ?string $model): object
    {
        return (object) [
            'retailers' => (int) $this->filtered($rdCode, $model)->distinct()->count('rt_code'),
            'distributors' => (int) $this->filtered($rdCode, $model)->distinct()->count('rd_code'),
            'models' => (int) $this->filtered($rdCode, $model)->whereNotNull('model')->where('model', '<>', '')->distinct()->count('model'),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * Fill each paginated row with ->cells (model => distinct RTs) and ->other.
     */
    private function attachCells(LengthAwarePaginator $rows, string $keyCol, ?string $rdCode, ?string $model, array $modelColumns, bool $hasOther): void
    {
        $keys = collect($rows->items())->pluck($keyCol)->all();
        if ($keys === []) {
            return;
        }

        $cells = $this->filtered($rdCode, $model)
            ->selectRaw($keyCol.', model, COUNT(DISTINCT rt_code) AS wod')
            ->whereIn($keyCol, $keys)
            ->groupBy($keyCol, 'model')
            ->get()
            ->groupBy($keyCol);

        $other = $hasOther
            ? $this->filtered($rdCode, $model)
                ->selectRaw($keyCol.', COUNT(DISTINCT rt_code) AS wod')
                ->whereIn($keyCol, $keys)
                ->whereNotIn('model', $modelColumns)
                ->groupBy($keyCol)
                ->pluck('wod', $keyCol)
            : collect();

        $modelSet = array_flip($modelColumns);

        foreach ($rows->getCollection() as $row) {
            $byModel = ($cells[$row->$keyCol] ?? new Collection)->pluck('wod', 'model');
            $row->cells = [];
            foreach ($byModel as $m => $n) {
                if (isset($modelSet[$m])) {
                    $row->cells[$m] = (int) $n;
                }
            }
            $row->other = $hasOther ? (int) ($other[$row->$keyCol] ?? 0) : 0;
        }
    }
}
