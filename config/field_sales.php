<?php

return [

    /*
    | Timezone every field-sales "day" is evaluated in (attendance days, routes,
    | leave, the daily summary). Follows the attendance timezone by default.
    */
    'timezone' => env('FIELD_SALES_TIMEZONE', env('ATTENDANCE_TIMEZONE', env('REPORTS_TIMEZONE', 'Asia/Kathmandu'))),

    /*
    | Defaults used when no attendance policy row exists yet (Settings → Field Sales
    | → Policies). An Area-specific policy overrides the default policy.
    */
    'policy_defaults' => [
        'duty_start' => '09:30',
        'duty_end' => '18:00',
        'late_grace_minutes' => 15,
        'half_day_below_minutes' => 240,
        'weekly_off' => [6],                 // 0 = Sunday … 6 = Saturday
        'geofence_mode' => 'flag',           // off | flag | block
        'geofence_radius_metres' => 200,
        'ping_interval_minutes' => 5,
    ],

    'tracking' => [
        // Bump when the consent wording changes so every user is asked again.
        'consent_version' => (int) env('FIELD_SALES_CONSENT_VERSION', 1),

        // Location history older than this is deleted by fs:prune-locations.
        'retention_days' => (int) env('FIELD_SALES_LOCATION_RETENTION_DAYS', 90),

        // Max pings accepted in one upload from the phone's offline queue.
        'max_batch' => 200,

        // Pings recorded longer ago than this are rejected (stale offline queue).
        'max_age_hours' => 72,

        // Pings worse than this are stored but ignored for distance and the live map.
        'max_accuracy_metres' => 100,

        // Movement smaller than this between two kept points is treated as GPS jitter.
        'jitter_metres' => 30,

        // A jump implying more than this speed is flagged as suspect and ignored for distance.
        'max_speed_kmh' => 150,

        // Minutes without movement before the time counts as idle.
        'idle_after_minutes' => 10,
    ],

    'daily_summary' => [
        'enabled' => (bool) env('FIELD_SALES_DAILY_SUMMARY', false),
        'time' => env('FIELD_SALES_DAILY_SUMMARY_TIME', '19:00'),
    ],
];
