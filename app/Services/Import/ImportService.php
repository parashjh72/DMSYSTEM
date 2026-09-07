<?php

namespace App\Services\Import;

use App\Enums\ImportMode;
use App\Enums\ImportStatus;
use App\Jobs\PrepareImportJob;
use App\Models\ImportBatch;
use App\Support\HeaderMap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImportService
{
    /**
     * Persist an uploaded file and create a pending batch. Does NOT start the
     * import — the caller reviews the column map first, then calls start().
     */
    public function createFromUpload(UploadedFile $file, ?int $userId, string $kind = 'records'): ImportBatch
    {
        $type = $this->detectType($file->getClientOriginalName(), $file->getMimeType());
        $disk = config('import.disk');
        $dir = config('import.directory');

        $name = Str::uuid().'.'.$type;
        $storedPath = $file->storeAs($dir, $name, $disk);

        if ($storedPath === false) {
            throw new RuntimeException('Could not store the uploaded file.');
        }

        return $this->makeBatch(
            disk: $disk,
            storedPath: $storedPath,
            originalName: $file->getClientOriginalName(),
            type: $type,
            size: $file->getSize(),
            userId: $userId,
            kind: $kind,
        );
    }

    /** Register an existing on-disk file (CLI import). */
    public function createFromPath(string $absolutePath, ?int $userId, string $kind = 'records'): ImportBatch
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("File not found: {$absolutePath}");
        }

        $type = $this->detectType($absolutePath, null);
        $disk = config('import.disk');
        $dir = config('import.directory');
        $name = Str::uuid().'.'.$type;
        $storedPath = $dir.'/'.$name;

        Storage::disk($disk)->put($storedPath, fopen($absolutePath, 'r'));

        return $this->makeBatch(
            disk: $disk,
            storedPath: $storedPath,
            originalName: basename($absolutePath),
            type: $type,
            size: filesize($absolutePath) ?: 0,
            userId: $userId,
            kind: $kind,
        );
    }

    /** @return array{0: array<string,array<int,string>>, 1: list<string>} [aliases, required] */
    public static function schemaFor(string $kind): array
    {
        return match ($kind) {
            'sell_through' => [config('import.sell_through_aliases'), config('import.sell_through_required')],
            'activation' => [config('import.activation_aliases'), config('import.activation_required')],
            default => [config('import.header_aliases'), config('import.required_fields')],
        };
    }

    private function makeBatch(string $disk, string $storedPath, string $originalName, string $type, int $size, ?int $userId, string $kind = 'records'): ImportBatch
    {
        $kind = in_array($kind, ['sell_through', 'activation'], true) ? $kind : 'records';
        $absolute = Storage::disk($disk)->path($storedPath);
        $reader = new SpreadsheetReader($absolute, $type);
        $headers = $reader->headers();
        [$aliases, $required] = self::schemaFor($kind);
        $resolved = HeaderMap::resolve($headers, $aliases, $required);

        return ImportBatch::create([
            'original_filename' => $originalName,
            'stored_path' => $storedPath,
            'disk' => $disk,
            'file_type' => $type,
            'file_size' => $size,
            'file_hash' => hash_file('sha256', $absolute),
            'kind' => $kind,
            'column_map' => $resolved['map'],
            'import_mode' => ImportMode::Upsert,
            'duplicate_strategy' => 'update',
            'status' => ImportStatus::Pending,
            'chunk_size' => config('import.chunk_size'),
            'created_by' => $userId,
        ]);
    }

    /**
     * Confirm mapping / options and queue the import.
     *
     * @param  array<string,int>|null  $columnMap  overrides the auto-resolved map
     */
    public function start(ImportBatch $batch, ?array $columnMap = null, ?ImportMode $mode = null, ?int $chunkSize = null): void
    {
        $map = $columnMap ?? $batch->column_map ?? [];

        [, $required] = self::schemaFor($batch->kind);
        foreach ($required as $field) {
            if (! array_key_exists($field, $map)) {
                throw new RuntimeException("Required column [{$field}] is not mapped.");
            }
        }

        $batch->update([
            'column_map' => $map,
            'import_mode' => $mode ?? $batch->import_mode,
            'chunk_size' => $chunkSize ?: $batch->chunk_size,
            'status' => ImportStatus::Queued,
        ]);

        PrepareImportJob::dispatch($batch->uuid)->onQueue(config('import.queues.prepare'));
    }

    public function detectType(string $filename, ?string $mime): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, ['csv', 'txt'], true) => 'csv',
            $ext === 'xlsx' => 'xlsx',
            $mime === 'text/csv' => 'csv',
            $mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => throw new RuntimeException("Unsupported file type: .{$ext}. Use CSV or XLSX."),
        };
    }
}
