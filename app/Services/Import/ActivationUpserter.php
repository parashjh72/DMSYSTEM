<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

/**
 * Applies one chunk of an activation file: sets activation_date on existing
 * IMEIs *that have no activation date yet*. Never inserts, never overwrites.
 *
 * A device that already has an activation date is reported back as "skipped" so
 * re-running the same file cannot move an activation that is already recorded.
 * IMEIs not in the system are reported by the caller as not-found.
 * is_activated / activation_days are generated columns and update themselves.
 */
class ActivationUpserter
{
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

        // Of the matched IMEIs, how many have no activation date yet.
        $applied = (int) DB::selectOne(
            'SELECT COUNT(*) c
               FROM import_staging_rows s
               JOIN sales_activation_records r ON r.imei = s.imei
              WHERE s.import_batch_id = :batch AND s.row_number BETWEEN :start AND :end
                AND r.activation_date IS NULL
                AND s.activation_date IS NOT NULL',
            $bindings,
        )->c;

        DB::statement(
            'UPDATE sales_activation_records r
               JOIN import_staging_rows s ON s.imei = r.imei
                SET r.activation_date = s.activation_date,
                    r.last_import_batch_id = :batchLast,
                    r.updated_at = :now
              WHERE s.import_batch_id = :batch
                AND s.row_number BETWEEN :start AND :end
                AND r.activation_date IS NULL
                AND s.activation_date IS NOT NULL',
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
