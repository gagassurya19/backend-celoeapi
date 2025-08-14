<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Auth helper functions for API authentication

if (!function_exists('validate_api_token')) {
    /**
     * Validate API/Webhook Token
     * @param string $token
     * @return boolean
     */
    function validate_api_token($token) {
        // Define valid tokens (you can move this to config later)
        $valid_tokens = [
            'default-webhook-token-change-this',
            // Add more tokens as needed
        ];
        
        return in_array($token, $valid_tokens);
    }
}

if (!function_exists('get_bearer_token')) {
    /**
     * Extract Bearer token from Authorization header
     * @param string $auth_header
     * @return string|null
     */
    function get_bearer_token($auth_header) {
        if (strpos($auth_header, 'Bearer ') === 0) {
            return substr($auth_header, 7);
        }
        return null;
    }
}

if (!function_exists('check_api_auth')) {
    /**
     * Check API authentication
     * @return boolean
     */
    function check_api_auth() {
        $CI =& get_instance();
        $auth_header = $CI->input->get_request_header('Authorization', TRUE);
        $token = get_bearer_token($auth_header);
        
        if ($token && validate_api_token($token)) {
            return true;
        }
        
        return false;
    }
}