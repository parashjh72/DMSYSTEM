<?php

namespace Database\Seeders;

use App\Models\Scheme;
use Illuminate\Database\Seeder;

class FestiveSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $scheme = Scheme::updateOrCreate(
            ['name' => 'Festive Sell-Out Scheme'],
            [
                'effective_from' => '2026-09-01',
                'effective_to' => '2026-11-15',
                'sellout_basis' => 'activation_date',
                'qualified_models' => 'running',
                'status' => 'active',
                'note' => 'Final Retailer Festival Scheme. Payout on DP without VAT. '
                    .'Qualifying: 60x, note 70, note 80, C71, C75, C100, C85, P, 14, 15, GT8 series. '
                    .'Note 60x 4+128 calculated at old MRP 19999.',
            ],
        );

        $slabs = [
            [1, '3 Lakh – 4,99,999', 300000, 499999, 2.00, '2%'],
            [2, '5 Lakh – 14,99,999', 500000, 1499999, 2.50, '2.50%'],
            [3, '15 Lakh – 24,99,999', 1500000, 2499999, 3.50, '1 Ton Inverter AC'],
            [4, '25 Lakh – 34,99,999', 2500000, 3499999, 4.00, 'Vietnam Tour 4N/5D'],
            [5, '35 Lakh – 44,99,999', 3500000, 4499999, 4.25, 'China Tour 5N/6D'],
            [6, '45 Lakh – 59,99,999', 4500000, 5999999, 4.50, 'Singapore, Malaysia, Thailand (8N/9D)'],
            [7, '60 Lakh – 79,99,999', 6000000, 7999999, 4.75, 'EV Scooter TVS Orbiter / Japan Tour (5N/6D)'],
            [8, '80 Lakh – 1,19,99,999', 8000000, 11999999, 5.00, 'EV Scooter TVS Orbiter + Vietnam Tour (4N/5D)'],
            [9, '1.2 Crore & Above', 12000000, null, 5.50, 'Japan Tour (5N/6D) Couple / Royal Enfield Classic 350'],
        ];

        foreach ($slabs as [$no, $label, $min, $max, $pct, $reward]) {
            $scheme->slabs()->updateOrCreate(
                ['slab_no' => $no],
                ['label' => $label, 'min_value' => $min, 'max_value' => $max, 'payout_percent' => $pct, 'reward' => $reward],
            );
        }
    }
}
