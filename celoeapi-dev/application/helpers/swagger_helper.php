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
	// Load config without relying on CI magic properties to satisfy linters
	$config = [];
	$config_file = APPPATH . 'config/swagger.php';
	if (file_exists($config_file)) {
		// This file defines $config array
		include $config_file;
		if (isset($config) && isset($config['swagger'])) {
			$config = $config['swagger'];
		} else {
			$config = [];
		}
	}
	
	// Debug: Log the loaded config
	log_message('debug', 'Swagger config loaded: ' . json_encode($config));
	
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
							
			
		];
	}
	
	// Ensure all required config keys exist
	if (!isset($config['title'])) $config['title'] = 'Celoe API Dev - ETL & Analytics API';
	if (!isset($config['description'])) $config['description'] = 'Comprehensive API for ETL processes, user activity analytics, and Moodle data management';
	if (!isset($config['version'])) $config['version'] = '1.0.0';
	if (!isset($config['contact'])) $config['contact'] = ['name' => 'Celoe Development Team', 'email' => 'dev@celoe.com'];
	if (!isset($config['license'])) $config['license'] = ['name' => 'MIT', 'url' => 'https://opensource.org/licenses/MIT'];
	if (!isset($config['servers'])) $config['servers'] = [['url' => 'http://localhost:8081', 'description' => 'Local Development Server (Docker)']];
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
	
	// Also look for REST_Controller specific methods (e.g. run_pipeline_post)
	preg_match_all('/public\s+function\s+(\w+)_(get|post|put|delete)\s*\(/', $content, $rest_matches);
	
	if (isset($rest_matches[1]) && isset($rest_matches[2])) {
		for ($i = 0; $i < count($rest_matches[1]); $i++) {
			$method_name = $rest_matches[1][$i] . '_' . $rest_matches[2][$i];
			if (!in_array($method_name, $methods)) {
				$methods[] = $method_name;
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
	
	$op = [
		'tags' => [$tag],
		'summary' => generate_summary($method_name),
		'description' => generate_description($method_name),
		'parameters' => generate_parameters($method_name, $content),
		'requestBody' => generate_request_body($method_name, $content),
		'responses' => generate_responses($method_name)
	];

	// Focus tag override for Course Performance endpoints
	if (stripos($endpoint_path, '/api/etl_cp') === 0 || stripos($endpoint_path, '/api/etl_cp_export') === 0) {
		$op['tags'] = ['Course Performance ETL'];
	}
	
	// Focus tag override for SAS endpoints
	if (stripos($endpoint_path, '/api/etl_sas') === 0) {
		$op['tags'] = ['Student Activity Summary ETL'];
	}

	$paths[$endpoint_path] = [ $http_method => $op ];
	
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
	if (strpos($method_name, 'get') === 0 || strpos($method_name, 'fetch') === 0 || strpos($method_name, 'list') === 0 || strpos($method_name, 'status') === 0 || strpos($method_name, 'health') === 0 || strpos($method_name, 'logs') === 0) {
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
	
	// Special handling for export methods
	if (strpos($method_name, 'export') === 0) {
		// Specific methods that should be POST
		if (in_array($method_name, ['export_incremental', 'export_batch', 'export_data'])) {
			return 'post';
		}
		// Default export methods are GET
		return 'get';
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
	
	// Handle special cases for ETL methods
	if ($clean_method_name === 'run_pipeline') {
		// Map SAS controller to new public path
		if (strtolower($controller_name) === 'etl_sas' || strtolower($controller_name) === 'etl_student_activity_summary') {
			return '/api/etl_sas/run';
		}
		return '/' . $path . '/run_pipeline';
	}

	// Map etl_cp_export/export to /api/etl_cp/export for nicer public path
	if (strtolower($controller_name) === 'etl_cp_export' && $clean_method_name === 'export') {
		return '/api/etl_cp/export';
	}
	
	// Map SAS export and clean to new public paths (for both controller names)
	if (strtolower($controller_name) === 'etl_sas' || strtolower($controller_name) === 'etl_student_activity_summary') {
		if ($clean_method_name === 'export') {
			return '/api/etl_sas/export';
		}
		if ($clean_method_name === 'clean_data') {
			return '/api/etl_sas/clean';
		}
		if ($clean_method_name === 'logs') {
			return '/api/etl_sas/logs';
		}
		if ($clean_method_name === 'status') {
			return '/api/etl_sas/status';
		}
	}
	
	// Map CP methods to new public paths
	if (strtolower($controller_name) === 'etl_cp') {
		if ($clean_method_name === 'run') {
			return '/api/etl_cp/run';
		}
		if ($clean_method_name === 'clean') {
			return '/api/etl_cp/clean';
		}
		if ($clean_method_name === 'logs') {
			return '/api/etl_cp/logs';
		}
		if ($clean_method_name === 'status') {
			return '/api/etl_cp/status';
		}
	}
	
	// Map SP ETL methods to proper paths
	if (strtolower($controller_name) === 'sp_etl') {
		if ($clean_method_name === 'run') {
			return '/api/sp_etl/run';
		}
		if ($clean_method_name === 'logs') {
			return '/api/sp_etl/logs';
		}
		if ($clean_method_name === 'get_log') {
			return '/api/sp_etl/get_log/{id}';
		}
		if ($clean_method_name === 'stats') {
			return '/api/sp_etl/stats';
		}
		if ($clean_method_name === 'export_incremental') {
			return '/api/sp_etl/export_incremental';
		}
	}
	
	// Map TP ETL methods to proper paths
	if (strtolower($controller_name) === 'tp_etl') {
		if ($clean_method_name === 'run') {
			return '/api/tp_etl/run';
		}
		if ($clean_method_name === 'logs') {
			return '/api/tp_etl/logs';
		}
		if ($clean_method_name === 'export') {
			return '/api/tp_etl/export';
		}
		if ($clean_method_name === 'help') {
			return '/api/tp_etl/help';
		}
	}
	
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
		'User_activity_etl' => 'User Activity',
		'Etl_cp' => 'Course Performance ETL',
		'Etl_cp_export' => 'Course Performance ETL',
		// Friendlier tag for SAS controller
		'etl_sas' => 'Student Activity Summary ETL',
		// Teacher ETL controller
		'tp_etl' => 'Teacher ETL'
	];
	
	return isset($tag_mappings[$controller_name]) ? $tag_mappings[$controller_name] : $tag;
}

/**
 * Generate summary for endpoint
 */
function generate_summary($method_name) {
	// Clean up method name by removing HTTP method suffixes
	$clean_method_name = preg_replace('/_(get|post|put|delete)$/', '', $method_name);
	
	// Provide specific summaries for ETL methods
	$summary_mappings = [
		'run_pipeline' => 'Run ETL Pipeline with Date Range',
		'run' => 'Run Course Performance ETL (Full or Backfill)',
		'backfill' => 'Start Backfill from Start Date',
		'export' => 'Export Data',
		'status' => 'Get Status',
		'clean_data' => 'Clean Data',
		'clean' => 'Clean Data',
		'logs' => 'Get ETL Logs',
		'list' => 'List Records',
		'get' => 'Get Record',
		'create' => 'Create Record',
		'update' => 'Update Record',
		'delete' => 'Delete Record',
		'help' => 'Get API Documentation'
	];
	
	if (isset($summary_mappings[$clean_method_name])) {
		return $summary_mappings[$clean_method_name];
	}
	
	// Fallback to generic summary
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
	
	// Provide specific descriptions for ETL methods
	$description_mappings = [
		'run_pipeline' => 'Start ETL pipeline processing for a specified date range. Supports both automatic date detection and manual date range specification.',
		'run' => 'Trigger Course Performance ETL (cp_*). If body contains start_date, runs backfill from that date up to the latest date in daily batches (optional concurrency). Otherwise performs a full refresh. Runs in background when called from API.',
		'backfill' => 'Backfill Course Performance data from the provided start_date up to latest date in daily batches. Designed for large datasets; supports optional concurrency and resource tuning via env.',
		'export' => 'Export Course Performance data (no date filters) with pagination. Use query params: limit, offset, and optional table/tables to select specific tables.',
		'status' => 'Get current status and progress information.',
		'clean_data' => 'Clean existing data for specified parameters.',
		'clean' => 'Clean all ETL data for the service.',
		'logs' => 'Get ETL execution logs with pagination. Optional query parameters: limit, offset, status.',
		'list' => 'Retrieve a list of records with pagination support.',
		'get' => 'Retrieve a specific record by identifier.',
		'create' => 'Create a new record.',
		'update' => 'Update an existing record.',
		'delete' => 'Delete a record.',
		'help' => 'Retrieve comprehensive API documentation and examples.'
	];
	
	if (isset($description_mappings[$clean_method_name])) {
		return $description_mappings[$clean_method_name];
	}
	
	// Fallback to generic description
	$description = str_replace('_', ' ', $clean_method_name);
	$description = ucwords($description);
	return $description . ' operation';
}

/**
 * Generate parameters for endpoint
 */
function generate_parameters($method_name, $content) {
	$parameters = [];
	
	// Add generic parameters for list/get methods
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
	
	// Add specific parameters for tp_etl export method
	if (strpos($method_name, 'export') !== false && strpos($method_name, 'export_incremental') === false) {
		// Check if this is tp_etl controller by analyzing content
		if (strpos($content, 'tp_etl') !== false || strpos($content, 'table_name') !== false) {
			$parameters[] = [
				'name' => 'table_name',
				'in' => 'query',
				'description' => 'Table to export data from (summary or detail)',
				'required' => true,
				'schema' => [
					'type' => 'string',
					'enum' => ['summary', 'detail'],
					'example' => 'summary'
				]
			];
			$parameters[] = [
				'name' => 'page',
				'in' => 'query',
				'description' => 'Page number',
				'required' => false,
				'schema' => [
					'type' => 'integer',
					'minimum' => 1,
					'default' => 1
				]
			];
			$parameters[] = [
				'name' => 'per_page',
				'in' => 'query',
				'description' => 'Records per page',
				'required' => false,
				'schema' => [
					'type' => 'integer',
					'minimum' => 1,
					'maximum' => 1000,
					'default' => 100
				]
			];
			$parameters[] = [
				'name' => 'order_by',
				'in' => 'query',
				'description' => 'Order by field',
				'required' => false,
				'schema' => [
					'type' => 'string',
					'default' => 'id'
				]
			];
			$parameters[] = [
				'name' => 'order_direction',
				'in' => 'query',
				'description' => 'Order direction',
				'required' => false,
				'schema' => [
					'type' => 'string',
					'enum' => ['ASC', 'DESC'],
					'default' => 'DESC'
				]
			];
		}
	}
	
	// Analyze controller content for specific query parameters (not body parameters)
	$specific_params = analyze_controller_parameters($method_name, $content);
	if (!empty($specific_params)) {
		$parameters = array_merge($parameters, $specific_params);
	}
	
	return $parameters;
}

/**
 * Analyze controller content to detect specific parameters
 */
function analyze_controller_parameters($method_name, $content) {
	$parameters = [];
	
	// Add table selection parameters for export (but exclude export_incremental which uses request body)
	if (strpos($method_name, 'export') !== false && strpos($method_name, 'export_incremental') === false) {
		// Skip if this is tp_etl export (already handled above)
		if (strpos($content, 'tp_etl') === false) {
			$parameters[] = [
				'name' => 'table',
				'in' => 'query',
				'description' => 'Single table to export (e.g., cp_student_profile, cp_course_summary, cp_activity_summary, cp_student_quiz_detail, cp_student_assignment_detail, cp_student_resource_access)',
				'required' => false,
				'schema' => ['type' => 'string']
			];
			$parameters[] = [
				'name' => 'tables',
				'in' => 'query',
				'description' => 'Comma-separated list of tables to export',
				'required' => false,
				'schema' => ['type' => 'string']
			];
			$parameters[] = [
				'name' => 'debug',
				'in' => 'query',
				'description' => 'Include debug information (counts, database)',
				'required' => false,
				'schema' => ['type' => 'boolean']
			];
		}
	}
	
	return $parameters;
}

/**
 * Generate request body for endpoint
 */
function generate_request_body($method_name, $content) {
	// Check if this is a POST/PUT method (including REST_Controller suffixes)
	$is_post_method = preg_match('/_(post)$/', $method_name) || strpos($method_name, 'post') === 0;
	$is_put_method = preg_match('/_(put)$/', $method_name) || strpos($method_name, 'put') === 0;
	$is_create_method = strpos($method_name, 'create') === 0;
	$is_update_method = strpos($method_name, 'update') === 0;
	
	if ($is_post_method || $is_put_method || $is_create_method || $is_update_method) {
		// Special handling for tp_etl/run method
		if (strpos($method_name, 'run') !== false) {
			return [
				'required' => false,
				'content' => [
					'application/json' => [
						'schema' => [
							'type' => 'object',
							'properties' => [
								'concurrency' => [
									'type' => 'integer',
									'minimum' => 1,
									'maximum' => 10,
									'default' => 1,
									'description' => 'Concurrency level for ETL process (1-10)',
									'example' => 2
								]
							],
							'description' => 'Optional parameters for Teacher ETL process'
						]
					]
				]
			];
		}
		
		// If controller contains start_date reference, allow optional body with start_date + concurrency
		if (strpos($content, 'start_date') !== false) {
			return [
				'required' => false,
				'content' => [
					'application/json' => [
						'schema' => [
							'type' => 'object',
							'properties' => [
								'start_date' => [
									'type' => 'string',
									'format' => 'date',
									'pattern' => '^\\d{4}-\\d{2}-\\d{2}$',
									'description' => 'Start date for ETL processing (YYYY-MM-DD format)',
									'example' => '2024-01-01'
								],
								'concurrency' => [
									'type' => 'integer',
									'minimum' => 1,
									'description' => 'Number of concurrent workers (pcntl) for backfill',
									'example' => 4
								]
							]
						]
					]
				]
			];
		}
		
		// Default: no body required
		return null;
	}
	
	// Special handling for export_incremental method (even if not detected as POST by pattern)
	if (strpos($method_name, 'export_incremental') !== false) {
		return [
			'required' => true,
			'content' => [
				'application/json' => [
					'schema' => [
						'type' => 'object',
						'properties' => [
							'table_name' => [
								'type' => 'string',
								'enum' => ['sp_etl_summary', 'sp_etl_detail'],
								'description' => 'Name of the table to export incrementally',
								'example' => 'sp_etl_summary'
							],
							'batch_size' => [
								'type' => 'integer',
								'minimum' => 1,
								'maximum' => 1000,
								'default' => 100,
								'description' => 'Number of records to process per batch'
							],
							'offset' => [
								'type' => 'integer',
								'default' => 0,
								'description' => 'Offset for pagination'
							]
						],
						'required' => ['table_name']
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
	// Special handling for export_incremental method
	if (strpos($method_name, 'export_incremental') !== false) {
		return [
			'200' => [
				'description' => 'Success - Incremental export completed',
				'content' => [
					'application/json' => [
						'schema' => [
							'type' => 'object',
							'properties' => [
								'success' => ['type' => 'boolean', 'example' => true],
								'message' => ['type' => 'string', 'example' => 'Incremental export completed successfully'],
								'log_id' => ['type' => 'integer', 'example' => 123],
								'duration' => ['type' => 'number', 'example' => 2.5],
								'table_name' => ['type' => 'string', 'example' => 'sp_etl_summary'],
								'batch_size' => ['type' => 'integer', 'example' => 100],
								'extraction_date' => ['type' => 'string', 'example' => '2024-08-28'],
								'export_summary' => ['type' => 'object'],
								'export_detail' => ['type' => 'object']
							]
						]
					]
				]
			],
			'400' => [
				'description' => 'Bad request - Invalid parameters',
				'content' => [
					'application/json' => [
						'schema' => [
							'type' => 'object',
							'properties' => [
								'status' => ['type' => 'integer', 'example' => 400],
								'message' => ['type' => 'string', 'example' => 'Bad Request'],
								'timestamp' => ['type' => 'string', 'example' => '2025-08-28 12:20:12'],
								'data' => ['type' => 'string', 'example' => 'Invalid table_name. Use: sp_etl_summary or sp_etl_detail']
							]
						]
					]
				]
			],
			'401' => ['$ref' => '#/components/schemas/UnauthorizedError'],
			'500' => ['$ref' => '#/components/schemas/ServerError']
		];
	}
	
	// Special handling for tp_etl/run method
	if (strpos($method_name, 'run') !== false && strpos($method_name, 'run_pipeline') === false) {
			return [
				'200' => [
					'description' => 'Success - Teacher ETL process completed',
					'content' => [
						'application/json' => [
							'schema' => [
								'type' => 'object',
								'properties' => [
									'log_id' => ['type' => 'integer', 'example' => 123],
									'summary' => [
										'type' => 'object',
										'properties' => [
											'extracted' => ['type' => 'integer', 'example' => 150],
											'inserted' => ['type' => 'integer', 'example' => 100],
											'updated' => ['type' => 'integer', 'example' => 50],
											'duration_seconds' => ['type' => 'number', 'example' => 45.2]
										]
									],
									'detail' => [
										'type' => 'object',
										'properties' => [
											'extracted' => ['type' => 'integer', 'example' => 1500],
											'inserted' => ['type' => 'integer', 'example' => 1500],
											'duration_seconds' => ['type' => 'number', 'example' => 45.2]
										]
									],
									'total_duration_seconds' => ['type' => 'number', 'example' => 45.2],
									'concurrency' => ['type' => 'integer', 'example' => 2],
									'date' => ['type' => 'string', 'example' => '2025-08-29']
								]
							]
						]
					]
				],
				'400' => [
					'description' => 'Bad request - ETL process failed',
					'content' => [
						'application/json' => [
							'schema' => [
								'type' => 'object',
								'properties' => [
									'log_id' => ['type' => 'integer', 'example' => 123],
									'error' => ['type' => 'string', 'example' => 'ETL process failed'],
									'duration_seconds' => ['type' => 'number', 'example' => 5.2],
									'concurrency' => ['type' => 'integer', 'example' => 2]
								]
							]
						]
					]
				],
				'405' => [
					'description' => 'Method Not Allowed',
					'content' => [
						'application/json' => [
							'schema' => [
								'type' => 'object',
								'properties' => [
									'status' => ['type' => 'integer', 'example' => 405],
									'message' => ['type' => 'string', 'example' => 'Method Not Allowed'],
									'timestamp' => ['type' => 'string', 'example' => '2025-08-29 12:00:00'],
									'data' => ['type' => 'string', 'example' => 'Only POST method is allowed']
								]
							]
						]
					]
				],
				'500' => [
					'description' => 'Internal Server Error',
					'content' => [
						'application/json' => [
							'schema' => [
								'type' => 'object',
								'properties' => [
									'error' => ['type' => 'string', 'example' => 'Exception occurred'],
									'log_id' => ['type' => 'integer', 'example' => 123]
								]
							]
						]
					]
				]
			];
		}
	
	// Treat ETL run/backfill endpoints as async (but exclude tp_etl/run which is synchronous)
	if (strpos($method_name, 'backfill') !== false || (strpos($method_name, 'run') !== false && strpos($method_name, 'run_pipeline') === false && strpos($method_name, 'tp_etl') === false) || strpos($method_name, 'etl') !== false) {
		return [
			'202' => [
				'description' => 'Accepted - ETL started in background',
				'content' => [
					'application/json' => [
						'schema' => [
							'$ref' => '#/components/schemas/ETLResponse'
						]
					]
				]
			],
			'400' => [
				'description' => 'Bad request - Invalid date format or range',
				'content' => [
					'application/json' => [
						'schema' => [
							'$ref' => '#/components/schemas/ETLErrorResponse'
						]
					]
				]
			],
			'401' => ['$ref' => '#/components/schemas/UnauthorizedError'],
			'500' => ['$ref' => '#/components/schemas/ServerError']
		];
	}
	
	// Default responses for other methods
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
		],
		
		// ETL specific schemas
		'ETLResponse' => [
			'type' => 'object',
			'properties' => [
				' status' => [
					'type' => 'boolean',
					'example' => true
				],
				'message' => [
					'type' => 'string',
					'example' => 'ETL pipeline started in background with date range'
				],
				'date_range' => [
					'type' => 'object',
					'properties' => [
						'start_date' => [
							'type' => 'string',
							'format' => 'date',
							'example' => '2024-01-01'
						],
						'end_date' => [
							'type' => 'string',
							'format' => 'date',
							'example' => '2024-01-31'
						]
					]
				],
				'note' => [
					'type' => 'string',
					'example' => 'Check logs for ETL progress and completion status'
				]
			]
		],
		
		'ETLErrorResponse' => [
			'type' => 'object',
			'properties' => [
				'status' => [
					'type' => 'boolean',
					'example' => false
				],
				'message' => [
					'type' => 'string',
					'example' => 'ETL pipeline failed to start'
				],
				'error' => [
					'type' => 'string',
					'example' => 'Invalid date format. Use YYYY-MM-DD format.'
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
