<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tp_etl extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        // Disable error display to prevent HTML output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Load required models
        try {
            $this->load->model('tp_etl_summary_model');
            $this->load->model('tp_etl_logs_model');
        } catch (Exception $e) {
            log_message('error', "Error loading models in Tp_etl controller: " . $e->getMessage());
        }
        
        // Load helpers
        $this->load->helper('url');
        $this->load->helper('form');
        
        // Set JSON response header
        header('Content-Type: application/json');
    }

    /**
     * Run complete teacher ETL process (Summary + Detail)
     * 
     * @method POST
     * @route /api/tp_etl/run
     * @param int concurrency - Optional concurrency level (default: 1)
     */
    public function run() {
        // Only allow POST method
        if ($this->input->method() !== 'post') {
            $this->_send_response(405, 'Method Not Allowed', 'Only POST method is allowed');
            return;
        }

        try {
            // Check if models are loaded
            if (!isset($this->tp_etl_logs_model) || !isset($this->tp_etl_summary_model)) {
                $this->_send_response(500, 'Internal Server Error', 'Required models not loaded');
                return;
            }

            // Set execution time limit for ETL process
            if (function_exists('set_time_limit')) {
                set_time_limit(600); // 10 minutes for ETL process
            }
            
            // Get concurrency parameter
            $concurrency = $this->input->post('concurrency') ?: 1;
            $concurrency = max(1, min(10, intval($concurrency))); // Limit between 1-10
            
            // Create log entry
            $log_id = $this->tp_etl_logs_model->create_log_entry(
                'teacher_summary_etl',
                'running',
                'Starting teacher ETL process (Summary + Detail)',
                $concurrency
            );
            
            if (!$log_id) {
                $this->_send_response(500, 'Internal Server Error', 'Failed to create ETL log entry');
                return;
            }
            
            log_message('info', "🚀 ETL Process Started - Log ID: {$log_id}, Concurrency: {$concurrency}");
            
            // Run Complete ETL (Summary + Detail integrated)
            log_message('info', "🚀 Running Complete Teacher ETL (Summary + Detail)...");
            $start_time = microtime(true);
            $etl_result = $this->tp_etl_summary_model->run_complete_teacher_etl();
            $total_duration = round(microtime(true) - $start_time, 2);
            
            if (!$etl_result['success']) {
                $error_message = $etl_result['error'] ?? 'ETL process failed';
                $this->tp_etl_logs_model->update_log_status($log_id, 'failed', $error_message, $total_duration);
                
                log_message('error', "❌ ETL Process Failed: {$error_message}");
                
                $this->_send_response(400, 'Bad Request', [
                    'log_id' => $log_id,
                    'error' => $error_message,
                    'duration_seconds' => $total_duration,
                    'concurrency' => $concurrency
                ]);
                return;
            }
            
            log_message('info', "✅ ETL Process Completed: {$etl_result['extracted']} summary records, {$etl_result['detail_extracted']} detail records in {$total_duration}s");
            
            // Update log as completed
            $message = "ETL completed successfully. Summary: {$etl_result['inserted']} inserted, {$etl_result['updated']} updated. Detail: {$etl_result['detail_extracted']} extracted, {$etl_result['detail_inserted']} inserted.";
            
            $this->tp_etl_logs_model->update_log_status($log_id, 'completed', $message, $total_duration);
            
            log_message('info', "🎉 ETL Process Completed - Total Duration: {$total_duration}s");
            
            // Return success response
            $response_data = [
                'success' => true,
                'message' => 'ETL completed successfully',
                'log_id' => $log_id,
                'summary' => [
                    'extracted' => $etl_result['extracted'],
                    'inserted' => $etl_result['inserted'],
                    'updated' => $etl_result['updated'],
                    'duration_seconds' => $total_duration
                ],
                'detail' => [
                    'extracted' => $etl_result['detail_extracted'],
                    'inserted' => $etl_result['detail_inserted'],
                    'duration_seconds' => $total_duration
                ],
                'total_duration_seconds' => $total_duration,
                'concurrency' => $concurrency,
                'date' => $etl_result['date']
            ];
            
            $this->_send_response(200, 'OK', $response_data, false);
            
        } catch (Exception $e) {
            // Update log as failed if log_id exists
            if (isset($log_id) && $log_id) {
                try {
                    $this->tp_etl_logs_model->update_log_status(
                        $log_id,
                        'failed',
                        'Exception: ' . $e->getMessage(),
                        isset($total_duration) ? $total_duration : 0
                    );
                } catch (Exception $update_e) {
                    log_message('error', "Failed to update log: " . $update_e->getMessage());
                }
            }
            
            log_message('error', "❌ ETL Exception: " . $e->getMessage());
            
            $this->_send_response(500, 'Internal Server Error', [
                'error' => $e->getMessage(),
                'log_id' => isset($log_id) ? $log_id : null
            ]);
        }
    }

    /**
     * Get ETL logs with pagination or specific log by ID
     * 
     * @method GET
     * @route /api/tp_etl/logs or /api/tp_etl/logs/{id}
     * @param int id - Optional log ID for specific log
     * @param int page - Page number (default: 1)
     * @param int per_page - Records per page (default: 20, max: 100)
     * @param string status - Filter by status (optional: running, completed, failed)
     * @param string process_type - Filter by process type (optional)
     * @param int concurrency - Filter by concurrency level (optional)
     */
     public function logs($id = null) {
         // Only allow GET method
         if ($this->input->method() !== 'get') {
             $this->_send_response(405, 'Method Not Allowed', 'Only GET method is allowed');
             return;
         }

         // If ID is provided, get specific log
         if ($id !== null) {
             try {
                 // Check if model is loaded
                 if (!isset($this->tp_etl_logs_model)) {
                     $this->_send_response(500, 'Internal Server Error', 'Required model not loaded');
                     return;
                 }

                 $id = intval($id);
                 
                 if ($id <= 0) {
                     $this->_send_response(400, 'Bad Request', 'Invalid log ID provided');
                     return;
                 }
                 
                 // Get specific log (using pagination with filters to get single record)
                 $result = $this->tp_etl_logs_model->get_etl_logs_with_pagination(
                     1,
                     1,
                     '',
                     ['id' => $id]
                 );
                 
                 if ($result['success'] && !empty($result['data'])) {
                     $this->_send_response(200, 'OK', $result['data'][0], false);
                 } else {
                     $this->_send_response(404, 'Not Found', 'ETL log not found');
                 }
                 
             } catch (Exception $e) {
                 log_message('error', "Error retrieving ETL log by ID {$id}: " . $e->getMessage());
                 $this->_send_response(500, 'Internal Server Error', 'An error occurred while retrieving ETL log');
             }
             return;
         }
         
         // If no ID provided, get logs with pagination
         try {
             // Check if model is loaded
             if (!isset($this->tp_etl_logs_model)) {
                 $this->_send_response(500, 'Internal Server Error', 'Required model not loaded');
                 return;
             }

             // Get pagination parameters
             $page = max(1, intval($this->input->get('page') ?: 1));
             $per_page = max(1, min(100, intval($this->input->get('per_page') ?: 20)));
             
             // Get filter parameters
             $filters = [];
             
             if ($this->input->get('status')) {
                 $allowed_statuses = ['running', 'completed', 'failed'];
                 if (in_array($this->input->get('status'), $allowed_statuses)) {
                     $filters['status'] = $this->input->get('status');
                 }
             }
             
             if ($this->input->get('process_type')) {
                 $filters['process_type'] = $this->input->get('process_type');
             }
             
             if ($this->input->get('concurrency')) {
                 $filters['concurrency'] = intval($this->input->get('concurrency'));
             }
             
             // Get logs with pagination
             $result = $this->tp_etl_logs_model->get_etl_logs_with_pagination(
                 $page,
                 $per_page,
                 '',
                 $filters
             );
             
             if ($result['success']) {
                 // Calculate pagination info
                 $total_records = $result['total'];
                 $total_pages = ceil($total_records / $per_page);
                 $has_next = $page < $total_pages;
                 $has_prev = $page > 1;
                 
                 $response_data = [
                     'data' => $result['data'],
                     'pagination' => [
                         'current_page' => $page,
                         'per_page' => $per_page,
                         'total_records' => $total_records,
                         'total_pages' => $total_pages,
                         'has_next_page' => $has_next,
                         'has_previous_page' => $has_prev,
                         'next_page' => $has_next ? $page + 1 : null,
                         'previous_page' => $has_prev ? $page - 1 : null
                     ],
                     'filters' => $filters
                 ];
                 
                 $this->_send_response(200, 'OK', $response_data, false);
                 
             } else {
                 $this->_send_response(500, 'Internal Server Error', $result['message'] ?? 'Failed to retrieve ETL logs');
             }
             
         } catch (Exception $e) {
             log_message('error', "Error retrieving ETL logs: " . $e->getMessage());
             $this->_send_response(500, 'Internal Server Error', 'An error occurred while retrieving ETL logs');
         }
     }

         /**
      * Export ETL data (summary or detail) with pagination
      * 
      * @method GET
      * @route /api/tp_etl/export
      * @param string table_name - Required: 'summary' or 'detail' to specify which table to export
      * @param int page - Page number (default: 1)
      * @param int per_page - Records per page (default: 100, max: 1000)
      * @param string order_by - Order by field (default: 'id')
      * @param string order_direction - Order direction: 'ASC' or 'DESC' (default: 'DESC')
      */
    public function export() {
        // Only allow GET method
        if ($this->input->method() !== 'get') {
            $this->_send_response(405, 'Method Not Allowed', 'Only GET method is allowed');
            return;
        }

        try {
            // Check if models are loaded
            if (!isset($this->tp_etl_summary_model)) {
                $this->_send_response(500, 'Internal Server Error', 'Required model not loaded');
                return;
            }

            // Get required table_name parameter
            $table_name = $this->input->get('table_name');
            
            if (!$table_name || !in_array($table_name, ['summary', 'detail'])) {
                $this->_send_response(400, 'Bad Request', 'Invalid or missing table_name parameter. Must be "summary" or "detail"');
                return;
            }
            
                         // Get pagination parameters
             $page = max(1, intval($this->input->get('page') ?: 1));
             $per_page = max(1, min(1000, intval($this->input->get('per_page') ?: 100)));
             
             // Get ordering parameters
             $order_by = $this->input->get('order_by') ?: 'id';
             $order_direction = strtoupper($this->input->get('order_direction') ?: 'DESC');
             
             if (!in_array($order_direction, ['ASC', 'DESC'])) {
                 $order_direction = 'DESC';
             }
             
             // Load appropriate model based on table_name
             if ($table_name === 'summary') {
                 $result = $this->tp_etl_summary_model->get_summary_data_with_pagination(
                     $page,
                     $per_page,
                     [], // No filters needed for export
                     $order_by,
                     $order_direction
                 );
             } else {
                 $this->load->model('tp_etl_detail_model');
                 $result = $this->tp_etl_detail_model->get_detail_data_with_pagination(
                     $page,
                     $per_page,
                     [], // No filters needed for export
                     $order_by,
                     $order_direction
                 );
             }
            
            if ($result['success']) {
                // Calculate pagination info
                $total_records = $result['total'];
                $total_pages = ceil($total_records / $per_page);
                $has_next = $page < $total_pages;
                $has_prev = $page > 1;
                
                                 $response_data = [
                     'success' => true,
                     'message' => ucfirst($table_name) . ' data exported successfully',
                     'table_name' => $table_name,
                     'pagination' => [
                         'current_page' => (int) $page,
                         'per_page' => (int) $per_page,
                         'total_records' => (int) $total_records,
                         'total_pages' => (int) $total_pages,
                         'has_next_page' => (bool) $has_next,
                         'has_previous_page' => (bool) $has_prev,
                         'next_page' => $has_next ? (int) ($page + 1) : null,
                         'previous_page' => $has_prev ? (int) ($page - 1) : null
                     ],
                     'export_info' => [
                         'records_exported' => (int) count($result['data']),
                         'total_available' => (int) $total_records,
                         'has_more_data' => (bool) $has_next,
                         'export_completed' => (bool) !$has_next,
                         'progress_percentage' => $total_records > 0 ? round(($page * $per_page / $total_records) * 100, 2) : 0.0
                     ],
                     'data' => $result['data']
                 ];
                
                $this->_send_response(200, 'OK', $response_data, false);
                
            } else {
                $this->_send_response(500, 'Internal Server Error', 'Failed to export ' . $table_name . ' ETL data: ' . ($result['message'] ?? 'Unknown error'));
            }
            
        } catch (Exception $e) {
            log_message('error', "Error exporting ETL data: " . $e->getMessage());
            $this->_send_response(500, 'Internal Server Error', 'An error occurred while exporting ETL data');
        }
    }

    /**
     * Get API documentation/help
     * 
     * @method GET
     * @route /api/tp_etl/help
     */
    public function help() {
        // Only allow GET method
        if ($this->input->method() !== 'get') {
            $this->_send_response(405, 'Method Not Allowed', 'Only GET method is allowed');
            return;
        }

        $response_data = [
            'endpoints' => [
                'POST /api/tp_etl/run' => [
                    'description' => 'Run complete teacher ETL process',
                    'parameters' => [
                        'concurrency' => 'int (optional, 1-10, default: 1) - Concurrency level'
                    ],
                    'example' => 'POST /api/tp_etl/run with body: {"concurrency": 2}'
                ],
                'GET /api/tp_etl/logs' => [
                    'description' => 'Get ETL logs with pagination',
                    'parameters' => [
                        'page' => 'int (optional, default: 1) - Page number',
                        'per_page' => 'int (optional, 1-100, default: 20) - Records per page',
                        'status' => 'string (optional) - Filter by status: running, completed, failed',
                        'process_type' => 'string (optional) - Filter by process type',
                        'concurrency' => 'int (optional) - Filter by concurrency level'
                    ],
                    'example' => 'GET /api/tp_etl/logs?page=1&per_page=10&status=completed'
                ],
                'GET /api/tp_etl/logs/{id}' => [
                    'description' => 'Get specific ETL log by ID',
                    'parameters' => [
                        'id' => 'int (required) - Log ID'
                    ],
                    'example' => 'GET /api/tp_etl/logs/123'
                ],
                'GET /api/tp_etl/export' => [
                    'description' => 'Export ETL data (summary or detail) with pagination',
                    'parameters' => [
                        'table_name' => 'string (required) - "summary" or "detail"',
                        'page' => 'int (optional, default: 1) - Page number',
                        'per_page' => 'int (optional, 1-1000, default: 100) - Records per page',
                        'order_by' => 'string (optional, default: "id") - Order by field',
                        'order_direction' => 'string (optional, default: "DESC") - "ASC" or "DESC"'
                    ],
                    'example' => 'GET /api/tp_etl/export?table_name=summary&page=1&per_page=100'
                ],
                'GET /api/tp_etl/help' => [
                    'description' => 'Get this API documentation',
                    'parameters' => [],
                    'example' => 'GET /api/tp_etl/help'
                ]
            ]
        ];
        
        $this->_send_response(200, 'OK', $response_data, false);
    }

    /**
     * Send JSON response
     * @param int $status_code HTTP status code
     * @param string $status_text Status text
     * @param mixed $data Response data
     * @param bool $wrap_response Whether to wrap response in standard format (default: true)
     */
    private function _send_response($status_code, $status_text, $data, $wrap_response = true) {
        // Ensure no output has been sent before
        if (headers_sent()) {
            log_message('error', 'Headers already sent in Tp_etl controller');
        }
        
        http_response_code($status_code);
        
        if ($wrap_response) {
            $response = [
                'status' => $status_code,
                'message' => $status_text,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            
            // If data is already an array with meta, export_info, and data keys, use it directly
            if (is_array($data) && isset($data['meta']) && isset($data['export_info']) && isset($data['data'])) {
                $response = array_merge($response, $data);
            } else {
                $response['data'] = $data;
            }
        } else {
            $response = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}
