<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rt_code', 'requested_by', 'proposed_latitude', 'proposed_longitude',
    'previous_latitude', 'previous_longitude', 'reason', 'status',
    'reviewed_by', 'reviewed_at', 'review_note',
])]
class RetailerLocationRequest extends Model
{
    protected function casts(): array
    {
        return [
            'proposed_latitude' => 'decimal:7',
            'proposed_longitude' => 'decimal:7',
            'previous_latitude' => 'decimal:7',
            'previous_longitude' => 'decimal:7',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class, 'rt_code', 'code');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
