<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class sas_user_counts_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get user counts for all courses with pagination
     * Based on UserCounts view from finals_query_mysql57.sql
     */
    public function get_user_counts_all($limit = null, $offset = 0)
    {
        // Use moodle database for source data
        $moodle_db = $this->load->database('moodle', TRUE);
        
        $sql = "
            SELECT
                ctx.instanceid AS courseid,
                COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
                COUNT(DISTINCT CASE WHEN ra.roleid = 4 THEN ra.userid END) AS Num_Teachers
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            WHERE ctx.contextlevel = 50
            GROUP BY ctx.instanceid
        ";
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $query = $moodle_db->query($sql);
        return $query->result_array();
    }

    /**
     * Get total count of user counts for pagination
     */
    public function get_user_counts_total()
    {
        // Use moodle database for source data
        $moodle_db = $this->load->database('moodle', TRUE);
        
        $sql = "
            SELECT COUNT(DISTINCT ctx.instanceid) as total
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            WHERE ctx.contextlevel = 50
        ";
        
        $query = $moodle_db->query($sql);
        return $query->row()->total;
    }

    /**
     * Get user counts for specific course
     */
    public function get_user_counts_by_course($course_id)
    {
        $sql = "
            SELECT
                ctx.instanceid AS courseid,
                COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
                COUNT(DISTINCT CASE WHEN ra.roleid = 4 THEN ra.userid END) AS Num_Teachers
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            WHERE ctx.contextlevel = 50
              AND ctx.instanceid = ?
            GROUP BY ctx.instanceid
        ";
        
        $query = $this->db->query($sql, [$course_id]);
        return $query->row_array();
    }

    /**
     * Get user counts for multiple courses
     */
    public function get_user_counts_by_courses($course_ids)
    {
        if (empty($course_ids)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        
        $sql = "
            SELECT
                ctx.instanceid AS courseid,
                COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
                COUNT(DISTINCT CASE WHEN ra.roleid IN (3, 4) THEN ra.userid END) AS Num_Teachers
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            WHERE ctx.contextlevel = 50
              AND ctx.instanceid IN ($placeholders)
            GROUP BY ctx.instanceid
        ";
        
        $query = $this->db->query($sql, $course_ids);
        return $query->result_array();
    }

    /**
     * Get user counts for date range with pagination
     */
    public function get_user_counts_by_date_range($start_date, $end_date, $limit = null, $offset = 0)
    {
        // Use moodle database for source data
        $moodle_db = $this->load->database('moodle', TRUE);
        
        $sql = "
            SELECT
                ctx.instanceid AS courseid,
                COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
                COUNT(DISTINCT CASE WHEN ra.roleid = 4 THEN ra.userid END) AS Num_Teachers
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            WHERE ctx.contextlevel = 50
              AND ra.timemodified >= ? AND ra.timemodified <= ?
            GROUP BY ctx.instanceid
            ORDER BY ctx.instanceid
        ";
        
        $start_timestamp = strtotime($start_date . ' 00:00:00');
        $end_timestamp = strtotime($end_date . ' 23:59:59');
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $query = $moodle_db->query($sql, [$start_timestamp, $end_timestamp]);
        return $query->result_array();
    }

    /**
     * Get total count of user counts for date range
     */
    public function get_user_counts_total_by_date_range($start_date, $end_date)
    {
        // Use moodle database for source data
        $moodle_db = $this->load->database('moodle', TRUE);
        
        $sql = "
            SELECT COUNT(DISTINCT ctx.instanceid) as total
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            WHERE ctx.contextlevel = 50
              AND ra.timemodified >= ? AND ra.timemodified <= ?
        ";
        
        $start_timestamp = strtotime($start_date . ' 00:00:00');
        $end_timestamp = strtotime($end_date . ' 23:59:59');
        
        $query = $moodle_db->query($sql, [$start_timestamp, $end_timestamp]);
        return $query->row()->total;
    }

    /**
     * Get user counts with role details
     */
    public function get_user_counts_with_roles($course_id = null)
    {
        $sql = "
            SELECT
                ctx.instanceid AS courseid,
                r.shortname AS role_name,
                r.id AS role_id,
                COUNT(DISTINCT ra.userid) AS user_count
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            JOIN mdl_role r ON ra.roleid = r.id
            WHERE ctx.contextlevel = 50
        ";
        
        if ($course_id) {
            $sql .= " AND ctx.instanceid = ?";
            $sql .= " GROUP BY ctx.instanceid, r.shortname, r.id";
            $query = $this->db->query($sql, [$course_id]);
        } else {
            $sql .= " GROUP BY ctx.instanceid, r.shortname, r.id";
            $query = $this->db->query($sql);
        }
        
        return $query->result_array();
    }

    /**
     * Get student list for a course
     */
    public function get_students_by_course($course_id)
    {
        $sql = "
            SELECT DISTINCT
                u.id AS user_id,
                u.firstname,
                u.lastname,
                u.email,
                u.idnumber
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            JOIN mdl_user u ON ra.userid = u.id
            WHERE ctx.contextlevel = 70
              AND ctx.instanceid = ?
              AND ra.roleid = 5
            ORDER BY u.firstname, u.lastname
        ";
        
        $query = $this->db->query($sql, [$course_id]);
        return $query->result_array();
    }

    /**
     * Get teacher list for a course
     */
    public function get_teachers_by_course($course_id)
    {
        $sql = "
            SELECT DISTINCT
                u.id AS user_id,
                u.firstname,
                u.lastname,
                u.email,
                u.idnumber,
                r.shortname AS role_name
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            JOIN mdl_user u ON ra.userid = u.id
            JOIN mdl_role r ON ra.roleid = r.id
            WHERE ctx.contextlevel = 50
              AND ctx.instanceid = ?
            AND ra.roleid = 4
            ORDER BY u.firstname, u.lastname
        ";
        
        $query = $this->db->query($sql, [$course_id]);
        return $query->result_array();
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
        
        // Clear existing data for this extraction date first
        $this->db->where('extraction_date', $extraction_date);
        $this->db->delete('sas_user_counts_etl');
        
        // Insert new data
        foreach ($data as $row) {
            $etl_data = [
                'courseid' => $row['courseid'],
                'num_students' => $row['Num_Students'] ?: 0,
                'num_teachers' => $row['Num_Teachers'] ?: 0,
                'extraction_date' => $extraction_date,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Insert new record (no need to check existing since we cleared first)
            $this->db->insert('sas_user_counts_etl', $etl_data);
        }
        
        return true;
    }

    /**
     * Get user counts from ETL table
     */
    public function get_user_counts_etl($course_id = null, $date = null)
    {
        $this->db->select('*');
        $this->db->from('sas_user_counts_etl');
        
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

    /**
     * Get total users by role
     */
    public function get_total_users_by_role()
    {
        $sql = "
            SELECT
                r.shortname AS role_name,
                r.id AS role_id,
                COUNT(DISTINCT ra.userid) AS total_users
            FROM mdl_role_assignments ra
            JOIN mdl_context ctx ON ra.contextid = ctx.id
            JOIN mdl_role r ON ra.roleid = r.id
            WHERE ctx.contextlevel = 50
            GROUP BY r.shortname, r.id
            ORDER BY total_users DESC
        ";
        
        $query = $this->db->query($sql);
        return $query->result_array();
    }
}