<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pjp_day_id', 'rt_code', 'rt_name'])]
class PjpDayRetailer extends Model
{
    public function day(): BelongsTo
    {
        return $this->belongsTo(PjpDay::class, 'pjp_day_id');
    }
}
