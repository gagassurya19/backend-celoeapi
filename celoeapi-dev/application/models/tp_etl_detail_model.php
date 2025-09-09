<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tp_etl_detail_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->table_name = 'tp_etl_detail';
    }

    /**
     * Extract detailed teacher activities from Moodle in batches (memory efficient)
     * @param string $extraction_date Date for extraction (YYYY-MM-DD) - used for extraction_date field only
     * @param array|int $user_ids Array of user IDs from summary or single user ID (optional)
     * @param int $batch_size Batch size for processing (default: 250)
     * @return array Extracted detailed data
     */
    public function extract_teacher_detail_from_moodle($extraction_date = null, $user_ids = null, $batch_size = 250) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // Use Moodle database connection
            $moodle_db = $this->load->database('moodle', TRUE);
            
            // Base query for teacher users
            $user_filter = '';
            if ($user_ids) {
                if (is_array($user_ids)) {
                    // Filter by array of user IDs from summary
                    if (!empty($user_ids)) {
                        $user_ids_str = implode(',', array_map('intval', $user_ids));
                        $user_filter = "AND u.id IN ({$user_ids_str})";
                        log_message('info', "Filtering detail ETL by user IDs from summary: " . count($user_ids) . " users");
                    }
                } else {
                    // Single user ID
                    $user_filter = "AND u.id = " . intval($user_ids);
                    log_message('info', "Filtering detail ETL by single user ID: {$user_ids}");
                }
            }
            
            // First, get total count to determine batches
            $count_sql = "
                SELECT COUNT(DISTINCT lsl.id) as total_count
                FROM mdl_user u
                INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                INNER JOIN mdl_course c ON ctx.instanceid = c.id
                INNER JOIN mdl_role r ON ra.roleid = r.id
                INNER JOIN mdl_logstore_standard_log lsl ON u.id = lsl.userid AND lsl.courseid = c.id
                WHERE u.deleted = 0 
                    AND u.suspended = 0
                    AND u.id > 1
                    AND ctx.contextlevel = 50
                    AND r.archetype IN ('teacher', 'editingteacher', 'manager')
                    AND c.id > 1
                    AND lsl.timecreated > 0
                    AND lsl.target NOT IN ('webservice_function', 'notification')
                    {$user_filter}
            ";
            
            $count_query = $moodle_db->query($count_sql);
            $total_count = $count_query->row_array()['total_count'];
            
            log_message('info', "Total teacher activities to extract: {$total_count}");
            
            if ($total_count == 0) {
                return [];
            }
            
            // Optimized batch size for memory efficiency
            $optimized_batch_size = min($batch_size, 250);
            $total_batches = ceil($total_count / $optimized_batch_size);
            
            log_message('info', "Starting optimized batch processing: {$total_batches} batches of {$optimized_batch_size} records each");
            
            // Process in optimized batches with immediate insertion
            $offset = 0;
            $total_inserted = 0;
            
            while ($offset < $total_count) {
                // Check execution time limit
                if (function_exists('set_time_limit')) {
                    set_time_limit(60); // Reset to 60 seconds
                }
                
                $batch_sql = "
                    SELECT 
                        u.id as user_id,
                        u.username,
                        u.firstname,
                        u.lastname,
                        u.email,
                        c.id as course_id,
                        c.fullname as course_name,
                        c.shortname as course_shortname,
                        DATE(FROM_UNIXTIME(lsl.timecreated)) as activity_date,
                        lsl.component,
                        lsl.action,
                        lsl.target,
                        lsl.objectid,
                        lsl.id as log_id,
                        lsl.timecreated as activity_timestamp
                    FROM mdl_user u
                    INNER JOIN mdl_role_assignments ra ON u.id = ra.userid
                    INNER JOIN mdl_context ctx ON ra.contextid = ctx.id
                    INNER JOIN mdl_course c ON ctx.instanceid = c.id
                    INNER JOIN mdl_role r ON ra.roleid = r.id
                    INNER JOIN mdl_logstore_standard_log lsl ON u.id = lsl.userid AND lsl.courseid = c.id AND lsl.courseid = c.id
                    WHERE u.deleted = 0 
                        AND u.suspended = 0
                        AND u.id > 1
                        AND ctx.contextlevel = 50
                        AND r.archetype IN ('teacher', 'editingteacher', 'manager')
                        AND c.id > 1
                        AND lsl.timecreated > 0
                        AND lsl.target NOT IN ('webservice_function', 'notification')
                        AND lsl.edulevel = 2
                        {$user_filter}
                    ORDER BY lsl.id ASC
                    LIMIT {$optimized_batch_size} OFFSET {$offset}
                ";

                $batch_query = $moodle_db->query($batch_sql);
                $batch_activities = $batch_query->result_array();
                
                if (empty($batch_activities)) {
                    break;
                }
                
                // Process batch immediately and insert
                $batch_data = [];
                foreach ($batch_activities as $activity) {
                    $batch_data[] = [
                        'user_id' => $activity['user_id'],
                        'username' => $activity['username'],
                        'firstname' => $activity['firstname'],
                        'lastname' => $activity['lastname'],
                        'email' => $activity['email'],
                        'course_id' => $activity['course_id'],
                        'course_name' => $activity['course_name'],
                        'course_shortname' => $activity['course_shortname'],
                        'activity_date' => $activity['activity_date'],
                        'component' => $activity['component'],
                        'action' => $activity['action'],
                        'target' => $activity['target'],
                        'objectid' => $activity['objectid'],
                        'log_id' => $activity['log_id'],
                        'activity_timestamp' => $activity['activity_timestamp'],
                        'extraction_date' => $extraction_date,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }
                
                // Insert batch immediately to free memory
                $batch_inserted = $this->insert_teacher_detail_data_batch($batch_data, $optimized_batch_size);
                $total_inserted += $batch_inserted;
                
                $offset += $optimized_batch_size;
                $current_batch = ceil($offset / $optimized_batch_size);
                
                // Aggressive memory cleanup
                unset($batch_activities);
                unset($batch_query);
                unset($batch_data);
                
                // Force garbage collection
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
                log_message('info', "Batch {$current_batch}/{$total_batches}: Processed records " . ($offset - $optimized_batch_size + 1) . " to " . min($offset, $total_count) . " of {$total_count} (Progress: " . round(($offset / $total_count) * 100, 1) . "%) - Inserted: {$batch_inserted}");
                
                // Delay for memory recovery
                usleep(200000); // 0.2 second
            }

            log_message('info', "✅ EXTRACTION COMPLETED: Total records inserted: {$total_inserted} in {$total_batches} batches");
            log_message('info', "📊 Final Summary: {$total_count} total activities found, {$total_inserted} successfully inserted");
            
            // Return data structure for compatibility with existing code
            // Since we're doing immediate insertion, we need to return the actual data
            // that was processed for the calling method to handle properly
            return [
                'total_count' => $total_count,
                'total_inserted' => $total_inserted,
                'batch_count' => $total_batches,
                'data' => [] // Empty array since data was already inserted
            ];

        } catch (Exception $e) {
            log_message('error', "Error extracting teacher detail data from Moodle: " . $e->getMessage());
            return [];
        }
    }



    /**
     * Insert teacher detail data in batches for memory efficiency
     * @param array $data Array of teacher detail data
     * @param int $batch_size Batch size for processing (default: 250)
     * @return int Number of inserted records
     */
    public function insert_teacher_detail_data_batch($data, $batch_size = 250) {
        if (empty($data)) {
            return 0;
        }

        try {
            $total_inserted = 0;
            $total_records = count($data);
            $batches = array_chunk($data, $batch_size);
            
            $total_batches = count($batches);
            log_message('info', "🔄 INSERTION STARTED: Processing {$total_records} records in {$total_batches} batches of {$batch_size}");
            
            foreach ($batches as $batch_num => $batch) {
                // Check execution time limit
                if (function_exists('set_time_limit')) {
                    set_time_limit(60); // Reset to 60 seconds
                }
                
                $batch_inserted = 0;
                $batch_skipped = 0;
                $batch_start_time = microtime(true);
                
                foreach ($batch as $record) {
                    // Check if record already exists using log_id from Moodle
                    $existing = $this->db->where('log_id', $record['log_id'])
                                       ->get($this->table_name)
                                       ->row_array();
                    
                    if (!$existing) {
                        // Insert new record only
                        $this->db->insert($this->table_name, $record);
                        if ($this->db->affected_rows() > 0) {
                            $batch_inserted++;
                        }
                    } else {
                        $batch_skipped++;
                    }
                }
                
                $total_inserted += $batch_inserted;
                $batch_duration = round(microtime(true) - $batch_start_time, 2);
                
                log_message('info', "📦 Batch " . ($batch_num + 1) . "/{$total_batches}: {$batch_inserted} inserted, {$batch_skipped} skipped (Duration: {$batch_duration}s)");
                
                // Aggressive memory cleanup
                unset($batch);
                
                // Force garbage collection
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
                // Delay between batches for memory recovery
                usleep(100000); // 0.1 second
            }
            
            $total_skipped = $total_records - $total_inserted;
            log_message('info', "✅ INSERTION COMPLETED: {$total_inserted} records inserted, {$total_skipped} skipped out of {$total_records} total records");
            log_message('info', "📊 Insertion Summary: {$total_inserted} new records, {$total_skipped} duplicates (log_id already exists)");
            
            return $total_inserted;
            
        } catch (Exception $e) {
            log_message('error', "Error inserting teacher detail data in batches: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Run complete teacher detail ETL process with batch processing
     * @param string $extraction_date Date for extraction
     * @param int $user_id Optional specific user ID
     * @param int $batch_size Batch size for processing (default: 250)
     * @return array ETL results
     */
    public function run_complete_teacher_detail_etl($extraction_date = null, $user_id = null, $batch_size = 250) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // Set execution time limit
            if (function_exists('set_time_limit')) {
                set_time_limit(300); // 5 minutes
            }
            
            $start_time = microtime(true);
            
            log_message('info', "🚀 DETAIL ETL PROCESS STARTED: Date={$extraction_date}, Batch Size={$batch_size}");
            
            // Step 1: Extract and insert data from Moodle in optimized batches
            log_message('info', "📥 STEP 1: Extracting and inserting teacher detail data from Moodle...");
            $result = $this->extract_teacher_detail_from_moodle($extraction_date, $user_id, $batch_size);
            
            if (empty($result) || !isset($result['total_count']) || $result['total_count'] == 0) {
                log_message('warning', "⚠️ No teacher detail data extracted from Moodle");
                return [
                    'success' => false,
                    'error' => 'No teacher detail data extracted from Moodle',
                    'extracted' => 0,
                    'inserted' => 0,
                    'skipped' => 0
                ];
            }
            
            $extracted_count = $result['total_count'];
            $inserted_count = $result['total_inserted'];
            $batch_count = $result['batch_count'];
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            
            $total_skipped = $extracted_count - $inserted_count;
            
            log_message('info', "🎉 DETAIL ETL PROCESS COMPLETED: Duration={$duration}s, Extracted={$extracted_count}, Inserted={$inserted_count}, Skipped={$total_skipped}, Batches={$batch_count}");

            return [
                'success' => true,
                'extracted' => $extracted_count,
                'inserted' => $inserted_count,
                'skipped' => $total_skipped,
                'updated' => 0, // No update logic
                'date' => $extraction_date,
                'duration' => $duration,
                'batch_size' => $batch_size,
                'batch_count' => $batch_count,
                'message' => "Processed {$extracted_count} teacher detail records: {$inserted_count} new records inserted, {$total_skipped} duplicates skipped in {$batch_count} batches of {$batch_size}"
            ];

                 } catch (Exception $e) {
             return [
                 'success' => false,
                 'error' => $e->getMessage()
             ];
         }
     }

    /**
     * Get detail data with pagination and filters
     * @param int $page Page number
     * @param int $per_page Records per page
     * @param array $filters Filter conditions
     * @param string $order_by Order by field
     * @param string $order_direction Order direction (ASC/DESC)
     * @return array Paginated data with total count
     */
    public function get_detail_data_with_pagination($page = 1, $per_page = 20, $filters = [], $order_by = 'id', $order_direction = 'DESC') {
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
            
            // Detail table specific filters
            if (!empty($filters['component'])) {
                $where_conditions[] = 'component = ?';
                $where_values[] = $filters['component'];
            }
            
            if (!empty($filters['action'])) {
                $where_conditions[] = 'action = ?';
                $where_values[] = $filters['action'];
            }
            
            if (!empty($filters['target'])) {
                $where_conditions[] = 'target = ?';
                $where_values[] = $filters['target'];
            }
            
            $where_clause = '';
            if (!empty($where_conditions)) {
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            }
            
            // Validate order_by field to prevent SQL injection
            $allowed_order_fields = ['id', 'user_id', 'username', 'firstname', 'lastname', 'email', 'course_id', 'course_name', 'activity_date', 'component', 'action', 'target', 'log_id', 'extraction_date', 'created_at', 'updated_at'];
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
            log_message('error', "Error getting detail data with pagination: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'total' => 0
            ];
        }
    }
 }
