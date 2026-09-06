<?php

return [

    /*
    | Payout plan a retailer picks for a scheme.
    */
    'plans' => [
        'option_one' => 'Option 1 — cash payout %',
        'option_two' => 'Option 2 — reward / gift',
    ],

    /*
    | Retailer category → the minimum slab it must reach to be eligible
    | (Festive scheme T&C #9: RA counters must reach Slab 5, Conditional RA and
    | Contracted Outlets must reach Slab 3).
    */
    'categories' => [
        'ra' => ['label' => 'RA Counter', 'min_slab' => 5],
        'conditional_ra' => ['label' => 'Conditional RA', 'min_slab' => 3],
        'contracted' => ['label' => 'Contracted Outlet', 'min_slab' => 3],
        'other' => ['label' => 'Other', 'min_slab' => 1],
    ],
];
