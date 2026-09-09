<?php

namespace App\Jobs;

use App\Imports\ActivationRowMapper;
use App\Imports\RowMapException;
use App\Imports\RowMapper;
use App\Imports\SellThroughRowMapper;
use App\Models\ImportBatch;
use App\Models\ImportBatchChunk;
use App\Services\Import\ActivationUpserter;
use App\Services\Import\RecordUpserter;
use App\Services\Import\SellThroughUpserter;
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

    public function handle(RecordUpserter $upserter, SellThroughUpserter $sellThrough, ActivationUpserter $activation): void
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

        $sell = $batch->isSellThrough();
        $activate = $batch->isActivation();
        $updateOnly = $sell || $activate; // update-existing kinds that never insert
        $path = Storage::disk($batch->disk)->path($batch->stored_path);
        $reader = new SpreadsheetReader($path, $batch->file_type);
        $mapper = match (true) {
            $sell => new SellThroughRowMapper($batch->column_map ?? []),
            $activate => new ActivationRowMapper($batch->column_map ?? []),
            default => new RowMapper($batch->column_map ?? []),
        };

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
        $notFound = 0;

        DB::transaction(function () use ($batch, $chunk, $valid, $upserter, $sellThrough, $activation, $sell, $updateOnly, &$applied, &$notFound, &$errors) {
            DB::table('import_staging_rows')
                ->where('import_batch_id', $batch->id)
                ->whereBetween('row_number', [$chunk->start_row, $chunk->end_row])
                ->delete();

            if ($valid !== []) {
                foreach (array_chunk(array_values($valid), config('import.insert_batch', 2000)) as $slice) {
                    DB::table('import_staging_rows')->insert($slice);
                }

                if ($updateOnly) {
                    $scope = $sell ? ($batch->scope_rd_codes ?: null) : null;

                    // Log IMEIs that can't be updated before the update runs: not
                    // in the system at all, or (for a scoped import) not one of
                    // the importer's own distributors.
                    $missing = DB::table('import_staging_rows as s')
                        ->leftJoin('sales_activation_records as r', 'r.imei', '=', 's.imei')
                        ->where('s.import_batch_id', $batch->id)
                        ->whereBetween('s.row_number', [$chunk->start_row, $chunk->end_row])
                        ->where(fn ($q) => $q->whereNull('r.id')
                            ->when($scope, fn ($w) => $w->orWhereNotIn('r.rd_code', $scope)))
                        ->get(['s.row_number', 's.imei', 'r.id as record_id']);
                    foreach ($missing as $row) {
                        $message = $row->record_id === null
                            ? "IMEI {$row->imei} is not in the system — import the ND → RD data first."
                            : "IMEI {$row->imei} belongs to another distributor.";
                        $errors[] = $this->errorRow($batch->id, (int) $row->row_number, 'imei_not_found', $message, [$row->imei]);
                    }

                    $r = $sell
                        ? $sellThrough->apply($batch->id, $chunk->start_row, $chunk->end_row, $scope)
                        : $activation->apply($batch->id, $chunk->start_row, $chunk->end_row);
                    // Existing IMEIs that already carry the info are skipped, not updated.
                    $applied = ['inserted' => 0, 'updated' => $r['applied'], 'skipped' => $r['skipped']];
                    $notFound = $r['not_found'];
                } else {
                    $applied = $upserter->apply(
                        $batch->id, $chunk->start_row, $chunk->end_row, $batch->import_mode,
                    );
                }
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

        $failedRows = max(0, $read - count($valid) - $invalid - $duplicates) + $notFound;

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
