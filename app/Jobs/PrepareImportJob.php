<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\ImportBatchChunk;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stage 1 of the import: count rows, slice into chunk descriptors, fan out
 * ImportChunkJob as a batch, and schedule FinalizeImportJob as the barrier.
 *
 * Streams the file once to count — never loads it.
 */
class PrepareImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public string $batchUuid) {}

    public function handle(): void
    {
        $batch = ImportBatch::where('uuid', $this->batchUuid)->firstOrFail();

        if ($batch->status === ImportStatus::Cancelled) {
            return;
        }

        $batch->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $path = Storage::disk($batch->disk)->path($batch->stored_path);
        $reader = new SpreadsheetReader($path, $batch->file_type);

        $totalRows = $reader->countDataRows();
        $chunkSize = max(500, (int) $batch->chunk_size);
        $totalChunks = (int) max(1, ceil($totalRows / $chunkSize));

        $batch->update([
            'total_rows' => $totalRows,
            'total_chunks' => $totalChunks,
            'processed_rows' => 0,
            'completed_chunks' => 0,
        ]);

        if ($totalRows === 0) {
            $batch->update([
                'status' => ImportStatus::CompletedWithErrors,
                'completed_at' => now(),
                'error_message' => 'File contained no data rows.',
            ]);

            return;
        }

        // (Re)create chunk descriptors — safe to re-run: unique (batch, chunk_number).
        $batch->chunks()->delete();
        $rows = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $start = $i * $chunkSize + 1;
            $end = min($totalRows, $start + $chunkSize - 1);
            $rows[] = [
                'import_batch_id' => $batch->id,
                'chunk_number' => $i,
                'start_row' => $start,
                'end_row' => $end,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 1000) as $slice) {
            ImportBatchChunk::insert($slice);
        }

        $jobs = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $jobs[] = new ImportChunkJob($batch->uuid, $i);
        }

        $uuid = $batch->uuid;

        Bus::batch($jobs)
            ->name("import:{$uuid}")
            ->allowFailures()
            ->onQueue(config('import.queues.chunk'))
            ->finally(fn (Batch $b) => FinalizeImportJob::dispatch($uuid)->onQueue(config('import.queues.finalize')))
            ->dispatch();
    }

    public function failed(Throwable $e): void
    {
        ImportBatch::where('uuid', $this->batchUuid)->update([
            'status' => ImportStatus::Failed->value,
            'error_message' => 'Preparation failed: '.$e->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
