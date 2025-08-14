<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Swagger Controller
 * Serves Swagger UI and generates API documentation
 */
class Swagger extends CI_Controller {
    
    public $swagger_generator;
    
    public function __construct() {
        parent::__construct();
        $this->load->helper('url');
        $this->load->library('Swagger_Generator');
        $this->swagger_generator = new Swagger_Generator();
    }
    
    /**
     * Main Swagger UI page
     */
    public function index() {
        $data['title'] = 'API Documentation';
        $data['swagger_url'] = base_url('swagger/docs');
        
        $this->load->view('swagger/index', $data);
    }
    
    /**
     * Generate and serve Swagger JSON
     */
    public function docs() {
        header('Content-Type: application/json');
        
        // Generate fresh documentation
        $swagger = $this->swagger_generator->generate_docs();
        
        // Save to file for caching
        $this->swagger_generator->save_to_file();
        
        echo json_encode($swagger, JSON_PRETTY_PRINT);
    }
    
    /**
     * Generate documentation and redirect to Swagger UI
     */
    public function generate() {
        // Generate fresh documentation
        $this->swagger_generator->generate_docs();
        
        // Save to file
        $this->swagger_generator->save_to_file();
        
        // Redirect to Swagger UI
        redirect('swagger');
    }
    
    /**
     * Download Swagger JSON file
     */
    public function download() {
        // Generate fresh documentation
        $swagger = $this->swagger_generator->generate_docs();
        
        $filename = 'celoeapi-swagger-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($swagger, JSON_PRETTY_PRINT);
    }
}
