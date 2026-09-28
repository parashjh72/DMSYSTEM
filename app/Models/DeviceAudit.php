<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One changed field on one device: old value -> new value, with the import
 * batch / transfer / user that caused it.
 */
#[Fillable([
    'imei', 'field', 'old_value', 'new_value', 'source',
    'import_batch_id', 'record_transfer_id', 'user_id', 'reason', 'created_at',
])]
class DeviceAudit extends Model
{
    public const UPDATED_AT = null;

    public const SOURCE_SELL_THROUGH = 'sell_through_import';

    public const SOURCE_ACTIVATION = 'activation_import';

    public const SOURCE_MANUAL = 'manual_edit';

    public const SOURCE_TRANSFER = 'transfer';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_SELL_THROUGH => 'Sell-through import',
            self::SOURCE_ACTIVATION => 'Activation import',
            self::SOURCE_MANUAL => 'Manual edit',
            self::SOURCE_TRANSFER => 'Transfer',
            default => $this->source,
        };
    }
}
