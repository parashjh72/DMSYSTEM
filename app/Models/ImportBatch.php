<?php

namespace App\Models;

use App\Enums\ImportMode;
use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'uuid', 'original_filename', 'stored_path', 'disk', 'file_type', 'file_size', 'file_hash', 'kind',
    'column_map', 'import_mode', 'duplicate_strategy', 'status', 'chunk_size',
    'total_chunks', 'completed_chunks', 'total_rows', 'processed_rows', 'valid_rows',
    'invalid_rows', 'inserted_rows', 'updated_rows', 'skipped_rows', 'duplicate_rows',
    'failed_rows', 'error_message', 'started_at', 'completed_at', 'duration_seconds', 'created_by',
])]
class ImportBatch extends Model
{
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'import_mode' => ImportMode::class,
            'status' => ImportStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ImportBatchChunk::class);
    }

    public function rowErrors(): HasMany
    {
        return $this->hasMany(ImportRowError::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelled(): bool
    {
        return $this->status === ImportStatus::Cancelled;
    }

    public function isSellThrough(): bool
    {
        return $this->kind === 'sell_through';
    }

    public function isActivation(): bool
    {
        return $this->kind === 'activation';
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'sell_through' => 'Sell-through (RD → RT)',
            'activation' => 'Activation',
            default => 'Model data',
        };
    }

    public function progressPercent(): int
    {
        if (! $this->total_rows) {
            return $this->status->isTerminal() ? 100 : 0;
        }

        return (int) min(100, floor($this->processed_rows / $this->total_rows * 100));
    }

    public function file(): string
    {
        return $this->stored_path;
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->stored_path);
    }
}
