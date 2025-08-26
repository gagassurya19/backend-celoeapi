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
     * Run SAS ETL process via API
     * POST /api/sas_users_login_hourly_etl/run
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

            // Create log entry for tracking
            $log_data = [
                'process_name' => 'sas_etl_complete',
                'status' => 'running',
                'message' => 'Starting SAS ETL process via API',
                'extraction_date' => $extraction_date,
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

                // Step 3: Get additional data for response
                $busiest_hours = $this->m_login_hourly->get_busiest_hours_analysis($extraction_date);
                $hourly_chart_data = $this->m_login_hourly->get_hourly_chart_data($extraction_date);
                $final_summary = $this->m_login_hourly->get_realtime_activity_summary($extraction_date);

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
                        'log_id' => $log_id,
                        'users_etl' => $users_result,
                        'hourly_etl' => $hourly_result,
                        'busiest_hours' => $busiest_hours,
                        'hourly_chart_data' => $hourly_chart_data,
                        'final_summary' => $final_summary,
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
                'error' => 'Exception occurred: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
     * Get specific log by ID
     * GET /api/sas_users_login_hourly_etl/logs/{id}
     */
    public function log($id = null) {
        try {
            if (!$id) {
                return $this->json_response([
                    'success' => false,
                    'error' => 'Log ID is required'
                ], 400);
            }
            
            $log = $this->db->get_where('sas_users_login_etl_logs', ['id' => $id])->row_array();
            
            if (!$log) {
                return $this->json_response([
                    'success' => false,
                    'error' => 'Log not found',
                    'id' => $id
                ], 404);
            }
            
            // Format timestamps
            $log['created_at_formatted'] = date('Y-m-d H:i:s', strtotime($log['created_at']));
            $log['updated_at_formatted'] = date('Y-m-d H:i:s', strtotime($log['updated_at']));
            
            if ($log['start_time']) {
                $log['start_time_formatted'] = date('Y-m-d H:i:s', strtotime($log['start_time']));
            }
            
            if ($log['end_time']) {
                $log['end_time_formatted'] = date('Y-m-d H:i:s', strtotime($log['end_time']));
            }
            
            return $this->json_response([
                'success' => true,
                'data' => $log
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
     * Get chart data for dashboard
     * GET /api/sas_users_login_hourly_etl/chart_data
     */
    public function chart_data() {
        try {
            $date = $this->input->get('date') ?: date('Y-m-d');
            
            // Get hourly chart data
            $hourly_data = $this->m_login_hourly->get_hourly_chart_data($date);
            
            // Get busiest hours analysis
            $busiest_hours = $this->m_login_hourly->get_busiest_hours_analysis($date);
            
            // Get real-time summary for current hour
            $current_summary = $this->m_login_hourly->get_realtime_activity_summary($date);
            
            return $this->json_response([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'hourly_chart_data' => $hourly_data,
                    'busiest_hours' => $busiest_hours,
                    'current_hour_summary' => $current_summary,
                    'timestamp' => date('Y-m-d H:i:s')
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
