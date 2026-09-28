<?php

namespace App\Services\Import;

use App\Models\DeviceAudit;
use Illuminate\Support\Facades\DB;

/**
 * Applies one chunk of an activation file: sets activation_date on existing
 * IMEIs *that have no activation date yet*. Never inserts, never overwrites.
 *
 * A device that already has an activation date is reported back as "skipped" so
 * re-running the same file cannot move an activation that is already recorded.
 * IMEIs not in the system are reported by the caller as not-found.
 * is_activated / activation_days are generated columns and update themselves.
 *
 * Overwrite mode (per-batch switch): a device whose stored activation date differs
 * from the file is corrected too, and the replaced date is written to
 * device_audits first. An empty incoming cell never clears a stored date.
 */
class ActivationUpserter
{
    /**
     * @return array{staged:int, matched:int, applied:int, skipped:int, not_found:int}
     */
    public function apply(int $batchId, int $startRow, int $endRow, bool $overwrite = false, ?int $userId = null): array
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

        // Eligible rows: no activation date yet, or (overwrite mode) a different one.
        $eligible = $overwrite
            ? 'NOT (r.activation_date <=> s.activation_date)'
            : 'r.activation_date IS NULL';
        $where = 'WHERE s.import_batch_id = :batch AND s.row_number BETWEEN :start AND :end
                AND s.activation_date IS NOT NULL AND '.$eligible;
        $now = now()->toDateTimeString();

        $applied = (int) DB::selectOne(
            'SELECT COUNT(*) c
               FROM import_staging_rows s
               JOIN sales_activation_records r ON r.imei = s.imei
              '.$where,
            $bindings,
        )->c;

        if ($overwrite) {
            DB::statement(
                "INSERT INTO device_audits (imei, field, old_value, new_value, source, import_batch_id, user_id, created_at)
                 SELECT r.imei, 'activation_date', r.activation_date, s.activation_date, :source, :auditBatch, :user, :now
                   FROM import_staging_rows s
                   JOIN sales_activation_records r ON r.imei = s.imei
                  {$where}
                    AND r.activation_date IS NOT NULL",
                $bindings + ['source' => DeviceAudit::SOURCE_ACTIVATION, 'auditBatch' => $batchId, 'user' => $userId, 'now' => $now],
            );
        }

        DB::statement(
            'UPDATE sales_activation_records r
               JOIN import_staging_rows s ON s.imei = r.imei
                SET r.activation_date = s.activation_date,
                    r.last_import_batch_id = :batchLast,
                    r.updated_at = :now
              '.$where,
            $bindings + ['batchLast' => $batchId, 'now' => $now],
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
