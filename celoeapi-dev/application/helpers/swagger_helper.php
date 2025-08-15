<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Swagger Helper Functions
 * 
 * Generates OpenAPI/Swagger documentation for CodeIgniter REST API
 */

/**
 * Generate OpenAPI specification
 */
function generate_swagger_spec() {
    $CI =& get_instance();
    
    // Try to load the config
    $CI->config->load('swagger', TRUE);
    $config = $CI->config->item('swagger');
    
    // Set default config if loading fails
    if (empty($config)) {
        $config = [
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
                ]
            ],
            'security' => [],
            'tags' => [
                [
                    'name' => 'ETL',
                    'description' => 'Extract, Transform, Load operations'
                ],
                [
                    'name' => 'User Activity',
                    'description' => 'User activity ETL and analytics'
                ]
            ]
        ];
    }
    
    // Ensure all required config keys exist
    if (!isset($config['title'])) $config['title'] = 'Celoe API Dev - ETL & Analytics API';
    if (!isset($config['description'])) $config['description'] = 'Comprehensive API for ETL processes, user activity analytics, and Moodle data management';
    if (!isset($config['version'])) $config['version'] = '1.0.0';
    if (!isset($config['contact'])) $config['contact'] = ['name' => 'Celoe Development Team', 'email' => 'dev@celoe.com'];
    if (!isset($config['license'])) $config['license'] = ['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'];
    if (!isset($config['servers'])) $config['servers'] = [['url' => 'http://localhost:8081', 'description' => 'Local Development Server']];
    if (!isset($config['security'])) $config['security'] = [];
    if (!isset($config['tags'])) $config['tags'] = [];
    
    $spec = [
        'openapi' => '3.0.0',
        'info' => [
            'title' => $config['title'],
            'description' => $config['description'],
            'version' => $config['version'],
            'contact' => $config['contact'],
            'license' => $config['license']
        ],
        'servers' => $config['servers'],
        'security' => [],
        'paths' => auto_discover_endpoints(),
        'components' => [
            'schemas' => auto_discover_schemas()
        ],
        'tags' => $config['tags']
    ];
    
    return $spec;
}

/**
 * Generate API paths from controllers
 */
function generate_swagger_paths() {
    return auto_discover_endpoints();
}

/**
 * Automatically discover API endpoints from controllers
 */
function auto_discover_endpoints() {
    $CI =& get_instance();
    $paths = [];
    
    // Get controllers directory and subdirectories
    $controllers_dirs = [
        // APPPATH . 'controllers/',
        APPPATH . 'controllers/api/'
    ];
    
    foreach ($controllers_dirs as $controllers_dir) {
        if (is_dir($controllers_dir)) {
            $controllers = glob($controllers_dir . '*.php');
            
            foreach ($controllers as $controller_file) {
                $controller_name = basename($controller_file, '.php');
                
                // Skip base classes and special files
                if (in_array($controller_name, ['CI_Controller', 'MY_Controller', 'Swagger', 'index'])) {
                    continue;
                }
                
                // Load controller file content
                $controller_content = file_get_contents($controller_file);
                
                // Extract class name
                if (preg_match('/class\s+(\w+)\s+extends\s+(CI_Controller|REST_Controller)/', $controller_content, $matches)) {
                    $class_name = $matches[1];
                    
                    // Get public methods
                    $methods = get_public_methods($controller_file, $class_name);
                    
                    foreach ($methods as $method) {
                        $endpoint = generate_endpoint_from_method($controller_name, $method, $controller_content, $controllers_dir);
                        if ($endpoint) {
                            $paths = array_merge($paths, $endpoint);
                        }
                    }
                }
            }
        }
    }
    
    return $paths;
}

/**
 * Get public methods from a controller file
 */
function get_public_methods($file_path, $class_name) {
    $methods = [];
    $content = file_get_contents($file_path);
    
    // Find public methods (excluding constructor and private methods)
    preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $matches);
    
    if (isset($matches[1])) {
        foreach ($matches[1] as $method) {
            // Skip constructor and special methods
            if (!in_array($method, ['__construct', 'index', 'test', 'simple'])) {
                $methods[] = $method;
            }
        }
    }
    
    return $methods;
}

/**
 * Generate OpenAPI endpoint from controller method
 */
function generate_endpoint_from_method($controller_name, $method_name, $content, $controllers_dir) {
    $paths = [];
    
    // Determine HTTP method based on method name
    $http_method = determine_http_method($method_name);
    
    // Generate endpoint path
    $endpoint_path = generate_endpoint_path($controller_name, $method_name, $controllers_dir);
    
    // Generate tag based on controller name
    $tag = generate_tag_from_controller($controller_name);
    
    $paths[$endpoint_path] = [
        $http_method => [
            'tags' => [$tag],
            'summary' => generate_summary($method_name),
            'description' => generate_description($method_name),
            'security' => [['BearerAuth' => []]],
            'parameters' => generate_parameters($method_name, $content),
            'requestBody' => generate_request_body($method_name),
            'responses' => generate_responses($method_name)
        ]
    ];
    
    return $paths;
}

/**
 * Determine HTTP method based on method name
 */
function determine_http_method($method_name) {
    // REST_Controller uses suffixes like _get, _post, _put, _delete
    if (preg_match('/_get$/', $method_name)) {
        return 'get';
    }
    if (preg_match('/_post$/', $method_name)) {
        return 'post';
    }
    if (preg_match('/_put$/', $method_name)) {
        return 'put';
    }
    if (preg_match('/_delete$/', $method_name)) {
        return 'delete';
    }
    
    // Fallback to method name patterns
    if (strpos($method_name, 'get') === 0 || strpos($method_name, 'fetch') === 0 || strpos($method_name, 'list') === 0 || strpos($method_name, 'export') === 0 || strpos($method_name, 'status') === 0 || strpos($method_name, 'health') === 0 || strpos($method_name, 'logs') === 0) {
        return 'get';
    }
    if (strpos($method_name, 'post') === 0 || strpos($method_name, 'create') === 0 || strpos($method_name, 'add') === 0 || strpos($method_name, 'run') === 0 || strpos($method_name, 'clear') === 0 || strpos($method_name, 'clean') === 0 || strpos($method_name, 'initialize') === 0) {
        return 'post';
    }
    if (strpos($method_name, 'put') === 0 || strpos($method_name, 'update') === 0 || strpos($method_name, 'edit') === 0) {
        return 'put';
    }
    if (strpos($method_name, 'delete') === 0 || strpos($method_name, 'remove') === 0) {
        return 'delete';
    }
    
    // Default to GET for most methods
    return 'get';
}

/**
 * Generate endpoint path from controller and method
 */
function generate_endpoint_path($controller_name, $method_name, $controllers_dir) {
    // Check if this is from the api subdirectory
    $is_api = strpos($controllers_dir, '/api/') !== false;
    
    if ($is_api) {
        $path = 'api/' . strtolower($controller_name);
    } else {
        $path = strtolower($controller_name);
    }
    
    // Clean up method name by removing HTTP method suffixes
    $clean_method_name = preg_replace('/_(get|post|put|delete)$/', '', $method_name);
    
    if ($clean_method_name === 'index') {
        return '/' . $path;
    }
    
    return '/' . $path . '/' . $clean_method_name;
}

/**
 * Generate tag from controller name
 */
function generate_tag_from_controller($controller_name) {
    $tag = str_replace('_', ' ', $controller_name);
    $tag = ucwords($tag);
    
    $tag_mappings = [
        'User_activity_etl' => 'User Activity'
    ];
    
    return isset($tag_mappings[$controller_name]) ? $tag_mappings[$controller_name] : $tag;
}

/**
 * Generate summary for endpoint
 */
function generate_summary($method_name) {
    // Clean up method name by removing HTTP method suffixes
    $clean_method_name = preg_replace('/_(get|post|put|delete)$/', '', $method_name);
    $summary = str_replace('_', ' ', $clean_method_name);
    $summary = ucwords($summary);
    return $summary;
}

/**
 * Generate description for endpoint
 */
function generate_description($method_name) {
    // Clean up method name by removing HTTP method suffixes
    $clean_method_name = preg_replace('/_(get|post|put|delete)$/', '', $method_name);
    $description = str_replace('_', ' ', $clean_method_name);
    $description = ucwords($description);
    return $description . ' operation';
}

/**
 * Generate parameters for endpoint
 */
function generate_parameters($method_name, $content) {
    $parameters = [];
    
    if (strpos($method_name, 'list') !== false || strpos($method_name, 'get') !== false) {
        $parameters[] = [
            'name' => 'limit',
            'in' => 'query',
            'description' => 'Number of records to return',
            'required' => false,
            'schema' => ['type' => 'integer', 'default' => 100]
        ];
        $parameters[] = [
            'name' => 'offset',
            'in' => 'query',
            'description' => 'Number of records to skip',
            'required' => false,
            'schema' => ['type' => 'integer', 'default' => 0]
        ];
    }
    
    return $parameters;
}

/**
 * Generate request body for endpoint
 */
function generate_request_body($method_name) {
    if (strpos($method_name, 'post') === 0 || strpos($method_name, 'put') === 0 || strpos($method_name, 'create') === 0 || strpos($method_name, 'update') === 0) {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/GenericRequest'
                    ]
                ]
            ]
        ];
    }
    
    return null;
}

/**
 * Generate responses for endpoint
 */
function generate_responses($method_name) {
    return [
        '200' => [
            'description' => 'Success',
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/SuccessResponse'
                    ]
                ]
            ]
        ],
        '401' => ['$ref' => '#/components/schemas/UnauthorizedError'],
        '500' => ['$ref' => '#/components/schemas/ServerError']
    ];
}

/**
 * Generate schema definitions
 */
function generate_swagger_schemas() {
    return auto_discover_schemas();
}

/**
 * Automatically discover schemas from models and data structures
 */
function auto_discover_schemas() {
    $schemas = [
        // Base schemas
        'GenericRequest' => [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Request data'
                ]
            ]
        ],
        
        'SuccessResponse' => [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'boolean',
                    'example' => true
                ],
                'message' => [
                    'type' => 'string',
                    'example' => 'Operation completed successfully'
                ]
            ]
        ],
        
        'UnauthorizedError' => [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'boolean',
                    'example' => false
                ],
                'message' => [
                    'type' => 'string',
                    'example' => 'Unauthorized'
                ]
            ]
        ],
        
        'ServerError' => [
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
    ];
    
    // Add more schemas based on your models
    $schemas = array_merge($schemas, generate_model_schemas());
    
    return $schemas;
}

/**
 * Generate schemas from models
 */
function generate_model_schemas() {
    $schemas = [];
    
    // Get models directory
    $models_dir = APPPATH . 'models/';
    $models = glob($models_dir . '*.php');
    
    foreach ($models as $model_file) {
        $model_name = basename($model_file, '.php');
        
        // Skip base classes
        if (in_array($model_name, ['CI_Model', 'MY_Model'])) {
            continue;
        }
        
        // Generate schema from model
        $schema = generate_model_schema($model_file, $model_name);
        if ($schema) {
            $schemas[$model_name] = $schema;
        }
    }
    
    return $schemas;
}

/**
 * Generate schema from a single model
 */
function generate_model_schema($model_file, $model_name) {
    $content = file_get_contents($model_file);
    
    // Look for table structure or field definitions
    if (preg_match('/protected\s+\$fields\s*=\s*\[(.*?)\]/s', $content, $matches)) {
        $fields_content = $matches[1];
        return parse_fields_to_schema($fields_content);
    }
    
    // Default schema for models
    return [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Unique identifier'
            ],
            'created_at' => [
                'type' => 'string',
                'format' => 'date-time',
                'description' => 'Creation timestamp'
            ],
            'updated_at' => [
                'type' => 'string',
                'format' => 'date-time',
                'description' => 'Last update timestamp'
            ]
        ]
    ];
}

/**
 * Parse model fields to schema
 */
function parse_fields_to_schema($fields_content) {
    $properties = [];
    
    // Extract field definitions
    preg_match_all('/\'(\w+)\'\s*=>\s*\[(.*?)\]/s', $fields_content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $field_name = $match[1];
        $field_def = $match[2];
        
        $properties[$field_name] = [
            'type' => determine_field_type($field_def),
            'description' => ucfirst(str_replace('_', ' ', $field_name))
        ];
    }
    
    return [
        'type' => 'object',
        'properties' => $properties
    ];
}

/**
 * Determine field type from field definition
 */
function determine_field_type($field_def) {
    if (strpos($field_def, 'int') !== false) return 'integer';
    if (strpos($field_def, 'float') !== false || strpos($field_def, 'decimal') !== false) return 'number';
    if (strpos($field_def, 'date') !== false) return 'string';
    if (strpos($field_def, 'bool') !== false) return 'boolean';
    
    return 'string'; // Default to string
}
