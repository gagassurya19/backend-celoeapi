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
    'description' => 'Comprehensive API for ETL processes, user activity analytics, and Moodle data management',
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
    'security' => [
        [
            'BearerAuth' => []
        ]
    ],
    'tags' => [
        [
            'name' => 'ETL',
            'description' => 'Extract, Transform, Load operations'
        ],
        [
            'name' => 'User Activity',
            'description' => 'User activity ETL and analytics'
        ],
        [
            'name' => 'ETL Chart',
            'description' => 'Chart data ETL processes'
        ],
        [
            'name' => 'Analytics',
            'description' => 'Data analytics and reporting'
        ],
        [
            'name' => 'Courses',
            'description' => 'Course management and analytics'
        ],
        [
            'name' => 'LMS Reports',
            'description' => 'Learning Management System reports'
        ]
    ]
];

