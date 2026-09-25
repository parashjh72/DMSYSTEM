<?php

namespace App\Models\FieldSales;

use App\Models\RetailDistributor;
use Database\Factories\FieldSales\GeofenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'retail_distributor_id', 'area_id', 'latitude', 'longitude', 'radius_metres', 'active'])]
class Geofence extends Model
{
    /** @use HasFactory<GeofenceFactory> */
    use HasFactory;

    protected $table = 'fs_geofences';

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_metres' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(RetailDistributor::class, 'retail_distributor_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
