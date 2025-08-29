<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

class etl_cp extends REST_Controller {

	public function run_post()
	{
		try {
			$this->load->database();
			$this->load->model('cp_etl_model', 'm_cp');

			$json = json_decode($this->input->raw_input_stream, true);
			$start_date = $json['start_date'] ?? $this->input->post('start_date');
			$end_date = $json['end_date'] ?? $this->input->post('end_date');
			$concurrency = (int)($json['concurrency'] ?? ($this->input->post('concurrency') ?: 1));

			if (!$start_date) { $start_date = date('Y-m-d', strtotime('-7 days')); }
			if (!$end_date) { $end_date = date('Y-m-d', strtotime('-1 day')); }

			// Normalize dates like 2025-02-7 -> 2025-02-07 before validation
			$start_date = $this->_normalize_date($start_date);
			$end_date = $this->_normalize_date($end_date);
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
				throw new Exception('Invalid date format. Use YYYY-MM-DD');
			}
			if (strtotime($start_date) > strtotime($end_date)) {
				throw new Exception('Start date cannot be after end date.');
			}

			// Create per-run log in cp_etl_logs (status inprogress=2)
			$log_id = null;
			$sql = "INSERT INTO cp_etl_logs (offset, numrow, type, message, requested_start_date, status, start_date)
					VALUES (0,0,'run_cp_backfill', ?, ?, 2, NOW())";
			$this->db->query($sql, [
				json_encode(['concurrency' => $concurrency, 'end_date' => $end_date]),
				$start_date
			]);
			$log_id = $this->db->insert_id();

			// Spawn background CP backfill
			$php = 'php';
			$index = APPPATH . '../index.php';
			$log_file = APPPATH . 'logs/cp_etl_' . date('Y-m-d_H-i-s') . '_' . $log_id . '.log';
			$cmd = $php . ' ' . $index . ' cli run_cp_backfill ' . escapeshellarg($start_date) . ' ' . escapeshellarg($end_date) . ' ' . $concurrency . ' ' . (int)$log_id . ' > ' . $log_file . ' 2>&1 &';
			log_message('info', 'Spawned CP backfill: ' . $cmd . ' (log: ' . $log_file . ')');
			exec($cmd);

			$this->response([
				'status' => true,
				'message' => 'CP ETL started in background',
				'date_range' => ['start_date' => $start_date, 'end_date' => $end_date],
				'concurrency' => $concurrency,
				'log_id' => (int)$log_id
			], REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			log_message('error', 'CP run failed: ' . $e->getMessage());
			$this->response([
				'status' => false,
				'message' => 'CP ETL failed to start',
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
		// Accept inputs like YYYY-M-D and convert to YYYY-MM-DD
		if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $date)) {
			$ts = strtotime($date);
			if ($ts !== false) {
				return date('Y-m-d', $ts);
			}
		}
		return $date;
	}

	// POST /api/etl_cp/stop_pipeline - Force stop running CP ETL pipeline
	public function stop_pipeline_post()
	{
		try {
			$this->load->database();
			
			// Get all running CP ETL processes (status = 2 for inprogress)
			$this->db->from('cp_etl_logs');
			$this->db->where('status', 2);
			$this->db->where('type', 'run_cp_backfill');
			$running_processes = $this->db->get()->result_array();
			
			if (empty($running_processes)) {
				$this->response([
					'status' => true,
					'message' => 'No running CP ETL pipelines found',
					'stopped_count' => 0
				], REST_Controller::HTTP_OK);
				return;
			}
			
			$stopped_count = 0;
			$stopped_processes = [];
			
			foreach ($running_processes as $process) {
				try {
					// Update log status to failed (status = 3)
					$this->db->where('id', $process['id']);
					$this->db->update('cp_etl_logs', [
						'status' => 3, // failed
						'end_date' => date('Y-m-d H:i:s'),
						'message' => 'Force stopped by API - data may be incomplete',
						'duration_seconds' => time() - strtotime($process['start_date'])
					]);
					
					$stopped_count++;
					$stopped_processes[] = [
						'log_id' => (int)$process['id'],
						'start_date' => $process['start_date'],
						'stopped_at' => date('Y-m-d H:i:s')
					];
					
					log_message('info', 'Force stopped CP ETL process ID: ' . $process['id']);
					
				} catch (Exception $e) {
					log_message('error', 'Failed to update log for process ID ' . $process['id'] . ': ' . $e->getMessage());
				}
			}
			
			// Try to kill any running PHP processes related to CP ETL
			$this->_kill_cp_etl_processes();
			
			$this->response([
				'status' => true,
				'message' => 'CP ETL pipeline stopped successfully',
				'stopped_count' => $stopped_count,
				'stopped_processes' => $stopped_processes,
				'timestamp' => date('Y-m-d H:i:s')
			], REST_Controller::HTTP_OK);
			
		} catch (Exception $e) {
			log_message('error', 'CP stop pipeline failed: ' . $e->getMessage());
			$this->response([
				'status' => false,
				'message' => 'Failed to stop CP ETL pipeline',
				'error' => $e->getMessage()
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
	}
	
	/**
	 * Kill running CP ETL processes
	 */
	private function _kill_cp_etl_processes()
	{
		try {
			// Kill processes containing 'run_cp_backfill' in command line
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				// Windows
				exec('taskkill /F /IM php.exe /FI "WINDOWTITLE eq *run_cp_backfill*" 2>nul', $output, $return);
			} else {
				// Linux/Unix - kill processes by grep pattern
				exec("pkill -f 'run_cp_backfill' 2>/dev/null", $output, $return);
				
				// Alternative: kill by process name if pkill fails
				if ($return !== 0) {
					exec("ps aux | grep 'run_cp_backfill' | grep -v grep | awk '{print \$2}' | xargs kill -9 2>/dev/null", $output, $return);
				}
			}
			
			log_message('info', 'Attempted to kill CP ETL processes, return code: ' . $return);
		} catch (Exception $e) {
			log_message('error', 'Failed to kill CP ETL processes: ' . $e->getMessage());
		}
	}

	// POST /api/etl_cp/clean - bersihkan semua data CP (tanpa menghapus cp_etl_logs)
	public function clean_post()
	{
		$log_id = null;
		try {
			$this->load->database();
			$tables = [
				'cp_activity_summary',
				'cp_course_summary',
				'cp_student_assignment_detail',
				'cp_student_profile',
				'cp_student_quiz_detail',
				'cp_student_resource_access',
				'cp_etl_watermarks',
			];

			// Log start (inprogress=2)
			$this->db->insert('cp_etl_logs', [
				'offset' => 0,
				'numrow' => 0,
				'type' => 'clear',
				' message' => 'CP clean start',
				'requested_start_date' => null,
				'extracted_start_date' => null,
				'extracted_end_date' => null,
				'status' => 2,
				'start_date' => date('Y-m-d H:i:s'),
				'end_date' => null,
				'duration_seconds' => null,
				'created_at' => date('Y-m-d H:i:s')
			]);
			$log_id = $this->db->insert_id();

			$summary = ['tables' => [], 'total_affected' => 0];
			foreach ($tables as $tbl) {
				$exists = $this->db->query("SHOW TABLES LIKE '$tbl'")->num_rows() > 0;
				if (!$exists) { continue; }
				$count_before = (int)$this->db->count_all($tbl);
				$summary['tables'][$tbl] = $count_before;
				$summary['total_affected'] += $count_before;
				try { $this->db->truncate($tbl); } catch (Exception $e) { $this->db->where('1=1'); $this->db->delete($tbl); }
			}

			// Finish log (finished=1)
			$this->db->where('id', $log_id)->update('cp_etl_logs', [
				'numrow' => $summary['total_affected'],
				'status' => 1,
				'end_date' => date('Y-m-d H:i:s'),
				' message' => 'CP clean completed'
			]);

			$this->response([
				'status' => true,
				'message' => 'CP data cleaned successfully',
				'log_id' => (int)$log_id,
				'summary' => $summary
			], REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			if ($log_id) {
				$this->db->where('id', $log_id)->update('cp_etl_logs', [
					'status' => 3,
					'end_date' => date('Y-m-d H:i:s'),
					' message' => $e->getMessage()
				]);
			}
			$this->response([
				'status' => false,
				'message' => 'Failed to clean CP data',
				'error' => $e->getMessage(),
				'log_id' => $log_id ? (int)$log_id : null
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	// GET /api/etl_cp/logs - list CP ETL logs (latest first)
	public function logs_get()
	{
		try {
			$this->load->database();
			$limit = (int) ($this->input->get('limit') ?: 50);
			$offset = (int) ($this->input->get('offset') ?: 0);
			$status = $this->input->get('status'); // optional: 1 finished, 2 inprogress, 3 failed

			$this->db->from('cp_etl_logs');
			if (!empty($status)) {
				$this->db->where('status', (int)$status);
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

	// GET /api/etl_cp/status - get latest CP ETL status
	public function status_get()
	{
		try {
			$this->load->database();
			
			// Get latest log entry
			$this->db->from('cp_etl_logs');
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
			
			// Get running count (status = 2 for inprogress)
			$this->db->from('cp_etl_logs');
			$this->db->where('status', 2);
			$running_count = $this->db->count_all_results();
			
			// Get recent activity (last 7 days)
			$this->db->from('cp_etl_logs');
			$this->db->where('start_date >=', date('Y-m-d H:i:s', strtotime('-7 days')));
			$recent_count = $this->db->count_all_results();
			
			// Get watermark data (last extracted and next to extract)
			$this->db->from('cp_etl_watermarks');
			$this->db->where('process_name', 'cp_etl');
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
			
			// Map status number to string
			$status_map = [
				1 => 'finished',
				2 => 'inprogress', 
				3 => 'failed'
			];
			
			$this->response([
				'status' => true,
				'data' => [
					'last_run' => [
						'id' => (int)$latest_log['id'],
						'start_date' => $latest_log['start_date'],
						'end_date' => $latest_log['end_date'],
						'status' => $status_map[$latest_log['status']] ?? 'unknown',
						'status_code' => (int)$latest_log['status'],
						'message' => $latest_log['message'],
						'type' => $latest_log['type'],
						'numrow' => (int)$latest_log['numrow'],
						'duration_seconds' => $latest_log['duration_seconds']
					],
					'currently_running' => $running_count,
					'recent_activity' => $recent_count,
					'watermark' => $watermark_info,
					'service' => 'CP'
				]
			], REST_Controller::HTTP_OK);
		} catch (Exception $e) {
			$this->response([
				'status' => false,
				'error' => $e->getMessage()
			], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
		}
	}
}