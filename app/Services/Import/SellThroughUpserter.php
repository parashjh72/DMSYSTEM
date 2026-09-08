<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

/**
 * Applies one chunk of a sell-through file: for each staged IMEI that exists in
 * sales_activation_records *and does not already carry retailer / invoice-date
 * information*, set its RT code (+ RT name from the retailers master) and
 * ST/invoice date. Never inserts, never overwrites.
 *
 * A record is only touched when all three of rt_code, rt_name and st_date are
 * still empty — an already-assigned device is reported back as "skipped" so a
 * re-run of the same file cannot change a retailer that is already recorded.
 * IMEIs not in the system at all are reported by the caller as not-found.
 */
class SellThroughUpserter
{
    /** SQL predicate: the record has no retailer / invoice-date yet. */
    private const UNASSIGNED = "(r.rt_code IS NULL OR r.rt_code = '')
        AND (r.rt_name IS NULL OR r.rt_name = '')
        AND r.st_date IS NULL";

    /**
     * @return array{staged:int, matched:int, applied:int, skipped:int, not_found:int}
     */
    public function apply(int $batchId, int $startRow, int $endRow): array
    {
        $bindings = ['batch' => $batchId, 'start' => $startRow, 'end' => $endRow];

        $staged = (int) DB::table('import_staging_rows')
            ->where('import_batch_id', $batchId)
            ->whereBetween('row_number', [$startRow, $endRow])
            ->count();

        if ($staged === 0) {
            return ['staged' => 0, 'matched' => 0, 'applied' => 0, 'skipped' => 0, 'not_found' => 0];
        }

        $matched = (int) DB::selectOne(
            'SELECT COUNT(*) c
               FROM import_staging_rows s
               JOIN sales_activation_records r ON r.imei = s.imei
              WHERE s.import_batch_id = :batch AND s.row_number BETWEEN :start AND :end',
            $bindings,
        )->c;

        // Of the matched IMEIs, how many are still unassigned (eligible to update).
        $applied = (int) DB::selectOne(
            'SELECT COUNT(*) c
               FROM import_staging_rows s
               JOIN sales_activation_records r ON r.imei = s.imei
              WHERE s.import_batch_id = :batch AND s.row_number BETWEEN :start AND :end
                AND '.self::UNASSIGNED,
            $bindings,
        )->c;

        // Only RT + invoice date are applied, and only to records that have none
        // yet. The device's RD and model come from the model-data import.
        DB::statement(
            'UPDATE sales_activation_records r
               JOIN import_staging_rows s ON s.imei = r.imei
          LEFT JOIN retailers rt ON rt.code = s.rt_code
                SET r.rt_code = s.rt_code,
                    r.rt_name = COALESCE(rt.name, r.rt_name),
                    r.st_date = COALESCE(s.st_date, r.st_date),
                    r.last_import_batch_id = :batchLast,
                    r.updated_at = :now
              WHERE s.import_batch_id = :batch
                AND s.row_number BETWEEN :start AND :end
                AND '.self::UNASSIGNED,
            $bindings + ['batchLast' => $batchId, 'now' => now()->toDateTimeString()],
        );

        return [
            'staged' => $staged,
            'matched' => $matched,
            'applied' => $applied,
            'skipped' => $matched - $applied,
            'not_found' => $staged - $matched,
        ];
    }
}
