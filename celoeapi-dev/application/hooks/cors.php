<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Simple CORS Handler for CodeIgniter 3
 * Handles Cross-Origin Resource Sharing (CORS) for all endpoints
 */
class Cors
{
    /**
     * Handle CORS preflight and actual requests
     */
    public function handle_cors()
    {
        // Simple CORS configuration for CI3
        $allowed_origins = [
            'http://localhost:3000',
            'http://127.0.0.1:3000'
        ];
        
        $allowed_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        $allowed_headers = [
            'Origin', 
            'X-Requested-With', 
            'Content-Type', 
            'Accept', 
            'Authorization',
            'Access-Control-Request-Method',
            'Access-Control-Request-Headers',
            'X-API-KEY',
            'Cache-Control'
        ];
        
        // Get origin
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        
        // Check if origin is allowed
        $origin_allowed = in_array($origin, $allowed_origins);
        
        // Set CORS headers if origin is allowed
        if ($origin_allowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: ' . implode(', ', $allowed_methods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $allowed_headers));
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
        
        // Handle preflight OPTIONS requests
        if ($request_method === 'OPTIONS') {
            if ($origin_allowed) {
                header('HTTP/1.1 200 OK');
                header('Content-Length: 0');
            } else {
                header('HTTP/1.1 403 Forbidden');
                header('Content-Length: 0');
            }
            exit;
        }
    }
    
    /**
     * Set additional CORS headers for specific response types
     * Can be called from controllers if needed
     */
    public static function set_response_headers($content_type = 'application/json')
    {
        // Additional headers for specific content types
        switch ($content_type) {
            case 'text/event-stream':
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no'); // Disable Nginx buffering for SSE
                break;
            case 'application/json':
                header('Content-Type: application/json; charset=utf-8');
                break;
        }
    }
    
    /**
     * Manually handle CORS for non-hook scenarios
     * Can be called from any controller
     */
         public static function handle_manual_cors($origins = ['*'], $methods = null, $headers = null)
     {
         $default_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
         $default_headers = ['Origin', 'X-Requested-With', 'Content-Type', 'Accept', 'Authorization'];
         
         $allowed_methods = $methods ?: $default_methods;
         $allowed_headers = $headers ?: $default_headers;
         
         $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
         $origin_allowed = in_array('*', $origins) || ($origin && in_array($origin, $origins));
         
         if ($origin_allowed) {
             header('Access-Control-Allow-Origin: ' . (in_array('*', $origins) ? '*' : $origin));
             header('Access-Control-Allow-Methods: ' . implode(', ', $allowed_methods));
             header('Access-Control-Allow-Headers: ' . implode(', ', $allowed_headers));
             header('Access-Control-Max-Age: 86400');
         }
         
         $request_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
         if ($request_method === 'OPTIONS') {
             header('HTTP/1.1 200 OK');
             header('Content-Length: 0');
             exit;
         }
     }
} 