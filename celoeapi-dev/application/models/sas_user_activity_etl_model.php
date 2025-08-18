<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class sas_user_activity_etl_model extends CI_Model {

	public function __construct()
	{
		parent::__construct();
		$this->load->database();
	}

	/**
	 * Get ETL status
	 */
	public function get_etl_status()
	{
		// If status table is not present, return default
		if (!$this->db->table_exists('sas_etl_logs')) {
			return [
				'isRunning' => false,
				'lastRun' => null,
				'lastStatus' => 'never_run',
				'currentStatus' => null,
			];
		}
		$this->db->select('*');
		$this->db->from('sas_etl_logs');
		$this->db->where('process_name', 'user_activity_etl');
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit(1);
		
		$query = $this->db->get();
		$result = $query->row_array();
		
		if ($result) {
			$isRunning = ($result['status'] === 'running');
			$lastRun = $result['start_time'];
			$lastStatus = $result['status'];
		} else {
			$isRunning = false;
			$lastRun = null;
			$lastStatus = 'never_run';
		}
		
		return [
			'isRunning' => $isRunning,
			'lastRun' => $lastRun,
			'lastStatus' => $lastStatus,
			'currentStatus' => $result ?: null
		];
	}

	/**
	 * Update ETL status
	 */
	public function update_etl_status($status, $extraction_date = null, $parameters = null)
	{
		$data = [
			'process_name' => 'user_activity_etl',
			'status' => $status,
			'message' => is_array($parameters) && isset($parameters['message']) ? $parameters['message'] : null,
			'start_time' => date('Y-m-d H:i:s'),
			'extraction_date' => $extraction_date ?: date('Y-m-d', strtotime('-1 day')),
			'parameters' => $parameters ? json_encode($parameters) : null,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		];
		
		if ($status === 'completed' || $status === 'failed') {
			$data['end_time'] = date('Y-m-d H:i:s');
			$data['duration_seconds'] = time() - strtotime($data['start_time']);
		}
		
		if ($this->db->table_exists('sas_etl_logs')) {
			return $this->db->insert('sas_etl_logs', $data);
		}
		return false;
	}

	/**
	 * Create a single ETL log entry and return log_id
	 */
	public function create_etl_log($status = 'running', $extraction_date = null, $parameters = null)
	{
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		$data = [
			'process_name' => 'user_activity_etl',
			'status' => $status,
			'message' => is_array($parameters) && isset($parameters['message']) ? $parameters['message'] : null,
			'start_time' => date('Y-m-d H:i:s'),
			'extraction_date' => $extraction_date,
			'parameters' => $parameters ? json_encode($parameters) : null,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		];
		if (!$this->db->table_exists('sas_etl_logs')) {
			return null;
		}
		$this->db->insert('sas_etl_logs', $data);
		return $this->db->insert_id();
	}

	/**
	 * Finalize/update an existing ETL log by id
	 */
	public function finish_etl_log($log_id, $status = 'completed', $parameters = null)
	{
		if (!$log_id || !$this->db->table_exists('sas_etl_logs')) {
			return false;
		}
		// Get start_time to compute duration
		$row = $this->db->get_where('sas_etl_logs', ['id' => $log_id])->row_array();
		$start = isset($row['start_time']) ? strtotime($row['start_time']) : time();
		$data = [
			'status' => $status,
			'message' => is_array($parameters) && isset($parameters['message']) ? $parameters['message'] : null,
			'end_time' => date('Y-m-d H:i:s'),
			'duration_seconds' => max(0, time() - $start),
			'updated_at' => date('Y-m-d H:i:s')
		];
		if ($parameters) {
			$data['parameters'] = json_encode($parameters);
		}
		$this->db->where('id', $log_id)->update('sas_etl_logs', $data);
		return true;
	}

	/**
	 * Watermark helpers
	 */
	public function get_watermark_date($process_name = 'user_activity_etl')
	{
		if (!$this->db->table_exists('sas_etl_watermarks')) {
			return null;
		}
		$row = $this->db->get_where('sas_etl_watermarks', ['process_name' => $process_name])->row_array();
		return $row && isset($row['last_date']) ? $row['last_date'] : null;
	}

	public function update_watermark_date($date, $timestamp = null, $process_name = 'user_activity_etl')
	{
		if (!$this->db->table_exists('sas_etl_watermarks')) {
			return false;
		}
		$sql = "INSERT INTO sas_etl_watermarks (process_name, last_date, last_timecreated, updated_at)
				VALUES (?, ?, ?, NOW())
				ON DUPLICATE KEY UPDATE last_date = VALUES(last_date), last_timecreated = VALUES(last_timecreated), updated_at = NOW()";
		return $this->db->query($sql, [$process_name, $date, $timestamp]);
	}

	/**
	 * Export ETL data with pagination
	 */
	public function export_data($limit = 100, $offset = 0, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_user_activity_etl');
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('id', 'DESC');
		$this->db->limit($limit, $offset);
		
		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get ETL logs
	 */
	public function get_etl_logs($limit = 50, $offset = 0)
	{
		if (!$this->db->table_exists('sas_etl_logs')) {
			return [];
		}
		$this->db->select('*');
		$this->db->from('sas_etl_logs');
		$this->db->where('process_name', 'user_activity_etl');
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit($limit, $offset);
		
		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Clear ETL data for a specific date
	 */
	public function clear_data($date)
	{
		$this->db->where('extraction_date', $date);
		$this->db->delete('sas_user_activity_etl');
		
		return $this->db->affected_rows();
	}

	/**
	 * Clear ALL ETL data across SAS tables (no date filter)
	 */
	public function clear_all_etl_data()
	{
		$tables = [
			'sas_user_activity_etl',
			'sas_activity_counts_etl',
			'sas_user_counts_etl'
		];
		
		$summary = [
			'tables' => [],
			'total_affected' => 0
		];
		
		foreach ($tables as $table) {
			if ($this->db->table_exists($table)) {
				// count before truncate to report cleared rows
				$count_before = (int) $this->db->count_all($table);
				$summary['tables'][$table] = $count_before;
				$summary['total_affected'] += $count_before;
				
				// prefer TRUNCATE for speed; fallback to DELETE if needed
				try {
					$this->db->truncate($table);
				} catch (Exception $e) {
					$this->db->where('1 = 1');
					$this->db->delete($table);
				}
			} else {
				log_message('info', 'Table does not exist, skipping: ' . $table);
			}
		}
		
		return $summary;
	}

	/**
	 * Get activity counts for a course
	 */
	public function get_activity_counts($course_id, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_activity_counts_etl');
		$this->db->where('courseid', $course_id);
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('extraction_date', 'DESC');
		$this->db->limit(1);
		
		$query = $this->db->get();
		return $query->row_array();
	}

	/**
	 * Get user counts for a course
	 */
	public function get_user_counts($course_id, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_user_counts_etl');
		$this->db->where('courseid', $course_id);
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('extraction_date', 'DESC');
		$this->db->limit(1);
		
		$query = $this->db->get();
		return $query->row_array();
	}

	/**
	 * Get course summary
	 */
	public function get_course_summary($course_id, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_course_summary');
		$this->db->where('course_id', $course_id);
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('extraction_date', 'DESC');
		$this->db->limit(1);
		
		$query = $this->db->get();
		return $query->row_array();
	}

	/**
	 * Get student profiles
	 */
	public function get_student_profiles($limit = 100, $offset = 0, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_student_profile');
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('user_id', 'ASC');
		$this->db->limit($limit, $offset);
		
		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get quiz details for a student
	 */
	public function get_student_quiz_details($user_id, $course_id = null, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_student_quiz_detail');
		$this->db->where('user_id', $user_id);
		
		if ($course_id) {
			$this->db->where('course_id', $course_id);
		}
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('timestart', 'DESC');
		
		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get assignment details for a student
	 */
	public function get_student_assignment_details($user_id, $course_id = null, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_student_assignment_detail');
		$this->db->where('user_id', $user_id);
		
		if ($course_id) {
			$this->db->where('course_id', $course_id);
		}
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('timecreated', 'DESC');
		
		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get resource access for a student
	 */
	public function get_student_resource_access($user_id, $course_id = null, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_student_resource_access');
		$this->db->where('user_id', $user_id);
		
		if ($course_id) {
			$this->db->where('course_id', $course_id);
		}
		
		if ($date) {
			$this->db->where('access_date', $date);
		}
		
		$this->db->order_by('access_time', 'DESC');
		
		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get raw log data
	 */
	public function get_raw_logs($limit = 100, $offset = 0, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_raw_log');
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('time', 'DESC');
		$this->db->limit($limit, $offset);
		
		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get course activity summary
	 */
	public function get_course_activity_summary($course_id, $date = null)
	{
		$this->db->select('*');
		$this->db->from('sas_course_activity_summary');
		$this->db->where('course_id', $course_id);
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		
		$this->db->order_by('extraction_date', 'DESC');
		$this->db->limit(1);
		
		$query = $this->db->get();
		return $query->row_array();
	}

	/**
	 * Get user activity data from ETL table with pagination
	 */
	public function get_user_activity_etl($course_id = null, $date = null, $limit = 100, $offset = 0)
	{
		$this->db->select('*');
		$this->db->from('sas_user_activity_etl');

		// Filter records where course_id is not null
		$this->db->where('course_id IS NOT NULL');
		if ($course_id) {
			$this->db->where('course_id', $course_id);
		}
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		// If date is null, no date filter is applied - returns all data
		
		$this->db->order_by('id', 'DESC');
		$this->db->limit($limit, $offset);
		
		$query = $this->db->get();
		
		// Log for debugging
		log_message('debug', 'get_user_activity_etl - course_id: ' . $course_id . ', date: ' . $date . ', limit: ' . $limit . ', offset: ' . $offset . ', result_count: ' . count($query->result_array()));
		
		return $query->result_array();
	}

	/**
	 * Get total count for user activity ETL data
	 */
	public function get_user_activity_total_count($course_id = null, $date = null)
	{
		$this->db->select('COUNT(*) as total');
		$this->db->from('sas_user_activity_etl');

		// Filter records where course_id is not null
		$this->db->where('course_id IS NOT NULL');
		
		if ($course_id) {
			$this->db->where('course_id', $course_id);
		}
		
		if ($date) {
			$this->db->where('extraction_date', $date);
		}
		// If date is null, no date filter is applied - returns total count of all data
		
		$query = $this->db->get();
		$total = $query->row()->total;
		
		// Log for debugging
		log_message('debug', 'get_user_activity_total_count - course_id: ' . $course_id . ', date: ' . $date . ', total: ' . $total);
		
		return $total;
	}

	/**
	 * Test function to verify data retrieval with different parameters
	 */
	public function test_data_retrieval()
	{
		log_message('info', '=== Testing Data Retrieval ===');
		
		// Test 1: Get all data (date = null)
		$all_data = $this->get_user_activity_etl(null, null, 10, 0);
		$all_count = $this->get_user_activity_total_count(null, null);
		log_message('info', 'Test 1 - All data: count=' . count($all_data) . ', total=' . $all_count);
		
		// Test 2: Get data with specific date
		$date_data = $this->get_user_activity_etl(null, date('Y-m-d', strtotime('-1 day')), 10, 0);
		$date_count = $this->get_user_activity_total_count(null, date('Y-m-d', strtotime('-1 day')));
		log_message('info', 'Test 2 - Date filtered: count=' . count($date_data) . ', total=' . $date_count);
		
		// Test 3: Get data with course_id
		$course_data = $this->get_user_activity_etl('123', null, 10, 0);
		$course_count = $this->get_user_activity_total_count('123', null);
		log_message('info', 'Test 3 - Course filtered: count=' . count($course_data) . ', total=' . $course_count);
		
		return [
			'all_data_count' => count($all_data),
			'all_total' => $all_count,
			'date_data_count' => count($date_data),
			'date_total' => $date_count,
			'course_data_count' => count($course_data),
			'course_total' => $course_count
		];
	}

	/**
	 * Get activity counts data from source
	 */
	public function get_activity_counts_all($start_date = null, $end_date = null, $limit = null, $offset = 0)
	{
		// Use moodle database for source data
		$moodle_db = $this->load->database('moodle', TRUE);
		
		$sql = "
			SELECT
				courseid,
				COUNT(CASE WHEN component = 'mod_resource' THEN 1 END) AS File_Views,
				COUNT(CASE WHEN component = 'mod_page' THEN 1 END) AS Video_Views,
				COUNT(CASE WHEN component = 'mod_forum' THEN 1 END) AS Forum_Views,
				COUNT(CASE WHEN component = 'mod_quiz' THEN 1 END) AS Quiz_Views,
				COUNT(CASE WHEN component = 'mod_assign' THEN 1 END) AS Assignment_Views,
				COUNT(CASE WHEN component = 'mod_url' THEN 1 END) AS URL_Views,
				DATEDIFF(FROM_UNIXTIME(MAX(timecreated)), FROM_UNIXTIME(MIN(timecreated))) + 1 AS ActiveDays
			FROM mdl_logstore_standard_log
			WHERE contextlevel = 70
			  AND action = 'viewed'
		";
		
		// Add date filters if provided
		if ($start_date && $end_date) {
			$start_timestamp = strtotime($start_date . ' 00:00:00');
			$end_timestamp = strtotime($end_date . ' 23:59:59');
			$sql .= " AND timecreated >= {$start_timestamp} AND timecreated <= {$end_timestamp}";
		}
		
		$sql .= " GROUP BY courseid";
		
		if ($limit !== null) {
			$sql .= " LIMIT {$limit} OFFSET {$offset}";
		}
		
		$query = $moodle_db->query($sql);
		return $query->result_array();
	}

	/**
	 * Get user counts data from source
	 */
	public function get_user_counts_all($start_date = null, $end_date = null, $limit = null, $offset = 0)
	{
		// Use moodle database for source data
		$moodle_db = $this->load->database('moodle', TRUE);
		
		$sql = "
			SELECT
				ctx.instanceid AS courseid,
				COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
				COUNT(DISTINCT CASE WHEN ra.roleid IN (3, 4) THEN ra.userid END) AS Num_Teachers
			FROM mdl_role_assignments ra
			JOIN mdl_context ctx ON ra.contextid = ctx.id
			WHERE ctx.contextlevel = 70
		";
		
		// Add date filters if provided - for user counts, we filter by timemodified
		if ($start_date && $end_date) {
			$start_timestamp = strtotime($start_date . ' 00:00:00');
			$end_timestamp = strtotime($end_date . ' 23:59:59');
			$sql .= " AND ra.timemodified >= {$start_timestamp} AND ra.timemodified <= {$end_timestamp}";
		}
		
		$sql .= " GROUP BY ctx.instanceid";
		
		if ($limit !== null) {
			$sql .= " LIMIT {$limit} OFFSET {$offset}";
		}
		
		$query = $moodle_db->query($sql);
		return $query->result_array();
	}

	/**
	 * Get user activity data with pagination from ETL tables using SQL JOIN
	 */
	public function get_user_activity_data_paginated($course_id = null, $date = null, $limit = null, $offset = 0)
	{
		$date = $date ?: date('Y-m-d', strtotime('-1 day'));
		
		// Get database names from config
		$main_db_name = $this->db->database;
		
		// Get moodle database config - fix the config access
		$moodle_db_config = $this->config->item('moodle');
		if (!$moodle_db_config) {
			// Fallback to hardcoded values if config not found
			$moodle_db_config = [
				'hostname' => 'db',
				'username' => 'moodleuser',
				'password' => 'moodlepass',
				'database' => 'moodle',
				'dbprefix' => 'mdl_'
			];
		}
		
		$moodle_db_name = $moodle_db_config['database'] ?: 'moodle';
		
		// Fallback if config is not available
		if (empty($moodle_db_name)) {
			$moodle_db_name = 'moodle'; // Default fallback
		}
		
		if (empty($main_db_name)) {
			$main_db_name = 'celoeapi'; // Default fallback
		}
		
		// Debug: Log database names and config
		log_message('debug', 'Main DB: ' . $main_db_name . ', Moodle DB: ' . $moodle_db_name);
		log_message('debug', 'Moodle config: ' . json_encode($moodle_db_config));
		
		// Check if ETL tables exist before trying to JOIN with them
		$etl_tables_exist = $this->_check_etl_tables_exist();
		
		if (!$etl_tables_exist) {
			log_message('info', 'ETL tables do not exist yet, returning empty result');
			return [];
		}
		
		// Use normalized course dimension table (sas_courses)
		$sql = "
			SELECT DISTINCT
				c.course_id AS course_id,
				c.program_id AS program_id,
				c.faculty_id AS faculty_id,
				c.subject_id AS `Subject_ID`,
				c.course_name AS `Course_Name`,
				c.course_shortname AS `Course_Shortname`,
				COALESCE(uc.num_teachers, 0) AS `Num_Teachers`,
				COALESCE(uc.num_students, 0) AS `Num_Students`,
				COALESCE(ac.file_views, 0) AS `File_Views`,
				COALESCE(ac.video_views, 0) AS `Video_Views`,
				COALESCE(ac.forum_views, 0) AS `Forum_Views`,
				COALESCE(ac.quiz_views, 0) AS `Quiz_Views`,
				COALESCE(ac.assignment_views, 0) AS `Assignment_Views`,
				COALESCE(ac.url_views, 0) AS `URL_Views`,
				(COALESCE(ac.file_views, 0) + COALESCE(ac.video_views, 0) + COALESCE(ac.forum_views, 0) + COALESCE(ac.quiz_views, 0) + COALESCE(ac.assignment_views, 0) + COALESCE(ac.url_views, 0)) AS `Total_Views`,
				COALESCE(ac.active_days, 0) AS `Active_Days`,
				ROUND(
					(COALESCE(ac.file_views, 0) + COALESCE(ac.video_views, 0) + COALESCE(ac.forum_views, 0) + COALESCE(ac.quiz_views, 0) + COALESCE(ac.assignment_views, 0) + COALESCE(ac.url_views, 0))
					/ NULLIF(uc.num_students, 0)
					/ NULLIF(ac.active_days, 0),
					2
				) AS `Avg_Activity_per_Student_per_Day`
			FROM `{$main_db_name}`.`sas_courses` c
			LEFT JOIN `{$main_db_name}`.`sas_activity_counts_etl` ac ON c.course_id = ac.courseid AND ac.extraction_date = ?
			LEFT JOIN `{$main_db_name}`.`sas_user_counts_etl` uc ON c.course_id = uc.courseid AND uc.extraction_date = ?
			WHERE c.visible = 1 AND c.subject_id IS NOT NULL AND c.subject_id != ''
		";
		
		$params = [$date, $date];
		
		if ($course_id) {
			$sql .= " AND c.id = ?";
			$params[] = $course_id;
		}
		
		$sql .= " GROUP BY c.course_id, c.program_id, c.faculty_id, c.subject_id, c.course_name, c.course_shortname, uc.num_teachers, uc.num_students, ac.file_views, ac.video_views, ac.forum_views, ac.quiz_views, ac.assignment_views, ac.url_views, ac.active_days";
		
		$sql .= " ORDER BY c.course_id ASC";
		
		if ($limit !== null) {
			$sql .= " LIMIT {$limit} OFFSET {$offset}";
		}
		
		// Debug: Log the SQL query
		log_message('debug', 'SQL Query: ' . $sql);
		log_message('debug', 'SQL Params: ' . json_encode($params));
		
		// Execute query using main database connection
		$query = $this->db->query($sql, $params);
		return $query->result_array();
	}

	/**
	 * Check if ETL tables exist
	 */
	private function _check_etl_tables_exist()
	{
		$required_tables = ['sas_user_activity_etl', 'sas_activity_counts_etl', 'sas_user_counts_etl', 'sas_courses'];
		
		foreach ($required_tables as $table) {
			if (!$this->db->table_exists($table)) {
				log_message('info', 'ETL table does not exist: ' . $table);
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Insert user activity data into ETL table
	 */
	public function insert_user_activity_etl($data, $extraction_date = null)
	{
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		
		// Check if table exists before proceeding
		if (!$this->db->table_exists('sas_user_activity_etl')) {
			log_message('error', 'Table sas_user_activity_etl does not exist. Please run migrations first.');
			return false;
		}
		
		// Clear existing data for this date
		$this->db->where('extraction_date', $extraction_date);
		$this->db->delete('sas_user_activity_etl');
		
		foreach ($data as $row) {
			$etl_data = [
				'course_id' => isset($row['course_id']) ? $row['course_id'] : null,
				'num_teachers' => isset($row['Num_Teachers']) ? $row['Num_Teachers'] : (isset($row['num_teachers']) ? $row['num_teachers'] : 0),
				'num_students' => isset($row['Num_Students']) ? $row['Num_Students'] : (isset($row['num_students']) ? $row['num_students'] : 0),
				'file_views' => isset($row['File_Views']) ? $row['File_Views'] : (isset($row['file_views']) ? $row['file_views'] : 0),
				'video_views' => isset($row['Video_Views']) ? $row['Video_Views'] : (isset($row['video_views']) ? $row['video_views'] : 0),
				'forum_views' => isset($row['Forum_Views']) ? $row['Forum_Views'] : (isset($row['forum_views']) ? $row['forum_views'] : 0),
				'quiz_views' => isset($row['Quiz_Views']) ? $row['Quiz_Views'] : (isset($row['quiz_views']) ? $row['quiz_views'] : 0),
				'assignment_views' => isset($row['Assignment_Views']) ? $row['Assignment_Views'] : (isset($row['assignment_views']) ? $row['assignment_views'] : 0),
				'url_views' => isset($row['URL_Views']) ? $row['URL_Views'] : (isset($row['url_views']) ? $row['url_views'] : 0),
				'total_views' => isset($row['Total_Views']) ? $row['Total_Views'] : (isset($row['total_views']) ? $row['total_views'] : 0),
				'avg_activity_per_student_per_day' => isset($row['Avg_Activity_per_Student_per_Day']) ? $row['Avg_Activity_per_Student_per_Day'] : (isset($row['avg_activity_per_student_per_day']) ? $row['avg_activity_per_student_per_day'] : null),
				'active_days' => isset($row['Active_Days']) ? $row['Active_Days'] : (isset($row['active_days']) ? $row['active_days'] : 0),
				'extraction_date' => $extraction_date,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s')
			];
			$this->db->insert('sas_user_activity_etl', $etl_data);
		}
		
		return true;
	}

	/**
	 * Insert activity counts into ETL table
	 */
	public function insert_activity_counts_etl($data, $extraction_date = null)
	{
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		
		// Check if table exists before proceeding
		if (!$this->db->table_exists('sas_activity_counts_etl')) {
			log_message('error', 'Table sas_activity_counts_etl does not exist. Please run migrations first.');
			return false;
		}
		
		// Clear existing data for this date
		$this->db->where('extraction_date', $extraction_date);
		$this->db->delete('sas_activity_counts_etl');
		
		foreach ($data as $row) {
			$etl_data = [
				'courseid' => $row['courseid'] ?: null,
				'file_views' => $row['File_Views'] ?: 0,
				'video_views' => $row['Video_Views'] ?: 0,
				'forum_views' => $row['Forum_Views'] ?: 0,
				'quiz_views' => $row['Quiz_Views'] ?: 0,
				'assignment_views' => $row['Assignment_Views'] ?: 0,
				'url_views' => $row['URL_Views'] ?: 0,
				'active_days' => $row['ActiveDays'] ?: 0,
				'extraction_date' => $extraction_date,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s')
			];
			
			$this->db->insert('sas_activity_counts_etl', $etl_data);
		}
		
		return true;
	}

	/**
	 * Insert user counts into ETL table
	 */
	public function insert_user_counts_etl($data, $extraction_date = null)
	{
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		
		// Check if table exists before proceeding
		if (!$this->db->table_exists('sas_user_counts_etl')) {
			log_message('error', 'Table sas_user_counts_etl does not exist. Please run migrations first.');
			return false;
		}
		
		// Clear existing data for this date
		$this->db->where('extraction_date', $extraction_date);
		$this->db->delete('sas_user_counts_etl');
		
		foreach ($data as $row) {
			$etl_data = [
				'courseid' => $row['courseid'] ?: null,
				'num_students' => $row['Num_Students'] ?: 0,
				'num_teachers' => $row['Num_Teachers'] ?: 0,
				'extraction_date' => $extraction_date,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s')
			];
			
			$this->db->insert('sas_user_counts_etl', $etl_data);
		}
		
		return true;
	}

	/**
	 * Clear ETL data for a specific date
	 */
	public function clear_etl_data($date)
	{
		$tables = [
			'sas_user_activity_etl',
			'sas_activity_counts_etl',
			'sas_user_counts_etl'
		];
		
		$total_affected = 0;
		
		foreach ($tables as $table) {
			// Check if table exists before trying to delete from it
			if ($this->db->table_exists($table)) {
				$this->db->where('extraction_date', $date);
				$this->db->delete($table);
				$total_affected += $this->db->affected_rows();
			} else {
				log_message('info', 'Table does not exist, skipping: ' . $table);
			}
		}
		
		return $total_affected;
	}

	/**
	 * Execute scheduler flow
	 */
	public function execute_scheduler_flow()
	{
		// If scheduler table is not present, skip flow gracefully
		if (!$this->db->table_exists('sas_log_scheduler')) {
			return [
				'status' => 'skipped',
				'message' => 'sas_log_scheduler table not found; scheduler flow disabled',
			];
		}
		try {
			// Step 1: Get Scheduler Data for Extraction
			$scheduler_data = $this->get_scheduler_data_for_extraction();
			
			// Step 2: Decision: Not empty data?
			if ($this->is_scheduler_data_empty($scheduler_data)) {
				// Step 3a: Set 1st Date Extraction (Y-m-d)
				$this->set_first_date_extraction();
				
				// Step 3b: Save Data Scheduler
				$scheduler_data = $this->get_scheduler_data_for_extraction();
			}
			
			// Step 4: Decision: Status is not running?
			if (!$this->is_status_not_running($scheduler_data)) {
				return [
					'status' => 'skipped',
					'message' => 'Scheduler is already running',
					'scheduler_data' => $scheduler_data
				];
			}
			
			// Step 5: Decision: Is enddate != (H-1 23:59)?
			if (!$this->is_enddate_not_yesterday_2359($scheduler_data)) {
				return [
					'status' => 'skipped',
					'message' => 'Extraction for yesterday is already complete',
					'scheduler_data' => $scheduler_data
				];
			}
			
			// Step 6: Run Extraction (Y-m-d)
			$result = $this->run_extraction_with_current_date();
			
			return [
				'status' => 'success',
				'message' => 'Scheduler flow executed successfully',
				'result' => $result,
				'scheduler_data' => $scheduler_data
			];
			
		} catch (Exception $e) {
			// Update scheduler status to failed
			if (isset($scheduler_data['id'])) {
				$this->db->where('id', $scheduler_data['id']);
				$this->db->update('sas_log_scheduler', [
					'status' => 3, // Failed
				]);
			}
			
			throw $e;
		}
	}

	/**
	 * Get scheduler data for extraction
	 */
	public function get_scheduler_data_for_extraction()
	{
		if (!$this->db->table_exists('sas_log_scheduler')) {
			return [];
		}
		$this->db->select('*');
		$this->db->from('sas_log_scheduler');
		$this->db->order_by('id', 'DESC');
		$this->db->limit(1);
		
		$query = $this->db->get();
		return $query->row_array();
	}

	/**
	 * Check if scheduler data is empty
	 */
	public function is_scheduler_data_empty($scheduler_data)
	{
		return empty($scheduler_data);
	}

	/**
	 * Set first date extraction
	 */
	public function set_first_date_extraction()
	{
		if (!$this->db->table_exists('sas_log_scheduler')) {
			return false;
		}
		$current_date = date('Y-m-d');
		$yesterday = date('Y-m-d', strtotime('-1 day'));
		
		$data = [
			'batch_name' => 'user_activity',
			'offset' => 0,
			'numrow' => 0,
			'status' => 0, // Not running
			'limit_size' => 1000,
			'start_date' => $yesterday . ' 00:00:00',
			'end_date' => $yesterday . ' 23:59:59',
			'created_at' => date('Y-m-d H:i:s')
		];
		
		log_message('info', 'First date extraction initialized for user_activity batch');
		return $this->db->insert('sas_log_scheduler', $data);
	}

	/**
	 * Check if status is not running
	 */
	public function is_status_not_running($scheduler_data)
	{
		return $scheduler_data['status'] != 2; // 2 = inprogress (running), 1 = finished, 3 = failed
	}

	/**
	 * Check if enddate is not equal to H-1 23:59
	 */
	public function is_enddate_not_yesterday_2359($scheduler_data)
	{
		$yesterday_2359 = date('Y-m-d', strtotime('-1 day')) . ' 23:59:59';
		return $scheduler_data['end_date'] != $yesterday_2359;
	}

	/**
	 * Run extraction with current date
	 */
	public function run_extraction_with_current_date()
	{
		$current_date = date('Y-m-d');
		$yesterday = date('Y-m-d', strtotime('-1 day'));
		
		// Update scheduler status to inprogress (2)
		$this->update_scheduler_status_inprogress($current_date);
		
		// Run the ETL process
		$result = $this->run_etl_process($current_date);
		
		// Update scheduler status to finished (1)
		$this->update_scheduler_status_finished($current_date);
		
		return $result;
	}

	/**
	 * Get latest scheduler ID
	 */
	private function get_latest_scheduler_id()
	{
		if (!$this->db->table_exists('sas_log_scheduler')) {
			return null;
		}
		$this->db->select('id');
		$this->db->from('sas_log_scheduler');
		$this->db->order_by('id', 'DESC');
		$this->db->limit(1);
		
		$query = $this->db->get();
		$result = $query->row();
		return $result ? $result->id : null;
	}

	/**
	 * Update scheduler status to finished (1)
	 */
	public function update_scheduler_status_finished($extraction_date = null)
	{
		if (!$this->db->table_exists('sas_log_scheduler')) {
			return true;
		}
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		
		// Get latest scheduler record
		$scheduler_id = $this->get_latest_scheduler_id();
		
		if ($scheduler_id) {
			$this->db->where('id', $scheduler_id);
			$this->db->update('sas_log_scheduler', [
				'status' => 1, // Finished (completed)
				'end_date' => date('Y-m-d H:i:s'),
			]);
			
			log_message('info', 'Scheduler status updated to finished (1) for date: ' . $extraction_date);
		} else {
			log_message('warning', 'No scheduler record found to update status for date: ' . $extraction_date);
		}
		
		return true;
	}
	
	/**
	 * Create new scheduler record for user_activity batch
	 */
	public function create_scheduler_record($extraction_date = null)
	{
		if (!$this->db->table_exists('sas_log_scheduler')) {
			return false;
		}
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		
		$data = [
			'batch_name' => 'user_activity',
			'offset' => 0,
			'numrow' => 0,
			'status' => 2, // Inprogress (running)
			'limit_size' => 1000,
			'start_date' => date('Y-m-d H:i:s'),
			'end_date' => null,
			'created_at' => date('Y-m-d H:i:s')
		];
		
		$result = $this->db->insert('sas_log_scheduler', $data);
		
		if ($result) {
			log_message('info', 'New scheduler record created for user_activity batch with date: ' . $extraction_date);
		}
		
		return $result;
	}
	
	/**
	 * Update scheduler status to inprogress (2)
	 */
	public function update_scheduler_status_inprogress($extraction_date = null)
	{
		if (!$this->db->table_exists('sas_log_scheduler')) {
			return true;
		}
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		
		// Create new scheduler record if none exists
		$scheduler_id = $this->get_latest_scheduler_id();
		if (!$scheduler_id) {
			$this->create_scheduler_record($extraction_date);
			$scheduler_id = $this->get_latest_scheduler_id();
		}
		
		if ($scheduler_id) {
			$this->db->where('id', $scheduler_id);
			$this->db->update('sas_log_scheduler', [
				'status' => 2, // Inprogress (running)
				'start_date' => date('Y-m-d H:i:s'),
			]);
			
			log_message('info', 'Scheduler status updated to inprogress (2) for date: ' . $extraction_date);
		} else {
			log_message('warning', 'No scheduler record found to update status for date: ' . $extraction_date);
		}
		
		return true;
	}
	
	/**
	 * Update scheduler status to failed (3)
	 */
	public function update_scheduler_status_failed($extraction_date = null, $error_message = null)
	{
		if (!$this->db->table_exists('sas_log_scheduler')) {
			return true;
		}
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		
		// Get latest scheduler record
		$scheduler_id = $this->get_latest_scheduler_id();
		
		if ($scheduler_id) {
			$this->db->where('id', $scheduler_id);
			$this->db->update('sas_log_scheduler', [
				'status' => 3, // Failed
				'end_date' => date('Y-m-d H:i:s'),
			]);
			
			log_message('error', 'Scheduler status updated to failed (3) for date: ' . $extraction_date . ' - Error: ' . $error_message);
		} else {
			log_message('warning', 'No scheduler record found to update status for date: ' . $extraction_date);
		}
		
		return true;
	}
	
	/**
	 * Run ETL process
	 */
	public function run_etl_process($extraction_date = null)
	{
		$extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
		
		try {
			// Start ETL process
			$this->update_etl_status('running', $extraction_date);
			
			// Get complete user activity data
			$user_activity_data = $this->get_user_activity_data_paginated(null, $extraction_date);
			$this->insert_user_activity_etl($user_activity_data, $extraction_date);
			
			// Complete ETL process
			$this->update_etl_status('completed', $extraction_date);
			
			return [
				' status' => 'success',
				'message' => 'ETL process completed successfully',
				'records_processed' => count($user_activity_data),
				'extraction_date' => $extraction_date
			];
			
		} catch (Exception $e) {
			// Mark ETL process as failed
			$this->update_etl_status('failed', $extraction_date, ['error' => $e->getMessage()]);
			
			throw $e;
		}
	}
} 