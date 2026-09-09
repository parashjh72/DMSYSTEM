<?php

namespace App\Services\Reporting;

use App\Models\User;
use App\Support\RecordScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Two mirror reports over the same pivot machinery:
 *
 *   stock   – devices NOT activated (unsold inventory)
 *   sellout – devices activated (sold out to the customer)
 *
 * RD-wise / RT-wise are pivoted: one row per RD (or RD+RT), one column per model,
 * quantity in the cell. All aggregation is SQL over indexed columns.
 */
class StockReportService
{
    private const NO_RT = "(rt_name IS NULL OR rt_name = '')";

    private const HAS_RT = "(rt_name IS NOT NULL AND rt_name <> '')";

    /** Max model columns before the tail is folded into an "Other" column. */
    public const MODEL_COLUMNS = 60;

    /** Label (non-model) columns shown before the pivot, per report scope. */
    public const LABELS = [
        'rd' => ['RD Code', 'RD Name'],
        'rt' => ['RD Code', 'RD Name', 'RT Code', 'RT Name'],
        'tso' => ['TSO'],
        'asm' => ['ASM'],
    ];

    /** null = both, otherwise 'running' | 'out' — restricts by device_models.status. */
    private ?string $lifecycle = null;

    /** stock | sellout */
    private string $mode = 'stock';

    /** Sellout-date (activation_date) range — sellout mode only. */
    private ?string $dateFrom = null;

    private ?string $dateTo = null;

    public function forLifecycle(?string $lifecycle): static
    {
        $this->lifecycle = in_array($lifecycle, ['running', 'out'], true) ? $lifecycle : null;

        return $this;
    }

    public function forMode(string $mode): static
    {
        $this->mode = $mode === 'sellout' ? 'sellout' : 'stock';

        return $this;
    }

    public function forDateRange(?string $from, ?string $to): static
    {
        $this->dateFrom = $from ?: null;
        $this->dateTo = $to ?: null;

        return $this;
    }

    /** Sellout date-range condition (no-op unless in sellout mode with dates set). */
    private function applyDateRange($query)
    {
        if ($this->mode !== 'sellout') {
            return $query;
        }

        return $query
            ->when($this->dateFrom, fn ($q, $v) => $q->where('activation_date', '>=', $v))
            ->when($this->dateTo, fn ($q, $v) => $q->where('activation_date', '<=', $v));
    }

    private function isActivated(): int
    {
        return $this->mode === 'sellout' ? 1 : 0;
    }

    /** Base record query, already narrowed to the current user's TSO scope. */
    private function base()
    {
        return RecordScope::apply(DB::table('sales_activation_records'));
    }

    private function applyLifecycle($query)
    {
        return $query->when($this->lifecycle, fn ($q, $status) => $q->whereIn(
            'model',
            fn ($sub) => $sub->select('name')->from('device_models')->where('status', $status),
        ));
    }

    /**
     * Model columns for the current scope, ordered by quantity (largest first),
     * capped at MODEL_COLUMNS, plus the column totals for the table footer.
     *
     * @return array{
     *   models: list<string>, hasOther: bool,
     *   totals: array<string,int>, otherTotal: int, grandTotal: int
     * }
     */
    public function modelColumns(string $scope, ?string $rdCode, array $rtCodes = []): array
    {
        $ranked = $this->stockQuery($scope, $rdCode, $rtCodes)
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
        $rows->getCollection()->each(fn ($r) => $r->labels = [$r->rd_code, $r->rd_name]);

        return $rows;
    }

    /** RT-wise, pivoted: row per RD+RT, column per model. */
    public function rtWise(?string $rdCode, array $rtCodes, array $modelColumns, int $perPage = 50): LengthAwarePaginator
    {
        $rows = $this->stockQuery('rt', $rdCode, $rtCodes)
            ->selectRaw('rd_code, MAX(rd_name) AS rd_name, rt_code, MAX(rt_name) AS rt_name, COUNT(*) AS total_qty')
            ->groupBy('rd_code', 'rt_code')
            ->orderByDesc('total_qty')
            ->paginate($perPage);

        $this->attachModelCells($rows, 'rt', ['rd_code', 'rt_code'], $modelColumns);
        $rows->getCollection()->each(fn ($r) => $r->labels = [$r->rd_code, $r->rd_name, $r->rt_code, $r->rt_name]);

        return $rows;
    }

    /** TSO-wise, pivoted: row per TSO, column per model. All unsold / sold in the territory. */
    public function tsoWise(?string $rdCode, array $modelColumns, int $perPage = 50): LengthAwarePaginator
    {
        $rows = $this->stockQuery('tso', $rdCode)
            ->selectRaw('tso, COUNT(*) AS total_qty')
            ->whereNotNull('tso')->where('tso', '<>', '')
            ->groupBy('tso')
            ->orderByDesc('total_qty')
            ->paginate($perPage);

        $this->attachModelCells($rows, 'tso', ['tso'], $modelColumns);
        $rows->getCollection()->each(fn ($r) => $r->labels = [$r->tso]);

        return $rows;
    }

    /**
     * ASM-wise: an ASM is a user role scoped to RD codes, so device → ASM is
     * resolved via users.scoped_rd_codes. Aggregation is folded in PHP (there are
     * only a handful of ASMs); devices whose RD has no ASM show as "(unassigned)".
     */
    public function asmWise(?string $rdCode, array $modelColumns, int $perPage = 50): LengthAwarePaginator
    {
        $rdToAsm = $this->rdToAsm();
        $modelSet = array_flip($modelColumns);

        $raw = $this->stockQuery('asm', $rdCode)
            ->selectRaw('rd_code, model, COUNT(*) AS qty')
            ->whereNotNull('rd_code')->where('rd_code', '<>', '')
            ->groupBy('rd_code', 'model')
            ->get();

        /** @var array<string, array{total:int, cells:array<string,int>}> $byAsm */
        $byAsm = [];
        foreach ($raw as $r) {
            $asm = $rdToAsm[$r->rd_code] ?? '(unassigned)';
            $byAsm[$asm] ??= ['total' => 0, 'cells' => []];
            $byAsm[$asm]['total'] += (int) $r->qty;
            if (isset($modelSet[$r->model])) {
                $byAsm[$asm]['cells'][$r->model] = ($byAsm[$asm]['cells'][$r->model] ?? 0) + (int) $r->qty;
            }
        }

        $items = collect($byAsm)
            ->map(fn ($g, $asm) => (object) [
                'labels' => [$asm],
                'total_qty' => $g['total'],
                'cells' => $g['cells'],
                'other' => max(0, $g['total'] - array_sum($g['cells'])),
            ])
            ->sortByDesc('total_qty')
            ->values();

        return $this->paginateCollection($items, $perPage);
    }

    /** @return array<string,string> rd_code => ASM name (first ASM wins on overlap) */
    private function rdToAsm(): array
    {
        $map = [];
        foreach (User::role('ASM')->get(['id', 'name', 'scoped_rd_codes']) as $asm) {
            foreach ($asm->scopedRdCodes() as $code) {
                $map[$code] ??= $asm->name;
            }
        }

        return $map;
    }

    /** @param  Collection<int,object>  $items */
    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /** Model-wise. Stock: RD/RT/total split. Sellout: qty per model. */
    public function modelWise(?string $rdCode, int $perPage = 50): LengthAwarePaginator
    {
        $act = $this->isActivated();

        if ($this->mode === 'sellout') {
            return $this->applyDateRange($this->applyLifecycle($this->base()))
                ->selectRaw('model, 0 AS rd_stock, COUNT(*) AS rt_stock, COUNT(*) AS total_stock')
                ->where('is_activated', $act)
                ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
                ->groupBy('model')
                ->orderByDesc('total_stock')
                ->paginate($perPage);
        }

        return $this->applyLifecycle($this->base())
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
    public function summary(?string $rdCode, array $rtCodes = []): object
    {
        $act = $this->isActivated();
        $split = $this->mode === 'sellout'
            ? "0 AS rd_stock, SUM(is_activated = {$act}) AS rt_stock"
            : 'SUM(is_activated = 0 AND '.self::NO_RT.') AS rd_stock, SUM(is_activated = 0 AND '.self::HAS_RT.') AS rt_stock';

        return $this->applyDateRange($this->applyLifecycle($this->base()))
            ->selectRaw("
                {$split},
                SUM(is_activated = {$act}) AS total_stock,
                COUNT(DISTINCT CASE WHEN is_activated = {$act} THEN model END) AS models
            ")
            ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->when($rtCodes !== [], fn ($q) => $q->whereIn('rt_code', $rtCodes))
            ->first() ?? (object) ['rd_stock' => 0, 'rt_stock' => 0, 'total_stock' => 0, 'models' => 0];
    }

    /**
     * Every pivot row for export (no pagination), uniform shape across scopes.
     *
     * @return list<array{labels: list<string>, cells: array<string,int>, other: int, total: int}>
     */
    public function exportRows(string $scope, ?string $rdCode, array $rtCodes, array $modelColumns, bool $hasOther): array
    {
        $modelSet = array_flip($modelColumns);

        // ASM is derived — fold RD rows in PHP.
        if ($scope === 'asm') {
            $rdToAsm = $this->rdToAsm();
            $raw = $this->stockQuery('asm', $rdCode)
                ->selectRaw('rd_code, model, COUNT(*) AS qty')
                ->whereNotNull('rd_code')->where('rd_code', '<>', '')
                ->groupBy('rd_code', 'model')->get();

            $byAsm = [];
            foreach ($raw as $r) {
                $asm = $rdToAsm[$r->rd_code] ?? '(unassigned)';
                $byAsm[$asm] ??= ['total' => 0, 'cells' => []];
                $byAsm[$asm]['total'] += (int) $r->qty;
                if (isset($modelSet[$r->model])) {
                    $byAsm[$asm]['cells'][$r->model] = ($byAsm[$asm]['cells'][$r->model] ?? 0) + (int) $r->qty;
                }
            }

            $out = [];
            foreach ($byAsm as $asm => $g) {
                $out[] = ['labels' => [$asm], 'cells' => $g['cells'],
                    'other' => $hasOther ? max(0, $g['total'] - array_sum($g['cells'])) : 0, 'total' => $g['total']];
            }
            usort($out, fn ($a, $b) => $b['total'] <=> $a['total']);

            return $out;
        }

        $keyCols = match ($scope) {
            'rt' => ['rd_code', 'rt_code'],
            'tso' => ['tso'],
            default => ['rd_code'],
        };
        $labelCols = match ($scope) {
            'rt' => ['rd_code', 'rd_name', 'rt_code', 'rt_name'],
            'tso' => ['tso'],
            default => ['rd_code', 'rd_name'],
        };
        $nameSelect = match ($scope) {
            'rt' => ', MAX(rd_name) AS rd_name, MAX(rt_name) AS rt_name',
            'tso' => '',
            default => ', MAX(rd_name) AS rd_name',
        };

        $groups = $this->stockQuery($scope, $rdCode, $rtCodes)
            ->selectRaw(implode(', ', $keyCols).$nameSelect.', model, COUNT(*) AS qty')
            ->when($scope === 'tso', fn ($q) => $q->whereNotNull('tso')->where('tso', '<>', ''))
            ->groupBy(...array_merge($keyCols, ['model']))
            ->get()
            ->groupBy(fn ($r) => implode('|', array_map(fn ($c) => $r->$c, $keyCols)));

        $out = [];
        foreach ($groups as $rows) {
            $first = $rows->first();
            $byModel = $rows->pluck('qty', 'model');
            $total = (int) $rows->sum('qty');
            $cells = [];
            foreach ($modelColumns as $m) {
                $cells[$m] = (int) ($byModel[$m] ?? 0);
            }
            $out[] = [
                'labels' => array_map(fn ($c) => (string) ($first->$c ?? ''), $labelCols),
                'cells' => $cells,
                'other' => $hasOther ? $total - array_sum($cells) : 0,
                'total' => $total,
            ];
        }

        usort($out, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $out;
    }

    // ------------------------------------------------------------------

    private function stockQuery(string $scope, ?string $rdCode, array $rtCodes = [])
    {
        $query = $this->base()
            ->where('is_activated', $this->isActivated())
            ->when($rdCode, fn ($q, $v) => $q->where('rd_code', $v))
            ->when($scope === 'rt' && $rtCodes !== [], fn ($q) => $q->whereIn('rt_code', $rtCodes));

        if ($this->mode === 'sellout') {
            // Sold-out units are all at a retailer; RT scope still needs a retailer name.
            if ($scope === 'rt') {
                $query->whereRaw(self::HAS_RT);
            }
        } else {
            // rd = distributor warehouse (no RT); rt = on a retailer's shelf;
            // tso / asm span the whole territory (every unsold device).
            match ($scope) {
                'rt' => $query->whereRaw(self::HAS_RT),
                'rd' => $query->whereRaw(self::NO_RT),
                default => null,
            };
        }

        return $this->applyDateRange($this->applyLifecycle($query));
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
