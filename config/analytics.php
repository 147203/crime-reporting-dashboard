<?php

declare(strict_types=1);

// Parameters of the documented rules (docs/02 A8 and V8). Changing a value changes results: log it in docs/09.
return [

    'hotspot' => [
        'z_threshold' => (float) env('HOTSPOT_Z_THRESHOLD', 1.5),
        'min_incidents' => (int) env('HOTSPOT_MIN_INCIDENTS', 10),
    ],

    'duplicates' => [
        'window_minutes' => (int) env('DUPLICATE_WINDOW_MINUTES', 120),
        'similarity' => (float) env('DUPLICATE_SIMILARITY', 0.8),
    ],

    'time_bands' => [
        ['label' => '00:00 to 05:59', 'from' => 0, 'to' => 5],
        ['label' => '06:00 to 11:59', 'from' => 6, 'to' => 11],
        ['label' => '12:00 to 17:59', 'from' => 12, 'to' => 17],
        ['label' => '18:00 to 23:59', 'from' => 18, 'to' => 23],
    ],

];
