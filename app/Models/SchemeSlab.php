<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scheme_id', 'slab_no', 'label', 'min_value', 'max_value', 'payout_percent', 'reward',
])]
class SchemeSlab extends Model
{
    protected function casts(): array
    {
        return [
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'payout_percent' => 'decimal:2',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function matches(float $value): bool
    {
        return $value >= (float) $this->min_value
            && ($this->max_value === null || $value <= (float) $this->max_value);
    }
}
