<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

class etl_student_activity_summary extends REST_Controller {

	// POST /api/etl_student_activity_summary/run_pipeline - Run ETL pipeline with date range
	public function run_pipeline_post()
    {
		try {
			// Ensure dependencies are loaded
        $this->load->database();
        $this->load->model('sas_user_activity_etl_model', 'm_user_activity');
        $this->load->model('sas_actvity_counts_model', 'm_activity_counts');
        $this->load->model('sas_user_counts_model', 'm_user_counts');

            log_message('info', 'ETL pipeline triggered with date range');
            
			// Get date range from JSON or POST
            $json_data = json_decode($this->input->raw_input_stream, true);
            $start_date = null;
            $end_date = null;
            
            if ($json_data) {
                $start_date = isset($json_data['start_date']) ? $json_data['start_date'] : null;
                $end_date = isset($json_data['end_date']) ? $json_data['end_date'] : null;
            } else {
                $start_date = $this->input->post('start_date');
                $end_date = $this->input->post('end_date');
            }
            
            // Set default dates if not provided
            if (!$start_date) {
                $start_date = date('Y-m-d', strtotime('-7 days')); // Default: 7 days ago
            }
            if (!$end_date) {
                $end_date = date('Y-m-d', strtotime('-1 day')); // Default: yesterday
            }
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                throw new Exception('Invalid date format. Use YYYY-MM-DD format.');
            }
            
            // Validate date range
            if (strtotime($start_date) > strtotime($end_date)) {
                throw new Exception('Start date cannot be after end date.');
            }
            
            log_message('info', 'JSON data: ' . json_encode($json_data));
            log_message('info', 'Date range: ' . $start_date . ' to ' . $end_date);
            
            // Log ETL trigger into SAS logs
            if (method_exists($this->m_user_activity, 'update_etl_status')) {
                $this->m_user_activity->update_etl_status('running', null, [
                    'trigger' => 'api_run_pipeline',
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ]);
            }

            // Start ETL process in background with date range
            $this->_run_etl_background_range($start_date, $end_date);
            
            $this->response([
                'status' => true,
                'message' => 'ETL pipeline started in background with date range',
                'date_range' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'note' => 'Check logs for ETL progress and completion status'
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'ETL pipeline failed to start: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'ETL pipeline failed to start',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Run ETL pipeline in background with date range
     * @param string $start_date Start date (YYYY-MM-DD format)
     * @param string $end_date End date (YYYY-MM-DD format)
     */
    private function _run_etl_background_range($start_date, $end_date)
    {
        try {
            // Get the path to the ETL runner script
            $script_path = APPPATH . '../run_etl_range.sh';
            
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
                $full_command = 'start /B ' . $php_path . ' ' . $index_path . ' cli run_student_activity_etl_range ' . $start_date . ' ' . $end_date . ' > nul 2>&1';
            } else {
                // Unix-like (Linux, macOS) - use shell script with date range parameters
                $full_command = $script_path . ' ' . $start_date . ' ' . $end_date . ' > /dev/null 2>&1 &';
            }
            
            log_message('info', 'Starting ETL background process with date range: ' . $full_command);
            
            // Execute the command in background
            exec($full_command, $output, $return_var);
            
            // Note: $return_var will be 0 for successful start, but doesn't guarantee ETL success
            if ($return_var !== 0) {
                log_message('error', 'ETL background process failed with return code: ' . $return_var);
                throw new Exception('Failed to start background ETL process');
            }
            
            log_message('info', 'ETL background process started successfully for date range: ' . $start_date . ' to ' . $end_date);
            
        } catch (Exception $e) {
            log_message('error', 'Failed to start ETL background process: ' . $e->getMessage());
            throw $e;
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