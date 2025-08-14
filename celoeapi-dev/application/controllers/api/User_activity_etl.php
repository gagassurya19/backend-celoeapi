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
        
        // Disable ALL error display and logging
        error_reporting(0);
        ini_set('display_errors', 0);
        ini_set('log_errors', 0);
        
        // Set JSON response format
        $this->output->set_content_type('application/json');
    }

    // GET /api/user_activity_etl/status - Get ETL status
    public function status_get()
    {
        try {
            $status = $this->m_user_activity->get_etl_status();
            
            $this->response([
                'status' => true,
                'message' => 'ETL status retrieved successfully',
                'data' => $status
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            $this->response([
                'status' => false,
                'message' => 'Failed to get ETL status',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // POST /api/user_activity_etl/run_pipeline - Run ETL pipeline
    public function run_pipeline_post() 
    {      
        try {
            // Disable ALL error display and logging for this method
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('log_errors', 0);
            
            // Clear any existing output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Start new output buffer
            ob_start();
            
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
            
            $current_date = date('Y-m-d', strtotime('+1 day'));
            
            // Update log_scheduler status to inprogress (2) when pipeline starts
            $this->m_user_activity->update_scheduler_status_inprogress($request_date);
            
            $results = [];
            
            // Step 1: Run Activity Counts ETL
            $activity_total = $this->m_activity_counts->get_activity_counts_total_by_date_range($request_date, $current_date);
            $activity_all_data = [];
            $activity_limit = 1000;
            $activity_offset = 0;
            
            // Process all data with pagination
            if ($activity_total > 0) {
                while ($activity_offset < $activity_total) {
                    $activity_batch = $this->m_activity_counts->get_activity_counts_by_date_range($request_date, $current_date, $activity_limit, $activity_offset);
                    $activity_all_data = array_merge($activity_all_data, $activity_batch);
                    $activity_offset += $activity_limit;
                }
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
            
            // Step 2: Run User Counts ETL
            $user_total = $this->m_user_counts->get_user_counts_total_by_date_range($request_date, $current_date);
            $user_all_data = [];
            $user_limit = 1000;
            $user_offset = 0;
            
            // Process all data with pagination
            if ($user_total > 0) {
                while ($user_offset < $user_total) {
                    $user_batch = $this->m_user_counts->get_user_counts_by_date_range($request_date, $current_date, $user_limit, $user_offset);
                    $user_all_data = array_merge($user_all_data, $user_batch);
                    $user_offset += $user_limit;
                }
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
            
            // Step 3: Run Main ETL
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
            
            // Clear output buffer and send clean JSON response
            ob_end_clean();
            
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
            
            // Clear output buffer and send clean JSON response
            ob_end_clean();
            
            $this->response([
                'status' => false,
                'message' => 'ETL pipeline failed',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/user_activity_etl/export - Export ETL data
    public function export_get()
    {
        try {
            $limit = $this->get('limit') ?: 100;
            $offset = $this->get('offset') ?: 0;
            $date = $this->get('date');
            
            $data = $this->m_user_activity->export_data($limit, $offset, $date);
            $total = $this->m_user_activity->get_user_activity_total_count(null, $date);
            
            $this->response([
                'status' => true,
                'message' => 'Data exported successfully',
                'data' => [
                    'records' => $data,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'total' => $total,
                        'has_more' => ($offset + $limit) < $total
                    ]
                ]
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            $this->response([
                'status' => false,
                'message' => 'Failed to export data',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/user_activity_etl/logs - Get ETL logs
    public function logs_get()
    {
        try {
            $limit = $this->get('limit') ?: 50;
            $offset = $this->get('offset') ?: 0;
            
            $logs = $this->m_user_activity->get_etl_logs($limit, $offset);
            
            $this->response([
                'status' => true,
                'message' => 'ETL logs retrieved successfully',
                'data' => $logs
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            $this->response([
                'status' => false,
                'message' => 'Failed to get ETL logs',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /api/user_activity_etl/clear - Clear ETL data
    public function clear_delete()
    {
        try {
            $date = $this->delete('date') ?: date('Y-m-d', strtotime('-1 day'));
            
            $affected_rows = $this->m_user_activity->clear_data($date);
            
            $this->response([
                'status' => true,
                'message' => 'Data cleared successfully',
                'data' => [
                    'date' => $date,
                    'affected_rows' => $affected_rows
                ]
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            $this->response([
                'status' => false,
                'message' => 'Failed to clear data',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // GET /api/user_activity_etl/test - Test data retrieval
    public function test_get()
    {
        try {
            $test_results = $this->m_user_activity->test_data_retrieval();
            
            $this->response([
                'status' => true,
                'message' => 'Test completed successfully',
                'data' => $test_results
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            $this->response([
                'status' => false,
                'message' => 'Test failed',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}