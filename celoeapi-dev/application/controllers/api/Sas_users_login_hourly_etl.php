<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sas_users_login_hourly_etl extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('sas_users_etl_model', 'm_users');
        $this->load->model('sas_user_login_hourly_model', 'm_login_hourly');
        $this->load->database();
        
        // Set JSON response header
        header('Content-Type: application/json');
    }

    /**
     * Run complete SAS ETL process with concurrency control
     * POST /api/sas_users_login_hourly_etl/run
     * 
     * @param string extraction_date (optional) - Date in YYYY-MM-DD format, defaults to current date
     * @param int concurrency (optional) - Concurrency level (1-10), defaults to 1
     * 
     * Concurrency parameter controls:
     * - 1: Sequential processing (default, safest)
     * - 2-5: Moderate parallel processing
     * - 6-10: High parallel processing (use with caution)
     * 
     * Usage examples:
     * - Basic: POST /api/sas_users_login_hourly_etl/run
     * - With date: POST /api/sas_users_login_hourly_etl/run {"extraction_date": "2024-01-15"}
     * - With concurrency: POST /api/sas_users_login_hourly_etl/run {"concurrency": 5}
     * - Both: POST /api/sas_users_login_hourly_etl/run {"extraction_date": "2024-01-15", "concurrency": 3}
     * 
     * Future enhancements can use this parameter for:
     * - Batch size control in data processing
     * - Parallel database operations
     * - Resource allocation management
     * - Performance optimization
     */
    public function run() {
        try {
            // Check if it's a POST request
            if ($this->input->method() !== 'post') {
                return $this->json_response([
                    'success' => false,
                    'error' => 'Method not allowed. Use POST.',
                    'method' => $this->input->method()
                ], 405);
            }

            // Get extraction date from POST data or use current date
            $extraction_date = $this->input->post('extraction_date') ?: date('Y-m-d');
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $extraction_date)) {
                return $this->json_response([
                    'success' => false,
                    'error' => 'Invalid date format. Use YYYY-MM-DD.',
                    'date_provided' => $extraction_date
                ], 400);
            }

            // Get concurrency from POST data or use default
            $concurrency = (int) ($this->input->post('concurrency') ?: 1);
            
            // Validate concurrency (must be between 1 and 10)
            if ($concurrency < 1 || $concurrency > 10) {
                return $this->json_response([
                    'success' => false,
                    'error' => 'Invalid concurrency value. Must be between 1 and 10.',
                    'concurrency_provided' => $concurrency
                ], 400);
            }

            // Create log entry for tracking
            $log_data = [
                'process_name' => 'sas_etl_complete',
                'status' => 'running',
                'message' => 'Starting SAS ETL process via API',
                'extraction_date' => $extraction_date,
                'parameters' => json_encode(['concurrency' => $concurrency]),
                'start_time' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('sas_users_login_etl_logs', $log_data);
            $log_id = $this->db->insert_id();

            try {
                // Step 1: Run Users ETL
                $users_result = $this->m_users->run_complete_users_etl($extraction_date);
                
                if (!$users_result['success']) {
                    // Update log to failed status
                    $this->db->where('id', $log_id);
                    $this->db->update('sas_users_login_etl_logs', [
                        'status' => 'failed',
                        'message' => 'Users ETL failed: ' . $users_result['error'],
                        'end_time' => date('Y-m-d H:i:s'),
                        'duration_seconds' => time() - strtotime($log_data['start_time']),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    return $this->json_response([
                        'success' => false,
                        'error' => 'Users ETL failed: ' . $users_result['error'],
                        'log_id' => $log_id
                    ], 500);
                }

                // Step 2: Run Login Hourly ETL
                $hourly_result = $this->m_login_hourly->run_complete_user_login_hourly_etl($extraction_date);
                
                if (!$hourly_result['success']) {
                    // Update log to failed status
                    $this->db->where('id', $log_id);
                    $this->db->update('sas_users_login_etl_logs', [
                        'status' => 'failed',
                        'message' => 'Login Hourly ETL failed: ' . $hourly_result['error'],
                        'end_time' => date('Y-m-d H:i:s'),
                        'duration_seconds' => time() - strtotime($log_data['start_time']),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    return $this->json_response([
                        'success' => false,
                        'error' => 'Login Hourly ETL failed: ' . $hourly_result['error'],
                        'log_id' => $log_id
                    ], 500);
                }

                // Update log to completed status
                $this->db->where('id', $log_id);
                $this->db->update('sas_users_login_etl_logs', [
                    'status' => 'completed',
                    'message' => 'SAS ETL process completed successfully via API',
                    'end_time' => date('Y-m-d H:i:s'),
                    'duration_seconds' => time() - strtotime($log_data['start_time']),
                    'extracted_count' => $users_result['results']['users']['extracted_count'] + $hourly_result['extracted'],
                    'inserted_count' => $users_result['results']['users']['inserted_count'] + $hourly_result['inserted'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                return $this->json_response([
                    'success' => true,
                    'message' => 'SAS ETL process completed successfully',
                    'data' => [
                        'extraction_date' => $extraction_date,
                        'concurrency' => $concurrency,
                        'log_id' => $log_id,
                        'users_etl' => $users_result,
                        'hourly_etl' => $hourly_result,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                ], 200);

            } catch (Exception $e) {
                // Update log to failed status
                $this->db->where('id', $log_id);
                $this->db->update('sas_users_login_etl_logs', [
                    'status' => 'failed',
                    'message' => 'SAS ETL process failed: ' . $e->getMessage(),
                    'end_time' => date('Y-m-d H:i:s'),
                    'duration_seconds' => time() - strtotime($log_data['start_time']),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                return $this->json_response([
                    'success' => false,
                    'error' => 'SAS ETL process failed: ' . $e->getMessage(),
                    'log_id' => $log_id
                ], 500);
            }
            
        } catch (Exception $e) {
            return $this->json_response([
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ETL logs from sas_users_login_etl_logs table
     * GET /api/sas_users_login_hourly_etl/logs
     */
    public function logs() {
        try {
            // Get query parameters
            $process_name = $this->input->get('process_name');
            $status = $this->input->get('status');
            $date_from = $this->input->get('date_from');
            $date_to = $this->input->get('date_to');
            $limit = (int) ($this->input->get('limit') ?: 50);
            $offset = (int) ($this->input->get('offset') ?: 0);
            
            // Validate limit
            if ($limit > 100) {
                $limit = 100;
            }
            
            // Build query
            $this->db->select('*');
            $this->db->from('sas_users_login_etl_logs');
            
            // Apply filters
            if ($process_name) {
                $this->db->where('process_name', $process_name);
            }
            
            if ($status) {
                $this->db->where('status', $status);
            }
            
            if ($date_from) {
                $this->db->where('extraction_date >=', $date_from);
            }
            
            if ($date_to) {
                $this->db->where('extraction_date <=', $date_to);
            }
            
            // Get total count for pagination
            $total_count = $this->db->count_all_results('', false);
            
            // Apply pagination
            $this->db->limit($limit, $offset);
            $this->db->order_by('created_at', 'DESC');
            
            $logs = $this->db->get()->result_array();
            
            // Process logs data
            foreach ($logs as &$log) {
                // Format timestamps
                $log['created_at_formatted'] = date('Y-m-d H:i:s', strtotime($log['created_at']));
                $log['updated_at_formatted'] = date('Y-m-d H:i:s', strtotime($log['updated_at']));
                
                if ($log['start_time']) {
                    $log['start_time_formatted'] = date('Y-m-d H:i:s', strtotime($log['start_time']));
                }
                
                if ($log['end_time']) {
                    $log['end_time_formatted'] = date('Y-m-d H:i:s', strtotime($log['end_time']));
                }
                
                // Calculate duration if available
                if ($log['start_time'] && $log['end_time']) {
                    $start = strtotime($log['start_time']);
                    $end = strtotime($log['end_time']);
                    $log['calculated_duration'] = $end - $start;
                }
            }
            
            return $this->json_response([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'pagination' => [
                        'total' => $total_count,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $total_count
                    ],
                    'filters' => [
                        'process_name' => $process_name,
                        'status' => $status,
                        'date_from' => $date_from,
                        'date_to' => $date_to
                    ]
                ]
            ], 200);
            
        } catch (Exception $e) {
            return $this->json_response([
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }

   
    /**
     * Get ETL status summary
     * GET /api/sas_users_login_hourly_etl/status
     */
    public function status() {
        try {
            // Get latest log for each process
            $latest_logs = $this->db->query("
                SELECT 
                    process_name,
                    status,
                    message,
                    extraction_date,
                    start_time,
                    end_time,
                    duration_seconds,
                    extracted_count,
                    inserted_count,
                    created_at
                FROM sas_users_login_etl_logs l1
                WHERE created_at = (
                    SELECT MAX(created_at) 
                    FROM sas_users_login_etl_logs l2 
                    WHERE l2.process_name = l1.process_name
                )
                ORDER BY created_at DESC
            ")->result_array();
            
            // Get counts by status
            $status_counts = $this->db->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM sas_users_login_etl_logs
                GROUP BY status
            ")->result_array();
            
            // Get today's summary
            $today = date('Y-m-d');
            $today_summary = $this->db->query("
                SELECT 
                    COUNT(*) as total_runs,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    COUNT(CASE WHEN status = 'running' THEN 1 END) as running
                FROM sas_users_login_etl_logs
                WHERE DATE(created_at) = ?
            ", [$today])->row_array();
            
            return $this->json_response([
                'success' => true,
                'data' => [
                    'latest_logs' => $latest_logs,
                    'status_counts' => $status_counts,
                    'today_summary' => $today_summary,
                    'current_time' => date('Y-m-d H:i:s')
                ]
            ], 200);
            
        } catch (Exception $e) {
            return $this->json_response([
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get login hourly data with pagination and relations
     * GET /api/sas_users_login_hourly_etl/login_hourly
     */
    public function login_hourly() {
        try {
            // Get query parameters
            $page = (int) ($this->input->get('page') ?: 1);
            $limit = (int) ($this->input->get('limit') ?: 10);
            $search = $this->input->get('search') ?: '';
            $extraction_date = $this->input->get('extraction_date');
            $hour = $this->input->get('hour');
            $role_type = $this->input->get('role_type');
            $is_active = $this->input->get('is_active');
            
            // Validate parameters
            if ($page < 1) $page = 1;
            if ($limit < 1) $limit = 10;
            
            // Build filters - only include filters that have actual values
            $filters = [];
            
            if ($extraction_date !== null && $extraction_date !== '') {
                $filters['extraction_date'] = $extraction_date;
            }
            
            if ($hour !== null && $hour !== '') {
                $filters['hour'] = $hour;
            }
            
            if ($role_type !== null && $role_type !== '') {
                $filters['role_type'] = $role_type;
            }
            
            if ($is_active !== null && $is_active !== '') {
                $filters['is_active'] = $is_active;
            }
            
            // Get data from model
            $result = $this->m_login_hourly->get_login_hourly_with_relations($page, $limit, $search, $filters);
            
            return $this->json_response([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
                'filters' => [
                    'search' => $search,
                    'extraction_date' => $extraction_date,
                    'hour' => $hour,
                    'role_type' => $role_type,
                    'is_active' => $is_active
                ]
            ], 200);
            
        } catch (Exception $e) {
            return $this->json_response([
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to send JSON response
     */
    private function json_response($data, $status_code = 200) {
        http_response_code($status_code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
