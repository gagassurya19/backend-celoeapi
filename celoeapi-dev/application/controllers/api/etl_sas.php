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

			// Normalize dates like 2025-02-7 -> 2025-02-07 before validation
			$start_date = $this->_normalize_date($start_date);
			$end_date = $this->_normalize_date($end_date);
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

	/**
	 * Normalize a date string into YYYY-MM-DD when possible.
	 */
	private function _normalize_date($date)
	{
		if (empty($date)) { return $date; }
		if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $date)) {
			$ts = strtotime($date);
			if ($ts !== false) {
				return date('Y-m-d', $ts);
			}
		}
		return $date;
	}

	// POST /api/etl_sas/stop_pipeline - Force stop running SAS ETL pipeline
	public function stop_pipeline_post()
	{
		try {
			$this->load->database();
			
			// Get all running SAS ETL processes (status = 'running')
			$this->db->from('sas_etl_logs');
			$this->db->where('process_name', 'user_activity_etl');
			$this->db->where('status', 'running');
			$running_processes = $this->db->get()->result_array();
			
			if (empty($running_processes)) {
				$this->response([
					'status' => true,
					'message' => 'No running SAS ETL pipelines found',
					'stopped_count' => 0
				], REST_Controller::HTTP_OK);
				return;
			}
			
			$stopped_count = 0;
			$stopped_processes = [];
			
			foreach ($running_processes as $process) {
				try {
					// Update log status to failed
					$this->db->where('id', $process['id']);
					$this->db->update('sas_etl_logs', [
						'status' => 'failed',
						'end_time' => date('Y-m-d H:i:s'),
						'message' => 'Force stopped by API - data may be incomplete',
						'duration_seconds' => time() - strtotime($process['start_time'])
					]);
					
					$stopped_count++;
					$stopped_processes[] = [
						'log_id' => (int)$process['id'],
						'start_time' => $process['start_time'],
						'stopped_at' => date('Y-m-d H:i:s')
					];
					
					log_message('info', 'Force stopped SAS ETL process ID: ' . $process['id']);
					
				} catch (Exception $e) {
					log_message('error', 'Failed to update log for process ID ' . $process['id'] . ': ' . $e->getMessage());
				}
			}
			
			// Try to kill any running PHP processes related to SAS ETL
			$this->_kill_sas_etl_processes();
			
			$this->response([
				'status' => true,
				'message' => 'SAS ETL pipeline stopped successfully',
				'stopped_count' => $stopped_count,
				'stopped_processes' => $stopped_processes,
				'timestamp' => date('Y-m-d H:i:s')
			], REST_Controller::HTTP_OK);
			
		} catch (Exception $e) {
			log_message('error', 'SAS stop pipeline failed: ' . $e->getMessage());
			$this->response([
				'status' => false,
				'message' => 'Failed to stop SAS ETL pipeline',
				'error' => $e->getMessage()
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
	}
	
	/**
	 * Kill running SAS ETL processes
	 */
	private function _kill_sas_etl_processes()
	{
		try {
			// Kill processes containing 'run_student_activity_from_start' in command line
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				// Windows
				exec('taskkill /F /IM php.exe /FI "WINDOWTITLE eq *run_student_activity_from_start*" 2>nul', $output, $return);
			} else {
				// Linux/Unix - kill processes by grep pattern
				exec("pkill -f 'run_student_activity_from_start' 2>/dev/null", $output, $return);
				
				// Alternative: kill by process name if pkill fails
				if ($return !== 0) {
					exec("ps aux | grep 'run_student_activity_from_start' | grep -v grep | awk '{print \$2}' | xargs kill -9 2>/dev/null", $output, $return);
				}
			}
			
			log_message('info', 'Attempted to kill SAS ETL processes, return code: ' . $return);
		} catch (Exception $e) {
			log_message('error', 'Failed to kill SAS ETL processes: ' . $e->getMessage());
		}
	}

	// Background helpers
	private function _run_sas_catchup_background($start_date, $end_date = null, $concurrency = 1)
	{
		try {
			$php = 'php';
			$index = APPPATH . '../index.php';
			$concurrency = (int)$concurrency ?: 1;
			$endArg = $end_date ? (' ' . escapeshellarg($end_date)) : '';
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$cmd = 'start /B ' . $php . ' ' . $index . ' cli run_student_activity_from_start ' . escapeshellarg($start_date) . $endArg . ' ' . $concurrency . ' > nul 2>&1';
			} else {
				$cmd = $php . ' ' . $index . ' cli run_student_activity_from_start ' . escapeshellarg($start_date) . $endArg . ' ' . $concurrency . ' > /dev/null 2>&1 &';
			}
			exec($cmd);
			log_message('info', 'Spawned SAS catch-up: ' . $cmd);
		} catch (Exception $e) {
			log_message('error', 'Failed to start SAS catch-up: ' . $e->getMessage());
			throw $e;
		}
	}

	// GET /api/etl_sas/logs - list SAS ETL logs (latest first)
	public function logs_get()
	{
		try {
			$this->load->database();
			$limit = (int) ($this->input->get('limit') ?: 50);
			$offset = (int) ($this->input->get('offset') ?: 0);
			$status = $this->input->get('status'); // optional: running/completed/failed

			$this->db->from('sas_etl_logs');
			$this->db->where('process_name', 'user_activity_etl');
			if (!empty($status)) {
				$this->db->where('status', $status);
			}
			$this->db->order_by('id', 'DESC');
			$this->db->limit($limit, $offset);
			$query = $this->db->get();

			$this->response([
				'status' => true,
				'data' => $query->result_array(),
				'pagination' => [
					'limit' => $limit,
					'offset' => $offset
				]
			], REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'status' => false,
				'error' => $e->getMessage()
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	// GET /api/etl_sas/status - get latest SAS ETL status
	public function status_get()
	{
		try {
			$this->load->database();
			
			// Get latest log entry
			$this->db->from('sas_etl_logs');
			$this->db->where('process_name', 'user_activity_etl');
			$this->db->order_by('id', 'DESC');
			$this->db->limit(1);
			$latest_log = $this->db->get()->row_array();
			
			if (!$latest_log) {
				$this->response([
					'status' => true,
					'data' => [
						'last_run' => null,
						'status' => 'no_data',
						'message' => 'No ETL runs found'
					]
				], REST_Controller::HTTP_OK);
				return;
			}
			
			// Get running count
			$this->db->from('sas_etl_logs');
			$this->db->where('process_name', 'user_activity_etl');
			$this->db->where('status', 'running');
			$running_count = $this->db->count_all_results();
			
			// Get recent activity (last 7 days)
			$this->db->from('sas_etl_logs');
			$this->db->where('process_name', 'user_activity_etl');
			$this->db->where('start_time >=', date('Y-m-d H:i:s', strtotime('-7 days')));
			$recent_count = $this->db->count_all_results();
			
			// Get watermark data (last extracted and next to extract)
			$this->db->from('sas_etl_watermarks');
			$this->db->where('process_name', 'user_activity_etl');
			$watermark = $this->db->get()->row_array();
			
			$watermark_info = null;
			if ($watermark) {
				$next_date = date('Y-m-d', strtotime($watermark['last_date'] . ' +1 day'));
				$watermark_info = [
					'last_extracted_date' => $watermark['last_date'],
					'last_extracted_timecreated' => $watermark['last_timecreated'],
					'next_extract_date' => $next_date,
					'updated_at' => $watermark['updated_at']
				];
			}
			
			$this->response([
				'status' => true,
				'data' => [
					'last_run' => [
						'id' => (int)$latest_log['id'],
						'start_time' => $latest_log['start_time'],
						'end_time' => $latest_log['end_time'],
						'status' => $latest_log['status'],
						'message' => $latest_log['message'],
						'parameters' => json_decode($latest_log['parameters'], true),
						'duration_seconds' => $latest_log['duration_seconds']
					],
					'currently_running' => $running_count,
					'recent_activity' => $recent_count,
					'watermark' => $watermark_info,
					'service' => 'SAS'
				]
			], REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'status' => false,
				'error' => $e->getMessage()
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	// GET /api/etl_sas/export - Export ETL data with pagination
	public function export_get()
	{
		try {
			$this->load->database();

			$limit = (int) ($this->input->get('limit') ?: 100);
			$offset = (int) ($this->input->get('offset') ?: 0);
			$date = $this->input->get('date');
			$course_id = $this->input->get('course_id');
			
			// Optional: include specific tables via comma-separated list
			$tablesParam = $this->get('tables');
			$singleTableParam = $this->get('table');
			$debug = $this->get('debug');

			if ($offset < 0) {
				$this->response([
					'status' => false,
					'message' => 'Offset must be 0 or greater'
				], REST_Controller::HTTP_BAD_REQUEST);
				return;
			}

			// Define all SAS tables to export
			$allTables = [
				'sas_user_activity_etl',
				'sas_activity_counts_etl', 
				'sas_user_counts_etl',
				'sas_courses'
			];

			$requestedTables = $allTables;
			if (!empty($singleTableParam)) {
				$requestedTables = array_values(array_intersect($allTables, [trim($singleTableParam)]));
			}
			if (!empty($tablesParam)) {
				$requested = array_map('trim', explode(',', $tablesParam));
				// Filter only known tables to prevent SQL injection on identifiers
				$requestedTables = array_values(array_intersect($allTables, $requested));
				if (empty($requestedTables)) {
					$requestedTables = $allTables;
				}
			}

			$tablesResult = [];
			$overallHasNext = false;

			foreach ($requestedTables as $table) {
				$tableResult = $this->_fetch_sas_table_page($table, $limit, $offset, $date, $course_id);
				if ($debug) { 
					$tableResult['debug'] = $this->_table_debug_counts($table, $date, $course_id); 
				}
				$tablesResult[$table] = $tableResult;
				if (!empty($tableResult['hasNext'])) {
					$overallHasNext = true;
				}
			}

			$this->response([
				'status' => true,
				'data' => $tablesResult,
				'has_next' => $overallHasNext,
				'filters' => [
					'date' => $date,
					'course_id' => $course_id
				],
				'pagination' => [
					'limit' => (int) $limit,
					'offset' => (int) $offset,
					'count' => count($tablesResult),
					'has_more' => $overallHasNext
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

	/**
	 * Fetch data from a specific SAS table with pagination and filters
	 */
	private function _fetch_sas_table_page($table, $limit, $offset, $date = null, $course_id = null)
	{
		$limitPlusOne = $limit + 1;
		$whereConditions = [];
		$params = [];

		// Build WHERE conditions based on table structure
		if ($course_id !== null) {
			if ($table === 'sas_courses') {
				$whereConditions[] = 'course_id = ?';
			} else {
				// Handle different column names for course_id
				if ($table === 'sas_activity_counts_etl' || $table === 'sas_user_counts_etl') {
					$whereConditions[] = 'courseid = ?';
				} else {
					$whereConditions[] = 'course_id = ?';
				}
			}
			$params[] = $course_id;
		}

		if ($date !== null) {
			if ($table === 'sas_courses') {
				// Courses table doesn't have date filter
			} else {
				$whereConditions[] = 'extraction_date = ?';
				$params[] = $date;
			}
		}

		$whereSql = '';
		if (!empty($whereConditions)) {
			$whereSql = ' WHERE ' . implode(' AND ', $whereConditions);
		}

		// Handle different primary key columns
		$orderBy = 'id';
		if ($table === 'sas_courses') {
			$orderBy = 'course_id';
		}

		$sql = "SELECT * FROM `{$table}`" . $whereSql . " ORDER BY `{$orderBy}` ASC LIMIT {$limitPlusOne} OFFSET {$offset}";
		$query = $this->db->query($sql, $params);
		$rows = $query->result_array();
		
		$hasNext = false;
		if (count($rows) > $limit) {
			$hasNext = true;
			$rows = array_slice($rows, 0, $limit);
		}

		return [
			'count' => count($rows),
			'hasNext' => $hasNext,
			'nextOffset' => $hasNext ? ($offset + $limit) : null,
			'rows' => $rows,
		];
	}

	/**
	 * Get debug counts for a specific table
	 */
	private function _table_debug_counts($table, $date = null, $course_id = null)
	{
		$total = 0;
		$filtered = null;
		
		try {
			// Get total count
			$q = $this->db->query("SELECT COUNT(*) AS c FROM `{$table}`");
			$row = $q->row_array();
			if ($row && isset($row['c'])) { 
				$total = intval($row['c']); 
			}
		} catch (Exception $e) {
			// ignore
		}

		// Get filtered count if filters applied
		if (($date !== null || $course_id !== null) && $table !== 'sas_courses') {
			try {
				$whereConditions = [];
				$params = [];

				if ($course_id !== null) {
					$whereConditions[] = 'course_id = ?';
					$params[] = $course_id;
				}

				if ($date !== null) {
					$whereConditions[] = 'extraction_date = ?';
					$params[] = $date;
				}

				if (!empty($whereConditions)) {
					$whereSql = ' WHERE ' . implode(' AND ', $whereConditions);
					$q2 = $this->db->query("SELECT COUNT(*) AS c FROM `{$table}`" . $whereSql, $params);
					$row2 = $q2->row_array();
					if ($row2 && isset($row2['c'])) { 
						$filtered = intval($row2['c']); 
					}
				}
			} catch (Exception $e) {
				// ignore
			}
		}

		return [
			'totalCount' => $total,
			'filteredCount' => $filtered,
		];
	}
}
