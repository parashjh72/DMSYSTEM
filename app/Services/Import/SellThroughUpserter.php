<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

/**
 * Applies one chunk of a sell-through file: for each staged IMEI that exists in
 * sales_activation_records, set its RT code (+ RT name from the retailers
 * master), ST/invoice date and RD code. Never inserts. IMEIs not already in the
 * system are reported by the caller as not-found.
 */
class SellThroughUpserter
{
    /**
     * @return array{staged:int, matched:int, not_found:int}
     */
    public function apply(int $batchId, int $startRow, int $endRow): array
    {
        $bindings = ['batch' => $batchId, 'start' => $startRow, 'end' => $endRow];

        $staged = (int) DB::table('import_staging_rows')
            ->where('import_batch_id', $batchId)
            ->whereBetween('row_number', [$startRow, $endRow])
            ->count();

        if ($staged === 0) {
            return ['staged' => 0, 'matched' => 0, 'not_found' => 0];
        }

        $matched = (int) DB::selectOne(
            'SELECT COUNT(*) c
               FROM import_staging_rows s
               JOIN sales_activation_records r ON r.imei = s.imei
              WHERE s.import_batch_id = :batch AND s.row_number BETWEEN :start AND :end',
            $bindings,
        )->c;

        // Only RT + invoice date are applied. The device's RD and model come from
        // the model-data import and are left untouched.
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
                AND s.row_number BETWEEN :start AND :end',
            $bindings + ['batchLast' => $batchId, 'now' => now()->toDateTimeString()],
        );

        return ['staged' => $staged, 'matched' => $matched, 'not_found' => $staged - $matched];
    }
}
