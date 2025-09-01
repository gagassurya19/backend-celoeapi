<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Centralized CORS Configuration
|--------------------------------------------------------------------------
|
| This configuration applies to all endpoints in the application.
| It's used by both the CORS hook and REST_Controller CORS settings.
|
*/

/*
|--------------------------------------------------------------------------
| Allowed Origins
|--------------------------------------------------------------------------
|
| List of allowed origins for CORS requests.
| Use ['*'] to allow all origins (development).
| For production, specify exact domains: ['https://frontend.example.com', 'https://admin.example.com']
|
*/
$config['cors_allowed_origins'] = ['*'];

/*
|--------------------------------------------------------------------------
| Allowed HTTP Methods
|--------------------------------------------------------------------------
|
| HTTP methods that are allowed for CORS requests
|
*/
$config['cors_allowed_methods'] = [
    'GET',
    'POST', 
    'PUT',
    'PATCH',
    'DELETE',
    'OPTIONS',
    'HEAD'
];

/*
|--------------------------------------------------------------------------
| Allowed Headers
|--------------------------------------------------------------------------
|
| Headers that are allowed in CORS requests
|
*/
$config['cors_allowed_headers'] = [
    'Origin',
    'X-Requested-With',
    'Content-Type', 
    'Accept',
            // 'Authorization', // Removed - no authentication required
    'Access-Control-Request-Method',
    'Access-Control-Request-Headers',
    'X-API-KEY',
    'Cache-Control'
];

/*
|--------------------------------------------------------------------------
| Exposed Headers
|--------------------------------------------------------------------------
|
| Headers that the client can access from the response
|
*/
$config['cors_exposed_headers'] = [
    'Content-Length',
    'X-JSON'
];

/*
|--------------------------------------------------------------------------
| Allow Credentials
|--------------------------------------------------------------------------
|
| Whether to allow credentials (cookies, authorization headers) in CORS requests
| Set to TRUE if you need to send cookies or authorization headers from browser
|
*/
$config['cors_allow_credentials'] = false;

/*
|--------------------------------------------------------------------------
| Max Age
|--------------------------------------------------------------------------
|
| How long (in seconds) the browser should cache preflight responses
|
*/
$config['cors_max_age'] = 86400; // 24 hours

/*
|--------------------------------------------------------------------------
| Environment-specific Settings
|--------------------------------------------------------------------------
|
| You can override CORS settings based on environment
|
*/
if (ENVIRONMENT === 'production') {
    // Production: Restrict to specific domains
    $config['cors_allowed_origins'] = [
        'http://localhost:3000',
        'https://clear.celoe.org'
    ];
    $config['cors_allow_credentials'] = true;
} elseif (ENVIRONMENT === 'testing') {
    // Testing: Allow localhost and testing domains
    $config['cors_allowed_origins'] = [
        'http://localhost:3000', 
        'http://127.0.0.1:3000',
        'https://clear.celoe.org'
    ];
} else {
    // Development: Allow all origins
    $config['cors_allowed_origins'] = [
        'http://localhost:3000',
        'http://localhost:8081',
        'http://127.0.0.1:8081',
        'https://clear.celoe.org'
    ];
}

/*
|--------------------------------------------------------------------------
| Debug Mode
|--------------------------------------------------------------------------
|
| Enable CORS debug logging
|
*/
$config['cors_debug'] = (ENVIRONMENT !== 'production'); 