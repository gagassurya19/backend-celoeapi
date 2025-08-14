<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

class ETL_Chart extends REST_Controller {

    /**
     * @var CI_Input
     */
    public $input;
    
    /**
     * @var CI_Config
     */
    public $config;
    
    /**
     * @var ETL_Chart_Model
     */
    public $m_ETL_Chart;

    function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('ETL_Chart_Model', 'm_ETL_Chart');
        $this->load->helper('auth');
        $this->load->config('etl_chart');
    }

    // GET /api/etl/chart/logs - Get ETL Chart logs
    public function logs_get() 
    {      
        try {
            // Basic authentication check
            $auth_header = $this->input->get_request_header('Authorization', TRUE);
            if (!$this->_validate_webhook_token($auth_header)) {
                $this->response([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], REST_Controller::HTTP_UNAUTHORIZED);
                return;
            }

            // Get query parameters
            $limit = $this->get('limit') ? (int)$this->get('limit') : 5;
            $offset = $this->get('offset') ? (int)$this->get('offset') : 0;
            
            $logs = $this->m_ETL_Chart->get_etl_logs($limit, $offset);
            
            $this->response([
                'status' => true,
                'data' => $logs
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Get ETL Chart logs failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to get ETL Chart logs',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/etl/chart/fetch - Start ETL Chart process in background
    public function fetch_get() 
    {      
        try {
            // Basic authentication check
            $auth_header = $this->input->get_request_header('Authorization', TRUE);
            if (!$this->_validate_webhook_token($auth_header)) {
                $this->response([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], REST_Controller::HTTP_UNAUTHORIZED);
                return;
            }

            log_message('info', 'ETL Chart fetch requested');
            
            // Check if ETL Chart is already running
            if ($this->m_ETL_Chart->is_etl_running()) {
                $this->response([
                    'status' => false,
                    'message' => 'ETL Chart process is already running'
                ], REST_Controller::HTTP_CONFLICT);
                return;
            }
            
            // Run ETL Chart process in background
            $this->_run_etl_chart_background();
            
            $this->response([
                'status' => true,
                'message' => 'ETL process started successfully in background',
                'info' => 'Use /api/etl/chart/logs to check progress'
            ], REST_Controller::HTTP_ACCEPTED);
            
        } catch (Exception $e) {
            log_message('error', 'ETL Chart fetch failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'ETL process failed to start',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/etl/chart/realtime-logs - Get real-time ETL Chart logs
    public function realtime_logs_get() 
    {      
        try {
            // Basic authentication check
            $auth_header = $this->input->get_request_header('Authorization', TRUE);
            if (!$this->_validate_webhook_token($auth_header)) {
                $this->response([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], REST_Controller::HTTP_UNAUTHORIZED);
                return;
            }

            // Get query parameters
            $log_id = $this->get('log_id') ? (int)$this->get('log_id') : null;
            $level = $this->get('level') ? $this->get('level') : null;
            $since = $this->get('since') ? $this->get('since') : null; // timestamp
            $limit = $this->get('limit') ? (int)$this->get('limit') : 50;
            $offset = $this->get('offset') ? (int)$this->get('offset') : 0;
            
            $logs = $this->m_ETL_Chart->get_realtime_logs($log_id, $level, $since, $limit, $offset);
            
            $this->response([
                'status' => true,
                'data' => $logs,
                'count' => count($logs),
                'timestamp' => date('Y-m-d H:i:s')
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Get real-time ETL Chart logs failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to get real-time ETL Chart logs',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/etl/chart/stream - Server-sent events stream for real-time logs
    public function stream_get() 
    {      
        try {
            // Basic authentication check
            $auth_header = $this->input->get_request_header('Authorization', TRUE);
            if (!$this->_validate_webhook_token($auth_header)) {
                // For SSE, we need to send proper headers first
                header('HTTP/1.1 401 Unauthorized');
                header('Content-Type: text/plain');
                echo 'Unauthorized';
                exit;
            }

            // Set headers for Server-Sent Events
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Get query parameters
            $log_id = $this->get('log_id') ? (int)$this->get('log_id') : null;
            $level = $this->get('level') ? $this->get('level') : null;
            
            $last_timestamp = null;
            $retry_count = 0;
            $max_retries = 300; // 5 minutes with 1-second intervals
            
            // Send initial connection message
            echo "data: " . json_encode([
                'type' => 'connected',
                'message' => 'Connected to ETL Chart real-time logs',
                'timestamp' => date('Y-m-d H:i:s')
            ]) . "\n\n";
            flush();
            
            while ($retry_count < $max_retries) {
                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }
                
                try {
                    // Get new logs since last timestamp
                    $logs = $this->m_ETL_Chart->get_realtime_logs($log_id, $level, $last_timestamp, 10, 0);
                    
                    if (!empty($logs)) {
                        foreach ($logs as $log) {
                            // Send log data
                            echo "data: " . json_encode([
                                'type' => 'log',
                                'id' => $log['id'],
                                'log_id' => $log['log_id'],
                                'timestamp' => $log['timestamp'],
                                'level' => $log['level'],
                                'message' => $log['message'],
                                'progress' => $log['progress']
                            ]) . "\n\n";
                            
                            $last_timestamp = $log['timestamp'];
                        }
                        flush();
                        $retry_count = 0; // Reset retry count when we get data
                    } else {
                        $retry_count++;
                    }
                    
                    // Send heartbeat every 30 seconds
                    if ($retry_count % 30 == 0) {
                        echo "data: " . json_encode([
                            'type' => 'heartbeat',
                            'timestamp' => date('Y-m-d H:i:s')
                        ]) . "\n\n";
                        flush();
                    }
                    
                } catch (Exception $e) {
                    // Send error message
                    echo "data: " . json_encode([
                        'type' => 'error',
                        'message' => $e->getMessage(),
                        'timestamp' => date('Y-m-d H:i:s')
                    ]) . "\n\n";
                    flush();
                    break;
                }
                
                sleep(1); // Wait 1 second before next check
            }
            
            // Send disconnect message
            echo "data: " . json_encode([
                'type' => 'disconnected',
                'message' => 'Stream ended',
                'timestamp' => date('Y-m-d H:i:s')
            ]) . "\n\n";
            flush();
            
        } catch (Exception $e) {
            log_message('error', 'ETL Chart stream failed: ' . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain');
            echo 'Stream failed: ' . $e->getMessage();
        }
        exit; // Important: exit to prevent CodeIgniter from adding extra output
    }

    // POST /api/etl/chart/log - Add real-time log entry (for internal use)
    public function log_post() 
    {      
        try {
            // Basic authentication check
            $auth_header = $this->input->get_request_header('Authorization', TRUE);
            if (!$this->_validate_webhook_token($auth_header)) {
                $this->response([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], REST_Controller::HTTP_UNAUTHORIZED);
                return;
            }

            // Get POST data
            $log_id = $this->post('log_id');
            $level = $this->post('level') ?: 'info';
            $message = $this->post('message');
            $progress = $this->post('progress');
            
            // Validate required fields
            if (!$log_id || !$message) {
                $this->response([
                    'status' => false,
                    'message' => 'Missing required fields: log_id and message'
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }
            
            // Validate level
            $valid_levels = ['info', 'warning', 'error', 'debug'];
            if (!in_array($level, $valid_levels)) {
                $this->response([
                    'status' => false,
                    'message' => 'Invalid level. Must be one of: ' . implode(', ', $valid_levels)
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }
            
            $log_entry_id = $this->m_ETL_Chart->add_realtime_log($log_id, $level, $message, $progress);
            
            $this->response([
                'status' => true,
                'message' => 'Real-time log added successfully',
                'id' => $log_entry_id
            ], REST_Controller::HTTP_CREATED);
            
        } catch (Exception $e) {
            log_message('error', 'Add real-time ETL Chart log failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to add real-time log',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /api/etl/chart/clear-stuck - Clear stuck ETL Chart processes
    public function clear_stuck_post() 
    {      
        try {
            // Basic authentication check
            $auth_header = $this->input->get_request_header('Authorization', TRUE);
            if (!$this->_validate_webhook_token($auth_header)) {
                $this->response([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], REST_Controller::HTTP_UNAUTHORIZED);
                return;
            }

            log_message('info', 'Clear stuck ETL Chart processes requested');
            
            // Clear stuck ETL Chart processes
            $result = $this->m_ETL_Chart->clear_stuck_etl_processes();
            
            $this->response([
                'status' => true,
                'message' => 'Stuck ETL Chart processes cleared successfully',
                'result' => $result
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Clear stuck ETL Chart processes failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to clear stuck ETL Chart processes',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Simple webhook token validation
    private function _validate_webhook_token($auth_header) 
    {
        // Extract token from Authorization header (Bearer token)
        if (strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            // Load valid tokens from config
            $valid_tokens = $this->config->item('etl_chart_webhook_tokens');
            if (!is_array($valid_tokens)) {
                return false;
            }
            return in_array($token, $valid_tokens);
        }
        return false;
    }

    // Run ETL Chart process in background
    private function _run_etl_chart_background()
    {
        try {
            // Check if we're on Windows or Unix-like system
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows - use PHP directly
                $php_path = 'php';
                $index_path = APPPATH . '../index.php';
                $full_command = 'start /B ' . $php_path . ' ' . $index_path . ' cli run_etl_chart > nul 2>&1';
            } else {
                // Unix-like (Linux, macOS) - use PHP directly
                $php_path = 'php';
                $index_path = APPPATH . '../index.php';
                $full_command = $php_path . ' ' . $index_path . ' cli run_etl_chart > /dev/null 2>&1 &';
            }
            
            log_message('info', 'Starting ETL Chart background process: ' . $full_command);
            
            // Execute the command in background
            exec($full_command, $output, $return_var);
            
            // Note: $return_var will be 0 for successful start, but doesn't guarantee ETL success
            if ($return_var !== 0) {
                log_message('error', 'ETL Chart background process failed with return code: ' . $return_var);
                throw new Exception('Failed to start background ETL Chart process');
            }
            
            log_message('info', 'ETL Chart background process started successfully');
            
        } catch (Exception $e) {
            log_message('error', 'Failed to start ETL Chart background process: ' . $e->getMessage());
            throw $e;
        }
    }
}