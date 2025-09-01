<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tp_etl_summary_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->table_name = 'tp_etl_summary';
    }

    /**
     * Extract teacher summary data from Moodle for teacher users
     * @param string $extraction_date Date for extraction (YYYY-MM-DD)
     * @return array Extracted data
     */
    public function extract_teacher_summary_from_moodle($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // Use Moodle database connection
            $moodle_db = $this->load->database('moodle', TRUE);
            
            // SQL query to get teacher users with their course and activity data
            $sql = "
                SELECT 
                    u.id as user_id,
                    u.username,
                    u.firstname,
                    u.lastname,
                    u.email,
                    COUNT(DISTINCT c.id) as total_courses_taught,
                    COALESCE(activity_stats.total_activities, 0) as total_activities,
                    COALESCE(forum_interactions.forum_replies, 0) as forum_replies,
                    COALESCE(assignment_feedback.assignment_feedback_count, 0) as assignment_feedback_count,
                    COALESCE(quiz_feedback.quiz_feedback_count, 0) as quiz_feedback_count,
                    COALESCE(grading_activities.grading_count, 0) as grading_count,
                    COALESCE(module_logs.mod_assign_logs, 0) as mod_assign_logs,
                    COALESCE(module_logs.mod_forum_logs, 0) as mod_forum_logs,
                    COALESCE(module_logs.mod_quiz_logs, 0) as mod_quiz_logs,
                    COALESCE(login_stats.total_login, 0) as total_login
                FROM mdl_user u
                INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                INNER JOIN mdl_course c ON ctx.instanceid = c.id
                INNER JOIN mdl_role r ON ra.roleid = r.id
                
                -- Login statistics
                LEFT JOIN (
                    SELECT 
                        lsl.userid as user_id,
                        COUNT(DISTINCT DATE(FROM_UNIXTIME(lsl.timecreated))) as total_login
                    FROM mdl_logstore_standard_log lsl
                    WHERE lsl.action = 'loggedin' 
                        AND lsl.target = 'user'
                        AND lsl.timecreated > 0
                    GROUP BY lsl.userid
                ) login_stats ON u.id = login_stats.user_id
                
                                 -- General activity statistics
                 LEFT JOIN (
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
                         AND u.id > 1
                         AND ctx.contextlevel = 50
                         AND r.archetype IN ('teacher', 'editingteacher', 'manager')
                         AND c.id > 1
                         AND lsl.target NOT IN ('webservice_function', 'notification')
                     GROUP BY lsl.userid
                 ) activity_stats ON u.id = activity_stats.user_id
                
                -- Forum interactions (replies to students)
                LEFT JOIN (
                    SELECT 
                        fp.userid as user_id,
                        COUNT(DISTINCT fp.id) as forum_replies
                    FROM mdl_forum_posts fp
                    INNER JOIN mdl_forum_discussions fd ON fp.discussion = fd.id
                    INNER JOIN mdl_forum f ON fd.forum = f.id
                    INNER JOIN mdl_course c ON f.course = c.id
                    INNER JOIN mdl_role_assignments ra ON fp.userid = ra.userid
                    INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                    INNER JOIN mdl_role r ON ra.roleid = r.id
                    WHERE fp.parent > 0  -- Only replies, not original posts
                        AND c.id = ctx.instanceid
                        AND ctx.contextlevel = 50
                        AND r.archetype IN ('teacher', 'editingteacher', 'manager')
                        AND c.id > 1
                    GROUP BY fp.userid
                ) forum_interactions ON u.id = forum_interactions.user_id
                
                -- Assignment feedback count using grade_grades (only used aggregation and non-null feedback)
                LEFT JOIN (
                    SELECT 
                        ra.userid as user_id,
                        COUNT(DISTINCT gg.id) as assignment_feedback_count
                    FROM mdl_grade_items gi
                    INNER JOIN mdl_grade_grades gg ON gg.itemid = gi.id
                    INNER JOIN mdl_course c ON gi.courseid = c.id
                    INNER JOIN mdl_context ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                    INNER JOIN mdl_role_assignments ra ON ra.contextid = ctx.id
                    INNER JOIN mdl_role r ON ra.roleid = r.id
                    INNER JOIN mdl_user u ON ra.userid = u.id
                    WHERE gi.itemmodule = 'assign'
                        AND r.archetype IN ('teacher', 'editingteacher', 'manager')
                        AND u.deleted = 0 
                        AND u.suspended = 0
                        AND u.id > 1
                        AND c.id > 1
                        AND gg.aggregationstatus = 'used'
                        AND gg.feedback IS NOT NULL
                        AND gg.feedback <> ''
                    GROUP BY ra.userid
                ) assignment_feedback ON u.id = assignment_feedback.user_id
                
                -- Quiz feedback count using quiz_feedback table (non-null/non-empty feedbacktext)
                LEFT JOIN (
                    SELECT 
                        ra.userid as user_id,
                        COUNT(DISTINCT qf.id) as quiz_feedback_count
                    FROM mdl_quiz_feedback qf
                    INNER JOIN mdl_quiz q ON qf.quizid = q.id
                    INNER JOIN mdl_course c ON q.course = c.id
                    INNER JOIN mdl_context ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                    INNER JOIN mdl_role_assignments ra ON ra.contextid = ctx.id
                    INNER JOIN mdl_role r ON ra.roleid = r.id
                    INNER JOIN mdl_user u ON ra.userid = u.id
                    WHERE r.archetype IN ('teacher', 'editingteacher', 'manager')
                        AND u.deleted = 0 
                        AND u.suspended = 0
                        AND u.id > 1
                        AND c.id > 1
                        AND qf.feedbacktext IS NOT NULL
                        AND qf.feedbacktext <> ''
                    GROUP BY ra.userid
                ) quiz_feedback ON u.id = quiz_feedback.user_id
                
                                 -- General grading activities
                 LEFT JOIN (
                     SELECT 
                         lsl.userid as user_id,
                         COUNT(DISTINCT lsl.id) as grading_count
                     FROM mdl_logstore_standard_log lsl
                     INNER JOIN mdl_user u ON lsl.userid = u.id
                     INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                     INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                     INNER JOIN mdl_course c ON ctx.instanceid = c.id
                     INNER JOIN mdl_role r ON ra.roleid = r.id
                     WHERE lsl.timecreated > 0
                         AND u.deleted = 0 
                         AND u.suspended = 0
                         AND u.id > 1
                         AND ctx.contextlevel = 50
                         AND r.archetype IN ('teacher', 'editingteacher', 'manager')
                         AND c.id > 1
                         AND lsl.action IN ('graded')
                         AND lsl.component IN ('mod_assign', 'mod_quiz')
                         AND lsl.target NOT IN ('webservice_function', 'notification')
                     GROUP BY lsl.userid
                 ) grading_activities ON u.id = grading_activities.user_id

                                 -- Total logs per module for teacher actions
                 LEFT JOIN (
                     SELECT 
                         lsl.userid as user_id,
                         SUM(CASE WHEN lsl.component = 'mod_assign' THEN 1 ELSE 0 END) as mod_assign_logs,
                         SUM(CASE WHEN lsl.component = 'mod_forum' THEN 1 ELSE 0 END) as mod_forum_logs,
                         SUM(CASE WHEN lsl.component = 'mod_quiz' THEN 1 ELSE 0 END) as mod_quiz_logs
                     FROM mdl_logstore_standard_log lsl
                     INNER JOIN mdl_user u ON lsl.userid = u.id
                     INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                     INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                     INNER JOIN mdl_course c ON ctx.instanceid = c.id
                     INNER JOIN mdl_role r ON ra.roleid = r.id
                     WHERE lsl.timecreated > 0
                         AND u.deleted = 0 
                         AND u.suspended = 0
                         AND u.id > 1
                         AND ctx.contextlevel = 50
                         AND r.archetype IN ('teacher', 'editingteacher', 'manager')
                         AND c.id > 1
                         AND lsl.component IN ('mod_assign', 'mod_forum', 'mod_quiz')
                         AND lsl.target NOT IN ('webservice_function', 'notification')
                     GROUP BY lsl.userid
                 ) module_logs ON u.id = module_logs.user_id

                WHERE u.deleted = 0 
                    AND u.suspended = 0
                    AND u.id > 1  -- Exclude guest user
                    AND ctx.contextlevel = 50  -- Course level only
                    AND r.archetype IN ('teacher', 'editingteacher', 'manager')  -- Teachers, editing teachers, and managers
                    AND c.id > 1  -- Exclude system course
                GROUP BY u.id, u.username, u.firstname, u.lastname, u.email
                HAVING total_courses_taught > 0  -- Only users teaching at least one course
                ORDER BY u.id
            ";

            $query = $moodle_db->query($sql);
            $teachers = $query->result_array();

            $summary_data = [];
            
            foreach ($teachers as $teacher) {
                $summary_data[] = [
                    'user_id' => $teacher['user_id'],
                    'username' => $teacher['username'],
                    'firstname' => $teacher['firstname'],
                    'lastname' => $teacher['lastname'],
                    'email' => $teacher['email'],
                    'total_courses_taught' => $teacher['total_courses_taught'],
                    'total_activities' => $teacher['total_activities'],
                    'forum_replies' => $teacher['forum_replies'],
                    'assignment_feedback_count' => $teacher['assignment_feedback_count'],
                    'quiz_feedback_count' => $teacher['quiz_feedback_count'],
                    'grading_count' => $teacher['grading_count'],
                    'mod_assign_logs' => $teacher['mod_assign_logs'],
                    'mod_forum_logs' => $teacher['mod_forum_logs'],
                    'mod_quiz_logs' => $teacher['mod_quiz_logs'],
                    'total_login' => $teacher['total_login'],
                    'total_student_interactions' => $teacher['forum_replies'] + $teacher['assignment_feedback_count'] + $teacher['quiz_feedback_count'] + $teacher['grading_count'],
                    'extraction_date' => $extraction_date,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            }

            log_message('info', "Extracted " . count($summary_data) . " teacher summary records from Moodle for date: {$extraction_date}");
            
            return $summary_data;

        } catch (Exception $e) {
            log_message('error', "Error extracting teacher summary data from Moodle: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Insert or update teacher summary data (upsert based on user_id and extraction_date)
     * @param array $data Array of teacher summary data
     * @return array Result with inserted and updated counts
     */
    public function upsert_teacher_summary_data($data) {
        if (empty($data)) {
            return ['inserted' => 0, 'updated' => 0];
        }

        try {
            $inserted_count = 0;
            $updated_count = 0;
            
            foreach ($data as $record) {
                // Check if user_id and extraction_date combination already exists
                $existing = $this->db->where('user_id', $record['user_id'])
                                   ->where('extraction_date', $record['extraction_date'])
                                   ->get($this->table_name)
                                   ->row_array();
                
                if ($existing) {
                    // Update existing record
                    $update_data = [
                        'username' => $record['username'],
                        'firstname' => $record['firstname'],
                        'lastname' => $record['lastname'],
                        'email' => $record['email'],
                        'total_courses_taught' => $record['total_courses_taught'],
                        'total_activities' => $record['total_activities'],
                        'forum_replies' => $record['forum_replies'],
                        'assignment_feedback_count' => $record['assignment_feedback_count'],
                        'quiz_feedback_count' => $record['quiz_feedback_count'],
                        'grading_count' => $record['grading_count'],
                        'mod_assign_logs' => $record['mod_assign_logs'],
                        'mod_forum_logs' => $record['mod_forum_logs'],
                        'mod_quiz_logs' => $record['mod_quiz_logs'],
                        'total_login' => $record['total_login'],
                        'total_student_interactions' => $record['total_student_interactions'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
            
                    $this->db->where('user_id', $record['user_id'])
                             ->where('extraction_date', $record['extraction_date'])
                             ->update($this->table_name, $update_data);
                    
                    if ($this->db->affected_rows() > 0) {
                        $updated_count++;
                    }
                    
                } else {
                    // Insert new record
                    $this->db->insert($this->table_name, $record);
                    if ($this->db->affected_rows() > 0) {
                        $inserted_count++;
                    }
                }
            }
            
            log_message('info', "Upserted teacher summary data: {$inserted_count} inserted, {$updated_count} updated");
            
            return ['inserted' => $inserted_count, 'updated' => $updated_count];
            
        } catch (Exception $e) {
            log_message('error', "Error upserting teacher summary data: " . $e->getMessage());
            return ['inserted' => 0, 'updated' => 0];
        }
    }

    /**
     * Run complete teacher ETL process
     * @param string $extraction_date Date for extraction
     * @return array ETL results
     */
    public function run_complete_teacher_etl($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            $start_time = microtime(true);
            
            // Step 1: Extract data from Moodle
            $extracted_data = $this->extract_teacher_summary_from_moodle($extraction_date);
            $extracted_count = count($extracted_data);

            if ($extracted_count == 0) {
                return [
                    'success' => false,
                    'error' => 'No teacher data extracted from Moodle',
                    'extracted' => 0,
                    'inserted' => 0,
                    'updated' => 0
                ];
            }

            // Step 2: Upsert data (insert new or update existing)
            $upsert_result = $this->upsert_teacher_summary_data($extracted_data);
            
            // Step 3: Run detail ETL after summary is completed
            // Pass the extracted user IDs to detail ETL for consistency
            $user_ids = array_column($extracted_data, 'user_id');
            $detail_result = $this->run_complete_teacher_detail_etl($extraction_date, $user_ids);
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);

            return [
                'success' => true,
                'extracted' => $extracted_count,
                'inserted' => $upsert_result['inserted'],
                'updated' => $upsert_result['updated'],
                'detail_extracted' => isset($detail_result['extracted']) ? $detail_result['extracted'] : 0,
                'detail_inserted' => isset($detail_result['inserted']) ? $detail_result['inserted'] : 0,
                'date' => $extraction_date,
                'duration' => $duration,
                'message' => "Processed {$extracted_count} teacher summary records: {$upsert_result['inserted']} inserted, {$upsert_result['updated']} updated. Detail: " . (isset($detail_result['extracted']) ? $detail_result['extracted'] : 0) . " extracted, " . (isset($detail_result['inserted']) ? $detail_result['inserted'] : 0) . " inserted"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

         /**
      * Run complete teacher detail ETL process with batch processing
      * @param string $extraction_date Date for extraction
      * @param array|int $user_ids Array of user IDs from summary or single user ID (optional)
      * @param int $batch_size Batch size for processing (default: 250)
      * @return array ETL results
      */
     public function run_complete_teacher_detail_etl($extraction_date = null, $user_ids = null, $batch_size = 250) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // Load detail model
            $this->load->model('tp_etl_detail_model');
            
            $start_time = microtime(true);
            
            // Step 1: Extract and insert detailed data from Moodle in batches
            // The extract method now handles insertion internally for memory efficiency
            $extract_result = $this->tp_etl_detail_model->extract_teacher_detail_from_moodle($extraction_date, $user_ids, $batch_size);
            
            if (empty($extract_result) || !isset($extract_result['total_count'])) {
                return [
                    'success' => false,
                    'error' => 'No teacher detail data extracted from Moodle',
                    'extracted' => 0,
                    'inserted' => 0,
                    'skipped' => 0
                ];
            }

            $extracted_count = $extract_result['total_count'];
            $inserted_count = $extract_result['total_inserted'];
            $batch_count = $extract_result['batch_count'];
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            
            $total_skipped = $extracted_count - $inserted_count;

            // Log the user IDs being processed for debugging
            if (is_array($user_ids)) {
                $user_count = count($user_ids);
                log_message('info', "Detail ETL processed {$extracted_count} records for {$user_count} users from summary");
            } else {
                log_message('info', "Detail ETL processed {$extracted_count} records for user ID: {$user_ids}");
            }

            return [
                'success' => true,
                'extracted' => $extracted_count,
                'inserted' => $inserted_count,
                'skipped' => $total_skipped,
                'date' => $extraction_date,
                'duration' => $duration,
                'batch_size' => $batch_size,
                'batch_count' => $batch_count,
                'user_count' => is_array($user_ids) ? count($user_ids) : ($user_ids ? 1 : 0),
                'message' => "Processed {$extracted_count} teacher detail records for " . (is_array($user_ids) ? count($user_ids) : ($user_ids ? 1 : 0)) . " users: {$inserted_count} inserted, {$total_skipped} duplicates skipped in {$batch_count} batches of {$batch_size}"
            ];

        } catch (Exception $e) {
                         return [
                 'success' => false,
                 'error' => $e->getMessage()
             ];
         }
     }

    /**
     * Get summary data with pagination and filters
     * @param int $page Page number
     * @param int $per_page Records per page
     * @param array $filters Filter conditions
     * @param string $order_by Order by field
     * @param string $order_direction Order direction (ASC/DESC)
     * @return array Paginated data with total count
     */
    public function get_summary_data_with_pagination($page = 1, $per_page = 20, $filters = [], $order_by = 'id', $order_direction = 'DESC') {
        try {
            $offset = ($page - 1) * $per_page;
            
            // Build WHERE clause based on filters
            $where_conditions = [];
            $where_values = [];
            
            if (!empty($filters['extraction_date'])) {
                $where_conditions[] = 'extraction_date = ?';
                $where_values[] = $filters['extraction_date'];
            }
            
            if (!empty($filters['user_id'])) {
                $where_conditions[] = 'user_id = ?';
                $where_values[] = $filters['user_id'];
            }
            
            if (!empty($filters['username'])) {
                $where_conditions[] = 'username LIKE ?';
                $where_values[] = '%' . $filters['username'] . '%';
            }
            
            if (!empty($filters['email'])) {
                $where_conditions[] = 'email LIKE ?';
                $where_values[] = '%' . $filters['email'] . '%';
            }
            
            $where_clause = '';
            if (!empty($where_conditions)) {
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            }
            
            // Validate order_by field to prevent SQL injection
            $allowed_order_fields = ['id', 'user_id', 'username', 'firstname', 'lastname', 'email', 'total_courses_taught', 'total_activities', 'extraction_date', 'created_at', 'updated_at'];
            if (!in_array($order_by, $allowed_order_fields)) {
                $order_by = 'id';
            }
            
            // Validate order direction
            $order_direction = strtoupper($order_direction) === 'ASC' ? 'ASC' : 'DESC';
            
            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM {$this->table_name} {$where_clause}";
            $count_query = $this->db->query($count_sql, $where_values);
            $total_count = $count_query->row_array()['total'];
            
            // Get paginated data
            $data_sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$order_by} {$order_direction} LIMIT {$per_page} OFFSET {$offset}";
            $data_query = $this->db->query($data_sql, $where_values);
            $data = $data_query->result_array();
            
            return [
                'success' => true,
                'data' => $data,
                'total' => $total_count,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total_count / $per_page)
            ];
            
        } catch (Exception $e) {
            log_message('error', "Error getting summary data with pagination: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'total' => 0
            ];
        }
    }
 }
