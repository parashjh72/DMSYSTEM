<?php

namespace App\Models\FieldSales;

use Database\Factories\FieldSales\RegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'active'])]
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    protected $table = 'fs_regions';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }
}
