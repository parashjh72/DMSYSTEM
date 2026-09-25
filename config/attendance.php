<?php

return [

    /*
    | GPS accuracy (metres) beyond which a check-in is still accepted but flagged
    | to the user and highlighted in the admin report.
    */
    'poor_accuracy_metres' => (int) env('ATTENDANCE_POOR_ACCURACY', 100),

    /*
    | Reverse-geocode the coordinates to a human address on check-in/out.
    | Best-effort only (OpenStreetMap Nominatim, cached); never blocks the save.
    */
    'reverse_geocode' => (bool) env('ATTENDANCE_REVERSE_GEOCODE', true),

    /*
    | Timezone the attendance_date and "today" are evaluated in.
    */
    'timezone' => env('ATTENDANCE_TIMEZONE', env('REPORTS_TIMEZONE', 'Asia/Kathmandu')),

    /*
    | Anti-Mock Location & GPS Spoofing Detection Settings
    |
    | Field staff may attempt to enable "Select mock location app" in Android
    | Developer Options to mark attendance with fake saved coordinates.
    */
    'anti_mock' => [
        'enabled' => (bool) env('ATTENDANCE_ANTI_MOCK_ENABLED', true),

        // Minimum acceptable accuracy in metres. Real civilian smartphone GPS is
        // almost never <= 0.5m. Mock providers frequently report 0 or < 0.5m.
        'min_accuracy_metres' => (float) env('ATTENDANCE_MIN_ACCURACY', 0.5),

        // If today's coordinates match any previous check-in within this distance (in metres),
        // it indicates a saved mock location pin repeatedly injected by a Fake GPS app.
        // Kept near-exact: real GPS at the same desk lands within a metre of an earlier
        // day often enough that a wider radius blocks genuine staff.
        'historical_repetition_threshold_metres' => (float) env('ATTENDANCE_REPEAT_THRESHOLD_METRES', 0.5),

        // How many days back to check for identical pinned coordinates.
        'history_days' => (int) env('ATTENDANCE_HISTORY_DAYS', 30),

        // Same-day check-in vs check-out repetition threshold (in metres) for shifts >= 15 mins.
        'checkout_repetition_threshold_metres' => (float) env('ATTENDANCE_CHECKOUT_REPEAT_THRESHOLD_METRES', 0.3),

        // Cross-user collision threshold on same date (in metres) to prevent shared mock pins.
        'cross_user_collision_metres' => (float) env('ATTENDANCE_CROSS_USER_COLLISION_METRES', 0.3),

        // Zero jitter across consecutive fixes is only treated as mock when the fixes claim
        // satellite-grade accuracy (metres). Wi-Fi/cell fixes can legitimately repeat.
        'jitter_max_accuracy_metres' => (float) env('ATTENDANCE_JITTER_MAX_ACCURACY', 25),

        // Reject coordinates with this many decimals or fewer on both axes (hand-typed pins).
        // Real GPS reports 6+ decimals. Set to 0 to disable.
        'round_coordinate_decimals' => (int) env('ATTENDANCE_ROUND_COORDINATE_DECIMALS', 4),

        // Impossible travel: reject when the speed implied by this punch versus the previous
        // punch exceeds this (km/h), for jumps longer than min distance (metres).
        'max_travel_speed_kmh' => (float) env('ATTENDANCE_MAX_TRAVEL_SPEED_KMH', 250),
        'travel_min_distance_metres' => (float) env('ATTENDANCE_TRAVEL_MIN_DISTANCE_METRES', 5000),
    ],
];
