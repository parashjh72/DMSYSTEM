<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Services\Reporting\FilterOptions;
use App\Support\ModelClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Barrier job: runs once every chunk in the batch has settled. Rolls the
 * per-chunk counters into the batch, sets the final status, refreshes master
 * data, and kicks the summary rebuild for the affected dates.
 */
class FinalizeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public string $batchUuid) {}

    public function handle(): void
    {
        $batch = ImportBatch::where('uuid', $this->batchUuid)->first();
        if (! $batch || $batch->status === ImportStatus::Cancelled) {
            return;
        }

        $totals = DB::table('import_batch_chunks')
            ->where('import_batch_id', $batch->id)
            ->selectRaw('
                COUNT(*) AS chunks,
                SUM(status = "completed") AS completed_chunks,
                SUM(status = "failed") AS failed_chunks,
                COALESCE(SUM(read_rows), 0) AS processed_rows,
                COALESCE(SUM(inserted), 0) AS inserted_rows,
                COALESCE(SUM(updated), 0) AS updated_rows,
                COALESCE(SUM(skipped), 0) AS skipped_rows,
                COALESCE(SUM(duplicates), 0) AS duplicate_rows,
                COALESCE(SUM(invalid), 0) AS invalid_rows,
                COALESCE(SUM(failed), 0) AS failed_rows
            ')
            ->first();

        $validRows = (int) $totals->inserted_rows + (int) $totals->updated_rows;
        $hadErrors = (int) $totals->failed_chunks > 0
            || (int) $totals->invalid_rows > 0
            || (int) $totals->failed_rows > 0;

        $status = match (true) {
            (int) $totals->completed_chunks === 0 => ImportStatus::Failed,
            $hadErrors => ImportStatus::CompletedWithErrors,
            default => ImportStatus::Completed,
        };

        $completedAt = now();

        $batch->update([
            'status' => $status,
            'completed_chunks' => (int) $totals->completed_chunks,
            'processed_rows' => (int) $totals->processed_rows,
            'valid_rows' => $validRows,
            'invalid_rows' => (int) $totals->invalid_rows,
            'inserted_rows' => (int) $totals->inserted_rows,
            'updated_rows' => (int) $totals->updated_rows,
            'skipped_rows' => (int) $totals->skipped_rows,
            'duplicate_rows' => (int) $totals->duplicate_rows,
            'failed_rows' => (int) $totals->failed_rows,
            'completed_at' => $completedAt,
            'duration_seconds' => $batch->started_at
                ? max(0, $completedAt->diffInSeconds($batch->started_at, absolute: true))
                : null,
            'error_message' => $status === ImportStatus::Failed
                ? 'All chunks failed. See chunk errors.'
                : $batch->error_message,
        ]);

        // Staging is per-chunk transient; make sure nothing is left behind.
        DB::table('import_staging_rows')->where('import_batch_id', $batch->id)->delete();

        if ($status !== ImportStatus::Failed) {
            $this->refreshMasterData($batch->id);
            ModelClassifier::applyAll();
            app(FilterOptions::class)->forget();
            RefreshSummariesJob::dispatch(affectedBatchId: $batch->id)
                ->onQueue(config('import.queues.summary'));
        }
    }

    /** Upsert distinct RD / RT / TSO / Model touched by this batch. */
    private function refreshMasterData(int $batchId): void
    {
        DB::statement('
            INSERT INTO territory_officers (name, created_at, updated_at)
            SELECT DISTINCT tso, NOW(), NOW() FROM sales_activation_records
            WHERE last_import_batch_id = ? AND tso IS NOT NULL AND tso <> ""
            ON DUPLICATE KEY UPDATE updated_at = NOW()', [$batchId]);

        DB::statement('
            INSERT INTO device_models (name, created_at, updated_at)
            SELECT DISTINCT model, NOW(), NOW() FROM sales_activation_records
            WHERE last_import_batch_id = ? AND model IS NOT NULL AND model <> ""
            ON DUPLICATE KEY UPDATE updated_at = NOW()', [$batchId]);

        DB::statement('
            INSERT INTO retail_distributors (code, name, created_at, updated_at)
            SELECT DISTINCT rd_code, MAX(rd_name), NOW(), NOW() FROM sales_activation_records
            WHERE last_import_batch_id = ? AND rd_code IS NOT NULL AND rd_code <> ""
            GROUP BY rd_code
            ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = NOW()', [$batchId]);

        DB::statement('
            INSERT INTO retailers (code, name, rd_code, created_at, updated_at)
            SELECT DISTINCT rt_code, MAX(rt_name), MAX(rd_code), NOW(), NOW() FROM sales_activation_records
            WHERE last_import_batch_id = ? AND rt_code IS NOT NULL AND rt_code <> ""
            GROUP BY rt_code
            ON DUPLICATE KEY UPDATE name = VALUES(name), rd_code = VALUES(rd_code), updated_at = NOW()', [$batchId]);
    }

    public function failed(Throwable $e): void
    {
        ImportBatch::where('uuid', $this->batchUuid)->update([
            'status' => ImportStatus::CompletedWithErrors->value,
            'error_message' => 'Finalization error: '.$e->getMessage(),
        ]);
    }
}
