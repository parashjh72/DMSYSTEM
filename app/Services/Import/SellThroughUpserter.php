<?php

namespace App\Services\Import;

use App\Models\DeviceAudit;
use Illuminate\Support\Facades\DB;

/**
 * Applies one chunk of a sell-through file: for each staged IMEI that exists in
 * sales_activation_records *and does not already carry retailer / invoice-date
 * information*, set its RT code (+ RT name from the retailers master) and
 * ST/invoice date. Never inserts, never overwrites. Any Model column is ignored.
 *
 * A record is only touched when all three of rt_code, rt_name and st_date are
 * still empty — an already-assigned device is reported back as "skipped" so a
 * re-run of the same file cannot change a retailer that is already recorded.
 *
 * When $scopeRdCodes is given (an RD-scoped user's import) the update is further
 * restricted to devices whose rd_code is in that list; IMEIs outside it are
 * counted as not-found.
 *
 * Overwrite mode (per-batch switch): a device that already has a retailer is
 * updated too when the row differs from what is stored — RT code, ST date and,
 * when the cell is filled, RD code. An empty incoming RD cell never blanks the
 * stored RD. Every replaced non-empty value is written to device_audits first.
 */
class SellThroughUpserter
{
    /** SQL predicate: the record has no retailer / invoice-date yet. */
    private const UNASSIGNED = "(r.rt_code IS NULL OR r.rt_code = '')
        AND (r.rt_name IS NULL OR r.rt_name = '')
        AND r.st_date IS NULL";

    /** SQL predicate: the staged row differs from the stored record (overwrite mode). */
    private const CHANGED = "(NOT (r.rt_code <=> s.rt_code)
        OR NOT (r.st_date <=> s.st_date)
        OR (s.rd_code IS NOT NULL AND s.rd_code <> '' AND NOT (r.rd_code <=> s.rd_code)))";

    /** Audited fields => whether a change only counts when the incoming cell is filled. */
    private const AUDITED = [
        'rt_code' => false,
        'st_date' => false,
        'rd_code' => true,
    ];

    /**
     * @param  list<string>|null  $scopeRdCodes
     * @return array{staged:int, matched:int, applied:int, skipped:int, not_found:int}
     */
    public function apply(int $batchId, int $startRow, int $endRow, ?array $scopeRdCodes = null, bool $overwrite = false, ?int $userId = null): array
    {
        $bindings = ['batch' => $batchId, 'start' => $startRow, 'end' => $endRow];

        [$scopeSql, $scopeBindings] = $this->scopeClause($scopeRdCodes);
        $bindings += $scopeBindings;

        $staged = (int) DB::table('import_staging_rows')
            ->where('import_batch_id', $batchId)
            ->whereBetween('row_number', [$startRow, $endRow])
            ->count();

        if ($staged === 0) {
            return ['staged' => 0, 'matched' => 0, 'applied' => 0, 'skipped' => 0, 'not_found' => 0];
        }

        // Matched = staged IMEIs that exist AND are in scope.
        $matched = (int) DB::selectOne(
            'SELECT COUNT(*) c
               FROM import_staging_rows s
               JOIN sales_activation_records r ON r.imei = s.imei
              WHERE s.import_batch_id = :batch AND s.row_number BETWEEN :start AND :end'.$scopeSql,
            $bindings,
        )->c;

        // Eligible rows: unassigned devices, or (overwrite mode) any device whose row changed.
        $eligible = $overwrite ? self::CHANGED : self::UNASSIGNED;

        $applied = (int) DB::selectOne(
            'SELECT COUNT(*) c
               FROM import_staging_rows s
               JOIN sales_activation_records r ON r.imei = s.imei
              WHERE s.import_batch_id = :batch AND s.row_number BETWEEN :start AND :end'.$scopeSql.'
                AND '.$eligible,
            $bindings,
        )->c;

        if ($overwrite) {
            $this->overwrite($bindings, $scopeSql, $batchId, $userId);

            return [
                'staged' => $staged,
                'matched' => $matched,
                'applied' => $applied,
                'skipped' => $matched - $applied,
                'not_found' => $staged - $matched,
            ];
        }

        // Only RT + invoice date are applied, and only to records that have none
        // yet. The device's RD and model come from the ND → RD import.
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
                AND s.row_number BETWEEN :start AND :end'.$scopeSql.'
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

    /**
     * Audits, then updates, every device whose staged row differs from what is stored.
     * Names are refreshed in a first statement (they compare against the still-old
     * codes); codes and dates follow in a second one.
     *
     * @param  array<string, mixed>  $bindings
     */
    private function overwrite(array $bindings, string $scopeSql, int $batchId, ?int $userId): void
    {
        $now = now()->toDateTimeString();
        $where = 'WHERE s.import_batch_id = :batch AND s.row_number BETWEEN :start AND :end'.$scopeSql.' AND '.self::CHANGED;

        foreach (self::AUDITED as $field => $onlyWhenFilled) {
            // Only replaced values are audited: an empty stored value being filled is a normal import.
            // Date columns cannot be compared with '' under strict SQL mode.
            $storedFilled = $field === 'st_date' ? "r.{$field} IS NOT NULL" : "r.{$field} IS NOT NULL AND r.{$field} <> ''";
            $incomingFilled = $onlyWhenFilled ? "s.{$field} IS NOT NULL AND s.{$field} <> ''" : "s.{$field} IS NOT NULL";

            DB::statement(
                "INSERT INTO device_audits (imei, field, old_value, new_value, source, import_batch_id, user_id, created_at)
                 SELECT r.imei, '{$field}', r.{$field}, s.{$field}, :source, :auditBatch, :user, :now
                   FROM import_staging_rows s
                   JOIN sales_activation_records r ON r.imei = s.imei
                  {$where}
                    AND {$storedFilled}
                    AND {$incomingFilled}
                    AND NOT (r.{$field} <=> s.{$field})",
                $bindings + ['source' => DeviceAudit::SOURCE_SELL_THROUGH, 'auditBatch' => $batchId, 'user' => $userId, 'now' => $now],
            );
        }

        DB::statement(
            "UPDATE sales_activation_records r
               JOIN import_staging_rows s ON s.imei = r.imei
          LEFT JOIN retailers rt ON rt.code = s.rt_code
          LEFT JOIN retail_distributors rd ON rd.code = s.rd_code
                SET r.rt_name = CASE WHEN r.rt_code <=> s.rt_code THEN COALESCE(rt.name, r.rt_name) ELSE rt.name END,
                    r.rd_name = CASE WHEN s.rd_code IS NULL OR s.rd_code = '' OR r.rd_code <=> s.rd_code
                                     THEN r.rd_name ELSE rd.name END
              {$where}",
            $bindings,
        );

        DB::statement(
            "UPDATE sales_activation_records r
               JOIN import_staging_rows s ON s.imei = r.imei
                SET r.rt_code = s.rt_code,
                    r.st_date = COALESCE(s.st_date, r.st_date),
                    r.rd_code = CASE WHEN s.rd_code IS NULL OR s.rd_code = '' THEN r.rd_code ELSE s.rd_code END,
                    r.last_import_batch_id = :batchLast,
                    r.updated_at = :now
              {$where}",
            $bindings + ['batchLast' => $batchId, 'now' => $now],
        );
    }

    /**
     * @param  list<string>|null  $codes
     * @return array{0: string, 1: array<string,string>} [" AND r.rd_code IN (:rd0,...)", bindings]
     */
    private function scopeClause(?array $codes): array
    {
        $codes = array_values(array_filter((array) $codes, fn ($c) => trim((string) $c) !== ''));
        if ($codes === []) {
            return ['', []];
        }

        $placeholders = [];
        $bindings = [];
        foreach ($codes as $i => $code) {
            $placeholders[] = ":rd{$i}";
            $bindings["rd{$i}"] = $code;
        }

        return [' AND r.rd_code IN ('.implode(', ', $placeholders).')', $bindings];
    }
}
