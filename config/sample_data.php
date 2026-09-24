<?php

declare(strict_types=1);

// Every distribution parameter of the synthetic data (docs/04). These are ASSUMPTIONS for demonstration, not claims about
// real crime in Nairobi; Chapter 5.3 copies them. The category mix is to be anchored to a verified source (VERIFY: KNBS
// Economic Survey crime tables; record table and page) before it is described as anchored in the thesis.
return [

    'seed' => (int) env('SAMPLE_DATA_SEED', 20260924),

    'months' => 24,

    'category_mix' => [
        'Theft' => 0.44,
        'Robbery' => 0.14,
        'Burglary' => 0.14,
        'Assault' => 0.20,
        'Other' => 0.08,
    ],

    // Relative weight per ISO weekday (1 = Monday).
    'weekday_weights' => [
        'default' => [1 => 1.0, 2 => 1.0, 3 => 1.0, 4 => 1.0, 5 => 1.15, 6 => 1.2, 7 => 0.95],
        'Assault' => [1 => 0.85, 2 => 0.85, 3 => 0.85, 4 => 0.95, 5 => 1.3, 6 => 1.6, 7 => 1.3],
    ],

    // Relative weight per hour band (00-05, 06-11, 12-17, 18-23).
    'band_weights' => [
        'default' => [0.15, 0.25, 0.30, 0.30],
        'Theft' => [0.08, 0.30, 0.40, 0.22],
        'Burglary' => [0.45, 0.15, 0.15, 0.25],
        'Assault' => [0.25, 0.10, 0.20, 0.45],
    ],

    // Mild seasonality: monthly weight = 1 + amplitude * sin(2 * pi * month / 12).
    'seasonality_amplitude' => 0.10,

    // Ward baseline weights are drawn uniformly from this range.
    'ward_weight_range' => [0.5, 2.0],

    // Planted patterns (ground truth written to truth/hotspots.json and truth/trend.json).
    'planted_ward_hotspots' => ['count' => 3, 'multiplier' => 3.0],
    'planted_category_hotspots' => ['category' => 'Theft', 'count' => 2, 'multiplier' => 3.0],
    'planted_trend' => ['category' => 'Burglary', 'growth' => 0.6],

    // Seeded data-quality problems, as a share of the base size (minimum 5 per rule).
    'bad_share_per_rule' => 0.01,
    'duplicate_share' => 0.02,
    'near_miss_share' => 0.01,

];
