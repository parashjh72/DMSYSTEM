<?php

namespace App\Services\Import;

use App\Enums\ImportMode;
use Illuminate\Support\Facades\DB;

/**
 * Moves one chunk's validated rows from the staging table into
 * sales_activation_records with a single set-based statement — never row by row.
 *
 * Idempotent: the caller re-stages the chunk's rows first (delete + bulk insert by
 * (import_batch_id, row_number)), so replaying a failed chunk reapplies the exact
 * same upsert and cannot double-count or double-insert.
 */
class RecordUpserter
{
    /** Columns copied from staging; names align 1:1 in both tables. */
    private const VALUE_COLUMNS = [
        'model', 'product_code', 'tso', 'rd_code', 'rd_name', 'rt_code', 'rt_name',
        'st_date', 'activation_date', 'sell_in_date', 'source',
    ];

    /**
     * @return array{staged:int, existing:int, inserted:int, updated:int, skipped:int}
     */
    public function apply(int $batchId, int $startRow, int $endRow, ImportMode $mode): array
    {
        $bindings = ['batch' => $batchId, 'start' => $startRow, 'end' => $endRow];
        $now = now()->toDateTimeString();

        $staged = (int) DB::table('import_staging_rows')
            ->where('import_batch_id', $batchId)
            ->whereBetween('row_number', [$startRow, $endRow])
            ->count();

        if ($staged === 0) {
            return ['staged' => 0, 'existing' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0];
        }

        // One indexed join — NOT one query per row — to split new vs existing.
        $existing = (int) DB::selectOne(
            'SELECT COUNT(*) AS c
               FROM import_staging_rows s
               JOIN sales_activation_records r ON r.imei = s.imei
              WHERE s.import_batch_id = :batch
                AND s.row_number BETWEEN :start AND :end',
            $bindings,
        )->c;

        $new = $staged - $existing;

        match ($mode) {
            ImportMode::Upsert => $this->insertOnDuplicateUpdate($bindings, $now, updateExisting: true),
            ImportMode::InsertNew, ImportMode::SkipExisting => $this->insertOnDuplicateUpdate($bindings, $now, updateExisting: false),
            ImportMode::UpdateExisting => $this->updateOnly($bindings, $now),
        };

        return match ($mode) {
            ImportMode::Upsert => [
                'staged' => $staged, 'existing' => $existing,
                'inserted' => $new, 'updated' => $existing, 'skipped' => 0,
            ],
            ImportMode::InsertNew, ImportMode::SkipExisting => [
                'staged' => $staged, 'existing' => $existing,
                'inserted' => $new, 'updated' => 0, 'skipped' => $existing,
            ],
            ImportMode::UpdateExisting => [
                'staged' => $staged, 'existing' => $existing,
                'inserted' => 0, 'updated' => $existing, 'skipped' => $new,
            ],
        };
    }

    private function insertOnDuplicateUpdate(array $bindings, string $now, bool $updateExisting): void
    {
        $insertCols = array_merge(
            ['imei'], self::VALUE_COLUMNS,
            ['first_import_batch_id', 'last_import_batch_id', 'created_at', 'updated_at'],
        );

        $selectExprs = array_merge(
            ['s.imei'],
            array_map(fn ($c) => "s.{$c}", self::VALUE_COLUMNS),
            [':batchInsert AS first_import_batch_id', ':batchLast AS last_import_batch_id',
                ':createdAt AS created_at', ':updatedAt AS updated_at'],
        );

        $t = 'sales_activation_records';

        if ($updateExisting) {
            // Only non-empty incoming values overwrite; first_import_batch_id preserved.
            // Target columns are table-qualified: the staging alias is in scope here too.
            $updates = array_map(
                fn ($c) => "`{$c}` = COALESCE(VALUES(`{$c}`), `{$t}`.`{$c}`)",
                self::VALUE_COLUMNS,
            );
            $updates[] = '`last_import_batch_id` = VALUES(`last_import_batch_id`)';
            $updates[] = '`updated_at` = VALUES(`updated_at`)';
        } else {
            // New IMEIs insert; existing untouched (no-op self-assignment).
            $updates = ["`imei` = `{$t}`.`imei`"];
        }

        $sql = sprintf(
            'INSERT INTO sales_activation_records (%s)
             SELECT %s
               FROM import_staging_rows s
              WHERE s.import_batch_id = :batch
                AND s.row_number BETWEEN :start AND :end
             ON DUPLICATE KEY UPDATE %s',
            implode(', ', array_map(fn ($c) => "`{$c}`", $insertCols)),
            implode(', ', $selectExprs),
            implode(', ', $updates),
        );

        DB::statement($sql, $bindings + [
            'batchInsert' => $bindings['batch'],
            'batchLast' => $bindings['batch'],
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
    }

    private function updateOnly(array $bindings, string $now): void
    {
        $sets = array_map(
            fn ($c) => "r.`{$c}` = COALESCE(s.`{$c}`, r.`{$c}`)",
            self::VALUE_COLUMNS,
        );
        $sets[] = 'r.`last_import_batch_id` = :batchLast';
        $sets[] = 'r.`updated_at` = :updatedAt';

        $sql = sprintf(
            'UPDATE sales_activation_records r
               JOIN import_staging_rows s ON s.imei = r.imei
                SET %s
              WHERE s.import_batch_id = :batch
                AND s.row_number BETWEEN :start AND :end',
            implode(', ', $sets),
        );

        DB::statement($sql, $bindings + ['batchLast' => $bindings['batch'], 'updatedAt' => $now]);
    }
}
