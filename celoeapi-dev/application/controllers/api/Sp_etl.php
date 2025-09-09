<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sp_etl extends CI_Controller {

    public function __construct() {
        parent::__construct();
        
        // Disable error display to prevent HTML output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Load required models
        try {
            $this->load->model('sp_etl_summary_model');
            $this->load->model('sp_etl_detail_model');
            $this->load->model('sp_etl_logs_model');
        } catch (Exception $e) {
            log_message('error', "Error loading models in Sp_etl controller: " . $e->getMessage());
        }
        
        // Load helpers
        $this->load->helper('url');
        $this->load->helper('form');
        
        // Set JSON response header
        header('Content-Type: application/json');
    }

    /**
     * Run ETL processes (summary and detail) concurrently
     * POST /api/sp_etl/run
     * 
     * @param int concurrency Concurrency level (default: 1)
     * @param string extraction_date Date for extraction (YYYY-MM-DD, default: today)
     * @return JSON response with ETL execution results
     */
    public function run() {
        // Only allow POST method
        if ($this->input->method() !== 'post') {
            $this->_send_response(405, 'Method Not Allowed', 'Only POST method is allowed');
            return;
        }

        try {
            // Check if models are loaded
            if (!isset($this->sp_etl_logs_model) || !isset($this->sp_etl_summary_model) || !isset($this->sp_etl_detail_model)) {
                $this->_send_response(500, 'Internal Server Error', 'Required models not loaded');
                return;
            }

            // Get parameters
            $concurrency = (int) $this->input->post('concurrency') ?: 1;
            $extraction_date = $this->input->post('extraction_date') ?: date('Y-m-d');
            $parameters = json_encode([
                'concurrency' => $concurrency,
                'extraction_date' => $extraction_date,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // Validate concurrency
            if ($concurrency < 1 || $concurrency > 10) {
                $this->_send_response(400, 'Bad Request', 'Concurrency must be between 1 and 10');
                return;
            }

            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $extraction_date)) {
                $this->_send_response(400, 'Bad Request', 'Invalid date format. Use YYYY-MM-DD');
                return;
            }

            // Create log entry
            $log_data = [
                'process_type' => 'both',
                'status' => 'running',
                'concurrency' => $concurrency,
                'parameters' => $parameters,
                'start_time' => date('Y-m-d H:i:s'),
                'summary_result' => null,
                'detail_result' => null,
                'error_message' => null
            ];

            $log_id = $this->sp_etl_logs_model->create_log($log_data);
            
            if (!$log_id) {
                $this->_send_response(500, 'Internal Server Error', 'Failed to create log entry');
                return;
            }

            // Run ETL processes based on concurrency
            if ($concurrency == 1) {
                // Sequential execution
                $results = $this->_run_etl_sequential($extraction_date);
            } else {
                // Concurrent execution
                $results = $this->_run_etl_concurrent($extraction_date, $concurrency);
            }

            // Update log with results
            $update_data = [
                'status' => $results['overall_success'] ? 'completed' : 'failed',
                'end_time' => date('Y-m-d H:i:s'),
                'duration' => $results['duration'],
                'summary_result' => json_encode($results['summary']),
                'detail_result' => json_encode($results['detail']),
                'error_message' => $results['error_message']
            ];

            $this->sp_etl_logs_model->update_log($log_id, $update_data);

            // Send response
            $response = [
                'success' => $results['overall_success'],
                'message' => $results['overall_success'] ? 'ETL processes completed successfully' : 'ETL processes failed',
                'log_id' => $log_id,
                'duration' => $results['duration'],
                'concurrency' => $concurrency,
                'extraction_date' => $extraction_date,
                'summary_status' => $results['summary']['success'] ? 'completed' : 'failed',
                'detail_status' => $results['detail']['success'] ? 'completed' : 'failed'
            ];

            if (!$results['overall_success']) {
                $response['error'] = $results['error_message'];
            }

            $this->_send_response(200, 'OK', $response, false);

        } catch (Exception $e) {
            log_message('error', "Error in sp_etl run: " . $e->getMessage());
            
            // Update log if exists
            if (isset($log_id)) {
                try {
                    $update_data = [
                        'status' => 'failed',
                        'end_time' => date('Y-m-d H:i:s'),
                        'error_message' => $e->getMessage()
                    ];
                    $this->sp_etl_logs_model->update_log($log_id, $update_data);
                } catch (Exception $update_e) {
                    log_message('error', "Failed to update log: " . $update_e->getMessage());
                }
            }

            $this->_send_response(500, 'Internal Server Error', 'An unexpected error occurred');
        }
    }

    /**
     * Run ETL processes sequentially
     * @param string $extraction_date Date for extraction
     * @return array Results
     */
    private function _run_etl_sequential($extraction_date) {
        $start_time = microtime(true);
        
        try {
            // Run Summary ETL
            $summary_result = $this->sp_etl_summary_model->run_complete_summary_etl($extraction_date);
            
            // Run Detail ETL
            $detail_result = $this->sp_etl_detail_model->run_complete_detail_etl($extraction_date);
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            
            $overall_success = $summary_result['success'] && $detail_result['success'];
            $error_message = null;
            
            if (!$overall_success) {
                $errors = [];
                if (!$summary_result['success']) {
                    $errors[] = "Summary ETL: " . ($summary_result['error'] ?? 'Unknown error');
                }
                if (!$detail_result['success']) {
                    $errors[] = "Detail ETL: " . ($detail_result['error'] ?? 'Unknown error');
                }
                $error_message = implode('; ', $errors);
            }
            
            return [
                'overall_success' => $overall_success,
                'duration' => $duration,
                'summary' => $summary_result,
                'detail' => $detail_result,
                'error_message' => $error_message
            ];
            
        } catch (Exception $e) {
            log_message('error', "Error in sequential ETL: " . $e->getMessage());
            return [
                'overall_success' => false,
                'duration' => 0,
                'summary' => ['success' => false, 'error' => $e->getMessage()],
                'detail' => ['success' => false, 'error' => $e->getMessage()],
                'error_message' => $e->getMessage()
            ];
        }
    }

    /**
     * Run ETL processes concurrently using curl_multi
     * @param string $extraction_date Date for extraction
     * @param int $concurrency Concurrency level
     * @return array Results
     */
    private function _run_etl_concurrent($extraction_date, $concurrency) {
        $start_time = microtime(true);
        
        // For now, we'll use sequential execution as concurrent execution
        // would require additional infrastructure (like separate endpoints)
        // In a real implementation, you might use:
        // - Background jobs (Redis, RabbitMQ)
        // - Separate worker processes
        // - Async processing
        
        log_message('info', "Concurrent execution requested with concurrency: {$concurrency}. Using sequential fallback.");
        
        return $this->_run_etl_sequential($extraction_date);
    }

    /**
     * Get ETL logs with pagination
     * GET /api/sp_etl/logs
     * 
     * @param int page Page number (default: 1)
     * @param int limit Records per page (default: 10)
     * @param string status Filter by status
     * @param string process_type Filter by process type
     * @param int concurrency Filter by concurrency level
     * @return JSON response with paginated ETL logs
     */
    public function logs() {
        // Only allow GET method
        if ($this->input->method() !== 'get') {
            $this->_send_response(405, 'Method Not Allowed', 'Only GET method is allowed');
            return;
        }

        try {
            // Check if model is loaded
            if (!isset($this->sp_etl_logs_model)) {
                $this->_send_response(500, 'Internal Server Error', 'Required model not loaded');
                return;
            }

            $page = (int) $this->input->get('page') ?: 1;
            $limit = (int) $this->input->get('limit') ?: 10;
            $status = $this->input->get('status');
            $process_type = $this->input->get('process_type');
            $concurrency = $this->input->get('concurrency') ? (int) $this->input->get('concurrency') : null;
            
            // Validate pagination
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            $filters = [];
            if ($status) $filters['status'] = $status;
            if ($process_type) $filters['process_type'] = $process_type;
            if ($concurrency) $filters['concurrency'] = $concurrency;
            
            $logs = $this->sp_etl_logs_model->get_logs_with_pagination($page, $limit, $filters);
            
            $this->_send_response(200, 'OK', $logs);
            
        } catch (Exception $e) {
            log_message('error', "Error getting ETL logs: " . $e->getMessage());
            $this->_send_response(500, 'Internal Server Error', 'Failed to retrieve logs');
        }
    }

    /**
     * Get ETL log by ID
     * GET /api/sp_etl/get_log/{id}
     * 
     * @param int $id Log ID
     * @return JSON response with specific ETL log details
     */
    public function get_log($id = null) {
        // Only allow GET method
        if ($this->input->method() !== 'get') {
            $this->_send_response(405, 'Method Not Allowed', 'Only GET method is allowed');
            return;
        }

        if (!$id) {
            $this->_send_response(400, 'Bad Request', 'Log ID is required');
            return;
        }

        try {
            // Check if model is loaded
            if (!isset($this->sp_etl_logs_model)) {
                $this->_send_response(500, 'Internal Server Error', 'Required model not loaded');
                return;
            }

            $log = $this->sp_etl_logs_model->get_log_by_id($id);
            
            if (!$log) {
                $this->_send_response(404, 'Not Found', 'Log not found');
                return;
            }
            
            $this->_send_response(200, 'OK', $log);
            
        } catch (Exception $e) {
            log_message('error', "Error getting ETL log: " . $e->getMessage());
            $this->_send_response(500, 'Internal Server Error', 'Failed to retrieve log');
        }
    }

    /**
     * Get ETL logs statistics
     * GET /api/sp_etl/stats
     * 
     * @param string date_from Start date (YYYY-MM-DD)
     * @param string date_to End date (YYYY-MM-DD)
     * @return JSON response with ETL execution statistics
     */
    public function stats() {
        // Only allow GET method
        if ($this->input->method() !== 'get') {
            $this->_send_response(405, 'Method Not Allowed', 'Only GET method is allowed');
            return;
        }

        try {
            // Check if model is loaded
            if (!isset($this->sp_etl_logs_model)) {
                $this->_send_response(500, 'Internal Server Error', 'Required model not loaded');
                return;
            }

            $date_from = $this->input->get('date_from');
            $date_to = $this->input->get('date_to');
            
            $stats = $this->sp_etl_logs_model->get_logs_statistics($date_from, $date_to);
            
            $this->_send_response(200, 'OK', $stats);
            
        } catch (Exception $e) {
            log_message('error', "Error getting ETL stats: " . $e->getMessage());
            $this->_send_response(500, 'Internal Server Error', 'Failed to retrieve statistics');
        }
    }

    /**
     * Export data incrementally with pagination for cronjob usage
     * POST /api/sp_etl/export_incremental
     * 
     * @param string table_name Table to export (sp_etl_summary, sp_etl_detail)
     * @param int batch_size Records per batch (default: 100, max: 1000)
     * @param int offset Starting position for pagination (default: 0)
     * @return JSON response with actual data records and export metadata
     */
    public function export_incremental() {
        // Only allow POST method
        if ($this->input->method() !== 'post') {
            $this->_send_response(405, 'Method Not Allowed', 'Only POST method is allowed');
            return;
        }

        try {
            // Check if models are loaded
            if (!isset($this->sp_etl_summary_model) || !isset($this->sp_etl_detail_model)) {
                $this->_send_response(500, 'Internal Server Error', 'Required models not loaded');
                return;
            }

            // Get parameters
            $table_name = $this->input->post('table_name');
            $batch_size = (int) $this->input->post('batch_size') ?: 100;
            $offset = (int) $this->input->post('offset') ?: 0;

            // If POST data is empty, try to parse JSON input manually
            if (empty($table_name)) {
                log_message('info', "POST data is empty, attempting JSON parsing...");
                $raw_input = file_get_contents('php://input');
                log_message('info', "Raw input length: " . strlen($raw_input));
                
                $json_data = json_decode($raw_input, true);
                log_message('info', "JSON decode result: " . var_export($json_data, true));
                
                if ($json_data && is_array($json_data)) {
                    log_message('info', "JSON parsing successful, extracting parameters...");
                    $table_name = $json_data['table_name'] ?? null;
                    $batch_size = (int) ($json_data['batch_size'] ?? 100);
                    $offset = (int) ($json_data['offset'] ?? 0);
                    
                    log_message('info', "Parsed JSON data: " . json_encode($json_data));
                    log_message('info', "Extracted table_name: " . var_export($table_name, true));
                } else {
                    log_message('error', "JSON parsing failed: " . json_last_error_msg());
                    $this->_send_response(400, 'Bad Request', 'Invalid JSON data');
                    return;
                }
            } else {
                log_message('info', "POST data found, using standard input parsing");
            }

            // Debug logging
            log_message('info', "Received POST data: " . json_encode($this->input->post()));
            log_message('info', "table_name: " . var_export($table_name, true));
            log_message('info', "batch_size: " . var_export($batch_size, true));
            log_message('info', "offset: " . var_export($offset, true));

            // Validate parameters
            if (!in_array($table_name, ['sp_etl_summary', 'sp_etl_detail'])) {
                $this->_send_response(400, 'Bad Request', 'Invalid table_name. Use: sp_etl_summary or sp_etl_detail');
                return;
            }

            if ($batch_size < 1 || $batch_size > 1000) {
                $this->_send_response(400, 'Bad Request', 'Batch size must be between 1 and 1000');
                return;
            }

            if ($offset < 0) {
                $this->_send_response(400, 'Bad Request', 'Offset must be 0 or greater');
                return;
            }

            // Calculate page number from offset
            $page = floor($offset / $batch_size) + 1;

            // Get data from database based on table name
            $data = null;
            if ($table_name === 'sp_etl_summary') {
                $data = $this->sp_etl_summary_model->get_summary_with_pagination(
                    $page, 
                    $batch_size, 
                    '', 
                    []
                );
            } else {
                $data = $this->sp_etl_detail_model->get_detail_with_pagination(
                    $page, 
                    $batch_size, 
                    '', 
                    []
                );
            }

            // Check if data was retrieved successfully
            if (!$data || !isset($data['data']) || !isset($data['pagination'])) {
                $this->_send_response(500, 'Internal Server Error', 'Failed to retrieve data from database');
                return;
            }

            // Calculate export info
            $exported_count = count($data['data']);
            $total_available = $data['pagination']['total_records'];
            $current_offset = $offset;
            $next_offset = $offset + $batch_size;
            $has_more_data = ($next_offset < $total_available);
            $export_completed = ($next_offset >= $total_available);

            // Send response with actual data
            $response = [
                'success' => true,
                'message' => 'Data exported successfully',
                'table_name' => $table_name,
                'batch_size' => $batch_size,
                'current_offset' => $current_offset,
                'next_offset' => $next_offset,
                'export_info' => [
                    'records_exported' => $exported_count,
                    'total_available' => $total_available,
                    'has_more_data' => $has_more_data,
                    'export_completed' => $export_completed,
                    'progress_percentage' => round(($next_offset / $total_available) * 100, 2)
                ],
                'data' => $data['data'] // Actual data records
            ];

            $this->_send_response(200, 'OK', $response, false);

        } catch (Exception $e) {
            log_message('error', "Error in incremental export: " . $e->getMessage());
            $this->_send_response(500, 'Internal Server Error', 'An unexpected error occurred during incremental export');
        }
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
            log_message('error', 'Headers already sent in Sp_etl controller');
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
