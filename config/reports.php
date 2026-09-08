<?php

return [

    /*
    | Timezone in which scheduled-report send times are entered and evaluated.
    | Stored timestamps stay UTC; only the "is it time to send?" comparison and
    | the relative date windows use this zone.
    */
    'timezone' => env('REPORTS_TIMEZONE', 'Asia/Kathmandu'),

    /*
    | Relative data windows a scheduled report can cover. Each resolves at run
    | time to a [from, to] date pair (inclusive) in the reports timezone.
    */
    'periods' => [
        'yesterday' => 'Yesterday',
        'last_7_days' => 'Last 7 days',
        'last_30_days' => 'Last 30 days',
        'last_month' => 'Last full month',
        'month_to_date' => 'Month to date',
        'none' => 'Current snapshot (no date filter)',
    ],

    /*
    | Report types that can be scheduled. key => [label, dateable].
    | `dateable` = the relative window applies (via the chosen date basis).
    | Stock reports are point-in-time snapshots, so their window is always "none".
    */
    'schedulable' => [
        'rd_report' => ['RD-wise report', true],
        'rt_report' => ['RT-wise report', true],
        'tso_report' => ['TSO-wise report', true],
        'model_report' => ['Model-wise report', true],
        'date_report' => ['Date-wise sell-through report', true],
        'records' => ['Raw records export', true],
        'stock_rd' => ['Stock report — RD-wise', false],
        'stock_rt' => ['Stock report — RT-wise', false],
        'stock_model' => ['Stock report — Model-wise', false],
        'sellout_rd' => ['Sellout report — RD-wise', true],
        'sellout_rt' => ['Sellout report — RT-wise', true],
        'sellout_model' => ['Sellout report — Model-wise', true],
    ],

    /*
    | Which date column the window filters on.
    */
    'date_bases' => [
        'st_date' => 'ST / invoice date',
        'activation_date' => 'Activation date',
        'sell_in_date' => 'Sell-in date',
    ],

];
