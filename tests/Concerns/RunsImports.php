<?php

namespace Tests\Concerns;

use App\Enums\ImportMode;
use App\Models\ImportBatch;
use App\Services\Import\ImportService;

/** Runs a real CSV through the import pipeline (sync queue) and returns the finished batch. */
trait RunsImports
{
    /**
     * @param  list<list<string>>  $rows  first row is the header
     */
    protected function runImport(array $rows, string $kind, bool $overwrite = false, ?int $userId = null, ImportMode $mode = ImportMode::Upsert): ImportBatch
    {
        $path = tempnam(sys_get_temp_dir(), 'dms').'.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $service = app(ImportService::class);
        $batch = $service->createFromPath($path, $userId, $kind);
        $service->start($batch, mode: $mode, overwrite: $overwrite);
        unlink($path);

        return $batch->refresh();
    }
}
