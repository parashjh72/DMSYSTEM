<?php

namespace App\Models\FieldSales;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'client_uuid', 'recorded_at', 'latitude', 'longitude',
    'accuracy', 'speed', 'battery', 'source', 'is_suspect', 'created_at',
])]
class LocationPing extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'fs_location_pings';

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'speed' => 'float',
            'battery' => 'integer',
            'is_suspect' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
