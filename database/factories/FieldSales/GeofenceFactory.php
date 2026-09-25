<?php

namespace Database\Factories\FieldSales;

use App\Models\FieldSales\Geofence;
use App\Models\RetailDistributor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Geofence>
 */
class GeofenceFactory extends Factory
{
    protected $model = Geofence::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'retail_distributor_id' => null,
            'area_id' => null,
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'radius_metres' => 200,
            'active' => true,
        ];
    }

    public function forDistributor(string $code): static
    {
        return $this->state(fn () => [
            'retail_distributor_id' => RetailDistributor::query()->firstOrCreate(['code' => $code], ['name' => $code.' Distributor'])->id,
        ]);
    }

    public function at(float $latitude, float $longitude, int $radius = 200): static
    {
        return $this->state(fn () => ['latitude' => $latitude, 'longitude' => $longitude, 'radius_metres' => $radius]);
    }
}
