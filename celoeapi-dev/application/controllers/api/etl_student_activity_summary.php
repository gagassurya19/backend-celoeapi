<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

class etl_student_activity_summary extends REST_Controller {

	// POST /api/etl_student_activity_summary/run_pipeline - Run ETL pipeline with correct flow
	public function run_pipeline_post()
    {
		try {
			// Ensure dependencies are loaded
        $this->load->database();
        $this->load->model('sas_user_activity_etl_model', 'm_user_activity');
        $this->load->model('sas_actvity_counts_model', 'm_activity_counts');
        $this->load->model('sas_user_counts_model', 'm_user_counts');

            log_message('info', 'ETL pipeline triggered with correct flow');
            
			// Get date from JSON or POST, default yesterday
            $json_data = json_decode($this->input->raw_input_stream, true);
            $request_date = null;
            if ($json_data && isset($json_data['date'])) {
                $request_date = $json_data['date'];
			} else if ($this->input->post('date')) {
                $request_date = $this->input->post('date');
			} else {
                $request_date = date('Y-m-d', strtotime('-1 day'));
            }
            
            log_message('info', 'JSON data: ' . json_encode($json_data));
            log_message('info', 'Request date: ' . $request_date);
            
            $current_date = date('Y-m-d', strtotime('+1 day'));
            
			// Update scheduler status to inprogress (2)
            $this->m_user_activity->update_scheduler_status_inprogress($request_date);
            
            $results = [];
            
			// Step 1: Activity Counts ETL with pagination
            log_message('info', 'Starting Activity Counts ETL with date range: ' . $request_date . ' to ' . $current_date);
            $activity_total = $this->m_activity_counts->get_activity_counts_total_by_date_range($request_date, $current_date);
            $activity_all_data = [];
            $activity_limit = 1000;
            $activity_offset = 0;
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
            
			// Step 2: User Counts ETL with pagination
            log_message('info', 'Starting User Counts ETL with date range: ' . $request_date . ' to ' . $current_date);
            $user_total = $this->m_user_counts->get_user_counts_total_by_date_range($request_date, $current_date);
            $user_all_data = [];
            $user_limit = 1000;
            $user_offset = 0;
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
            
			// Step 3: Main ETL - join data into user_activity_etl
            log_message('info', 'Starting Main ETL - joining data from activity_counts_etl and user_counts_etl');
            $user_activity_data = $this->m_user_activity->get_user_activity_data_paginated(null, $request_date);
            $main_result = $this->m_user_activity->insert_user_activity_etl($user_activity_data, $request_date);
            $results['main_etl'] = [
                'status' => 'completed',
                'records_processed' => count($user_activity_data),
                'date_used' => $request_date,
                'result' => $main_result
            ];
            
			// Update scheduler status to finished (1)
            $this->m_user_activity->update_scheduler_status_finished($request_date);
            
            $this->response([
                'status' => true,
                'message' => 'ETL pipeline executed successfully with correct flow',
                'request_date' => $request_date,
                'current_date' => $current_date,
                'data' => $results
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
			// Update scheduler status to failed (3)
			if (isset($request_date)) {
            $this->m_user_activity->update_scheduler_status_failed($request_date, $e->getMessage());
			}
            log_message('error', 'ETL pipeline failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'ETL pipeline failed',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

            // GET /api/etl_student_activity_summary/export - Export ETL data with pagination
    public function export_get() 
    {      
        try {
			$this->load->database();
			$this->load->model('sas_user_activity_etl_model', 'm_user_activity');

            $limit = $this->input->get('limit') ?: 100;
            $offset = $this->input->get('offset') ?: 0;
			$date = $this->input->get('date');
            $course_id = $this->input->get('course_id');
            
            if ($offset < 0) {
                $this->response([
                    'status' => false,
                    'message' => 'Offset must be 0 or greater'
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }

            if ($offset == 0) {
                $data = [];
            } else {
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

            // POST /api/etl_student_activity_summary/clean_data - Clean ETL data for specific date
    public function clean_data_post() 
    {      
        try {
			$this->load->database();
			$this->load->model('sas_user_activity_etl_model', 'm_user_activity');

			// Get date from JSON or POST, default yesterday
			$json_data = json_decode($this->input->raw_input_stream, true);
			if ($json_data && isset($json_data['date'])) {
				$date = $json_data['date'];
			} else if ($this->input->post('date')) {
				$date = $this->input->post('date');
			} else {
				$date = date('Y-m-d', strtotime('-1 day'));
			}
            
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
} 