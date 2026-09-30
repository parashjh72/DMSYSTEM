<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pjp_day_id', 'pjp_id', 'user_id', 'rt_code', 'visited_at',
    'latitude', 'longitude', 'accuracy', 'note',
    'outcome', 'order_items', 'order_value', 'no_order_reason',
])]
class PjpVisit extends Model
{
    public const EFFECTIVE = 'effective';

    public const NON_EFFECTIVE = 'non_effective';

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'order_items' => 'array',
            'order_value' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(PjpDay::class, 'pjp_day_id');
    }

    public function isEffective(): bool
    {
        return $this->outcome === self::EFFECTIVE;
    }

    public function isNonEffective(): bool
    {
        return $this->outcome === self::NON_EFFECTIVE;
    }

    public function reasonLabel(): ?string
    {
        return $this->no_order_reason ? (config('pjp.no_order_reasons')[$this->no_order_reason] ?? $this->no_order_reason) : null;
    }
}
