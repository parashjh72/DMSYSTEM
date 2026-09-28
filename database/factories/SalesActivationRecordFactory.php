<?php

namespace Database\Factories;

use App\Models\SalesActivationRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Device rows shaped like production: 15-digit IMEIs, realme models,
 * MD-prefixed RD codes and NP-prefixed RT codes.
 *
 * @extends Factory<SalesActivationRecord>
 */
class SalesActivationRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'imei' => '86'.fake()->unique()->numerify('#############'),
            'model' => fake()->randomElement(['Note 80', 'C100i', 'C85 5G (8+256GB)', 'C63 (8+128GB)', 'P3 Ultra']),
            'tso' => fake()->randomElement(['Roshan Singh', 'Madan Thapa', 'Bikash Rai']),
            'rd_code' => 'MDDX2803',
            'rd_name' => 'BRAHMA DIGITAL PVT LTD',
            'sell_in_date' => '2026-08-01',
            'source' => 'Manual',
        ];
    }

    /** RT-level stock: assigned to a retailer, not activated. */
    public function atRetailer(string $rtCode = 'NP057002', string $stDate = '2026-08-10'): static
    {
        return $this->state(fn () => [
            'rt_code' => $rtCode,
            'rt_name' => 'Ashish electronics & mobile',
            'st_date' => $stDate,
        ]);
    }

    public function activated(string $date = '2026-09-01'): static
    {
        return $this->state(fn () => ['activation_date' => $date]);
    }
}
