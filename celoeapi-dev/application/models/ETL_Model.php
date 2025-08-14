<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ETL_Model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Activity_counts_model', 'activity_counts');
        $this->load->model('User_counts_model', 'user_counts');
    }

    /**
     * Get ETL status
     */
    public function get_etl_status()
    {
        $this->db->select('*');
        $this->db->from('etl_status');
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
        
        return $this->db->insert('etl_status', $data);
    }

    /**
     * Get complete user activity data
     * Based on the final query from finals_query_mysql57.sql
     */
    public function get_user_activity_data($course_id = null, $date = null)
    {
        $sql = "
            SELECT 
                categories.idnumber AS `Course_ID`,
                subjects.idnumber AS `ID_Number`,
                subjects.fullname AS `Course_Name`,
                subjects.shortname AS `Course_Shortname`,
                COALESCE(uc.Num_Teachers, 0) AS `Num_Teachers`,
                COALESCE(uc.Num_Students, 0) AS `Num_Students`,
                COALESCE(ac.File_Views, 0) AS `File_Views`,
                COALESCE(ac.Video_Views, 0) AS `Video_Views`,
                COALESCE(ac.Forum_Views, 0) AS `Forum_Views`,
                COALESCE(ac.Quiz_Views, 0) AS `Quiz_Views`,
                COALESCE(ac.Assignment_Views, 0) AS `Assignment_Views`,
                COALESCE(ac.URL_Views, 0) AS `URL_Views`,
                (COALESCE(ac.File_Views, 0) + COALESCE(ac.Video_Views, 0) + COALESCE(ac.Forum_Views, 0) + COALESCE(ac.Quiz_Views, 0) + COALESCE(ac.Assignment_Views, 0) + COALESCE(ac.URL_Views, 0)) AS `Total_Views`,
                COALESCE(ac.ActiveDays, 0) AS `Active_Days`,
                ROUND(
                    (COALESCE(ac.File_Views, 0) + COALESCE(ac.Video_Views, 0) + COALESCE(ac.Forum_Views, 0) + COALESCE(ac.Quiz_Views, 0) + COALESCE(ac.Assignment_Views, 0) + COALESCE(ac.URL_Views, 0))
                    / NULLIF(uc.Num_Students, 0)
                    / NULLIF(ac.ActiveDays, 0),
                    2
                ) AS `Avg_Activity_per_Student_per_Day`
            FROM mdl_course subjects
            LEFT JOIN mdl_course_categories categories ON subjects.category = categories.id
            LEFT JOIN (
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
            ) ac ON subjects.id = ac.courseid
            LEFT JOIN (
                SELECT
                    ctx.instanceid AS courseid,
                    COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
                    COUNT(DISTINCT CASE WHEN ra.roleid IN (3, 4) THEN ra.userid END) AS Num_Teachers
                FROM mdl_role_assignments ra
                JOIN mdl_context ctx ON ra.contextid = ctx.id
                WHERE ctx.contextlevel = 70
                GROUP BY ctx.instanceid
            ) uc ON subjects.id = uc.courseid
            WHERE subjects.visible = 1
        ";
        
        $params = [];
        
        if ($course_id) {
            $sql .= " AND subjects.id = ?";
            $params[] = $course_id;
        }
        
        $sql .= " ORDER BY subjects.id";
        
        $query = $this->db->query($sql, $params);
        return $query->result_array();
    }

    /**
     * Insert user activity data into ETL table
     */
    public function insert_user_activity_etl($data, $extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
        
        foreach ($data as $row) {
            $etl_data = [
                'course_id' => $row['Course_ID'] ?: null,
                'id_number' => $row['ID_Number'] ?: null,
                'course_name' => $row['Course_Name'] ?: null,
                'course_shortname' => $row['Course_Shortname'] ?: null,
                'num_teachers' => $row['Num_Teachers'] ?: 0,
                'num_students' => $row['Num_Students'] ?: 0,
                'file_views' => $row['File_Views'] ?: 0,
                'video_views' => $row['Video_Views'] ?: 0,
                'forum_views' => $row['Forum_Views'] ?: 0,
                'quiz_views' => $row['Quiz_Views'] ?: 0,
                'assignment_views' => $row['Assignment_Views'] ?: 0,
                'url_views' => $row['URL_Views'] ?: 0,
                'total_views' => $row['Total_Views'] ?: 0,
                'avg_activity_per_student_per_day' => $row['Avg_Activity_per_Student_per_Day'] ?: null,
                'active_days' => $row['Active_Days'] ?: 0,
                'extraction_date' => $extraction_date,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Check if record exists for this course and date
            $this->db->where('course_id', $row['Course_ID']);
            $this->db->where('extraction_date', $extraction_date);
            $existing = $this->db->get('user_activity_etl')->row();
            
            if ($existing) {
                // Update existing record
                $this->db->where('id', $existing->id);
                $this->db->update('user_activity_etl', $etl_data);
            } else {
                // Insert new record
                $this->db->insert('user_activity_etl', $etl_data);
            }
        }
        
        return true;
    }

    /**
     * Run complete ETL process
     */
    public function run_etl_process($extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d');
        
        try {
            // Start ETL process
            $this->update_etl_status('running', $extraction_date);
            
            // Get activity counts
            $activity_counts = $this->activity_counts->get_activity_counts_all();
            $this->activity_counts->insert_activity_counts_etl($activity_counts, $extraction_date);
            
            // Get user counts
            $user_counts = $this->user_counts->get_user_counts_all();
            $this->user_counts->insert_user_counts_etl($user_counts, $extraction_date);
            
            // Get complete user activity data
            $user_activity_data = $this->get_user_activity_data(null, $extraction_date);
            $this->insert_user_activity_etl($user_activity_data, $extraction_date);
            
            // Complete ETL process
            $this->update_etl_status('completed', $extraction_date);
            
            return [
                'status' => 'success',
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

    /**
     * Get ETL logs
     */
    public function get_etl_logs($limit = 50, $offset = 0)
    {
        $this->db->select('*');
        $this->db->from('etl_status');
        $this->db->where('process_name', 'user_activity_etl');
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit, $offset);
        
        $query = $this->db->get();
        return $query->result_array();
    }

    /**
     * Get user activity data from ETL table
     */
    public function get_user_activity_etl($course_id = null, $date = null, $limit = 100, $offset = 0)
    {
        $this->db->select('*');
        $this->db->from('user_activity_etl');
        
        if ($course_id) {
            $this->db->where('course_id', $course_id);
        }
        
        if ($date) {
            $this->db->where('extraction_date', $date);
        }
        
        $this->db->order_by('id', 'DESC');
        $this->db->limit($limit, $offset);
        
        $query = $this->db->get();
        return $query->result_array();
    }

    /**
     * Clear ETL data for a specific date
     */
    public function clear_etl_data($date)
    {
        $tables = [
            'user_activity_etl',
            'activity_counts_etl',
            'user_counts_etl'
        ];
        
        $total_affected = 0;
        
        foreach ($tables as $table) {
            $this->db->where('extraction_date', $date);
            $this->db->delete($table);
            $total_affected += $this->db->affected_rows();
        }
        
        return $total_affected;
    }

    /**
     * Get ETL statistics
     */
    public function get_etl_statistics()
    {
        $stats = [];
        
        // Get counts from each ETL table
        $tables = [
            'user_activity_etl' => 'User Activity',
            'activity_counts_etl' => 'Activity Counts',
            'user_counts_etl' => 'User Counts',
            'etl_status' => 'ETL Status'
        ];
        
        foreach ($tables as $table => $name) {
            $this->db->select('COUNT(*) as total');
            $this->db->from($table);
            $query = $this->db->get();
            $result = $query->row();
            $stats[$name] = $result->total;
        }
        
        // Get latest extraction date
        $this->db->select('MAX(extraction_date) as latest_date');
        $this->db->from('user_activity_etl');
        $query = $this->db->get();
        $result = $query->row();
        $stats['Latest Extraction Date'] = $result->latest_date;
        
        return $stats;
    }

    /**
     * SCHEDULER FLOW METHODS - Based on "Get Data from Scheduler" flowchart
     */

    /**
     * Get scheduler data for extraction
     * Step 1: Get Scheduler Data for Extraction
     */
    public function get_scheduler_data_for_extraction()
    {
        $this->db->select('*');
        $this->db->from('log_scheduler');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        
        $query = $this->db->get();
        return $query->row_array();
    }

    /**
     * Check if scheduler data is empty
     * Step 2: Decision: Not empty data?
     */
    public function is_scheduler_data_empty($scheduler_data)
    {
        return empty($scheduler_data);
    }

    /**
     * Set first date extraction (Y-m-d)
     * Step 3a: Set 1st Date Extraction (Y-m-d)
     */
    public function set_first_date_extraction()
    {
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
            'error_details' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('log_scheduler', $data);
    }

    /**
     * Save data scheduler
     * Step 3b: Save Data Scheduler
     */
    public function save_data_scheduler($data)
    {
        if (isset($data['id'])) {
            // Update existing record
            $this->db->where('id', $data['id']);
            return $this->db->update('log_scheduler', $data);
        } else {
            // Insert new record
            return $this->db->insert('log_scheduler', $data);
        }
    }

    /**
     * Check if status is not running
     * Step 4: Decision: Status is not running?
     */
    public function is_status_not_running($scheduler_data)
    {
        return $scheduler_data['status'] != 2; // 2 = inprogress (running), 1 = finished, 3 = failed
    }

    /**
     * Check if enddate is not equal to H-1 23:59
     * Step 5: Decision: Is enddate != (H-1 23:59)?
     */
    public function is_enddate_not_yesterday_2359($scheduler_data)
    {
        $yesterday_2359 = date('Y-m-d', strtotime('-1 day')) . ' 23:59:59';
        return $scheduler_data['end_date'] != $yesterday_2359;
    }

    /**
     * Run extraction with current date
     * Step 6: Run Extraction (Y-m-d)
     */
    public function run_extraction_with_current_date()
    {
        $current_date = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Update scheduler status to inprogress (2)
        $this->db->where('id', $this->get_latest_scheduler_id());
        $this->db->update('log_scheduler', [
            'status' => 2, // Inprogress (running)
            'start_date' => $current_date . ' 00:00:00',
            'end_date' => $yesterday . ' 23:59:59'
        ]);
        
        // Run the ETL process
        $result = $this->run_etl_process($current_date);
        
        // Update scheduler status to finished (1)
        $this->db->where('id', $this->get_latest_scheduler_id());
        $this->db->update('log_scheduler', [
            'status' => 1, // Finished (completed)
            'end_date' => $current_date . ' 23:59:59'
        ]);
        
        return $result;
    }

    /**
     * Get latest scheduler ID
     */
    private function get_latest_scheduler_id()
    {
        $this->db->select('id');
        $this->db->from('log_scheduler');
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        
        $query = $this->db->get();
        $result = $query->row();
        return $result ? $result->id : null;
    }

    /**
     * Main scheduler flow method - implements the complete "Get Data from Scheduler" flow
     */
    public function execute_scheduler_flow()
    {
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
                $this->db->update('log_scheduler', [
                    'status' => 3, // Failed
                    'error_details' => $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }

    /**
     * Update get_user_activity_data method to support pagination
     */
    public function get_user_activity_data_paginated($course_id = null, $date = null, $limit = null, $offset = 0)
    {
        $sql = "
                    SELECT 
                categories.idnumber AS `Course_ID`,
                subjects.idnumber AS `ID_Number`,
                subjects.fullname AS `Course_Name`,
                subjects.shortname AS `Course_Shortname`,
                COALESCE(uc.Num_Teachers, 0) AS `Num_Teachers`,
                COALESCE(uc.Num_Students, 0) AS `Num_Students`,
                COALESCE(ac.File_Views, 0) AS `File_Views`,
                COALESCE(ac.Video_Views, 0) AS `Video_Views`,
                COALESCE(ac.Forum_Views, 0) AS `Forum_Views`,
                COALESCE(ac.Quiz_Views, 0) AS `Quiz_Views`,
                COALESCE(ac.Assignment_Views, 0) AS `Assignment_Views`,
                COALESCE(ac.URL_Views, 0) AS `URL_Views`,
                (COALESCE(ac.File_Views, 0) + COALESCE(ac.Video_Views, 0) + COALESCE(ac.Forum_Views, 0) + COALESCE(ac.Quiz_Views, 0) + COALESCE(ac.Assignment_Views, 0) + COALESCE(ac.URL_Views, 0)) AS `Total_Views`,
                COALESCE(ac.ActiveDays, 0) AS `Active_Days`,
                ROUND(
                    (COALESCE(ac.File_Views, 0) + COALESCE(ac.Video_Views, 0) + COALESCE(ac.Forum_Views, 0) + COALESCE(ac.Quiz_Views, 0) + COALESCE(ac.Assignment_Views, 0) + COALESCE(ac.URL_Views, 0))
                    / NULLIF(uc.Num_Students, 0)
                    / NULLIF(ac.ActiveDays, 0),
                    2
                ) AS `Avg_Activity_per_Student_per_Day`
            FROM mdl_course subjects
            LEFT JOIN mdl_course_categories categories ON subjects.category = categories.id
            LEFT JOIN (
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
            ) ac ON subjects.id = ac.courseid
            LEFT JOIN (
                SELECT
                    ctx.instanceid AS courseid,
                    COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
                    COUNT(DISTINCT CASE WHEN ra.roleid IN (3, 4) THEN ra.userid END) AS Num_Teachers
                FROM mdl_role_assignments ra
                JOIN mdl_context ctx ON ra.contextid = ctx.id
                WHERE ctx.contextlevel = 70
                GROUP BY ctx.instanceid
            ) uc ON subjects.id = uc.courseid
            WHERE subjects.visible = 1
        ";
        
        $params = [];
        
        if ($course_id) {
            $sql .= " AND subjects.id = ?";
            $params[] = $course_id;
        }
        
        $sql .= " ORDER BY subjects.id";
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $query = $this->db->query($sql, $params);
        return $query->result_array();
    }

    /**
     * Get total count for user activity data pagination
     */
    public function get_user_activity_total_count($course_id = null)
    {
        $sql = "
            SELECT COUNT(*) as total
            FROM mdl_course subjects
            WHERE subjects.visible = 1
        ";
        
        $params = [];
        
        if ($course_id) {
            $sql .= " AND subjects.id = ?";
            $params[] = $course_id;
        }
        
        $query = $this->db->query($sql, $params);
        return $query->row()->total;
    }

    /**
     * Insert activity counts into ETL table
     */
    public function insert_activity_counts_etl($data, $extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
        
        // Clear existing data for this date
        $this->db->where('extraction_date', $extraction_date);
        $this->db->delete('activity_counts_etl');
        
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
            
            $this->db->insert('activity_counts_etl', $etl_data);
        }
        
        return true;
    }

    /**
     * Insert user counts into ETL table
     */
    public function insert_user_counts_etl($data, $extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d', strtotime('-1 day'));
        
        // Clear existing data for this date
        $this->db->where('extraction_date', $extraction_date);
        $this->db->delete('user_counts_etl');
        
        foreach ($data as $row) {
            $etl_data = [
                'courseid' => $row['courseid'] ?: null,
                'num_students' => $row['Num_Students'] ?: 0,
                'num_teachers' => $row['Num_Teachers'] ?: 0,
                'extraction_date' => $extraction_date,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('user_counts_etl', $etl_data);
        }
        
        return true;
    }

    /**
     * Get activity counts from ETL table
     */
    public function get_activity_counts_etl($date = null, $limit = 100, $offset = 0)
    {
        $this->db->select('*');
        $this->db->from('activity_counts_etl');
        
        if ($date) {
            $this->db->where('extraction_date', $date);
        }
        
        $this->db->order_by('id', 'DESC');
        $this->db->limit($limit, $offset);
        
        $query = $this->db->get();
        return $query->result_array();
    }

    /**
     * Get user counts from ETL table
     */
    public function get_user_counts_etl($date = null, $limit = 100, $offset = 0)
    {
        $this->db->select('*');
        $this->db->from('user_counts_etl');
        
        if ($date) {
            $this->db->where('extraction_date', $date);
        }
        
        $this->db->order_by('id', 'DESC');
        $this->db->limit($limit, $offset);
        
        $query = $this->db->get();
        return $query->result_array();
    }
}
