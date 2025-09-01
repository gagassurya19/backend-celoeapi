<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class sas_actvity_counts_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get activity counts for all courses with pagination
     * Based on ActivityCounts view from finals_query_mysql57.sql
     */
    public function get_activity_counts_all($limit = null, $offset = 0)
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
            GROUP BY courseid
        ";
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $query = $moodle_db->query($sql);
        return $query->result_array();
    }

    /**
     * Get total count of activity counts for pagination
     */
    public function get_activity_counts_total()
    {
        // Use moodle database for source data
        $moodle_db = $this->load->database('moodle', TRUE);
        
        $sql = "
            SELECT COUNT(DISTINCT courseid) as total
            FROM mdl_logstore_standard_log
            WHERE contextlevel = 70
              AND action = 'viewed'
        ";
        
        $query = $moodle_db->query($sql);
        return $query->row()->total;
    }

    /**
     * Get activity counts for specific course
     */
    public function get_activity_counts_by_course($course_id)
    {
        $sql = "
            SELECT DISTINCT
                courseid,
                SUM(CASE WHEN component = 'mod_resource' THEN 1 ELSE 0 END) AS File_Views,
                SUM(CASE WHEN component = 'mod_page' THEN 1 ELSE 0 END) AS Video_Views,
                SUM(CASE WHEN component = 'mod_forum' THEN 1 ELSE 0 END) AS Forum_Views,
                SUM(CASE WHEN component = 'mod_quiz' THEN 1 ELSE 0 END) AS Quiz_Views,
                SUM(CASE WHEN component = 'mod_assign' THEN 1 ELSE 0 END) AS Assignment_Views,
                SUM(CASE WHEN component = 'mod_url' THEN 1 ELSE 0 END) AS URL_Views,
                DATEDIFF(FROM_UNIXTIME(MAX(timecreated)), FROM_UNIXTIME(MIN(timecreated))) + 1 AS ActiveDays
            FROM mdl_logstore_standard_log
            WHERE contextlevel = 70
              AND action = 'viewed'
              AND courseid = ?
            GROUP BY courseid
            ORDER BY courseid ASC
        ";
        
        $query = $this->db->query($sql, [$course_id]);
        return $query->row_array();
    }

    /**
     * Get activity counts for multiple courses
     */
    public function get_activity_counts_by_courses($course_ids)
    {
        if (empty($course_ids)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        
        $sql = "
            SELECT DISTINCT
                courseid,
                SUM(CASE WHEN component = 'mod_resource' THEN 1 ELSE 0 END) AS File_Views,
                SUM(CASE WHEN component = 'mod_page' THEN 1 ELSE 0 END) AS Video_Views,
                SUM(CASE WHEN component = 'mod_forum' THEN 1 ELSE 0 END) AS Forum_Views,
                SUM(CASE WHEN component = 'mod_quiz' THEN 1 ELSE 0 END) AS Quiz_Views,
                SUM(CASE WHEN component = 'mod_assign' THEN 1 ELSE 0 END) AS Assignment_Views,
                SUM(CASE WHEN component = 'mod_url' THEN 1 ELSE 0 END) AS URL_Views,
                DATEDIFF(FROM_UNIXTIME(MAX(timecreated)), FROM_UNIXTIME(MIN(timecreated))) + 1 AS ActiveDays
            FROM mdl_logstore_standard_log
            WHERE contextlevel = 70
              AND action = 'viewed'
              AND courseid IN ($placeholders)
            GROUP BY courseid
            ORDER BY courseid ASC
        ";
        
        $query = $this->db->query($sql, $course_ids);
        return $query->result_array();
    }

    /**
     * Get activity counts for date range with pagination
     */
    public function get_activity_counts_by_date_range($start_date, $end_date, $limit = null, $offset = 0)
    {
        // Use moodle database for source data
        $moodle_db = $this->load->database('moodle', TRUE);
        
        $sql = "
            SELECT DISTINCT
                courseid,
                COUNT(CASE WHEN component = 'mod_resource' AND objecttable IS NOT NULL THEN 1 END) AS File_Views,
		        COUNT(CASE WHEN component = 'mod_page' AND objecttable IS NOT NULL THEN 1 END) AS Video_Views,
		        COUNT(CASE WHEN component = 'mod_forum' AND objecttable IS NOT NULL THEN 1 END) AS Forum_Views,
		        COUNT(CASE WHEN component = 'mod_quiz' AND objecttable IS NOT NULL THEN 1 END) AS Quiz_Views,
		        COUNT(CASE WHEN component = 'mod_assign' AND objecttable IS NOT NULL THEN 1 END) AS Assignment_Views,
		        COUNT(CASE WHEN component = 'mod_url' AND objecttable IS NOT NULL THEN 1 END) AS URL_Views,
		        DATEDIFF(FROM_UNIXTIME(MAX(timecreated)), FROM_UNIXTIME(MIN(timecreated))) + 1 AS ActiveDays
            FROM mdl_logstore_standard_log
            WHERE contextlevel = 70
              AND action = 'viewed'
              AND DATE(FROM_UNIXTIME(timecreated)) BETWEEN ? AND ?
            GROUP BY courseid
            ORDER BY courseid ASC
        ";
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $query = $moodle_db->query($sql, [$start_date, $end_date]);
        return $query->result_array();
    }

    /**
     * Get total count of activity counts for date range
     */
    public function get_activity_counts_total_by_date_range($start_date, $end_date)
    {
        // Use moodle database for source data
        $moodle_db = $this->load->database('moodle', TRUE);
        
        $sql = "
            SELECT COUNT(DISTINCT courseid) as total
            FROM mdl_logstore_standard_log
            WHERE contextlevel = 70
              AND action = 'viewed'
              AND DATE(FROM_UNIXTIME(timecreated)) BETWEEN ? AND ?
        ";
        
        $query = $moodle_db->query($sql, [$start_date, $end_date]);
        return $query->row()->total;
    }

    /**
     * Insert activity counts into ETL table
     */
    public function insert_activity_counts_etl($data, $extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
        etl_log('info', 'Insert activity counts start', ['extraction_date' => $extraction_date, 'rows' => is_array($data) ? count($data) : 0]);
        
        // Check if table exists before proceeding
        if (!$this->db->table_exists('sas_activity_counts_etl')) {
            log_message('error', 'Table sas_activity_counts_etl does not exist. Please run migrations first.');
            return false;
        }
        
        // Clear existing data for this extraction date first
        $sql = "DELETE FROM sas_activity_counts_etl WHERE extraction_date = ?";
        $this->db->query($sql, [$extraction_date]);
        
        // Insert new data
        foreach ($data as $row) {
            $etl_data = [
                'courseid' => $row['courseid'],
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
            
            // Insert new record (no need to check existing since we cleared first)
            $sql = "INSERT INTO sas_activity_counts_etl (courseid, file_views, video_views, forum_views, quiz_views, assignment_views, url_views, active_days, extraction_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $this->db->query($sql, [
                $etl_data['courseid'], $etl_data['file_views'], $etl_data['video_views'],
                $etl_data['forum_views'], $etl_data['quiz_views'], $etl_data['assignment_views'],
                $etl_data['url_views'], $etl_data['active_days'], $etl_data['extraction_date'],
                $etl_data['created_at'], $etl_data['updated_at']
            ]);
        }
        etl_log('info', 'Insert activity counts done', ['extraction_date' => $extraction_date]);
        
        return true;
    }

    /**
     * Get activity counts from ETL table
     */
    public function get_activity_counts_etl($course_id = null, $date = null)
    {
        $sql = "SELECT * FROM sas_activity_counts_etl";
        $params = [];
        $conditions = [];
        
        if ($course_id) {
            $conditions[] = "courseid = ?";
            $params[] = $course_id;
        }
        
        if ($date) {
            $conditions[] = "extraction_date = ?";
            $params[] = $date;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY extraction_date DESC";
        
        $query = $this->db->query($sql, $params);
        return $query->result_array();
    }
} 