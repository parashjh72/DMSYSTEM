<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Canonical raw record — one row per IMEI.
 *
 * The import pipeline writes to this table in bulk (query builder / raw upsert),
 * NOT through this model. The model exists for reads: IMEI search, the Data
 * Explorer, and single-record detail views. Model events / observers are
 * intentionally absent so nothing hooks per-row work onto a bulk load.
 *
 * @property string $imei
 * @property ?string $model
 * @property ?Carbon $st_date
 * @property ?Carbon $activation_date
 * @property ?int $activation_days
 * @property bool $is_activated
 */
#[Fillable([
    'imei', 'model', 'tso', 'rd_code', 'rd_name', 'rt_code', 'rt_name',
    'st_date', 'activation_date', 'source',
    'first_import_batch_id', 'last_import_batch_id',
])]
class SalesActivationRecord extends Model
{
    protected function casts(): array
    {
        return [
            'st_date' => 'date',
            'activation_date' => 'date',
            'activation_days' => 'integer',
            'is_activated' => 'boolean',
        ];
    }

    public function lastImportBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'last_import_batch_id');
    }

    public function firstImportBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'first_import_batch_id');
    }

    /** ST -> activation lag bucket key, matching the summary table columns. */
    public function lagBucket(): ?string
    {
        return match (true) {
            $this->activation_days === null => null,
            $this->activation_days <= 0 => 'lag_d0',
            $this->activation_days <= 7 => 'lag_d1_7',
            $this->activation_days <= 15 => 'lag_d8_15',
            $this->activation_days <= 30 => 'lag_d16_30',
            default => 'lag_d31_plus',
        };
    }
}
