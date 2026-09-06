<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-load helper (§2 "write-cost trade-off"). For the first massive historical
 * load into a near-empty table, drop the secondary indexes, import, then restore
 * them in one ALTER — a single sort per index beats incremental B-tree
 * maintenance across millions of INSERTs. NEVER run --drop on a live reporting
 * table; searches and reports will table-scan until --restore completes.
 */
class ManageIndexesCommand extends Command
{
    protected $signature = 'records:indexes {--drop} {--restore}';

    protected $description = 'Drop or restore the secondary indexes on sales_activation_records for bulk loads';

    /** name => column list. The UNIQUE(imei) key is deliberately never touched. */
    private const SECONDARY = [
        'sales_activation_records_st_date_index' => '(st_date)',
        'sales_activation_records_activation_date_index' => '(activation_date)',
        'sales_activation_records_rd_code_st_date_index' => '(rd_code, st_date)',
        'sales_activation_records_rt_code_st_date_index' => '(rt_code, st_date)',
        'sales_activation_records_model_st_date_index' => '(model, st_date)',
        'sales_activation_records_tso_st_date_index' => '(tso, st_date)',
        'sales_activation_records_is_activated_st_date_index' => '(is_activated, st_date)',
        'sales_activation_records_activation_days_index' => '(activation_days)',
        'sales_activation_records_last_import_batch_id_index' => '(last_import_batch_id)',
    ];

    public function handle(): int
    {
        if ((bool) $this->option('drop') === (bool) $this->option('restore')) {
            $this->error('Pass exactly one of --drop or --restore.');

            return self::FAILURE;
        }

        $existing = collect(DB::select('SHOW INDEX FROM sales_activation_records'))
            ->pluck('Key_name')->unique()->all();

        if ($this->option('drop')) {
            $drops = collect(self::SECONDARY)
                ->keys()
                ->filter(fn ($name) => in_array($name, $existing, true))
                ->map(fn ($name) => "DROP INDEX `{$name}`");

            if ($drops->isEmpty()) {
                $this->info('No secondary indexes present.');

                return self::SUCCESS;
            }

            DB::statement('ALTER TABLE sales_activation_records '.$drops->implode(', '));
            $this->info("Dropped {$drops->count()} secondary index(es).");

            return self::SUCCESS;
        }

        $adds = collect(self::SECONDARY)
            ->reject(fn ($cols, $name) => in_array($name, $existing, true))
            ->map(fn ($cols, $name) => "ADD INDEX `{$name}` {$cols}");

        if ($adds->isEmpty()) {
            $this->info('All secondary indexes already present.');

            return self::SUCCESS;
        }

        $this->info("Restoring {$adds->count()} index(es) — this can take a while on a large table ...");
        DB::statement('ALTER TABLE sales_activation_records '.$adds->implode(', '));
        $this->info('Done.');

        return self::SUCCESS;
    }
}
