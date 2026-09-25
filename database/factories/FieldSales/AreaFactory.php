<?php

namespace Database\Factories\FieldSales;

use App\Models\FieldSales\Area;
use App\Models\FieldSales\Region;
use App\Models\RetailDistributor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'code' => strtoupper(fake()->unique()->bothify('A??##')),
            'name' => fake()->unique()->city().' Area',
            'active' => true,
        ];
    }

    /** Place the given distributor codes in this area, creating the distributors if needed. */
    public function withDistributors(string ...$codes): static
    {
        return $this->afterCreating(function (Area $area) use ($codes) {
            foreach ($codes as $code) {
                $area->distributors()->attach(RetailDistributor::query()->firstOrCreate(['code' => $code], ['name' => $code.' Distributor']));
            }
        });
    }
}
