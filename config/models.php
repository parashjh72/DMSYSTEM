<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model lifecycle classification
    |--------------------------------------------------------------------------
    |
    | A device model is "running" (currently sold) or "out" (discontinued).
    | A model whose cleaned name (the RAM/storage suffix in parentheses removed)
    | matches ANY of the patterns below is marked "running"; everything else is
    | "out". Edit this list as the line-up changes, then run:
    |
    |     php artisan models:classify
    |
    | (imports also re-run the classifier automatically).
    */
    'running_series' => [
        '/\b60x\b/i',            // 60x series
        '/^note\s*70\b/i',       // Note 70 series
        '/^note\s*80\b/i',       // Note 80
        '/^c71\b/i',             // C71 series
        '/^c75\b/i',             // C75 series
        '/^c100/i',              // C100 series (C100i, C100x, …)
        '/^c85\b/i',             // C85 series (C85, C85 Pro, C85 5G)
        '/^p\s*\d/i',            // P series (P1, P3, P3 5G, …)
        '/^14t?\b/i',            // 14 series (14, 14T)
        '/^15t?\b/i',            // 15 series (15, 15T)
        '/^gt\s*8/i',            // GT8 series
    ],

    'statuses' => ['running', 'out'],
    'default_status' => 'out',
];
