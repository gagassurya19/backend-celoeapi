<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Activity_counts_model extends CI_Model {

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
        
        // Clear existing data for this extraction date first
        $this->db->where('extraction_date', $extraction_date);
        $this->db->delete('activity_counts_etl');
        
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
            $this->db->insert('activity_counts_etl', $etl_data);
        }
        
        return true;
    }

    /**
     * Get activity counts from ETL table
     */
    public function get_activity_counts_etl($course_id = null, $date = null)
    {
        $this->db->select('*');
        $this->db->from('activity_counts_etl');
        
        if ($course_id) {
            $this->db->where('courseid', $course_id);
        }
        
        if ($date) {
            $this->db->where('extraction_date', $date);
        }
        
        $this->db->order_by('extraction_date', 'DESC');
        
        $query = $this->db->get();
        return $query->result_array();
    }
} 