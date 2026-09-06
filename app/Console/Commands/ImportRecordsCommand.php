<?php

namespace App\Console\Commands;

use App\Enums\ImportMode;
use App\Services\Import\ImportService;
use Illuminate\Console\Command;

class ImportRecordsCommand extends Command
{
    protected $signature = 'records:import
        {file : Absolute path to a CSV or XLSX file}
        {--mode=upsert : insert_new|skip_existing|update_existing|upsert}
        {--chunk= : Rows per chunk job (defaults to config/import.php)}
        {--sync : Process inline instead of dispatching to the queue}';

    protected $description = 'Queue a large CSV/XLSX file for import into sales_activation_records';

    public function handle(ImportService $service): int
    {
        $mode = ImportMode::tryFrom($this->option('mode'))
            ?? ImportMode::Upsert;

        $batch = $service->createFromPath($this->argument('file'), userId: null);

        $this->info("Batch {$batch->uuid} created for {$batch->original_filename}");
        $this->table(
            ['Field', 'Column #'],
            collect($batch->column_map)->map(fn ($i, $f) => [$f, $i])->values()->all(),
        );

        if ($missing = array_diff(config('import.required_fields'), array_keys($batch->column_map))) {
            $this->error('Unmapped required columns: '.implode(', ', $missing));

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            config(['queue.default' => 'sync']);
        }

        $service->start(
            $batch,
            mode: $mode,
            chunkSize: $this->option('chunk') ? (int) $this->option('chunk') : null,
        );

        $this->info($this->option('sync')
            ? 'Import finished.'
            : 'Import queued. Run `php artisan queue:work --queue=imports,summaries` to process.');

        $batch->refresh();
        $this->line("Status: {$batch->status->value}  Rows: {$batch->total_rows}  "
            ."Inserted: {$batch->inserted_rows}  Updated: {$batch->updated_rows}  "
            ."Invalid: {$batch->invalid_rows}  Duplicates: {$batch->duplicate_rows}");

        return self::SUCCESS;
    }
}
