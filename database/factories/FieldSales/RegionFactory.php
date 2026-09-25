<?php

namespace Database\Factories\FieldSales;

use App\Models\FieldSales\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('R??##')),
            'name' => fake()->unique()->city().' Region',
            'active' => true,
        ];
    }
}
