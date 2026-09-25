<?php

namespace App\Models\FieldSales;

use App\Models\RetailDistributor;
use Database\Factories\FieldSales\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['region_id', 'code', 'name', 'active'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $table = 'fs_areas';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function distributors(): BelongsToMany
    {
        return $this->belongsToMany(RetailDistributor::class, 'fs_area_distributors', 'area_id', 'retail_distributor_id')
            ->withTimestamps();
    }

    public function policy(): HasOne
    {
        return $this->hasOne(AttendancePolicy::class);
    }
}
