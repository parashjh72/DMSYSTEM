<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scheme_id', 'rt_code', 'rt_name', 'plan', 'category', 'min_slab', 'note', 'added_by',
])]
class SchemeRetailer extends Model
{
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    /** Minimum slab this retailer must reach — explicit override or the category default. */
    public function effectiveMinSlab(): int
    {
        return $this->min_slab
            ?? config("schemes.categories.{$this->category}.min_slab", 1);
    }
}
