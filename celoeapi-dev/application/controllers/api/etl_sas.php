<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

class etl_sas extends REST_Controller {

	// POST /api/etl_sas/run - Run ETL pipeline (catch-up) starting from start_date
	public function run_pipeline_post()
    {
		try {
			$this->load->database();
			$this->load->model('sas_user_activity_etl_model', 'm_user_activity');
			$this->load->model('sas_actvity_counts_model', 'm_activity_counts');
			$this->load->model('sas_user_counts_model', 'm_user_counts');

			log_message('info', 'SAS ETL run triggered');

			$json_data = json_decode($this->input->raw_input_stream, true);
			$start_date = null;
			$end_date = null;
			$concurrency = 1;

			if ($json_data) {
				$start_date = isset($json_data['start_date']) ? $json_data['start_date'] : null;
				$end_date = isset($json_data['end_date']) ? $json_data['end_date'] : null;
				$concurrency = isset($json_data['concurrency']) ? (int)$json_data['concurrency'] : 1;
			} else {
				$start_date = $this->input->post('start_date');
				$end_date = $this->input->post('end_date');
				$concurrency = (int)$this->input->post('concurrency') ?: 1;
			}

			if (!$start_date) {
				$start_date = date('Y-m-d', strtotime('-7 days'));
			}
			if (!$end_date) {
				$end_date = date('Y-m-d', strtotime('-1 day'));
			}

			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
				throw new Exception('Invalid date format. Use YYYY-MM-DD format.');
			}
			if (strtotime($start_date) > strtotime($end_date)) {
				throw new Exception('Start date cannot be after end date.');
			}

			log_message('info', 'SAS run payload: ' . json_encode($json_data));
			log_message('info', 'SAS run range: ' . $start_date . ' to ' . $end_date . ' concurrency=' . $concurrency);

			// Create one ETL log row (will be updated on completion/failure)
			$log_id = null;
			if (method_exists($this->m_user_activity, 'create_etl_log')) {
				$log_id = $this->m_user_activity->create_etl_log('running', $start_date, [
					'trigger' => 'api_run_pipeline',
					'framework' => 'etl_sas',
					'start_date_param' => $start_date,
					'end_date_param' => $end_date,
					'concurrency_param' => $concurrency,
					'message' => 'API triggered SAS catch-up'
				]);
			}

			// Start background catch-up
			$this->_run_sas_catchup_background($start_date, $end_date, $concurrency);

			$response = [
				'status' => true,
				'message' => 'SAS ETL started in background',
				'date_range' => [
					'start_date' => $start_date,
					'end_date' => $end_date
				],
				'concurrency' => $concurrency,
				'note' => 'Check sas_etl_logs for progress'
			];
			if ($log_id) { $response['log_id'] = (int)$log_id; }
			$this->response($response, REST_Controller::HTTP_OK);

		} catch (Exception $e) {
			log_message('error', 'SAS run failed to start: ' . $e->getMessage());
			$this->response([
				'status' => false,
				'message' => 'SAS ETL failed to start',
				'error' => $e->getMessage()
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
    }

	// GET /api/etl_sas/export - Export ETL data with pagination
	public function export_get()
	{
		try {
			$this->load->database();
			$this->load->model('sas_user_activity_etl_model', 'm_user_activity');

			$limit = (int) ($this->input->get('limit') ?: 100);
			$offset = (int) ($this->input->get('offset') ?: 0);
			$date = $this->input->get('date');
			$course_id = $this->input->get('course_id');

			if ($offset < 0) {
				$this->response([
					'status' => false,
					'message' => 'Offset must be 0 or greater'
				], REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			$data = $this->m_user_activity->get_user_activity_etl($course_id, $date, $limit, $offset);
			$total_count = (int) $this->m_user_activity->get_user_activity_total_count($course_id, $date);

			$this->response([
				'status' => true,
				'data' => $data,
				'has_next' => (($offset + $limit) < $total_count),
				'filters' => [
					'date' => $date,
					'course_id' => $course_id
				],
				'pagination' => [
					'limit' => (int) $limit,
					'offset' => (int) $offset,
					'count' => count($data),
					'total_count' => (int) $total_count,
					'has_more' => ($offset + $limit) < $total_count
				]
			], REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			log_message('error', 'SAS export failed: ' . $e->getMessage());
			$this->response([
				'status' => false,
				'message' => 'Failed to export SAS data',
				'error' => $e->getMessage()
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	// POST /api/etl_sas/clean - Clean ALL SAS ETL data
	public function clean_data_post()
	{
		try {
			$this->load->database();
			$this->load->model('sas_user_activity_etl_model', 'm_user_activity');

			if (method_exists($this->m_user_activity, 'update_etl_status')) {
				$this->m_user_activity->update_etl_status('running', null, [
					'trigger' => 'api_clean_all',
					'message' => 'clean_all start'
				]);
			}

			$summary = $this->m_user_activity->clear_all_etl_data();

			if (method_exists($this->m_user_activity, 'update_etl_status')) {
				$this->m_user_activity->update_etl_status('completed', null, [
					'trigger' => 'api_clean_all',
					'message' => 'clean_all completed',
					'summary' => $summary,
				]);
			}

			$this->response([
				'status' => true,
				'message' => 'All SAS ETL tables cleaned successfully',
				'summary' => $summary,
				'timestamp' => date('Y-m-d H:i:s')
			], REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			log_message('error', 'SAS clean failed: ' . $e->getMessage());
			if (method_exists($this->m_user_activity, 'update_etl_status')) {
				$this->m_user_activity->update_etl_status('failed', null, [
					'trigger' => 'api_clean_all',
					'message' => 'clean_all failed',
					'error' => $e->getMessage(),
				]);
			}
			$this->response([
				'status' => false,
				'message' => 'Failed to clean all ETL data',
				'error' => $e->getMessage()
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	// Background helpers
	private function _run_sas_catchup_background($start_date, $end_date = null, $concurrency = 1)
	{
		try {
			$php = 'php';
			$index = APPPATH . '../index.php';
			$concurrency = (int)$concurrency ?: 1;
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$cmd = 'start /B ' . $php . ' ' . $index . ' cli run_student_activity_from_start ' . escapeshellarg($start_date) . ' ' . $concurrency . ' > nul 2>&1';
			} else {
				$cmd = $php . ' ' . $index . ' cli run_student_activity_from_start ' . escapeshellarg($start_date) . ' ' . $concurrency . ' > /dev/null 2>&1 &';
			}
			exec($cmd);
			log_message('info', 'Spawned SAS catch-up: ' . $cmd);
		} catch (Exception $e) {
			log_message('error', 'Failed to start SAS catch-up: ' . $e->getMessage());
			throw $e;
		}
	}
}
