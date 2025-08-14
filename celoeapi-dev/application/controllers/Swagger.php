<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Swagger Controller
 * 
 * Serves OpenAPI documentation and Swagger UI
 */
class Swagger extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        // Load the swagger helper
        $this->load->helper('swagger');
        // Load URL helper for base_url() function
        $this->load->helper('url');
    }
    
    /**
     * Display Swagger UI
     */
    public function index() {
        try {
            $data['swagger_url'] = base_url('swagger/spec');
            $this->load->view('swagger/index', $data);
        } catch (Exception $e) {
            log_message('error', 'Swagger UI error: ' . $e->getMessage());
            show_error('Error loading Swagger documentation: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate and serve OpenAPI specification
     */
    public function spec() {
        try {
            // Load the swagger helper
            $this->load->helper('swagger');
            
            // Generate the specification using the helper function
            $spec = generate_swagger_spec();
            
            header('Content-Type: application/json');
            echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            log_message('error', 'Swagger spec error: ' . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to generate OpenAPI specification',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Download OpenAPI specification as JSON file
     */
    public function download() {
        try {
            $this->load->helper('swagger');
            
            $spec = generate_swagger_spec();
            
            $filename = 'celoe-api-openapi-' . date('Y-m-d') . '.json';
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen(json_encode($spec)));
            
            echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            log_message('error', 'Swagger download error: ' . $e->getMessage());
            show_error('Error generating download: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate OpenAPI specification as YAML
     */
    public function yaml() {
        try {
            $this->load->helper('swagger');
            
            $spec = generate_swagger_spec();
            
            // Convert to YAML (basic conversion)
            $yaml = $this->array_to_yaml($spec);
            
            $filename = 'celoe-api-openapi-' . date('Y-m-d') . '.yaml';
            
            header('Content-Type: text/yaml');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($yaml));
            
            echo $yaml;
        } catch (Exception $e) {
            log_message('error', 'Swagger YAML error: ' . $e->getMessage());
            show_error('Error generating YAML: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Convert array to YAML format
     */
    private function array_to_yaml($array, $indent = 0) {
        $yaml = '';
        $indent_str = str_repeat('  ', $indent);
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $yaml .= $indent_str . "- " . $this->array_to_yaml($value, $indent + 1);
                } else {
                    $yaml .= $indent_str . $key . ":\n" . $this->array_to_yaml($value, $indent + 1);
                }
            } else {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } elseif (is_string($value) && (strpos($value, ':') !== false || strpos($value, '#') !== false)) {
                    $value = '"' . $value . '"';
                }
                
                if (is_numeric($key)) {
                    $yaml .= $indent_str . "- " . $value . "\n";
                } else {
                    $yaml .= $indent_str . $key . ": " . $value . "\n";
                }
            }
        }
        
        return $yaml;
    }
}
