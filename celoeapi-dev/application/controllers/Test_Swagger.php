<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Test Controller for Swagger
 */
class Test_Swagger extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Test endpoint
     */
    public function index() {
        echo "Test Swagger Controller Loaded Successfully!";
    }
    
    /**
     * Test library loading
     */
    public function test_library() {
        try {
            $this->load->library('Swagger_Generator');
            echo "Swagger_Generator library loaded successfully!";
        } catch (Exception $e) {
            echo "Error loading Swagger_Generator: " . $e->getMessage();
        }
    }
    
    /**
     * Test file existence
     */
    public function test_files() {
        echo "<h3>File Check Results:</h3>";
        
        $files = [
            'Swagger_Generator library' => APPPATH . 'libraries/Swagger_Generator.php',
            'Swagger controller' => APPPATH . 'controllers/Swagger.php',
            'Swagger view' => APPPATH . 'views/swagger/index.php'
        ];
        
        foreach ($files as $name => $path) {
            if (file_exists($path)) {
                echo "<p style='color: green;'>✓ $name: EXISTS</p>";
            } else {
                echo "<p style='color: red;'>✗ $name: NOT FOUND at $path</p>";
            }
        }
        
        echo "<h3>Directory Contents:</h3>";
        echo "<p>Libraries: " . implode(', ', array_diff(scandir(APPPATH . 'libraries'), ['.', '..'])) . "</p>";
        echo "<p>Controllers: " . implode(', ', array_diff(scandir(APPPATH . 'controllers'), ['.', '..'])) . "</p>";
        echo "<p>Views: " . implode(', ', array_diff(scandir(APPPATH . 'views'), ['.', '..'])) . "</p>";
    }
}
