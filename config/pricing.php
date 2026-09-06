<?php

return [
    /*
    | Currency shown on value reports. Prices are stored as plain DECIMAL(12,2);
    | this only affects display.
    */
    'symbol' => env('PRICE_SYMBOL', 'Rs'),
    'code' => env('PRICE_CODE', 'NPR'),

    /*
    | Which device date a value report prices against by default.
    | 'activation_date' — value of devices activated in the period (sell-out value)
    | 'st_date'         — value of devices sold-through in the period
    */
    'default_basis' => 'activation_date',
];
