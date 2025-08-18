<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Swagger/OpenAPI Configuration
|--------------------------------------------------------------------------
|
| Configuration for Swagger/OpenAPI documentation
|
*/

$config['swagger'] = [
    'title' => 'Celoe API Dev - ETL & Analytics API',
    'description' => 'Comprehensive API for ETL processes, user activity analytics, and Moodle data management.\n\nBackfill: a background ETL process that starts from a provided start_date and incrementally processes per-day data until the latest available date. It is designed for very large datasets (e.g., 300M rows), runs in daily chunks, supports optional concurrency, and can be tuned via environment variables to fit server capacity.',
    'version' => '1.0.0',
    'contact' => [
        'name' => 'Celoe Development Team',
        'email' => 'dev@celoe.com'
    ],
    'license' => [
        'name' => 'MIT',
        'url' => 'https://opensource.org/licenses/MIT'
    ],
    'servers' => [
        [
            'url' => 'http://localhost:8081',
            'description' => 'Local Development Server'
        ],
        [
            'url' => 'https://api.celoe.com',
            'description' => 'Production Server'
        ]
    ],
    'security' => [],
    'tags' => [
        [
            'name' => 'ETL',
            'description' => 'Extract, Transform, Load operations'
        ],
        [
            'name' => 'Student Activity Summary',
            'description' => 'Student activity ETL and analytics'
        ],
        [
            'name' => 'Course Performance ETL',
            'description' => 'Course Performance ETL (cp_*) including backfill. Backfill processes data from a given start_date up to current date in daily batches, can run concurrently.'
        ],
        [
            'name' => 'Data Export',
            'description' => 'Data export operations'
        ],
        [
            'name' => 'Analytics',
            'description' => 'Data analytics and reporting'
        ]
    ]
];

