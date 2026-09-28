<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Keeps impossible date orders out of sales_activation_records at import time:
 * a device cannot activate before its ST (sell-through) date, and cannot be sold
 * through before its sell-in date.
 *
 * Runs on a chunk's staged rows before they are applied. Each row is judged on
 * the dates the device would end up with (incoming value, else the stored one).
 * Conflicting rows are removed from staging and returned as `date_conflict`
 * errors, so they appear in the batch's downloadable error list for review.
 */
class ImportDateGuard
{
    /**
     * @param  list<string>|null  $scopeRdCodes
     * @return list<array{row_number:int, imei:string, message:string}>
     */
    public function quarantine(ImportBatch $batch, int $startRow, int $endRow, ?array $scopeRdCodes = null): array
    {
        [$join, $st, $activation, $sellIn, $extra] = $this->effectiveDates($batch);

        $scope = array_values(array_filter((array) $scopeRdCodes, fn ($c) => trim((string) $c) !== ''));

        $rows = DB::table('import_staging_rows as s')
            ->{$join}('sales_activation_records as r', 'r.imei', '=', 's.imei')
            ->where('s.import_batch_id', $batch->id)
            ->whereBetween('s.row_number', [$startRow, $endRow])
            ->when($extra, fn ($q) => $q->whereRaw($extra))
            ->when($scope !== [], fn ($q) => $q->whereIn('r.rd_code', $scope))
            ->where(fn ($q) => $q
                ->whereRaw("({$activation}) IS NOT NULL AND ({$st}) IS NOT NULL AND ({$activation}) < ({$st})")
                ->orWhereRaw("({$st}) IS NOT NULL AND ({$sellIn}) IS NOT NULL AND ({$st}) < ({$sellIn})"))
            ->selectRaw("s.row_number, s.imei, ({$st}) AS st_date, ({$activation}) AS activation_date, ({$sellIn}) AS sell_in_date")
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        DB::table('import_staging_rows')
            ->where('import_batch_id', $batch->id)
            ->whereIn('row_number', $rows->pluck('row_number')->all())
            ->delete();

        return $rows->map(fn (object $row): array => [
            'row_number' => (int) $row->row_number,
            'imei' => (string) $row->imei,
            'message' => self::describe($row->st_date, $row->activation_date, $row->sell_in_date),
        ])->all();
    }

    public static function describe(?string $stDate, ?string $activationDate, ?string $sellInDate): string
    {
        $problems = [];
        if ($activationDate !== null && $stDate !== null && $activationDate < $stDate) {
            $problems[] = "activation date {$activationDate} is before ST date {$stDate}";
        }
        if ($stDate !== null && $sellInDate !== null && $stDate < $sellInDate) {
            $problems[] = "ST date {$stDate} is before sell-in date {$sellInDate}";
        }

        return 'Date conflict: '.implode('; ', $problems).'. Fix the source data and re-import.';
    }

    /**
     * SQL for the dates each import kind would leave on the device.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: ?string} [join method, st, activation, sell-in, extra where]
     */
    private function effectiveDates(ImportBatch $batch): array
    {
        if ($batch->isSellThrough()) {
            return ['join', 's.st_date', 'r.activation_date', 'r.sell_in_date', null];
        }

        if ($batch->isActivation()) {
            return ['join', 'r.st_date', 's.activation_date', 'r.sell_in_date', null];
        }

        // Only rows the mode will actually write are judged.
        $onlyWritten = match (true) {
            ! $batch->import_mode->updatesExisting() => 'r.id IS NULL',
            ! $batch->import_mode->insertsNew() => 'r.id IS NOT NULL',
            default => null,
        };

        return [
            'leftJoin',
            'COALESCE(s.st_date, r.st_date)',
            'COALESCE(s.activation_date, r.activation_date)',
            'COALESCE(s.sell_in_date, r.sell_in_date)',
            $onlyWritten,
        ];
    }
}
