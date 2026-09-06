<?php

namespace App\Console\Commands;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use Illuminate\Console\Command;

/**
 * Fail-safe: a batch left in 'processing' with no chunk activity past the
 * configured window (worker crash / OOM kill) is marked failed and its
 * unfinished chunks released so it can be retried cleanly.
 */
class ReapStaleImportsCommand extends Command
{
    protected $signature = 'import:reap-stale';

    protected $description = 'Mark crashed/stalled import batches as failed and release their chunks';

    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) config('import.stale_after_minutes', 30));

        $stale = ImportBatch::whereIn('status', [ImportStatus::Processing, ImportStatus::Queued])
            ->where('updated_at', '<', $cutoff)
            ->get();

        foreach ($stale as $batch) {
            $batch->chunks()->whereIn('status', ['pending', 'processing'])->update([
                'status' => 'failed',
                'error_message' => 'Reaped: no progress before stale cutoff.',
            ]);

            $batch->update([
                'status' => ImportStatus::Failed,
                'error_message' => 'Import stalled (no worker progress). Retry from the batch page.',
                'completed_at' => now(),
            ]);

            $this->warn("Reaped batch {$batch->uuid} ({$batch->original_filename}).");
        }

        $this->info("Checked. {$stale->count()} batch(es) reaped.");

        return self::SUCCESS;
    }
}
