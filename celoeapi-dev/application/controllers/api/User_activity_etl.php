<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

class User_activity_etl extends REST_Controller {

    function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('User_activity_etl_model', 'm_user_activity');
        $this->load->model('Activity_counts_model', 'm_activity_counts');
        $this->load->model('User_counts_model', 'm_user_counts');
        $this->load->helper('auth');
        $this->load->config('etl');
    }

    // GET /api/user_activity_etl/status - Get ETL status
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

            $status = $this->m_user_activity->get_etl_status();
            
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

    // POST /api/user_activity_etl/run_pipeline - Run ETL pipeline with correct flow
    public function run_pipeline_post() 
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

            log_message('info', 'ETL pipeline triggered with correct flow');
            
            // Get date from request parameter (JSON or POST), default to yesterday
            $json_data = json_decode($this->input->raw_input_stream, true);
            $request_date = null;
            
            // Try to get date from JSON first
            if ($json_data && isset($json_data['date'])) {
                $request_date = $json_data['date'];
            }
            // Fallback to POST data
            else if ($this->input->post('date')) {
                $request_date = $this->input->post('date');
            }
            // Default to yesterday
            else {
                $request_date = date('Y-m-d', strtotime('-1 day'));
            }
            
            // Log the request data for debugging
            log_message('info', 'JSON data: ' . json_encode($json_data));
            log_message('info', 'Request date: ' . $request_date);
            
            $current_date = date('Y-m-d', strtotime('+1 day'));
            
            // Update log_scheduler status to inprogress (2) when pipeline starts
            $this->m_user_activity->update_scheduler_status_inprogress($request_date);
            
            $results = [];
            
            // Step 1: Run Activity Counts ETL (Model View 1) with dynamic pagination
            log_message('info', 'Starting Activity Counts ETL with date range: ' . $request_date . ' to ' . $current_date);
            $activity_total = $this->m_activity_counts->get_activity_counts_total_by_date_range($request_date, $current_date);
            $activity_all_data = [];
            $activity_limit = 1000;
            $activity_offset = 0;
            
            // Process all data with pagination
            while ($activity_offset < $activity_total) {
                $activity_batch = $this->m_activity_counts->get_activity_counts_by_date_range($request_date, $current_date, $activity_limit, $activity_offset);
                $activity_all_data = array_merge($activity_all_data, $activity_batch);
                $activity_offset += $activity_limit;
                
                log_message('info', 'Activity Counts batch processed: ' . count($activity_batch) . ' records (offset: ' . ($activity_offset - $activity_limit) . ')');
            }
            
            $activity_result = $this->m_activity_counts->insert_activity_counts_etl($activity_all_data, $request_date);
            $results['activity_counts'] = [
                'status' => 'completed',
                'records_processed' => count($activity_all_data),
                'total_records' => $activity_total,
                'date_range' => $request_date . ' to ' . $current_date,
                'pagination' => [
                    'limit' => $activity_limit,
                    'total_batches' => ceil($activity_total / $activity_limit),
                    'has_more' => false
                ],
                'result' => $activity_result
            ];
            
            // Step 2: Run User Counts ETL (Model View 2) with dynamic pagination
            log_message('info', 'Starting User Counts ETL with date range: ' . $request_date . ' to ' . $current_date);
            $user_total = $this->m_user_counts->get_user_counts_total_by_date_range($request_date, $current_date);
            $user_all_data = [];
            $user_limit = 1000;
            $user_offset = 0;
            
            // Process all data with pagination
            while ($user_offset < $user_total) {
                $user_batch = $this->m_user_counts->get_user_counts_by_date_range($request_date, $current_date, $user_limit, $user_offset);
                $user_all_data = array_merge($user_all_data, $user_batch);
                $user_offset += $user_limit;
                
                log_message('info', 'User Counts batch processed: ' . count($user_batch) . ' records (offset: ' . ($user_offset - $user_limit) . ')');
            }
            
            $user_result = $this->m_user_counts->insert_user_counts_etl($user_all_data, $request_date);
            $results['user_counts'] = [
                'status' => 'completed',
                'records_processed' => count($user_all_data),
                'total_records' => $user_total,
                'date_range' => $request_date . ' to ' . $current_date,
                'pagination' => [
                    'limit' => $user_limit,
                    'total_batches' => ceil($user_total / $user_limit),
                    'has_more' => false
                ],
                'result' => $user_result
            ];
            
            // Step 3: Run Main ETL (Model Utama) - Join data from 2 ETL tables
            log_message('info', 'Starting Main ETL - joining data from activity_counts_etl and user_counts_etl');
            $user_activity_data = $this->m_user_activity->get_user_activity_data_paginated(null, $request_date);
            $main_result = $this->m_user_activity->insert_user_activity_etl($user_activity_data, $request_date);
            $results['main_etl'] = [
                'status' => 'completed',
                'records_processed' => count($user_activity_data),
                'date_used' => $request_date,
                'result' => $main_result
            ];
            
            // Update log_scheduler status to finished (1) when pipeline completes successfully
            $this->m_user_activity->update_scheduler_status_finished($request_date);
            
            $this->response([
                'status' => true,
                'message' => 'ETL pipeline executed successfully with correct flow',
                'request_date' => $request_date,
                'current_date' => $current_date,
                'data' => $results
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            // Update log_scheduler status to failed (3) when pipeline fails
            $this->m_user_activity->update_scheduler_status_failed($request_date, $e->getMessage());
            
            log_message('error', 'ETL pipeline failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'ETL pipeline failed',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/user_activity_etl/export - Export ETL data with pagination
    public function export_get() 
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

            $limit = $this->input->get('limit') ?: 100;
            $offset = $this->input->get('offset') ?: 0;
            $date = $this->input->get('date'); // Optional - null if not provided
            $course_id = $this->input->get('course_id');
            
            // Validate parameters
            if ($offset < 0) {
                $this->response([
                    'status' => false,
                    'message' => 'Offset must be 0 or greater'
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }

            // Get paginated data directly from user_activity_etl table
            // If offset is 0, return empty data
            if ($offset == 0) {
                $data = [];
            } else {
                // Convert offset from 1-based to 0-based for database query
                $db_offset = $offset - 1;
                $data = $this->m_user_activity->get_user_activity_etl($course_id, $date, $limit, $db_offset);
            }
            $total_count = $this->m_user_activity->get_user_activity_total_count($course_id, $date);
            
            $this->response([
                'status' => true,
                'data' => $data,
                'has_next' => ($offset + $limit) < $total_count,
                'filters' => [
                    'date' => $date,
                    'course_id' => $course_id
                ],
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($data),
                    'total_count' => $total_count,
                    'has_more' => ($offset + $limit) < $total_count
                ]
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Export ETL data failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to export ETL data',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/user_activity_etl/logs - Get ETL logs
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

            $limit = $this->input->get('limit') ?: 50;
            $offset = $this->input->get('offset') ?: 0;
            
            $logs = $this->m_user_activity->get_etl_logs($limit, $offset);
            
            $this->response([
                'status' => true,
                'data' => $logs,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($logs)
                ]
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

    // POST /api/user_activity_etl/clear - Clear ETL data
    public function clear_post() 
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

            $date = $this->_get_date_from_request('Clear ETL');
            
            $result = $this->m_user_activity->clear_data($date);
            
            $this->response([
                'status' => true,
                'message' => 'ETL data cleared successfully',
                'date' => $date,
                'affected_rows' => $result
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Clear ETL data failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to clear ETL data',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/user_activity_etl/scheduler - Get scheduler status
    public function scheduler_get() 
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

            $scheduler_data = $this->m_user_activity->get_scheduler_data_for_extraction();
            
            $this->response([
                'status' => true,
                'data' => $scheduler_data,
                'current_date' => date('Y-m-d'),
                'yesterday' => date('Y-m-d', strtotime('-1 day'))
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Get scheduler status failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to get scheduler status',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /api/user_activity_etl/scheduler/initialize - Initialize scheduler
    public function scheduler_initialize_post() 
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

            $result = $this->m_user_activity->set_first_date_extraction();
            
            $this->response([
                'status' => true,
                'message' => 'Scheduler initialized successfully',
                'data' => $result
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Initialize scheduler failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to initialize scheduler',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /api/user_activity_etl/run_activity_counts - Run activity counts ETL
    public function run_activity_counts_post() 
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

            $date = $this->_get_date_from_request('Activity Counts ETL');
            
            // Update log_scheduler status to inprogress (2) when activity counts ETL starts
            $this->m_user_activity->update_scheduler_status_inprogress($date);
            
            // Get activity counts data with dynamic pagination
            $activity_total = $this->m_activity_counts->get_activity_counts_total_by_date_range($date, $date);
            $activity_all_data = [];
            $activity_limit = 1000;
            $activity_offset = 0;
            
            // Process all data with pagination
            while ($activity_offset < $activity_total) {
                $activity_batch = $this->m_activity_counts->get_activity_counts_by_date_range($date, $date, $activity_limit, $activity_offset);
                $activity_all_data = array_merge($activity_all_data, $activity_batch);
                $activity_offset += $activity_limit;
            }
            
            $result = $this->m_activity_counts->insert_activity_counts_etl($activity_all_data, $date);
            
            // Update log_scheduler status to finished (1) when activity counts ETL completes
            $this->m_user_activity->update_scheduler_status_finished($date);
            
            $this->response([
                'status' => true,
                'message' => 'Activity counts ETL completed successfully',
                'date' => $date,
                'records_processed' => count($activity_all_data),
                'total_records' => $activity_total,
                'pagination' => [
                    'limit' => $activity_limit,
                    'total_batches' => ceil($activity_total / $activity_limit)
                ],
                'data' => $result
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            // Update log_scheduler status to failed (3) when activity counts ETL fails
            $this->m_user_activity->update_scheduler_status_failed($date, $e->getMessage());
            
            log_message('error', 'Activity counts ETL failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Activity counts ETL failed',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /api/user_activity_etl/run_user_counts - Run user counts ETL
    public function run_user_counts_post() 
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

            $date = $this->_get_date_from_request('User Counts ETL');
            
            // Update log_scheduler status to inprogress (2) when user counts ETL starts
            $this->m_user_activity->update_scheduler_status_inprogress($date);
            
            // Get user counts data with dynamic pagination
            $user_total = $this->m_user_counts->get_user_counts_total_by_date_range($date, $date);
            $user_all_data = [];
            $user_limit = 1000;
            $user_offset = 0;
            
            // Process all data with pagination
            while ($user_offset < $user_total) {
                $user_batch = $this->m_user_counts->get_user_counts_by_date_range($date, $date, $user_limit, $user_offset);
                $user_all_data = array_merge($user_all_data, $user_batch);
                $user_offset += $user_limit;
            }
            
            $result = $this->m_user_counts->insert_user_counts_etl($user_all_data, $date);
            
            // Update log_scheduler status to finished (1) when user counts ETL completes
            $this->m_user_activity->update_scheduler_status_finished($date);
            
            $this->response([
                'status' => true,
                'message' => 'User counts ETL completed successfully',
                'date' => $date,
                'records_processed' => count($user_all_data),
                'total_records' => $user_total,
                'pagination' => [
                    'limit' => $user_limit,
                    'total_batches' => ceil($user_total / $user_limit)
                ],
                'data' => $result
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            // Update log_scheduler status to failed (3) when user counts ETL fails
            $this->m_user_activity->update_scheduler_status_failed($date, $e->getMessage());
            
            log_message('error', 'User counts ETL failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'User counts ETL failed',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /api/user_activity_etl/run_main_etl - Run main ETL process
    public function run_main_etl_post() 
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

            $date = $this->_get_date_from_request('Main ETL');
            
            // Update log_scheduler status to inprogress (2) when main ETL starts
            $this->m_user_activity->update_scheduler_status_inprogress($date);
            
            // Get user activity data and insert into ETL table
            $user_activity_data = $this->m_user_activity->get_user_activity_data_paginated(null, $date);
            $result = $this->m_user_activity->insert_user_activity_etl($user_activity_data, $date);
            
            // Update log_scheduler status to finished (1) when main ETL completes
            $this->m_user_activity->update_scheduler_status_finished($date);
            
            $this->response([
                'status' => true,
                'message' => 'Main ETL completed successfully',
                'date' => $date,
                'records_processed' => count($user_activity_data),
                'data' => $result
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            // Update log_scheduler status to failed (3) when main ETL fails
            $this->m_user_activity->update_scheduler_status_failed($date, $e->getMessage());
            
            log_message('error', 'Main ETL failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Main ETL failed',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/user_activity_etl/results - Get ETL results
    public function results_get() 
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

            $limit = $this->input->get('limit') ?: 100;
            $offset = $this->input->get('offset') ?: 0;
            
            // Get latest ETL results
            $data = $this->m_user_activity->get_user_activity_etl(null, null, $limit, $offset);
            $total_count = $this->m_user_activity->get_user_activity_total_count();
            
            $this->response([
                'status' => true,
                'data' => $data,
                'count' => count($data),
                'total_count' => $total_count,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_next' => ($offset + $limit) < $total_count
                ]
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Get ETL results failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to get ETL results',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/user_activity_etl/results/{date} - Get ETL results for specific date
    public function results_date_get($date = null) 
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

            if (!$date) {
                $this->response([
                    'status' => false,
                    'message' => 'Date parameter is required'
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }

            $limit = $this->input->get('limit') ?: 100;
            $offset = $this->input->get('offset') ?: 0;
            
            // Get ETL results for specific date
            $data = $this->m_user_activity->get_user_activity_etl(null, $date, $limit, $offset);
            $total_count = $this->m_user_activity->get_user_activity_total_count(null, $date);
            
            $this->response([
                'status' => true,
                'data' => $data,
                'filters' => [
                    'date' => $date
                ],
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($data),
                    'total_count' => $total_count,
                    'has_more' => ($offset + $limit) < $total_count
                ]
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Get ETL results for date failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to get ETL results for date',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /api/user_activity_etl/clean_data - Clean ETL data for specific date
    public function clean_data_post() 
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

            $date = $this->_get_date_from_request('Clean Data');
            
            $result = $this->m_user_activity->clear_etl_data($date);
            
            $this->response([
                'status' => true,
                'message' => 'Data cleanup completed successfully',
                'date' => $date,
                'affected_rows' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Clean ETL data failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to clean ETL data',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Private helper methods
    private function _get_date_from_request($function_name = 'Unknown')
    {
        // Parse JSON input first, then fallback to POST
        $json_data = json_decode($this->input->raw_input_stream, true);
        $date = null;
        if ($json_data && isset($json_data['date'])) {
            $date = $json_data['date'];
        } else if ($this->input->post('date')) {
            $date = $this->input->post('date');
        } else {
            $date = date('Y-m-d', strtotime('-1 day'));
        }
        
        log_message('info', $function_name . ' - JSON data: ' . json_encode($json_data));
        log_message('info', $function_name . ' - Date: ' . $date);
        
        return $date;
    }
    
    private function _validate_webhook_token($auth_header)
    {
        if (!$auth_header) {
            return false;
        }

        $expected_token = $this->config->item('webhook_token') ?: 'default-webhook-token-change-this';
        $provided_token = str_replace('Bearer ', '', $auth_header);
        
        return $provided_token === $expected_token;
    }

    private function _run_etl_pipeline_background($date)
    {
        // Update ETL status to running
        $this->m_user_activity->update_etl_status('running', $date);
        
        // In a real implementation, you would start a background process here
        // For now, we'll just log the request
        log_message('info', 'ETL pipeline background process requested for date: ' . $date);
        
        // You could use exec() to run a background script
        // exec("php /path/to/etl_script.php --date={$date} > /dev/null 2>&1 &");
    }
} 