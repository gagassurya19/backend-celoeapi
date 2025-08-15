<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CORS Helper
 * 
 * Provides utility functions for manual CORS handling in controllers
 * when the automatic hook handling is not sufficient
 */

if (!function_exists('set_cors_headers')) {
    /**
     * Set CORS headers manually
     * 
     * @param array $origins Allowed origins (default: ['*'])
     * @param array $methods Allowed HTTP methods
     * @param array $headers Allowed headers
     * @return void
     */
    function set_cors_headers($origins = ['*'], $methods = null, $headers = null)
    {
        $CI =& get_instance();
        
        // Load CORS config
        $CI->load->config('cors', TRUE);
        
        $default_methods = $CI->config->item('cors_allowed_methods', 'cors') ?: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        $default_headers = $CI->config->item('cors_allowed_headers', 'cors') ?: ['Origin', 'X-Requested-With', 'Content-Type', 'Accept']; // Authorization removed
        
        $allowed_methods = $methods ?: $default_methods;
        $allowed_headers = $headers ?: $default_headers;
        
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
        $origin_allowed = in_array('*', $origins) || ($origin && in_array($origin, $origins));
        
        if ($origin_allowed) {
            header('Access-Control-Allow-Origin: ' . (in_array('*', $origins) ? '*' : $origin));
            header('Access-Control-Allow-Methods: ' . implode(', ', $allowed_methods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $allowed_headers));
            header('Access-Control-Max-Age: 86400');
            
            // Allow credentials if configured
            $allow_credentials = $CI->config->item('cors_allow_credentials', 'cors');
            if ($allow_credentials) {
                header('Access-Control-Allow-Credentials: true');
            }
        }
    }
}

if (!function_exists('handle_options_request')) {
    /**
     * Handle OPTIONS preflight request
     * 
     * @param array $origins Allowed origins (default: ['*'])
     * @param array $methods Allowed HTTP methods
     * @param array $headers Allowed headers
     * @return void
     */
    function handle_options_request($origins = ['*'], $methods = null, $headers = null)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            set_cors_headers($origins, $methods, $headers);
            header('HTTP/1.1 200 OK');
            header('Content-Length: 0');
            exit;
        }
    }
}

if (!function_exists('set_sse_headers')) {
    /**
     * Set headers for Server-Sent Events (SSE) with CORS
     * 
     * @param array $origins Allowed origins (default: ['*'])
     * @return void
     */
    function set_sse_headers($origins = ['*'])
    {
        // Set CORS headers first
        set_cors_headers($origins);
        
        // Set SSE-specific headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable Nginx buffering
        
        // Disable output buffering for real-time streaming
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Ensure immediate output
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        ini_set('zlib.output_compression', 0);
        ini_set('implicit_flush', 1);
    }
}

if (!function_exists('validate_cors_origin')) {
    /**
     * Validate if the request origin is allowed
     * 
     * @param array $allowed_origins Allowed origins
     * @return bool
     */
    function validate_cors_origin($allowed_origins = ['*'])
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
        
        if (in_array('*', $allowed_origins)) {
            return true;
        }
        
        return $origin && in_array($origin, $allowed_origins);
    }
}

if (!function_exists('get_cors_config')) {
    /**
     * Get CORS configuration from config file
     * 
     * @return array
     */
    function get_cors_config()
    {
        $CI =& get_instance();
        $CI->load->config('cors', TRUE);
        
        return [
            'allowed_origins' => $CI->config->item('cors_allowed_origins', 'cors') ?: ['*'],
            'allowed_methods' => $CI->config->item('cors_allowed_methods', 'cors') ?: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
            'allowed_headers' => $CI->config->item('cors_allowed_headers', 'cors') ?: ['Origin', 'X-Requested-With', 'Content-Type', 'Accept', 'Authorization'],
            'exposed_headers' => $CI->config->item('cors_exposed_headers', 'cors') ?: [],
            'allow_credentials' => $CI->config->item('cors_allow_credentials', 'cors') ?: false,
            'max_age' => $CI->config->item('cors_max_age', 'cors') ?: 86400,
            'debug' => $CI->config->item('cors_debug', 'cors') ?: false
        ];
    }
}

if (!function_exists('log_cors_request')) {
    /**
     * Log CORS request for debugging
     * 
     * @param string $type Type of request (preflight, actual)
     * @param bool $allowed Whether the request was allowed
     * @return void
     */
    function log_cors_request($type = 'actual', $allowed = true)
    {
        $cors_config = get_cors_config();
        
        if ($cors_config['debug']) {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'null';
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'UNKNOWN';
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            
            log_message('debug', "CORS {$type}: {$method} {$uri} Origin: {$origin} Allowed: " . ($allowed ? 'true' : 'false'));
        }
    }
} 