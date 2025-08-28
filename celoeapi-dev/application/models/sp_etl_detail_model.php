<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sp_etl_detail_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();  // Add this line to load database
        $this->table_name = 'sp_etl_detail';
    }

    /**
     * Extract detailed data from Moodle for enrolled students per module type
     * @param string $extraction_date Date for extraction (YYYY-MM-DD)
     * @return array Extracted data
     */
    public function extract_detail_from_moodle($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // Use Moodle database connection
            $moodle_db = $this->load->database('moodle', TRUE);
            
            $detail_data = [];
            
            // Extract data for each module type
            $module_types = ['mod_assign', 'mod_forum', 'mod_quiz'];
            
            foreach ($module_types as $module_type) {
                $sql = $this->build_module_query($module_type, $extraction_date);
                $query = $moodle_db->query($sql);
                $module_data = $query->result_array();
                
                foreach ($module_data as $record) {
                    $detail_data[] = [
                        'user_id' => $record['user_id'],
                        'course_id' => $record['course_id'],
                        'course_name' => $record['course_name'],
                        'module_type' => $module_type,
                        'module_name' => $record['module_name'],
                        'object_id' => $record['object_id'],
                        'grade' => $record['grade'],
                        'timecreated' => $record['timecreated'],
                        'log_id' => $record['log_id'],
                        'action_type' => $record['action_type'],
                        'extraction_date' => $extraction_date,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            log_message('info', "Extracted " . count($detail_data) . " detailed module records from Moodle for date: {$extraction_date}");
            
            return $detail_data;

        } catch (Exception $e) {
            log_message('error', "Error extracting detailed data from Moodle: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Build SQL query for specific module type
     * @param string $module_type Module type (mod_assign, mod_forum, mod_quiz)
     * @param string $extraction_date Extraction date
     * @return string SQL query
     */
    private function build_module_query($module_type, $extraction_date) {
        switch ($module_type) {
            case 'mod_assign':
                return "
                    SELECT 
                        lsl.userid as user_id,
                        lsl.courseid as course_id,
                        c.fullname as course_name,
                        COALESCE(a.name, 'Assignment') as module_name,
                        COALESCE(a.id, lsl.objectid) as object_id,
                        COALESCE(ag.grade, NULL) as grade,
                        lsl.timecreated,
                        lsl.id as log_id,
                        lsl.action as action_type
                    FROM mdl_logstore_standard_log lsl
                    INNER JOIN mdl_user u ON lsl.userid = u.id
                    INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                    INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                    INNER JOIN mdl_course c ON ctx.instanceid = c.id
                    INNER JOIN mdl_role r ON ra.roleid = r.id
                    LEFT JOIN mdl_assign a ON lsl.objectid = a.id AND c.id = a.course
                    LEFT JOIN mdl_assign_grades ag ON u.id = ag.userid AND a.id = ag.assignment
                    WHERE u.deleted = 0 
                        AND u.suspended = 0
                        AND u.id > 1  -- Exclude guest user
                        AND ctx.contextlevel = 50  -- Course level only
                        AND r.archetype = 'student'  -- Only students (not admin, teacher, etc.)
                        AND r.shortname IN ('student')  -- Additional check for student role
                        AND c.visible = 1  -- Only visible courses
                        AND c.id > 1  -- Exclude system course
                        AND lsl.component = 'mod_assign'
                        AND lsl.timecreated > 0
                    ORDER BY lsl.userid, lsl.courseid, lsl.timecreated
                ";
                
            case 'mod_forum':
                return "
                    SELECT 
                        lsl.userid as user_id,
                        lsl.courseid as course_id,
                        c.fullname as course_name,
                        COALESCE(f.name, 'Forum') as module_name,
                        COALESCE(f.id, lsl.objectid) as object_id,
                        NULL as grade,
                        lsl.timecreated,
                        lsl.id as log_id,
                        lsl.action as action_type
                    FROM mdl_logstore_standard_log lsl
                    INNER JOIN mdl_user u ON lsl.userid = u.id
                    INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                    INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                    INNER JOIN mdl_course c ON ctx.instanceid = c.id
                    INNER JOIN mdl_role r ON ra.roleid = r.id
                    LEFT JOIN mdl_forum f ON lsl.objectid = f.id AND c.id = f.course
                    WHERE u.deleted = 0 
                        AND u.suspended = 0
                        AND u.id > 1  -- Exclude guest user
                        AND ctx.contextlevel = 50  -- Course level only
                        AND r.archetype = 'student'  -- Only students (not admin, teacher, etc.)
                        AND r.shortname IN ('student')  -- Additional check for student role
                        AND c.visible = 1  -- Only visible courses
                        AND c.id > 1  -- Exclude system course
                        AND lsl.component = 'mod_forum'
                        AND lsl.timecreated > 0
                    ORDER BY lsl.userid, lsl.courseid, lsl.timecreated
                ";
                
            case 'mod_quiz':
                return "
                    SELECT 
                        lsl.userid as user_id,
                        lsl.courseid as course_id,
                        c.fullname as course_name,
                        COALESCE(q.name, 'Quiz') as module_name,
                        COALESCE(q.id, lsl.objectid) as object_id,
                        COALESCE(qa.sumgrades, NULL) as grade,
                        lsl.timecreated,
                        lsl.id as log_id,
                        lsl.action as action_type
                    FROM mdl_logstore_standard_log lsl
                    INNER JOIN mdl_user u ON lsl.userid = u.id
                    INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                    INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                    INNER JOIN mdl_course c ON ctx.instanceid = c.id
                    INNER JOIN mdl_role r ON ra.roleid = r.id
                    LEFT JOIN mdl_quiz q ON lsl.objectid = q.id AND c.id = q.course
                    LEFT JOIN mdl_quiz_attempts qa ON u.id = qa.userid AND q.id = qa.quiz
                    WHERE u.deleted = 0 
                        AND u.suspended = 0
                        AND u.id > 1  -- Exclude guest user
                        AND ctx.contextlevel = 50  -- Course level only
                        AND r.archetype = 'student'  -- Only students (not admin, teacher, etc.)
                        AND r.shortname IN ('student')  -- Additional check for student role
                        AND c.visible = 1  -- Only visible courses
                        AND c.id > 1  -- Exclude system course
                        AND lsl.component = 'mod_quiz'
                        AND lsl.timecreated > 0
                    ORDER BY lsl.userid, lsl.courseid, lsl.timecreated
                ";
                
            default:
                return "";
        }
    }

    /**
     * Insert extracted detailed data to ETL table
     * @param array $data Array of detailed data
     * @return int Number of affected rows
     */
    public function insert_detail_data($data) {
        if (empty($data)) {
            return 0;
        }

        try {
            // Use batch insert for better performance
            $this->db->insert_batch($this->table_name, $data);
            $affected_rows = $this->db->affected_rows();
            
            log_message('info', "Inserted {$affected_rows} detailed records to {$this->table_name}");
            
            return $affected_rows;
            
        } catch (Exception $e) {
            log_message('error', "Error inserting detailed data: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update existing detailed data
     * @param array $data Array of detailed data to update
     * @return int Number of affected rows
     */
    public function update_detail_data($data) {
        if (empty($data)) {
            return 0;
        }

        $updated_count = 0;
        
        foreach ($data as $record) {
            $this->db->where('user_id', $record['user_id']);
            $this->db->where('course_id', $record['course_id']);
            $this->db->where('module_type', $record['module_type']);
            $this->db->where('object_id', $record['object_id']);
            $this->db->where('extraction_date', $record['extraction_date']);
            
            $update_data = [
                'course_name' => $record['course_name'],
                'module_name' => $record['module_name'],
                'grade' => $record['grade'],
                'timecreated' => $record['timecreated'],
                'log_id' => $record['log_id'],
                'action_type' => $record['action_type'],
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update($this->table_name, $update_data);
            $updated_count += $this->db->affected_rows();
        }
        
        log_message('info', "Updated {$updated_count} detailed records in {$this->table_name}");
        
        return $updated_count;
    }

    /**
     * Get detailed data with pagination and filters
     * @param int $page Page number
     * @param int $limit Records per page
     * @param string $search Search term
     * @param array $filters Additional filters
     * @return array Data with pagination
     */
    public function get_detail_with_pagination($page = 1, $limit = 10, $search = '', $filters = []) {
        $offset = ($page - 1) * $limit;
        
        // Base query
        $this->db->select('*');
        $this->db->from($this->table_name);
        
        // Apply search filter
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('course_name', $search);
            $this->db->or_like('module_type', $search);
            $this->db->or_like('module_name', $search);
            $this->db->group_end();
        }
        
        // Apply filters
        if (!empty($filters['extraction_date'])) {
            $this->db->where('extraction_date', $filters['extraction_date']);
        }
        
        if (!empty($filters['user_id'])) {
            $this->db->where('user_id', $filters['user_id']);
        }
        
        if (!empty($filters['course_id'])) {
            $this->db->where('course_id', $filters['course_id']);
        }
        
        if (!empty($filters['module_type'])) {
            $this->db->where('module_type', $filters['module_type']);
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
            $this->db->like('course_name', $search);
            $this->db->or_like('module_type', $search);
            $this->db->or_like('module_name', $search);
            $this->db->group_end();
        }
        
        // Apply filters for main query
        if (!empty($filters['extraction_date'])) {
            $this->db->where('extraction_date', $filters['extraction_date']);
        }
        
        if (!empty($filters['user_id'])) {
            $this->db->where('user_id', $filters['user_id']);
        }
        
        if (!empty($filters['course_id'])) {
            $this->db->where('course_id', $filters['course_id']);
        }
        
        if (!empty($filters['module_type'])) {
            $this->db->where('module_type', $filters['module_type']);
        }
        
        // Apply pagination and ordering
        $this->db->limit($limit, $offset);
        $this->db->order_by('extraction_date', 'DESC');
        $this->db->order_by('user_id', 'ASC');
        $this->db->order_by('course_id', 'ASC');
        
        $detail_data = $this->db->get()->result_array();
        
        return [
            'data' => $detail_data,
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
     * Get detailed statistics
     * @param string $extraction_date Date for statistics
     * @return array Statistics data
     */
    public function get_detail_statistics($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }
        
        $this->db->select('
            COUNT(*) as total_records,
            COUNT(DISTINCT user_id) as unique_students,
            COUNT(DISTINCT course_id) as unique_courses,
            COUNT(DISTINCT module_type) as unique_module_types
        ');
        $this->db->from($this->table_name);
        $this->db->where('extraction_date', $extraction_date);
        
        $stats = $this->db->get()->row_array();
        
        return $stats;
    }

    /**
     * Get course enrollment summary
     * @param string $extraction_date Date for summary
     * @return array Course summary data
     */
    public function get_course_enrollment_summary($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }
        
        $this->db->select('
            course_id,
            course_name,
            module_type,
            COUNT(*) as total_records,
            COUNT(DISTINCT user_id) as unique_students
        ');
        $this->db->from($this->table_name);
        $this->db->where('extraction_date', $extraction_date);
        $this->db->group_by('course_id, course_name, module_type');
        $this->db->order_by('total_records', 'DESC');
        
        $course_summary = $this->db->get()->result_array();
        
        return $course_summary;
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
     * Run complete detailed ETL process
     * @param string $extraction_date Date for extraction
     * @return array ETL results
     */
    public function run_complete_detail_etl($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            $start_time = microtime(true);
            
            // Step 1: Extract data from Moodle
            $extracted_data = $this->extract_detail_from_moodle($extraction_date);
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
            $inserted_count = $this->insert_detail_data($extracted_data);
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);

            return [
                'success' => true,
                'extracted' => $extracted_count,
                'inserted' => $inserted_count,
                'updated' => 0,
                'date' => $extraction_date,
                'duration' => $duration,
                'message' => "Processed {$extracted_count} detailed module records: {$inserted_count} inserted"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
