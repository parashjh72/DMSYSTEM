<?php

namespace App\Console\Commands;

use App\Services\Reporting\DashboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Wipe all imported / derived data while keeping the schema, users, roles and
 * permissions. Intended for resetting a dev or staging environment.
 */
class FlushDataCommand extends Command
{
    protected $signature = 'data:flush {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all records, imports, summaries, master data and exports (keeps users/roles)';

    /** Truncated in this order; FK checks are disabled around the batch. */
    private const TABLES = [
        'import_row_errors',
        'import_batch_chunks',
        'import_staging_rows',
        'import_batches',
        'sales_activation_records',
        'daily_activation_summary',
        'rd_summary',
        'rt_summary',
        'model_summary',
        'tso_summary',
        'retailers',
        'retail_distributors',
        'device_models',
        'territory_officers',
        'export_jobs',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(DashboardService $dashboard): int
    {
        if (! $this->option('force') && ! $this->confirm('This deletes ALL imported data, summaries, master data and exports. Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  truncated {$table}");
            }
        }
        Schema::enableForeignKeyConstraints();

        // Remove stored import uploads and generated export files.
        foreach (['imports', 'exports'] as $dir) {
            Storage::disk(config('import.disk'))->deleteDirectory($dir);
        }

        $dashboard->forget();
        $this->call('cache:clear');

        $this->info('All demo/imported data cleared. Schema, users and roles are intact.');

        return self::SUCCESS;
    }
}
