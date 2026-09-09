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
];
