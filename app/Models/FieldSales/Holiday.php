<?php

namespace App\Models\FieldSales;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['holiday_date', 'name', 'region_id'])]
class Holiday extends Model
{
    protected $table = 'fs_holidays';

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
