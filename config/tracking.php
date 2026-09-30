<?php

return [

    /*
    | Background GPS tracking of checked-in field users. The browser sends a
    | breadcrumb while the app is open between check-in and check-out; it stops
    | on its own at check-out.
    */
    'enabled' => (bool) env('TRACKING_ENABLED', true),

    /*
    | Client cadence: a breadcrumb is queued at most every `interval_seconds`,
    | or sooner once the user has moved `min_move_metres`. The queue is posted
    | to the server every `flush_seconds`.
    */
    'interval_seconds' => (int) env('TRACKING_INTERVAL_SECONDS', 60),
    'min_move_metres' => (int) env('TRACKING_MIN_MOVE_METRES', 30),
    'flush_seconds' => (int) env('TRACKING_FLUSH_SECONDS', 60),

    /*
    | Server filtering. Fixes worse than `max_accuracy_metres` are discarded;
    | a new route anchor (counted distance) needs `anchor_metres` of movement;
    | a jump implying more than `max_speed_kmh` is treated as a GPS glitch.
    */
    'max_accuracy_metres' => (float) env('TRACKING_MAX_ACCURACY', 150),
    'anchor_metres' => (float) env('TRACKING_ANCHOR_METRES', 25),
    'max_speed_kmh' => (float) env('TRACKING_MAX_SPEED_KMH', 160),
    'max_batch' => 500,

    /*
    | Stops: staying within `stop_radius_metres` for `stop_minutes` or more.
    */
    'stop_radius_metres' => (float) env('TRACKING_STOP_RADIUS', 100),
    'stop_minutes' => (int) env('TRACKING_STOP_MINUTES', 10),

    /*
    | A checked-in user whose last breadcrumb is newer than this is shown "Live".
    */
    'live_minutes' => (int) env('TRACKING_LIVE_MINUTES', 5),

    /*
    | A beat-plan visit logged within this distance of the retailer's saved location
    | is marked "at store".
    */
    'visit_match_metres' => (float) env('TRACKING_VISIT_MATCH_METRES', 150),

    /*
    | Breadcrumbs older than this are pruned nightly.
    */
    'retention_days' => (int) env('TRACKING_RETENTION_DAYS', 120),
];
