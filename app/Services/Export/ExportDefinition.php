<?php

namespace App\Services\Export;

use App\Models\Scheme;
use App\Services\Reporting\PriceService;
use App\Services\Reporting\QuickReportService;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\ReportService;
use App\Services\Reporting\SchemeService;
use App\Services\Reporting\StockReportService;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Maps an export type + filter set to a header row and a row generator.
 *
 * - "records" streams the raw table with keyset pagination (chunkById) — safe for
 *   millions of rows, flat memory.
 * - report exports run one aggregation query; the grouped result set is bounded
 *   (at most a few thousand groups) so a single lazy cursor is fine.
 */
class ExportDefinition
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly StockReportService $stock,
        private readonly QuickReportService $quick,
        private readonly PriceService $prices,
        private readonly SchemeService $schemes,
    ) {}

    /**
     * @return array{0: list<string>, 1: iterable<array<int,mixed>>, 2: list<array{name:string,header:list<string>,rows:list<list<mixed>>}>}
     *                                                                                                                                       [header, rows, extraSheets] — extraSheets are extra XLSX tabs with their own precomputed rows
     */
    public function build(string $type, ReportFilters $filters, callable $onProgress): array
    {
        $result = match ($type) {
            'records' => $this->records($filters, $onProgress),
            'rd_report' => $this->grouped($this->reports->rdWise($filters, PHP_INT_MAX),
                ['RD Code', 'RD Name', 'Total IMEI', 'Activated', 'Not Activated', 'Activation %']),
            'rt_report' => $this->grouped($this->reports->rtWise($filters, PHP_INT_MAX),
                ['RT Code', 'RT Name', 'RD Code', 'RD Name', 'Total IMEI', 'Activated', 'Not Activated', 'Activation %']),
            'tso_report' => $this->grouped($this->reports->tsoWise($filters, PHP_INT_MAX),
                ['TSO', 'Total IMEI', 'Activated', 'Not Activated', 'Activation %']),
            'model_report' => $this->grouped($this->reports->modelWise($filters, PHP_INT_MAX),
                ['Model', 'Total IMEI', 'Activated', 'Not Activated', 'Activation %']),
            'date_report' => $this->grouped($this->reports->dateWise($filters, PHP_INT_MAX),
                ['ST Date', 'Total Sell-Through', 'Activated', 'Not Activated', 'Activation %']),
            'stock_rd' => $this->stockPivot('rd', $filters),
            'stock_rt' => $this->stockPivot('rt', $filters),
            'stock_model' => $this->grouped(
                $this->stock->forLifecycle($filters->lifecycle)->modelWise($filters->rdCode, PHP_INT_MAX),
                ['Model', 'RD stock', 'RT stock', 'Total stock']),
            'quick_zero_stock' => $this->grouped(
                $this->quick->zeroStockSoldNotSellThrough($filters, PHP_INT_MAX),
                ['RT Code', 'RT Name', 'RD Code', 'RD Name', 'Activated', 'In stock', 'Sell-thru']),
            'quick_act_value' => $this->valueRows($filters),
            'scheme_achievement' => $this->schemeRows($filters),
            default => throw new InvalidArgumentException("Unknown export type [{$type}]."),
        };

        return $result + [2 => []]; // pad missing extraSheets
    }

    private function records(ReportFilters $filters, callable $onProgress): array
    {
        $header = ['IMEI', 'Model', 'TSO', 'RD Code', 'RD Name', 'RTCode', 'RT Name',
            'ST Date', 'Activation', 'SELL-IN', 'Source', 'Activation Days', 'Import Batch'];

        $query = DB::table('sales_activation_records')->select([
            'id', 'imei', 'model', 'tso', 'rd_code', 'rd_name', 'rt_code', 'rt_name',
            'st_date', 'activation_date', 'sell_in_date', 'source', 'activation_days', 'last_import_batch_id',
        ]);
        $filters->apply($query);

        $rows = (function () use ($query, $onProgress) {
            $done = 0;
            foreach ($this->chunkById($query, 5000) as $r) {
                yield [
                    $r->imei, $r->model, $r->tso, $r->rd_code, $r->rd_name, $r->rt_code, $r->rt_name,
                    $r->st_date, $r->activation_date, $r->sell_in_date, $r->source, $r->activation_days, $r->last_import_batch_id,
                ];
                if (++$done % 5000 === 0) {
                    $onProgress($done);
                }
            }
            $onProgress($done);
        })();

        return [$header, $rows];
    }

    /** Keyset pagination on the primary key — no OFFSET, stable under concurrent writes. */
    private function chunkById(Builder $query, int $size): iterable
    {
        $lastId = 0;
        do {
            $page = (clone $query)->where('id', '>', $lastId)->orderBy('id')->limit($size)->get();
            foreach ($page as $row) {
                yield $row;
                $lastId = $row->id;
            }
        } while ($page->count() === $size);
    }

    /** Pivoted stock export: RD (or RD+RT) rows, one column per model, qty in cells. */
    private function stockPivot(string $scope, ReportFilters $f): array
    {
        $rd = $f->rdCode;
        $rtCodes = $scope === 'rt' ? $f->rtCodes : [];
        $this->stock->forLifecycle($f->lifecycle);

        ['models' => $models, 'hasOther' => $hasOther] = $this->stock->modelColumns($scope, $rd, $rtCodes);
        $rows = $this->stock->exportRows($scope, $rd, $rtCodes, $models, $hasOther);

        $keyCols = $scope === 'rt'
            ? ['rd_code' => 'RD Code', 'rd_name' => 'RD Name', 'rt_code' => 'RT Code', 'rt_name' => 'RT Name']
            : ['rd_code' => 'RD Code', 'rd_name' => 'RD Name'];

        $dataKeys = array_merge(array_keys($keyCols), $models, $hasOther ? ['Other'] : [], ['Total']);
        $header = array_merge(array_values($keyCols), $models, $hasOther ? ['Other'] : [], ['Total']);

        $matrix = array_map(fn ($r) => array_map(fn ($k) => $r[$k] ?? 0, $dataKeys), $rows);

        // Second XLSX sheet for RT: RT Code + RT Name only, one row per RT code
        // (rows for the same retailer under different distributors are summed).
        $extraSheets = [];
        if ($scope === 'rt') {
            $numericCount = count($matrix ? $matrix[0] : []) - count($keyCols); // models (+Other) + Total
            $byRt = [];
            foreach ($matrix as $row) {
                $rtCode = $row[2];
                $byRt[$rtCode] ??= ['name' => $row[3], 'nums' => array_fill(0, $numericCount, 0)];
                foreach (array_slice($row, count($keyCols)) as $i => $v) {
                    $byRt[$rtCode]['nums'][$i] += (int) $v;
                }
            }

            $compact = [];
            foreach ($byRt as $rtCode => $g) {
                $compact[] = array_merge([$rtCode, $g['name']], $g['nums']);
            }
            usort($compact, fn ($a, $b) => end($b) <=> end($a)); // by Total

            $extraSheets[] = [
                'name' => 'By retailer',
                'header' => array_merge(['RT Code', 'RT Name'], $models, $hasOther ? ['Other'] : [], ['Total']),
                'rows' => $compact,
            ];
        }

        return [$header, $matrix, $extraSheets];
    }

    private function valueRows(ReportFilters $f): array
    {
        $header = ['Model', 'Qty', 'Total value', 'Avg price', 'Price range', 'Unpriced qty'];
        $data = $this->prices->valueByModel($f->valueFrom, $f->valueTo, $f->valueBasis ?? 'activation_date', $f->rdCode);

        $rows = (function () use ($data) {
            foreach ($data as $r) {
                yield [$r['model'], $r['qty'], $r['total_value'], $r['avg_price'], $r['price_range'], $r['unpriced_qty']];
            }
        })();

        return [$header, $rows];
    }

    private function schemeRows(ReportFilters $f): array
    {
        $scheme = Scheme::with('slabs')->where('uuid', $f->schemeUuid)->firstOrFail();
        $data = $this->schemes->achievement($scheme, $f->rdCode);

        $header = ['RT Code', 'RT Name', 'RD Code', 'RD Name', 'Qualified Qty',
            'Qualified Value', 'Slab', 'Payout %', 'Payout Amount', 'Reward'];

        $rows = (function () use ($data) {
            foreach ($data as $r) {
                yield [
                    $r['rt_code'], $r['rt_name'], $r['rd_code'], $r['rd_name'], $r['qty'],
                    $r['qualified_value'], $r['slab_no'] ?? '—', $r['payout_percent'],
                    $r['payout_amount'], $r['reward'] ?? '',
                ];
            }
        })();

        return [$header, $rows];
    }

    private function grouped(LengthAwarePaginator|Collection $result, array $header): array
    {
        $items = is_object($result) && method_exists($result, 'items') ? $result->items() : $result;

        $rows = (function () use ($items, $header) {
            foreach ($items as $row) {
                $row = (array) $row;
                $total = (int) ($row['total_imei'] ?? $row['total_activations'] ?? 0);
                $activated = (int) ($row['activated'] ?? 0);
                $out = [];
                foreach ($header as $col) {
                    $out[] = match ($col) {
                        'Activation %' => $total > 0 ? round($activated / $total * 100, 2) : 0,
                        default => $row[$this->slug($col)] ?? '',
                    };
                }
                yield $out;
            }
        })();

        return [$header, $rows];
    }

    private function slug(string $col): string
    {
        return match ($col) {
            'RD Code' => 'rd_code', 'RD Name' => 'rd_name',
            'RT Code', 'RTCode' => 'rt_code', 'RT Name' => 'rt_name',
            'Total IMEI', 'Total Sell-Through' => 'total_imei',
            'Activated' => 'activated', 'Not Activated' => 'not_activated',
            'ST Date' => 'st_date', 'Model' => 'model', 'TSO' => 'tso',
            'Sell-thru' => 'sell_through', 'In stock' => 'in_stock',
            default => str_replace([' ', '-'], '_', strtolower($col)),
        };
    }
}
