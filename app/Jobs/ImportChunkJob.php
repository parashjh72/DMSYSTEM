<?php

namespace App\Jobs;

use App\Imports\RowMapException;
use App\Imports\RowMapper;
use App\Models\ImportBatch;
use App\Models\ImportBatchChunk;
use App\Services\Import\RecordUpserter;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Processes exactly one chunk. Idempotent and retry-safe:
 *  - a chunk already 'completed' is skipped
 *  - staging rows are deleted then re-inserted by (batch, row_number)
 *  - the staging -> records apply is a single set-based upsert
 * so replaying a half-finished chunk converges to the same result.
 *
 * Bad rows are logged to import_row_errors and never abort the chunk (§6).
 */
class ImportChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public string $batchUuid,
        public int $chunkNumber,
    ) {}

    public function handle(RecordUpserter $upserter): void
    {
        $batch = ImportBatch::where('uuid', $this->batchUuid)->first();
        if (! $batch || $batch->cancelled()) {
            return;
        }
        $chunk = ImportBatchChunk::where('import_batch_id', $batch->id)
            ->where('chunk_number', $this->chunkNumber)
            ->firstOrFail();

        if ($chunk->status === 'completed') {
            return;
        }

        $chunk->update(['status' => 'processing', 'attempts' => $chunk->attempts + 1]);

        $path = Storage::disk($batch->disk)->path($batch->stored_path);
        $reader = new SpreadsheetReader($path, $batch->file_type);
        $mapper = new RowMapper($batch->column_map ?? []);

        $valid = [];        // imei => normalised row (last occurrence wins)
        $firstSeenRow = [];  // imei => row_number of first occurrence
        $errors = [];
        $read = 0;
        $invalid = 0;
        $duplicates = 0;

        foreach ($reader->dataRows($chunk->start_row, $chunk->end_row) as $rowNumber => $cells) {
            $read++;
            try {
                $mapped = $mapper->map($cells);
            } catch (RowMapException $e) {
                $invalid++;
                $errors[] = $this->errorRow($batch->id, $rowNumber, $e->errorType, $e->getMessage(), $cells);

                continue;
            }

            $imei = $mapped['imei'];
            if (isset($valid[$imei])) {
                $duplicates++;
                $errors[] = $this->errorRow(
                    $batch->id, $rowNumber, 'duplicate_in_file',
                    "IMEI {$imei} also on row {$firstSeenRow[$imei]} of this file; later value kept.",
                    $cells,
                );
            } else {
                $firstSeenRow[$imei] = $rowNumber;
            }
            $valid[$imei] = $mapped + [
                'import_batch_id' => $batch->id,
                'row_number' => $rowNumber,
            ];
        }

        $applied = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($batch, $chunk, $valid, $upserter, &$applied) {
            DB::table('import_staging_rows')
                ->where('import_batch_id', $batch->id)
                ->whereBetween('row_number', [$chunk->start_row, $chunk->end_row])
                ->delete();

            if ($valid !== []) {
                foreach (array_chunk(array_values($valid), config('import.insert_batch', 2000)) as $slice) {
                    DB::table('import_staging_rows')->insert($slice);
                }

                $applied = $upserter->apply(
                    $batch->id, $chunk->start_row, $chunk->end_row, $batch->import_mode,
                );
            }

            DB::table('import_staging_rows')
                ->where('import_batch_id', $batch->id)
                ->whereBetween('row_number', [$chunk->start_row, $chunk->end_row])
                ->delete();
        });

        if ($errors !== []) {
            foreach (array_chunk($errors, 500) as $slice) {
                DB::table('import_row_errors')->insert($slice);
            }
        }

        $failedRows = max(0, $read - count($valid) - $invalid - $duplicates);

        $chunk->update([
            'status' => 'completed',
            'read_rows' => $read,
            'inserted' => $applied['inserted'],
            'updated' => $applied['updated'],
            'skipped' => $applied['skipped'],
            'duplicates' => $duplicates,
            'invalid' => $invalid,
            'failed' => $failedRows,
            'processed_at' => now(),
            'error_message' => null,
        ]);

        // Live progress only — authoritative totals are summed in FinalizeImportJob.
        DB::table('import_batches')->where('id', $batch->id)->update([
            'processed_rows' => DB::raw('processed_rows + '.$read),
            'completed_chunks' => DB::raw('completed_chunks + 1'),
            'updated_at' => now(),
        ]);
    }

    private function errorRow(int $batchId, int $rowNumber, string $type, string $message, array $cells): array
    {
        return [
            'import_batch_id' => $batchId,
            'chunk_number' => $this->chunkNumber,
            'row_number' => $rowNumber,
            'error_type' => $type,
            'error_message' => mb_substr($message, 0, 500),
            'row_payload' => json_encode(array_slice($cells, 0, 15)),
            'created_at' => now(),
        ];
    }

    public function failed(Throwable $e): void
    {
        $batch = ImportBatch::where('uuid', $this->batchUuid)->first();
        if (! $batch) {
            return;
        }

        ImportBatchChunk::where('import_batch_id', $batch->id)
            ->where('chunk_number', $this->chunkNumber)
            ->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'processed_at' => now(),
            ]);
    }
}
