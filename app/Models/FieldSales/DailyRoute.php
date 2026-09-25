<?php

namespace App\Models\FieldSales;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'route_date', 'distance_km', 'ping_count', 'kept_count', 'idle_minutes',
    'first_ping_at', 'last_ping_at', 'path', 'computed_at',
])]
class DailyRoute extends Model
{
    protected $table = 'fs_daily_routes';

    protected function casts(): array
    {
        return [
            'route_date' => 'date',
            'distance_km' => 'float',
            'ping_count' => 'integer',
            'kept_count' => 'integer',
            'idle_minutes' => 'integer',
            'first_ping_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'path' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
