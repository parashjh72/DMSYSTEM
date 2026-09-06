<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_batch_id', 'chunk_number', 'start_row', 'end_row', 'status', 'attempts',
    'read_rows', 'inserted', 'updated', 'skipped', 'duplicates', 'invalid', 'failed',
    'error_message', 'processed_at',
])]
class ImportBatchChunk extends Model
{
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
