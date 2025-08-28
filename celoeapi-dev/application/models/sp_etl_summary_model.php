<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sp_etl_summary_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();  // Add this line to load database
        $this->table_name = 'sp_etl_summary';
    }

    /**
     * Extract summary data from Moodle for enrolled students
     * @param string $extraction_date Date for extraction (YYYY-MM-DD)
     * @return array Extracted data
     */
    public function extract_summary_from_moodle($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // Use Moodle database connection
            $moodle_db = $this->load->database('moodle', TRUE);
            
            // SQL query to get enrolled students with their course and activity data
            $sql = "
                SELECT 
                    u.id as user_id,
                    u.username,
                    u.firstname,
                    u.lastname,
                    COUNT(DISTINCT c.id) as total_course,
                    COALESCE(login_stats.total_login, 0) as total_login,
                    COALESCE(activity_stats.total_activities, 0) as total_activities
                FROM mdl_user u
                INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                INNER JOIN mdl_course c ON ctx.instanceid = c.id
                INNER JOIN mdl_role r ON ra.roleid = r.id
                LEFT JOIN (
                    -- Get login count for each user from mdl_logstore_standard_log
                    SELECT 
                        lsl.userid as user_id,
                        COUNT(DISTINCT DATE(FROM_UNIXTIME(lsl.timecreated))) as total_login
                    FROM mdl_logstore_standard_log lsl
                    WHERE lsl.action = 'loggedin' 
                        AND lsl.target = 'user'
                        AND lsl.timecreated > 0
                    GROUP BY lsl.userid
                ) login_stats ON u.id = login_stats.user_id
                LEFT JOIN (
                    -- Get activity count for each user from mdl_logstore_standard_log
                    -- ONLY for the same components as detail model (mod_assign, mod_forum, mod_quiz)
                    SELECT 
                        lsl.userid as user_id,
                        COUNT(DISTINCT lsl.id) as total_activities
                    FROM mdl_logstore_standard_log lsl
                    INNER JOIN mdl_user u ON lsl.userid = u.id
                    INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                    INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                    INNER JOIN mdl_course c ON ctx.instanceid = c.id
                    INNER JOIN mdl_role r ON ra.roleid = r.id
                    WHERE lsl.timecreated > 0
                        AND u.deleted = 0 
                        AND u.suspended = 0
                        AND u.id > 1  -- Exclude guest user
                        AND ctx.contextlevel = 50  -- Course level only
                        AND r.archetype = 'student'  -- Only students (not admin, teacher, etc.)
                        AND r.shortname IN ('student')  -- Additional check for student role
                        AND c.visible = 1  -- Only visible courses
                        AND c.id > 1  -- Exclude system course
                        AND lsl.component IN ('mod_assign', 'mod_forum', 'mod_quiz')  -- Same components as detail model
                    GROUP BY lsl.userid
                ) activity_stats ON u.id = activity_stats.user_id

                WHERE u.deleted = 0 
                    AND u.suspended = 0
                    AND u.id > 1  -- Exclude guest user
                    AND ctx.contextlevel = 50  -- Course level only
                    AND r.archetype = 'student'  -- Only students (not admin, teacher, etc.)
                    AND r.shortname IN ('student')  -- Additional check for student role
                    AND c.visible = 1  -- Only visible courses
                    AND c.id > 1  -- Exclude system course
                GROUP BY u.id, u.username, u.firstname, u.lastname
                HAVING total_course > 0  -- Only users enrolled in at least one course
                ORDER BY u.id
            ";

            $query = $moodle_db->query($sql);
            $users = $query->result_array();

            $summary_data = [];
            
            foreach ($users as $user) {
                $summary_data[] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'firstname' => $user['firstname'],
                    'lastname' => $user['lastname'],
                    'total_course' => (int) $user['total_course'],
                    'total_login' => (int) $user['total_login'],
                    'total_activities' => (int) $user['total_activities'],
                    'extraction_date' => $extraction_date,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }

            log_message('info', "Extracted " . count($summary_data) . " student summary records from Moodle for date: {$extraction_date}");
            
            return $summary_data;

        } catch (Exception $e) {
            log_message('error', "Error extracting summary data from Moodle: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Insert extracted summary data to ETL table
     * @param array $data Array of summary data
     * @return int Number of affected rows
     */
    public function insert_summary_data($data) {
        if (empty($data)) {
            return 0;
        }

        try {
            // Use batch insert for better performance
            $this->db->insert_batch($this->table_name, $data);
            $affected_rows = $this->db->affected_rows();
            
            log_message('info', "Inserted {$affected_rows} summary records to {$this->table_name}");
            
            return $affected_rows;
            
        } catch (Exception $e) {
            log_message('error', "Error inserting summary data: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update existing summary data
     * @param array $data Array of summary data to update
     * @return int Number of affected rows
     */
    public function update_summary_data($data) {
        if (empty($data)) {
            return 0;
        }

        $updated_count = 0;
        
        foreach ($data as $record) {
            $this->db->where('user_id', $record['user_id']);
            $this->db->where('extraction_date', $record['extraction_date']);
            
            $update_data = [
                'username' => $record['username'],
                'firstname' => $record['firstname'],
                'lastname' => $record['lastname'],
                'total_course' => $record['total_course'],
                'total_login' => $record['total_login'],
                'total_activities' => $record['total_activities'],
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update($this->table_name, $update_data);
            $updated_count += $this->db->affected_rows();
        }
        
        log_message('info', "Updated {$updated_count} summary records in {$this->table_name}");
        
        return $updated_count;
    }

    /**
     * Get summary data with pagination and filters
     * @param int $page Page number
     * @param int $limit Records per page
     * @param string $search Search term
     * @param array $filters Additional filters
     * @return array Data with pagination
     */
    public function get_summary_with_pagination($page = 1, $limit = 10, $search = '', $filters = []) {
        $offset = ($page - 1) * $limit;
        
        // Base query
        $this->db->select('*');
        $this->db->from($this->table_name);
        
        // Apply search filter
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('username', $search);
            $this->db->or_like('firstname', $search);
            $this->db->or_like('lastname', $search);
            $this->db->group_end();
        }
        
        // Apply filters
        if (!empty($filters['extraction_date'])) {
            $this->db->where('extraction_date', $filters['extraction_date']);
        }
        
        if (!empty($filters['min_courses'])) {
            $this->db->where('total_course >=', $filters['min_courses']);
        }
        
        if (!empty($filters['max_courses'])) {
            $this->db->where('total_course <=', $filters['max_courses']);
        }
        
        if (!empty($filters['min_activities'])) {
            $this->db->where('total_activities >=', $filters['min_activities']);
        }
        
        // Get total count for pagination
        $total_query = $this->db->get_compiled_select();
        $total_count = $this->db->query($total_query)->num_rows();
        
        // Reset query builder for main query
        $this->db->reset_query();
        
        // Build main query for data
        $this->db->select('*');
        $this->db->from($this->table_name);
        
        // Apply search filter for main query
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('username', $search);
            $this->db->or_like('firstname', $search);
            $this->db->or_like('lastname', $search);
            $this->db->group_end();
        }
        
        // Apply filters for main query
        if (!empty($filters['extraction_date'])) {
            $this->db->where('extraction_date', $filters['extraction_date']);
        }
        
        if (!empty($filters['min_courses'])) {
            $this->db->where('total_course >=', $filters['min_courses']);
        }
        
        if (!empty($filters['max_courses'])) {
            $this->db->where('total_course <=', $filters['max_courses']);
        }
        
        if (!empty($filters['min_activities'])) {
            $this->db->where('total_activities >=', $filters['min_activities']);
        }
        
        // Apply pagination and ordering
        $this->db->limit($limit, $offset);
        $this->db->order_by('extraction_date', 'DESC');
        $this->db->order_by('total_course', 'DESC');
        $this->db->order_by('total_activities', 'DESC');
        
        $summary_data = $this->db->get()->result_array();
        
        return [
            'data' => $summary_data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_records' => $total_count,
                'total_pages' => ceil($total_count / $limit),
                'has_next_page' => $page < ceil($total_count / $limit),
                'has_prev_page' => $page > 1
            ]
        ];
    }

    /**
     * Get summary statistics
     * @param string $extraction_date Date for statistics
     * @return array Statistics data
     */
    public function get_summary_statistics($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }
        
        $this->db->select('
            COUNT(*) as total_students,
            AVG(total_course) as avg_courses,
            AVG(total_login) as avg_logins,
            AVG(total_activities) as avg_activities,
            SUM(total_course) as total_course_enrollments,
            SUM(total_activities) as total_all_activities
        ');
        $this->db->from($this->table_name);
        $this->db->where('extraction_date', $extraction_date);
        
        $stats = $this->db->get()->row_array();
        
        return $stats;
    }

    /**
     * Clear ETL data for specific date
     * @param string $date Date to clear
     * @return bool Success status
     */
    public function clear_etl_data($date = null) {
        try {
            if ($date) {
                $this->db->where('extraction_date', $date);
            }
            $result = $this->db->delete($this->table_name);
            
            log_message('info', "Cleared ETL data for date: " . ($date ?: 'all dates'));
            
            return $result;
            
        } catch (Exception $e) {
            log_message('error', "Error clearing ETL data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Run complete summary ETL process
     * @param string $extraction_date Date for extraction
     * @return array ETL results
     */
    public function run_complete_summary_etl($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            $start_time = microtime(true);
            
            // Step 1: Extract data from Moodle
            $extracted_data = $this->extract_summary_from_moodle($extraction_date);
            $extracted_count = count($extracted_data);

            if ($extracted_count == 0) {
                return [
                    'success' => false,
                    'error' => 'No data extracted from Moodle',
                    'extracted' => 0,
                    'inserted' => 0,
                    'updated' => 0
                ];
            }

            // Step 2: Clear existing data for this date
            $this->clear_etl_data($extraction_date);

            // Step 3: Insert new data
            $inserted_count = $this->insert_summary_data($extracted_data);
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);

            return [
                'success' => true,
                'extracted' => $extracted_count,
                'inserted' => $inserted_count,
                'updated' => 0,
                'date' => $extraction_date,
                'duration' => $duration,
                'message' => "Processed {$extracted_count} student summary records: {$inserted_count} inserted"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
