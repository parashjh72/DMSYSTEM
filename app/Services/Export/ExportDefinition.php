<?php

namespace App\Services\Export;

use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\ReportService;
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
    ) {}

    /** @return array{0: list<string>, 1: iterable<array<int,mixed>>} [header, rows] */
    public function build(string $type, ReportFilters $filters, callable $onProgress): array
    {
        return match ($type) {
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
            'stock_rd' => $this->grouped($this->stock->rdWise($filters->rdCode, PHP_INT_MAX),
                ['RD Code', 'RD Name', 'Model', 'Qty']),
            'stock_rt' => $this->grouped($this->stock->rtWise($filters->rdCode, $filters->rtCode, PHP_INT_MAX),
                ['RD Code', 'RD Name', 'RT Code', 'RT Name', 'Model', 'Qty']),
            'stock_model' => $this->grouped($this->stock->modelWise($filters->rdCode, PHP_INT_MAX),
                ['Model', 'RD stock', 'RT stock', 'Total stock']),
            default => throw new InvalidArgumentException("Unknown export type [{$type}]."),
        };
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
            default => str_replace([' ', '-'], '_', strtolower($col)),
        };
    }
}
