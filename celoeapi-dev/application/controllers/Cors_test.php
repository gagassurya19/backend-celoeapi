<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CORS Test Controller
 * Simple controller to test if CORS is working properly
 */
class Cors_test extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Test endpoint to verify CORS headers
     * Access: http://localhost:8081/cors_test
     */
    public function index()
    {
        header('Content-Type: application/json');
        
        echo json_encode([
            'status' => true,
            'message' => 'CORS test endpoint working',
            'timestamp' => date('Y-m-d H:i:s'),
            'origin' => isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'No origin',
            'method' => $_SERVER['REQUEST_METHOD'],
            'headers_sent' => $this->_get_response_headers()
        ]);
    }

    /**
     * API style test endpoint
     * Access: http://localhost:8081/cors_test/api_test
     */
    public function api_test()
    {
        header('Content-Type: application/json');
        
        $data = [
            'success' => true,
            'data' => [
                'message' => 'API CORS test successful',
                'origin' => isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null,
                'method' => $_SERVER['REQUEST_METHOD'],
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null
            ],
            'cors_info' => [
                'hook_enabled' => true,
                'allowed_origins' => ['http://localhost:3000', 'http://127.0.0.1:3000'],
                'note' => 'If you can see this from frontend, CORS is working!'
            ]
        ];

        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Test OPTIONS handling
     */
    public function options_test()
    {
        header('Content-Type: application/json');
        
        // This should be handled by the CORS hook automatically
        echo json_encode([
            'message' => 'OPTIONS request handled by controller (hook should have intercepted this)',
            'method' => $_SERVER['REQUEST_METHOD']
        ]);
    }

    /**
     * Get response headers that were sent
     */
    private function _get_response_headers()
    {
        $headers = [];
        
        // Check if headers_list() function exists
        if (function_exists('headers_list')) {
            $sent_headers = headers_list();
            foreach ($sent_headers as $header) {
                if (strpos(strtolower($header), 'access-control') !== false) {
                    $headers[] = $header;
                }
            }
        }
        
        return $headers;
    }
} 