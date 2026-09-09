<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scheme_id', 'rt_code', 'rt_name', 'enrolled_on', 'effective_from', 'effective_to',
    'status', 'deactivated_at', 'deactivated_by', 'plan', 'category', 'min_slab', 'note', 'added_by',
])]
class SchemeRetailer extends Model
{
    protected function casts(): array
    {
        return [
            'enrolled_on' => 'date',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'deactivated_at' => 'datetime',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Minimum slab this retailer must reach — explicit override or the category default. */
    public function effectiveMinSlab(): int
    {
        return $this->min_slab
            ?? config("schemes.categories.{$this->category}.min_slab", 1);
    }
}
