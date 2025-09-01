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
            'description' => 'Local Development Server (Docker - Port 8081)'
        ],
        [
            'url' => 'http://localhost:8081/api',
            'description' => 'Local Development Server - API Base Path'
        ],
        [
            'url' => 'https://api.celoe.com',
            'description' => 'Production Server'
        ]
    ],
    'security' => [],
    'tags' => [
        
    ]
];

