<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rt_code', 'rt_name', 'rd_code', 'target_volume', 'incentive_pct',
    'starts_on', 'ends_on', 'status', 'notes', 'created_by',
])]
class AnnualContract extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'target_volume' => 'integer',
            'incentive_pct' => 'decimal:2',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class, 'rt_code', 'code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrent(): bool
    {
        return $this->status === 'active'
            && ! $this->starts_on->isFuture()
            && ! $this->ends_on->isPast();
    }
}
