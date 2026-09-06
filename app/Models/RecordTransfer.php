<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid', 'mode', 'only_in_stock', 'move_distributor',
    'from_rt_code', 'from_rd_code', 'to_rt_code', 'to_rt_name', 'to_rd_code', 'to_rd_name',
    'requested_count', 'affected_count', 'imeis', 'not_found', 'performed_by', 'created_at',
])]
class RecordTransfer extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'only_in_stock' => 'boolean',
            'move_distributor' => 'boolean',
            'imeis' => 'array',
            'not_found' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
