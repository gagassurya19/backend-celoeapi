<?php
    use Restserver\Libraries\REST_Controller;
    defined('BASEPATH') OR exit('No direct script access allowed');

    require APPPATH . 'libraries/REST_Controller.php';
    require APPPATH . 'libraries/Format.php';

    class ETL_Course_Performance extends REST_Controller {

        function __construct()
        {
            parent::__construct();
            $this->load->database();
            $this->load->model('ETL_course_performance_model', 'm_ETL');
            $this->load->helper('auth');
            $this->load->config('etl');
        }

        // POST /api/etl/run - Manually trigger ETL process
        public function run_post() 
        {      
            try {
                // Basic authentication check (you can enhance this based on your webhook auth needs)
                $auth_header = $this->input->get_request_header('Authorization', TRUE);
                if (!$this->_validate_webhook_token($auth_header)) {
                    $this->response([
                        'status' => false,
                        'message' => 'Unauthorized'
                    ], REST_Controller::HTTP_UNAUTHORIZED);
                    return;
                }

                log_message('info', 'Manual ETL trigger requested');
                
                // Check if ETL is already running
                $etl_status = $this->m_ETL->get_etl_status();
                if ($etl_status['isRunning']) {
                    $this->response([
                        'status' => false,
                        'message' => 'ETL process is already running'
                    ], REST_Controller::HTTP_CONFLICT);
                    return;
                }
                
                // Run ETL process in background
                $this->_run_etl_background();
                
                $this->response([
                    'status' => true,
                    'message' => 'ETL process started successfully in background',
                    'info' => 'Use /api/etl/status to check progress'
                ], REST_Controller::HTTP_ACCEPTED);
                
            } catch (Exception $e) {
                log_message('error', 'Manual ETL trigger failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'ETL process failed to start',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // GET /api/etl/status - Get ETL status
        public function status_get() 
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

                $status = $this->m_ETL->get_etl_status();
                
                $this->response([
                    'status' => true,
                    'data' => $status
                ], REST_Controller::HTTP_OK);
                
            } catch (Exception $e) {
                log_message('error', 'Get ETL status failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'Failed to get ETL status',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // GET /api/etl/logs - Get ETL logs history
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
                $limit = $this->get('limit') ? (int)$this->get('limit') : 20;
                $offset = $this->get('offset') ? (int)$this->get('offset') : 0;
                
                $logs = $this->m_ETL->get_etl_logs($limit, $offset);
                
                $this->response([
                    'status' => true,
                    'data' => $logs
                ], REST_Controller::HTTP_OK);
                
            } catch (Exception $e) {
                log_message('error', 'Get ETL logs failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'Failed to get ETL logs',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // POST /api/etl/run-incremental - Run incremental ETL in background
        public function run_incremental_post() 
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

                log_message('info', 'Incremental ETL trigger requested');
                
                // Check if ETL is already running
                $etl_status = $this->m_ETL->get_etl_status();
                if ($etl_status['isRunning']) {
                    $this->response([
                        'status' => false,
                        'message' => 'ETL process is already running'
                    ], REST_Controller::HTTP_CONFLICT);
                    return;
                }
                
                // Run incremental ETL process in background
                $this->_run_incremental_etl_background();
                
                $this->response([
                    'status' => true,
                    'message' => 'Incremental ETL process started successfully in background',
                    'info' => 'Use /api/etl/status to check progress'
                ], REST_Controller::HTTP_ACCEPTED);
                
            } catch (Exception $e) {
                log_message('error', 'Incremental ETL trigger failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'Incremental ETL process failed to start',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // POST /api/etl/clear-stuck - Clear stuck ETL processes
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

                log_message('info', 'Clear stuck ETL processes requested');
                
                // Clear stuck ETL processes
                $result = $this->_clear_stuck_etl_processes();
                
                $this->response([
                    'status' => true,
                    'message' => 'Stuck ETL processes cleared successfully',
                    'result' => $result
                ], REST_Controller::HTTP_OK);
                
            } catch (Exception $e) {
                log_message('error', 'Clear stuck ETL processes failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'Failed to clear stuck ETL processes',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // POST /api/etl/force-clear - Force clear all inprogress ETL processes
        public function force_clear_post() 
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

                log_message('info', 'Force clear all inprogress ETL processes requested');
                
                // Force clear all inprogress ETL processes
                $result = $this->_force_clear_all_inprogress_etl();
                
                $this->response([
                    'status' => true,
                    'message' => 'All inprogress ETL processes cleared successfully',
                    'result' => $result
                ], REST_Controller::HTTP_OK);
                
            } catch (Exception $e) {
                log_message('error', 'Force clear all inprogress ETL processes failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'Failed to force clear all inprogress ETL processes',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // GET /api/etl/debug - Debug ETL status (show raw database data)
        public function debug_get() 
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

                // Get all ETL processes from database
                $all_processes = $this->db->query("SELECT * FROM celoeapi.log_scheduler ORDER BY id DESC LIMIT 10")->result();
                
                // Get running processes
                $running_processes = $this->db->query("SELECT * FROM celoeapi.log_scheduler WHERE status = 2")->result();
                
                $this->response([
                    'status' => true,
                    'debug_data' => [
                        'all_processes' => $all_processes,
                        'running_processes' => $running_processes,
                        'running_count' => count($running_processes)
                    ]
                ], REST_Controller::HTTP_OK);
                
            } catch (Exception $e) {
                log_message('error', 'Debug ETL status failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'Failed to get debug data',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Simple webhook token validation (you can enhance this)
        private function _validate_webhook_token($auth_header) 
        {
            // Extract token from Authorization header (Bearer token)
            if (strpos($auth_header, 'Bearer ') === 0) {
                $token = substr($auth_header, 7);
                // Load valid tokens from config
                $valid_tokens = $this->config->item('etl_webhook_tokens');
                return in_array($token, $valid_tokens);
            }
            return false;
        }

        // Run ETL process in background
        private function _run_etl_background()
        {
            try {
                // Get the path to the ETL runner script
                $script_path = APPPATH . '../run_etl.sh';
                
                // Check if script exists
                if (!file_exists($script_path)) {
                    throw new Exception('ETL runner script not found: ' . $script_path);
                }
                
                // Make sure script is executable
                if (!is_executable($script_path)) {
                    chmod($script_path, 0755);
                }
                
                // Check if we're on Windows or Unix-like system
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    // Windows - use PHP directly
                    $php_path = 'php';
                    $index_path = APPPATH . '../index.php';
                    $full_command = 'start /B ' . $php_path . ' ' . $index_path . ' cli run_etl > nul 2>&1';
                } else {
                    // Unix-like (Linux, macOS) - use shell script
                    $full_command = $script_path . ' > /dev/null 2>&1 &';
                }
                
                log_message('info', 'Starting ETL background process: ' . $full_command);
                
                // Execute the command in background
                exec($full_command, $output, $return_var);
                
                // Note: $return_var will be 0 for successful start, but doesn't guarantee ETL success
                if ($return_var !== 0) {
                    log_message('error', 'ETL background process failed with return code: ' . $return_var);
                    throw new Exception('Failed to start background ETL process');
                }
                
                log_message('info', 'ETL background process started successfully');
                
            } catch (Exception $e) {
                log_message('error', 'Failed to start ETL background process: ' . $e->getMessage());
                throw $e;
            }
        }

        // Run incremental ETL process in background
        private function _run_incremental_etl_background()
        {
            try {
                // Get the path to the ETL runner script
                $script_path = APPPATH . '../run_etl.sh';
                
                // Check if script exists
                if (!file_exists($script_path)) {
                    throw new Exception('ETL runner script not found: ' . $script_path);
                }
                
                // Make sure script is executable
                if (!is_executable($script_path)) {
                    chmod($script_path, 0755);
                }
                
                // Check if we're on Windows or Unix-like system
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    // Windows - use PHP directly
                    $php_path = 'php';
                    $index_path = APPPATH . '../index.php';
                    $full_command = 'start /B ' . $php_path . ' ' . $index_path . ' cli run_incremental_etl > nul 2>&1';
                } else {
                    // Unix-like (Linux, macOS) - use PHP directly for incremental
                    $php_path = 'php';
                    $index_path = APPPATH . '../index.php';
                    $full_command = $php_path . ' ' . $index_path . ' cli run_incremental_etl > /dev/null 2>&1 &';
                }
                
                log_message('info', 'Starting incremental ETL background process: ' . $full_command);
                
                // Execute the command in background
                exec($full_command, $output, $return_var);
                
                // Note: $return_var will be 0 for successful start, but doesn't guarantee ETL success
                if ($return_var !== 0) {
                    log_message('error', 'Incremental ETL background process failed with return code: ' . $return_var);
                    throw new Exception('Failed to start background incremental ETL process');
                }
                
                log_message('info', 'Incremental ETL background process started successfully');
                
            } catch (Exception $e) {
                log_message('error', 'Failed to start incremental ETL background process: ' . $e->getMessage());
                throw $e;
            }
        }

        // Clear stuck ETL processes
        private function _clear_stuck_etl_processes()
        {
            try {
                // Find stuck ETL processes (running for more than 2 hours)
                $stuck_query = "
                    SELECT id, start_date, 
                           TIMESTAMPDIFF(MINUTE, start_date, NOW()) as minutes_running 
                    FROM celoeapi.log_scheduler 
                    WHERE status = 2 
                    AND start_date < DATE_SUB(NOW(), INTERVAL 2 HOUR)
                ";
                
                $stuck_processes = $this->db->query($stuck_query)->result();
                
                if (empty($stuck_processes)) {
                    return [
                        'action' => 'no_stuck_processes',
                        'message' => 'No stuck ETL processes found'
                    ];
                }
                
                $cleared_count = 0;
                foreach ($stuck_processes as $process) {
                    // Mark as failed
                    $this->db->query(
                        "UPDATE celoeapi.log_scheduler SET status = 3, end_date = NOW() WHERE id = ?",
                        array($process->id)
                    );
                    
                    log_message('info', "Cleared stuck ETL process ID: {$process->id}, was running for {$process->minutes_running} minutes");
                    $cleared_count++;
                }
                
                // Also clear any processes that have been running for more than 10 minutes without proper start
                $hanging_query = "
                    SELECT id FROM celoeapi.log_scheduler 
                    WHERE status = 2 
                    AND (end_date IS NULL OR end_date = '0000-00-00 00:00:00')
                    AND start_date < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                ";
                
                $hanging_processes = $this->db->query($hanging_query)->result();
                
                foreach ($hanging_processes as $process) {
                    $this->db->query(
                        "UPDATE celoeapi.log_scheduler SET status = 3, end_date = NOW() WHERE id = ?",
                        array($process->id)
                    );
                    
                    log_message('info', "Cleared hanging ETL process ID: {$process->id}");
                    $cleared_count++;
                }
                
                return [
                    'action' => 'cleared_stuck_processes',
                    'cleared_count' => $cleared_count,
                    'message' => "Cleared {$cleared_count} stuck ETL processes"
                ];
                
            } catch (Exception $e) {
                log_message('error', 'Failed to clear stuck ETL processes: ' . $e->getMessage());
                throw $e;
            }
        }

        // Force clear all inprogress ETL processes
        private function _force_clear_all_inprogress_etl()
        {
            try {
                // Get all inprogress ETL processes
                $inprogress_query = "SELECT id, start_date FROM celoeapi.log_scheduler WHERE status = 2";
                $inprogress_processes = $this->db->query($inprogress_query)->result();
                
                if (empty($inprogress_processes)) {
                    return [
                        'action' => 'no_inprogress_processes',
                        'message' => 'No inprogress ETL processes found'
                    ];
                }
                
                $cleared_count = 0;
                
                // Start transaction
                $this->db->trans_start();
                
                foreach ($inprogress_processes as $process) {
                    // Mark as failed (status 3)
                    $this->db->query(
                        "UPDATE celoeapi.log_scheduler SET status = 3, end_date = NOW() WHERE id = ?",
                        array($process->id)
                    );
                    
                    log_message('info', "Force cleared inprogress ETL process ID: {$process->id}");
                    $cleared_count++;
                }
                
                // Commit transaction
                $this->db->trans_complete();
                
                if ($this->db->trans_status() === FALSE) {
                    throw new Exception('Transaction failed while clearing inprogress ETL processes');
                }
                
                return [
                    'action' => 'force_cleared_all_inprogress',
                    'cleared_count' => $cleared_count,
                    'message' => "Force cleared {$cleared_count} inprogress ETL processes"
                ];
                
            } catch (Exception $e) {
                log_message('error', 'Failed to force clear all inprogress ETL processes: ' . $e->getMessage());
                throw $e;
            }
        }
    }