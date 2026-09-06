<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_batch_id', 'chunk_number', 'row_number', 'error_type', 'error_message', 'row_payload',
])]
class ImportRowError extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'row_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
