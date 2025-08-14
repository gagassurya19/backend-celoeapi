<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Swagger Generator Library
 * Automatically generates Swagger documentation from CodeIgniter controllers
 */
class Swagger_Generator {
    
    private $CI;
    private $swagger_doc;
    private $initialized = false;
    
    public function __construct() {
        $this->CI =& get_instance();
        // Don't initialize here, wait until generate_docs() is called
    }
    
    /**
     * Initialize Swagger document structure
     */
    private function init_swagger_doc() {
        if ($this->initialized) {
            return;
        }
        
        $this->swagger_doc = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'CeloeAPI Documentation',
                'description' => 'API Documentation for CeloeAPI - Learning Management System Analytics',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'API Support',
                    'email' => 'support@celoeapi.com'
                ]
            ],
            'servers' => [
                [
                    'url' => base_url('api'),
                    'description' => 'Production server'
                ]
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ],
            'tags' => []
        ];
        
        $this->initialized = true;
    }
    
    /**
     * Generate Swagger documentation from controllers
     */
    public function generate_docs() {
        $this->init_swagger_doc(); // Initialize here
        $this->scan_controllers();
        $this->add_common_schemas();
        return $this->swagger_doc;
    }
    
    /**
     * Scan all controllers in the api folder
     */
    private function scan_controllers() {
        $controllers_path = APPPATH . 'controllers/api/';
        $files = glob($controllers_path . '*.php');
        
        foreach ($files as $file) {
            $controller_name = basename($file, '.php');
            if ($controller_name !== 'index') {
                $this->parse_controller($controller_name, $file);
            }
        }
    }
    
    /**
     * Parse individual controller for endpoints
     */
    private function parse_controller($controller_name, $file_path) {
        $content = file_get_contents($file_path);
        
        // Extract class methods
        preg_match_all('/public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)_(get|post|put|delete|patch)\s*\(/', $content, $matches);
        
        if (empty($matches[1])) {
            return;
        }
        
        // Add controller as tag
        $this->add_tag($controller_name);
        
        // Parse each method
        for ($i = 0; $i < count($matches[1]); $i++) {
            $method_name = $matches[1][$i];
            $http_method = strtolower($matches[2][$i]);
            
            $this->parse_endpoint($controller_name, $method_name, $http_method, $content);
        }
    }
    
    /**
     * Parse individual endpoint
     */
    private function parse_endpoint($controller_name, $method_name, $http_method, $content) {
        $path = '/' . strtolower($controller_name);
        
        // Handle special cases
        if ($method_name === 'index') {
            $path = '/' . strtolower($controller_name);
        } else {
            $path .= '/' . $method_name;
        }
        
        // Extract method documentation
        $method_pattern = '/public\s+function\s+' . $method_name . '_' . strtoupper($http_method) . '\s*\([^)]*\)\s*\{[^}]*\/\*\*([^*]|\*(?!\/))*\*\/[^}]*\}/s';
        preg_match($method_pattern, $content, $method_match);
        
        $description = $this->extract_method_description($method_name, $controller_name);
        $parameters = $this->extract_parameters($method_name, $controller_name);
        $responses = $this->generate_responses($method_name, $controller_name);
        
        // Add to paths
        if (!isset($this->swagger_doc['paths'][$path])) {
            $this->swagger_doc['paths'][$path] = [];
        }
        
        $this->swagger_doc['paths'][$path][$http_method] = [
            'tags' => [$controller_name],
            'summary' => ucfirst($method_name) . ' ' . ucfirst($controller_name),
            'description' => $description,
            'parameters' => $parameters,
            'responses' => $responses
        ];
    }
    
    /**
     * Extract method description
     */
    private function extract_method_description($method_name, $controller_name) {
        $descriptions = [
            'index' => 'Get all ' . strtolower($controller_name) . ' data',
            'courses' => 'Get courses with filtering and pagination',
            'course' => 'Get specific course information',
            'analytics' => 'Get analytics data',
            'export' => 'Export data',
            'bulk' => 'Bulk operations',
            'chart' => 'Get chart data',
            'report' => 'Generate report',
            'etl' => 'ETL operations',
            'user' => 'User related operations',
            'activity' => 'Activity related operations'
        ];
        
        return isset($descriptions[$method_name]) ? $descriptions[$method_name] : ucfirst($method_name) . ' operation';
    }
    
    /**
     * Extract parameters based on method and controller
     */
    private function extract_parameters($method_name, $controller_name) {
        $parameters = [];
        
        // Common parameters
        if (in_array($method_name, ['courses', 'analytics', 'export', 'bulk'])) {
            $parameters[] = [
                'name' => 'page',
                'in' => 'query',
                'description' => 'Page number for pagination',
                'required' => false,
                'schema' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1
                ]
            ];
            
            $parameters[] = [
                'name' => 'limit',
                'in' => 'query',
                'description' => 'Number of items per page',
                'required' => false,
                'schema' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ]
            ];
        }
        
        // Search parameter
        if (in_array($method_name, ['courses', 'analytics', 'export'])) {
            $parameters[] = [
                'name' => 'search',
                'in' => 'query',
                'description' => 'Search term',
                'required' => false,
                'schema' => [
                    'type' => 'string'
                ]
            ];
        }
        
        // Controller specific parameters
        switch ($controller_name) {
            case 'Analytics':
                if ($method_name === 'courses') {
                    $parameters[] = [
                        'name' => 'dosen_pengampu',
                        'in' => 'query',
                        'description' => 'Filter by lecturer',
                        'required' => false,
                        'schema' => [
                            'type' => 'string'
                        ]
                    ];
                    
                    $parameters[] = [
                        'name' => 'activity_type',
                        'in' => 'query',
                        'description' => 'Filter by activity type',
                        'required' => false,
                        'schema' => [
                            'type' => 'string'
                        ]
                    ];
                }
                break;
                
            case 'ETL':
                if ($method_name === 'export') {
                    $parameters[] = [
                        'name' => 'tables',
                        'in' => 'query',
                        'description' => 'Comma-separated list of tables to export',
                        'required' => true,
                        'schema' => [
                            'type' => 'string'
                        ]
                    ];
                }
                break;
        }
        
        return $parameters;
    }
    
    /**
     * Generate response schemas
     */
    private function generate_responses($method_name, $controller_name) {
        $responses = [
            '200' => [
                'description' => 'Successful operation',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => [
                                    'type' => 'boolean',
                                    'example' => true
                                ],
                                'data' => [
                                    'type' => 'array',
                                    'items' => [
                                        '$ref' => '#/components/schemas/' . ucfirst($controller_name)
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '400' => [
                'description' => 'Bad request',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => [
                                    'type' => 'boolean',
                                    'example' => false
                                ],
                                'message' => [
                                    'type' => 'string',
                                    'example' => 'Invalid parameters'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '404' => [
                'description' => 'Not found',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => [
                                    'type' => 'boolean',
                                    'example' => false
                                ],
                                'message' => [
                                    'type' => 'string',
                                    'example' => 'Data not found'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '500' => [
                'description' => 'Internal server error',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => [
                                    'type' => 'boolean',
                                    'example' => false
                                ],
                                'message' => [
                                    'type' => 'string',
                                    'example' => 'Internal server error'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // Add pagination for list methods
        if (in_array($method_name, ['courses', 'analytics', 'export', 'bulk'])) {
            $responses['200']['content']['application/json']['schema']['properties']['pagination'] = [
                'type' => 'object',
                'properties' => [
                    'current_page' => [
                        'type' => 'integer',
                        'example' => 1
                    ],
                    'total_pages' => [
                        'type' => 'integer',
                        'example' => 5
                    ],
                    'total_items' => [
                        'type' => 'integer',
                        'example' => 50
                    ],
                    'items_per_page' => [
                        'type' => 'integer',
                        'example' => 10
                    ]
                ]
            ];
        }
        
        return $responses;
    }
    
    /**
     * Add tag to swagger doc
     */
    private function add_tag($controller_name) {
        $tag_exists = false;
        foreach ($this->swagger_doc['tags'] as $tag) {
            if ($tag['name'] === $controller_name) {
                $tag_exists = true;
                break;
            }
        }
        
        if (!$tag_exists) {
            $this->swagger_doc['tags'][] = [
                'name' => $controller_name,
                'description' => ucfirst($controller_name) . ' operations'
            ];
        }
    }
    
    /**
     * Add common schemas
     */
    private function add_common_schemas() {
        // Course schema
        $this->swagger_doc['components']['schemas']['Course'] = [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Course ID'
                ],
                'fullname' => [
                    'type' => 'string',
                    'description' => 'Course full name'
                ],
                'shortname' => [
                    'type' => 'string',
                    'description' => 'Course short name'
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Course category'
                ],
                'startdate' => [
                    'type' => 'string',
                    'format' => 'date-time',
                    'description' => 'Course start date'
                ],
                'enddate' => [
                    'type' => 'string',
                    'format' => 'date-time',
                    'description' => 'Course end date'
                ]
            ]
        ];
        
        // Analytics schema
        $this->swagger_doc['components']['schemas']['Analytics'] = [
            'type' => 'object',
            'properties' => [
                'course_id' => [
                    'type' => 'integer',
                    'description' => 'Course ID'
                ],
                'course_name' => [
                    'type' => 'string',
                    'description' => 'Course name'
                ],
                'jumlah_mahasiswa' => [
                    'type' => 'integer',
                    'description' => 'Number of students'
                ],
                'jumlah_aktivitas' => [
                    'type' => 'integer',
                    'description' => 'Number of activities'
                ],
                'keaktifan' => [
                    'type' => 'number',
                    'format' => 'float',
                    'description' => 'Activity level'
                ]
            ]
        ];
        
        // ETL schema
        $this->swagger_doc['components']['schemas']['ETL'] = [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'ETL job ID'
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'ETL job status',
                    'enum' => ['pending', 'running', 'completed', 'failed']
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'ETL job message'
                ],
                'created_at' => [
                    'type' => 'string',
                    'format' => 'date-time',
                    'description' => 'Creation timestamp'
                ]
            ]
        ];
    }
    
    /**
     * Get JSON representation
     */
    public function get_json() {
        return json_encode($this->swagger_doc, JSON_PRETTY_PRINT);
    }
    
    /**
     * Save to file
     */
    public function save_to_file($file_path = null) {
        if (!$file_path) {
            $file_path = APPPATH . 'swagger.json';
        }
        
        $json = $this->get_json();
        return file_put_contents($file_path, $json);
    }
}
