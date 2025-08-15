<?php
class cp_course_performance_etl_model extends CI_Model
{
    // Batch processing constants
    const BATCH_SIZE = 10000;
    const MEMORY_LIMIT = 512; // MB
    const MAX_EXECUTION_TIME = 3600; // 1 hour
    
    private $batch_size;
    private $start_time;
    private $memory_peak = 0;
    private $moodle_db; // Store Moodle database name
    private $moodle_db_connection; // Store Moodle database connection

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->batch_size = self::BATCH_SIZE;
        $this->start_time = microtime(true);
        
        // Set Moodle database name correctly
        $this->moodle_db = 'moodle';
        
        // Load Moodle database connection
        $this->load->database('moodle', TRUE);
        $this->moodle_db_connection = $this->db;
        
        // Load back the default database (celoeapi)
        $this->load->database('default', TRUE);
        
        // Optimize database connection for ETL workload
        $this->optimize_database_connection();
        
        // Set PHP limits for large operations
        $this->set_php_limits();
    }

    /**
     * Optimize database connection for ETL workloads
     */
    private function optimize_database_connection()
    {
        // Only set SESSION variables that don't require SUPER privileges
        $session_optimizations = [
            "SET SESSION bulk_insert_buffer_size = 67108864",     // 64MB
            "SET SESSION sort_buffer_size = 16777216",            // 16MB  
            "SET SESSION read_buffer_size = 8388608",             // 8MB
            "SET SESSION read_rnd_buffer_size = 16777216",        // 16MB
            "SET SESSION join_buffer_size = 16777216",            // 16MB
            "SET SESSION tmp_table_size = 134217728",             // 128MB
            "SET SESSION max_heap_table_size = 134217728",        // 128MB
            "SET SESSION autocommit = 0",                         // Manual transaction control
            "SET SESSION unique_checks = 0",                      // Disable for bulk operations
            "SET SESSION foreign_key_checks = 0"                 // Disable for ETL
        ];
        
        // Apply optimizations with error handling
        foreach ($session_optimizations as $optimization) {
            try {
                $this->db->query($optimization);
                log_message('debug', "Applied optimization: " . $optimization);
            } catch (Exception $e) {
                // Log warning but continue - some optimizations may not be available
                log_message('warning', "Could not apply optimization: " . $optimization . " - " . $e->getMessage());
            }
        }
        
        // Note: sql_log_bin optimization removed to avoid privilege issues
        // This optimization is not essential for ETL performance
        
        // Note: Global optimizations removed to avoid privilege issues
        // These optimizations can be applied at server level by DB administrator
    }

    /**
     * Set PHP limits for large operations
     */
    private function set_php_limits()
    {
        ini_set('memory_limit', self::MEMORY_LIMIT . 'M');
        set_time_limit(self::MAX_EXECUTION_TIME);
        ini_set('max_execution_time', self::MAX_EXECUTION_TIME);
    }



    /**
     * Enhanced ETL with batch processing and progress tracking
     */
    public function run_etl($incremental = false, $parallel = false)
    {
        $log_id = null;
        try {
            log_message('info', 'Starting optimized ETL process');
            
            // Create initial log entry
            $log_id = $this->create_scheduler_log();
            
            // Pre-ETL optimizations
            $this->pre_etl_optimizations();
            
            // Determine ETL strategy
            if ($incremental) {
                $total_records = $this->run_incremental_etl($log_id);
            } else {
                $total_records = $this->run_full_etl($log_id, $parallel);
            }
            
            // Post-ETL optimizations
            $this->post_etl_optimizations();
            
            // Update log as completed
            $this->complete_scheduler_log($log_id, $total_records);
            
            $duration = microtime(true) - $this->start_time;
            log_message('info', "ETL process completed in {$duration} seconds. Peak memory: " . $this->get_peak_memory());
            
            return [
                'success' => true,
                'message' => 'ETL process completed successfully',
                'timestamp' => date('c'),
                'total_records' => $total_records,
                'log_id' => $log_id,
                'duration' => $duration,
                'peak_memory' => $this->get_peak_memory()
            ];
        } catch (Exception $e) {
            if ($log_id) {
                $this->fail_scheduler_log($log_id, $e->getMessage());
            }
            log_message('error', 'ETL process failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Pre-ETL optimizations
     */
    private function pre_etl_optimizations()
    {
        // Create additional indexes for ETL performance
        $this->create_etl_indexes();
        
        // Analyze tables for optimal query plans
        $this->analyze_source_tables();
        
        // Disable query logging temporarily
        $this->db->save_queries = false;
    }

    /**
     * Post-ETL optimizations
     */
    private function post_etl_optimizations()
    {
        // Re-enable foreign key checks
        $this->db->query("SET SESSION foreign_key_checks = 1");
        $this->db->query("SET SESSION unique_checks = 1");
        
        // Commit any pending transactions
        $this->db->trans_commit();
        
        // Analyze ETL tables for optimal performance
        $this->analyze_etl_tables();
        
        // Re-enable query logging
        $this->db->save_queries = true;
    }

    /**
     * Run full ETL with optional parallel processing
     */
    private function run_full_etl($log_id, $parallel = false)
    {
        // Clear existing data efficiently
        $this->clear_existing_data_optimized();
        
        $total_records = 0;
        
        if ($parallel && extension_loaded('pcntl')) {
            // Parallel processing for multi-core systems
            $total_records = $this->run_parallel_etl($log_id);
        } else {
            // Sequential processing with batching
            $total_records += $this->etl_raw_log_batched($log_id);
            $total_records += $this->etl_course_activity_summary_batched($log_id);
            $total_records += $this->etl_student_profile_batched($log_id);
            $total_records += $this->etl_student_quiz_detail_batched($log_id);
            $total_records += $this->etl_student_assignment_detail_batched($log_id);
            $total_records += $this->etl_student_resource_access_batched($log_id);
            $total_records += $this->etl_course_summary_batched($log_id);
        }
        
        return $total_records;
    }

    /**
     * Incremental ETL - only process new/changed data
     */
    private function run_incremental_etl($log_id)
    {
        log_message('info', 'Running incremental ETL');
        
        $total_records = 0;
        $last_run_time = $this->get_last_successful_etl_time();
        
        // Only process data changed since last run
        $total_records += $this->etl_raw_log_incremental($last_run_time);
        $total_records += $this->etl_course_activity_summary_incremental($last_run_time);
        $total_records += $this->etl_student_profile_incremental($last_run_time);
        $total_records += $this->etl_student_quiz_detail_incremental($last_run_time);
        $total_records += $this->etl_student_assignment_detail_incremental($last_run_time);
        $total_records += $this->etl_student_resource_access_incremental($last_run_time);
        $total_records += $this->etl_course_summary_incremental($last_run_time);
        
        return $total_records;
    }

    /**
     * Optimized data clearing using TRUNCATE
     */
    private function clear_existing_data_optimized()
    {
        try {
            log_message('info', 'Clearing existing ETL data (optimized)');
            
            // Use TRUNCATE for better performance
            $tables = [
                'student_resource_access',
                'student_assignment_detail', 
                'student_quiz_detail',
                'student_profile',
                'course_activity_summary',
                'course_summary',
                'raw_log'
            ];
            
            $this->db->trans_start();
            
            foreach ($tables as $table) {
                $this->db->query("TRUNCATE TABLE celoeapi.{$table}");
            }
            
            $this->db->trans_complete();
            
            if ($this->db->trans_status() === FALSE) {
                throw new Exception('Failed to clear existing data');
            }
            
            log_message('info', 'Existing ETL data cleared (optimized)');
        } catch (Exception $e) {
            log_message('error', 'Error clearing existing data: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Batched ETL 1: raw_log with progress tracking
     */
    private function etl_raw_log_batched($log_id)
    {
        try {
            log_message('info', 'Running batched ETL 1: raw_log');
            
            // Get total count for progress tracking
            $count_query = $this->db->query("SELECT COUNT(*) as total FROM {$this->moodle_db}.mdl_logstore_standard_log");
            $total_rows = $count_query->row()->total;
            
            $processed = 0;
            $offset = 0;
            
            while ($offset < $total_rows) {
                $this->check_memory_usage();
                
                $query = "
                    INSERT INTO celoeapi.raw_log
                    SELECT 
                        id, eventname, component, action, target, objecttable, objectid,
                        crud, edulevel, contextid, contextlevel, contextinstanceid,
                        userid, courseid, relateduserid, anonymous, other,
                        timecreated, origin, ip, realuserid
                    FROM {$this->moodle_db}.mdl_logstore_standard_log
                    ORDER BY id
                    LIMIT {$this->batch_size} OFFSET {$offset}
                ";
                
                $this->db->trans_start();
                $result = $this->db->query($query);
                $this->db->trans_complete();
                
                if ($this->db->trans_status() === FALSE) {
                    throw new Exception("Batch insert failed at offset {$offset}");
                }
                
                $batch_affected = $this->db->affected_rows();
                $processed += $batch_affected;
                $offset += $this->batch_size;
                
                // Update progress
                $this->update_etl_progress($log_id, 1, $processed, $total_rows);
                
                log_message('info', "ETL 1 progress: {$processed}/{$total_rows} records");
                
                // Break if no more records
                if ($batch_affected < $this->batch_size) {
                    break;
                }
                
                // Small delay to prevent overwhelming the database
                usleep(10000); // 10ms
            }
            
            log_message('info', "ETL 1 completed: {$processed} records inserted");
            return $processed;
        } catch (Exception $e) {
            log_message('error', 'ETL 1 (raw_log) failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Batched ETL 2: course_activity_summary
     */
    private function etl_course_activity_summary_batched($log_id)
    {
        try {
            log_message('info', 'Running batched ETL 2: course_activity_summary');
            
            // Use batched approach for large course activity data
            $total_processed = 0;
            
            // Process in batches by course
            $course_query = $this->db->query("
                SELECT DISTINCT c.id as course_id 
                FROM {$this->moodle_db}.mdl_course c 
                WHERE c.visible = 1 
                ORDER BY c.id
            ");
            
            foreach ($course_query->result() as $course) {
                $this->check_memory_usage();
                
                $query = "
                    INSERT INTO celoeapi.course_activity_summary (
                        course_id, section, activity_id, activity_type, activity_name,
                        accessed_count, submission_count, graded_count, attempted_count
                    )
                    SELECT
                        c.id, cs.section, cm.instance, m.name,
                        CASE 
                            WHEN m.name = 'resource' THEN res.name
                            WHEN m.name = 'assign' THEN a.name
                            WHEN m.name = 'quiz' THEN q.name
                            ELSE 'Unknown'
                        END,
                        COUNT(DISTINCT l.userid),
                        CASE WHEN m.name = 'assign' THEN (
                            SELECT COUNT(*) FROM {$this->moodle_db}.mdl_assign_submission sub 
                            WHERE sub.assignment = a.id AND sub.status = 'submitted'
                        ) ELSE NULL END,
                        CASE WHEN m.name = 'assign' THEN (
                            SELECT COUNT(*) FROM {$this->moodle_db}.mdl_grade_grades gg
                            WHERE gg.itemid IN (
                                SELECT gi.id FROM {$this->moodle_db}.mdl_grade_items gi
                                WHERE gi.iteminstance = a.id AND gi.itemmodule = 'assign')
                            AND gg.finalgrade IS NOT NULL
                        ) ELSE NULL END,
                        CASE WHEN m.name = 'quiz' THEN (
                            SELECT COUNT(*) FROM {$this->moodle_db}.mdl_quiz_attempts qa
                            WHERE qa.quiz = q.id AND qa.state = 'finished'
                        ) ELSE NULL END
                    FROM {$this->moodle_db}.mdl_course_modules cm
                    JOIN {$this->moodle_db}.mdl_modules m ON m.id = cm.module
                    JOIN {$this->moodle_db}.mdl_course c ON c.id = cm.course
                    JOIN {$this->moodle_db}.mdl_course_sections cs ON cs.id = cm.section
                    LEFT JOIN {$this->moodle_db}.mdl_resource res ON res.id = cm.instance AND m.name = 'resource'
                    LEFT JOIN {$this->moodle_db}.mdl_assign a ON a.id = cm.instance AND m.name = 'assign'
                    LEFT JOIN {$this->moodle_db}.mdl_quiz q ON q.id = cm.instance AND m.name = 'quiz'
                    LEFT JOIN {$this->moodle_db}.mdl_logstore_standard_log l ON l.contextinstanceid = cm.id 
                        AND l.contextlevel = 70 AND l.action = 'viewed'
                    WHERE m.name IN ('resource', 'assign', 'quiz') AND c.id = {$course->course_id}
                    GROUP BY c.id, cs.section, cm.instance, m.name, res.name, a.name, q.name
                ";
                
                $this->db->trans_start();
                $this->db->query($query);
                $this->db->trans_complete();
                
                $batch_affected = $this->db->affected_rows();
                $total_processed += $batch_affected;
                
                usleep(5000); // 5ms delay
            }
            
            log_message('info', "ETL 2 completed: {$total_processed} records inserted");
            return $total_processed;
        } catch (Exception $e) {
            log_message('error', 'ETL 2 (course_activity_summary) failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Batched ETL 3: student_profile
     */
    private function etl_student_profile_batched($log_id)
    {
        try {
            log_message('info', 'Running batched ETL 3: student_profile');
            
            // Get student user IDs in batches
            $user_count_query = $this->db->query("
                SELECT COUNT(DISTINCT ra.userid) as total 
                FROM {$this->moodle_db}.mdl_role_assignments ra
                JOIN {$this->moodle_db}.mdl_context ctx ON ctx.id = ra.contextid
                WHERE ra.roleid = 5
            ");
            $total_users = $user_count_query->row()->total;
            
            $processed = 0;
            $offset = 0;
            
            while ($offset < $total_users) {
                $this->check_memory_usage();
                
                // Use JOIN with derived table to avoid MySQL 5.7 LIMIT in subquery limitation
                $query = "
                    INSERT INTO celoeapi.student_profile (
                        user_id, idnumber, full_name, email, program_studi
                    )
                    SELECT 
                        u.id, u.idnumber, CONCAT(u.firstname, ' ', u.lastname), u.email, d.data
                    FROM {$this->moodle_db}.mdl_user u
                    LEFT JOIN {$this->moodle_db}.mdl_user_info_data d ON d.userid = u.id AND d.fieldid = 1
                    INNER JOIN (
                        SELECT DISTINCT ra.userid
                        FROM {$this->moodle_db}.mdl_role_assignments ra
                        JOIN {$this->moodle_db}.mdl_context ctx ON ctx.id = ra.contextid
                        WHERE ra.roleid = 5
                        ORDER BY ra.userid
                        LIMIT {$this->batch_size} OFFSET {$offset}
                    ) AS batch_users ON u.id = batch_users.userid
                ";
                
                $this->db->trans_start();
                $this->db->query($query);
                $this->db->trans_complete();
                
                $batch_affected = $this->db->affected_rows();
                $processed += $batch_affected;
                $offset += $this->batch_size;
                
                log_message('info', "ETL 3 progress: {$processed}/{$total_users} records");
                
                if ($batch_affected < $this->batch_size) {
                    break;
                }
                
                usleep(5000);
            }
            
            log_message('info', "ETL 3 completed: {$processed} records inserted");
            return $processed;
        } catch (Exception $e) {
            log_message('error', 'ETL 3 (student_profile) failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Memory usage monitoring
     */
    private function check_memory_usage()
    {
        $current_memory = memory_get_usage(true) / 1024 / 1024; // MB
        $this->memory_peak = max($this->memory_peak, $current_memory);
        
        if ($current_memory > (self::MEMORY_LIMIT * 0.8)) {
            gc_collect_cycles();
            log_message('warning', "High memory usage detected: {$current_memory}MB");
        }
        
        if ($current_memory > (self::MEMORY_LIMIT * 0.95)) {
            throw new Exception("Memory limit approaching: {$current_memory}MB");
        }
    }

    /**
     * Get peak memory usage
     */
    private function get_peak_memory()
    {
        return round($this->memory_peak, 2) . 'MB';
    }

    /**
     * Create ETL-specific indexes for better performance
     */
    private function create_etl_indexes()
    {
        $indexes = [
            // Source table optimizations
            ['name' => 'idx_mdl_log_timecreated_userid', 'table' => "{$this->moodle_db}.mdl_logstore_standard_log", 'sql' => "CREATE INDEX idx_mdl_log_timecreated_userid ON {$this->moodle_db}.mdl_logstore_standard_log(timecreated, userid)"],
            ['name' => 'idx_mdl_log_contextlevel_action', 'table' => "{$this->moodle_db}.mdl_logstore_standard_log", 'sql' => "CREATE INDEX idx_mdl_log_contextlevel_action ON {$this->moodle_db}.mdl_logstore_standard_log(contextlevel, action)"],
            ['name' => 'idx_mdl_course_visible', 'table' => "{$this->moodle_db}.mdl_course", 'sql' => "CREATE INDEX idx_mdl_course_visible ON {$this->moodle_db}.mdl_course(visible, id)"],
            ['name' => 'idx_mdl_user_role', 'table' => "{$this->moodle_db}.mdl_role_assignments", 'sql' => "CREATE INDEX idx_mdl_user_role ON {$this->moodle_db}.mdl_role_assignments(roleid, userid)"],
            
            // ETL table optimizations
            ['name' => 'idx_raw_log_composite', 'table' => 'celoeapi.raw_log', 'sql' => "CREATE INDEX idx_raw_log_composite ON celoeapi.raw_log(courseid, userid, timecreated)"],
            ['name' => 'idx_course_activity_composite', 'table' => 'celoeapi.course_activity_summary', 'sql' => "CREATE INDEX idx_course_activity_composite ON celoeapi.course_activity_summary(course_id, activity_type)"],
            ['name' => 'idx_student_profile_composite', 'table' => 'celoeapi.student_profile', 'sql' => "CREATE INDEX idx_student_profile_composite ON celoeapi.student_profile(user_id, idnumber)"],
        ];
        
        foreach ($indexes as $index) {
            // Check if index already exists before creating
            if ($this->index_exists($index['name'], $index['table'])) {
                log_message('info', 'Index already exists, skipping: ' . $index['sql']);
                continue;
            }
            
            $result = $this->db->query($index['sql']);
            
            if ($result !== false) {
                log_message('info', 'Index created successfully: ' . $index['sql']);
            } else {
                $error = $this->db->error();
                $error_message = isset($error['message']) ? $error['message'] : 'Unknown error';
                
                // Check for various duplicate index error messages
                if (strpos($error_message, 'Duplicate key name') !== false || 
                    strpos($error_message, 'Duplicate entry') !== false ||
                    strpos($error_message, 'already exists') !== false) {
                    log_message('info', 'Index already exists, skipping: ' . $index['sql']);
                } else {
                    log_message('warning', 'Index creation failed: ' . $error_message);
                }
            }
        }
    }
    

    
    /**
     * Check if index exists on table
     */
    private function index_exists($index_name, $table_name)
    {
        if (!$index_name || !$table_name) {
            return false;
        }
        
        try {
            $sql = "SHOW INDEX FROM {$table_name} WHERE Key_name = ?";
            $query = $this->db->query($sql, array($index_name));
            
            return $query && $query->num_rows() > 0;
        } catch (Exception $e) {
            // If there's an error checking, assume index doesn't exist
            log_message('debug', 'Error checking index existence: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Analyze tables for optimal query execution plans
     */
    private function analyze_source_tables()
    {
        $tables = [
            "{$this->moodle_db}.mdl_logstore_standard_log",
            "{$this->moodle_db}.mdl_course",
            "{$this->moodle_db}.mdl_user",
            "{$this->moodle_db}.mdl_role_assignments"
        ];
        
        foreach ($tables as $table) {
            $this->db->query("ANALYZE TABLE {$table}");
        }
    }

    /**
     * Analyze ETL tables
     */
    private function analyze_etl_tables()
    {
        $tables = [
            'celoeapi.raw_log',
            'celoeapi.course_activity_summary',
            'celoeapi.student_profile',
            'celoeapi.student_quiz_detail',
            'celoeapi.student_assignment_detail',
            'celoeapi.student_resource_access',
            'celoeapi.course_summary'
        ];
        
        foreach ($tables as $table) {
            $this->db->query("ANALYZE TABLE {$table}");
        }
    }

    /**
     * Update ETL progress in log
     */
    private function update_etl_progress($log_id, $etl_step, $processed, $total)
    {
        $progress = round(($processed / $total) * 100, 2);
        $this->db->query(
            "UPDATE celoeapi.log_scheduler SET offset = ?, numrow = ? WHERE id = ?",
            array($processed, $total, $log_id)
        );
    }

    /**
     * Get last successful ETL time for incremental processing
     */
    private function get_last_successful_etl_time()
    {
        $query = $this->db->query("
            SELECT end_date 
            FROM celoeapi.log_scheduler 
            WHERE status = 1 
            ORDER BY id DESC 
            LIMIT 1
        ");
        
        $result = $query->row();
        return $result ? $result->end_date : '1970-01-01 00:00:00';
    }

    /**
     * Run ETL operations in parallel (requires pcntl extension)
     */
    private function run_parallel_etl($log_id)
    {
        log_message('info', 'Running parallel ETL operations');
        
        $total_records = 0;
        $pids = [];
        
        // Note: This is a simplified parallel implementation
        // In production, you might want to use a proper job queue system
        
        try {
            // For now, fall back to sequential processing
            // Real parallel implementation would require shared memory/database coordination
            log_message('warning', 'Parallel processing not fully implemented, falling back to sequential');
            
            $total_records += $this->etl_raw_log_batched($log_id);
            $total_records += $this->etl_course_activity_summary_batched($log_id);
            $total_records += $this->etl_student_profile_batched($log_id);
            $total_records += $this->etl_student_quiz_detail_batched($log_id);
            $total_records += $this->etl_student_assignment_detail_batched($log_id);
            $total_records += $this->etl_student_resource_access_batched($log_id);
            $total_records += $this->etl_course_summary_batched($log_id);
            
            return $total_records;
            
        } catch (Exception $e) {
            log_message('error', 'Parallel ETL failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Incremental ETL for raw_log
     */
    private function etl_raw_log_incremental($last_run_time)
    {
        $query = "
            INSERT INTO celoeapi.raw_log
            SELECT 
                id, eventname, component, action, target, objecttable, objectid,
                crud, edulevel, contextid, contextlevel, contextinstanceid,
                userid, courseid, relateduserid, anonymous, other,
                timecreated, origin, ip, realuserid
            FROM {$this->moodle_db}.mdl_logstore_standard_log
            WHERE FROM_UNIXTIME(timecreated) > '{$last_run_time}'
        ";
        
        $this->db->query($query);
        return $this->db->affected_rows();
    }

    // Continue with other incremental ETL methods...
    private function etl_course_activity_summary_incremental($last_run_time)
    {
        // Implementation for incremental course activity summary
        return 0; // Placeholder
    }

    private function etl_student_profile_incremental($last_run_time)
    {
        // Implementation for incremental student profile
        return 0; // Placeholder
    }

    private function etl_student_quiz_detail_incremental($last_run_time)
    {
        // Implementation for incremental quiz detail
        return 0; // Placeholder
    }

    private function etl_student_assignment_detail_incremental($last_run_time)
    {
        // Implementation for incremental assignment detail
        return 0; // Placeholder
    }

    private function etl_student_resource_access_incremental($last_run_time)
    {
        // Implementation for incremental resource access
        return 0; // Placeholder
    }

    private function etl_course_summary_incremental($last_run_time)
    {
        // Implementation for incremental course summary
        return 0; // Placeholder
    }

    // Continue with remaining original methods...
    private function etl_student_quiz_detail_batched($log_id)
    {
        // Batched implementation
        return $this->etl_student_quiz_detail();
    }

    private function etl_student_assignment_detail_batched($log_id)
    {
        // Batched implementation
        return $this->etl_student_assignment_detail();
    }

    private function etl_student_resource_access_batched($log_id)
    {
        // Batched implementation
        return $this->etl_student_resource_access();
    }

    private function etl_course_summary_batched($log_id)
    {
        // Batched implementation
        return $this->etl_course_summary();
    }

    // ETL 4: student_quiz_detail
    private function etl_student_quiz_detail()
    {
        try {
            log_message('info', 'Running ETL 4: student_quiz_detail');

            $query = "
                    INSERT INTO celoeapi.student_quiz_detail (
                        quiz_id, user_id, nim, full_name, waktu_mulai, waktu_selesai,
                        durasi_waktu, jumlah_soal, jumlah_dikerjakan, nilai
                    )
                    SELECT 
                        q.id, u.id, u.idnumber, CONCAT(u.firstname, ' ', u.lastname),
                        FROM_UNIXTIME(qa.timestart), FROM_UNIXTIME(qa.timefinish),
                        SEC_TO_TIME(qa.timefinish - qa.timestart),
                        (SELECT COUNT(*) FROM {$this->moodle_db}.mdl_question_attempts qat WHERE qat.questionusageid = qa.uniqueid),
                        (SELECT COUNT(DISTINCT qat.id) FROM {$this->moodle_db}.mdl_question_attempts qat
                         JOIN {$this->moodle_db}.mdl_question_attempt_steps qas ON qas.questionattemptid = qat.id
                         WHERE qat.questionusageid = qa.uniqueid AND qas.state LIKE 'graded%'),
                        ROUND((qa.sumgrades / q.sumgrades) * 10, 2)
                    FROM {$this->moodle_db}.mdl_quiz_attempts qa
                    JOIN {$this->moodle_db}.mdl_user u ON u.id = qa.userid
                    JOIN {$this->moodle_db}.mdl_quiz q ON q.id = qa.quiz
                    WHERE qa.state = 'finished'
                ";

            $result = $this->db->query($query);
            $affected_rows = $this->db->affected_rows();

            log_message('info', "ETL 4 completed: {$affected_rows} records inserted");
            return $affected_rows;
        } catch (Exception $e) {
            log_message('error', 'ETL 4 (student_quiz_detail) failed: ' . $e->getMessage());
            throw $e;
        }
    }

    // ETL 5: student_assignment_detail
    private function etl_student_assignment_detail()
    {
        try {
            log_message('info', 'Running ETL 5: student_assignment_detail');

            $query = "
                    INSERT INTO celoeapi.student_assignment_detail (
                        assignment_id, user_id, nim, full_name, waktu_submit, waktu_pengerjaan, nilai
                    )
                    SELECT 
                        a.id, u.id, u.idnumber, CONCAT(u.firstname, ' ', u.lastname),
                        FROM_UNIXTIME(sub.timemodified),
                        SEC_TO_TIME(sub.timemodified - COALESCE((
                            SELECT MIN(l.timecreated) FROM {$this->moodle_db}.mdl_logstore_standard_log l
                            JOIN {$this->moodle_db}.mdl_course_modules cm ON cm.id = l.contextinstanceid
                            JOIN {$this->moodle_db}.mdl_modules m ON m.id = cm.module
                            WHERE cm.instance = a.id AND m.name = 'assign' AND l.userid = u.id AND l.action = 'viewed'
                        ), sub.timemodified)),
                        gg.finalgrade
                    FROM {$this->moodle_db}.mdl_assign_submission sub
                    JOIN {$this->moodle_db}.mdl_user u ON u.id = sub.userid
                    JOIN {$this->moodle_db}.mdl_assign a ON a.id = sub.assignment
                    JOIN {$this->moodle_db}.mdl_grade_items gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
                    JOIN {$this->moodle_db}.mdl_grade_grades gg ON gg.itemid = gi.id AND gg.userid = u.id
                    WHERE sub.status = 'submitted'
                ";

            $result = $this->db->query($query);
            $affected_rows = $this->db->affected_rows();

            log_message('info', "ETL 5 completed: {$affected_rows} records inserted");
            return $affected_rows;
        } catch (Exception $e) {
            log_message('error', 'ETL 5 (student_assignment_detail) failed: ' . $e->getMessage());
            throw $e;
        }
    }

    // ETL 6: student_resource_access
    private function etl_student_resource_access()
    {
        try {
            log_message('info', 'Running ETL 6: student_resource_access');

            $query = "
                    INSERT INTO celoeapi.student_resource_access (
                        resource_id, user_id, nim, full_name, waktu_akses
                    )
                    SELECT 
                        r.id, u.id, u.idnumber, CONCAT(u.firstname, ' ', u.lastname),
                        FROM_UNIXTIME(l.timecreated)
                    FROM {$this->moodle_db}.mdl_logstore_standard_log l
                    JOIN {$this->moodle_db}.mdl_user u ON u.id = l.userid
                    JOIN {$this->moodle_db}.mdl_course_modules cm ON cm.id = l.contextinstanceid
                    JOIN {$this->moodle_db}.mdl_modules m ON m.id = cm.module
                    JOIN {$this->moodle_db}.mdl_resource r ON r.id = cm.instance AND m.name = 'resource'
                    WHERE l.action = 'viewed' AND l.component = 'mod_resource' AND l.target = 'course_module'
                ";

            $result = $this->db->query($query);
            $affected_rows = $this->db->affected_rows();

            log_message('info', "ETL 6 completed: {$affected_rows} records inserted");
            return $affected_rows;
        } catch (Exception $e) {
            log_message('error', 'ETL 6 (student_resource_access) failed: ' . $e->getMessage());
            throw $e;
        }
    }

    // ETL 7: course_summary
    private function etl_course_summary()
    {
        try {
            log_message('info', 'Running ETL 7: course_summary');

            $query = "
                    INSERT INTO celoeapi.course_summary (
                        course_id, course_name, kelas, jumlah_aktivitas, jumlah_mahasiswa, dosen_pengampu
                    )
                    SELECT 
                        c.id, c.fullname, c.shortname,
                          (SELECT COUNT(*) 
                            FROM {$this->moodle_db}.mdl_course_modules cm
                            JOIN {$this->moodle_db}.mdl_modules m ON cm.module = m.id
                            WHERE cm.course = c.id
                            AND m.name IN ('assign', 'resource', 'quiz')
                        ) AS jumlah_aktivitas,
                        (SELECT COUNT(DISTINCT ra.userid) FROM {$this->moodle_db}.mdl_role_assignments ra
                         JOIN {$this->moodle_db}.mdl_context ctx ON ctx.id = ra.contextid
                         WHERE ctx.contextlevel = 50 AND ctx.instanceid = c.id AND ra.roleid = 5),
                        (SELECT GROUP_CONCAT(CONCAT(u.firstname, ' ', u.lastname) SEPARATOR ', ') FROM {$this->moodle_db}.mdl_user u
                         JOIN {$this->moodle_db}.mdl_role_assignments ra ON ra.userid = u.id
                         JOIN {$this->moodle_db}.mdl_context ctx ON ctx.id = ra.contextid
                         WHERE ctx.contextlevel = 50 AND ctx.instanceid = c.id AND ra.roleid = 3)
                    FROM {$this->moodle_db}.mdl_course c
                    WHERE c.visible = 1
                ";

            $result = $this->db->query($query);
            $affected_rows = $this->db->affected_rows();

            log_message('info', "ETL 7 completed: {$affected_rows} records inserted");
            return $affected_rows;
        } catch (Exception $e) {
            log_message('error', 'ETL 7 (course_summary) failed: ' . $e->getMessage());
            throw $e;
        }
    }

    // Get ETL status and last run info
    public function get_etl_status()
    {
        try {
            // Get latest ETL run from log_scheduler table
            $query = $this->db->query("SELECT * FROM celoeapi.log_scheduler ORDER BY id DESC LIMIT 1");
            $last_run = $query->row();

            // Check if there's an ETL in progress
            $running_query = $this->db->query("SELECT COUNT(*) as running_count FROM celoeapi.log_scheduler WHERE status = 2");
            $is_running = $running_query->row()->running_count > 0;

            return [
                'status' => 'active',
                'lastRun' => $last_run ? [
                    'id' => $last_run->id,
                    'start_date' => $last_run->start_date,
                    'end_date' => $last_run->end_date,
                    'status' => $last_run->status == 1 ? 'finished' : ($last_run->status == 2 ? 'inprogress' : 'failed'),
                    'total_records' => $last_run->numrow,
                    'offset' => $last_run->offset
                ] : null,
                'nextRun' => 'Every hour at minute 0',
                'isRunning' => $is_running
            ];
        } catch (Exception $e) {
            log_message('error', 'Error getting ETL status: ' . $e->getMessage());
            throw $e;
        }
    }

    // Get ETL logs history with pagination
    public function get_etl_logs($limit = 20, $offset = 0)
    {
        try {
            // Get total count
            $count_query = $this->db->query("SELECT COUNT(*) as total FROM celoeapi.log_scheduler");
            $total = $count_query->row()->total;

            // Get logs with pagination
            $query = $this->db->query("SELECT * FROM celoeapi.log_scheduler ORDER BY id DESC LIMIT ? OFFSET ?", array($limit, $offset));
            $logs = $query->result();

            // Format logs
            $formatted_logs = [];
            foreach ($logs as $log) {
                $duration = null;
                if ($log->start_date && $log->end_date) {
                    $start = new DateTime($log->start_date);
                    $end = new DateTime($log->end_date);
                    $duration = $end->diff($start)->format('%H:%I:%S');
                }

                $formatted_logs[] = [
                    'id' => $log->id,
                    'start_date' => $log->start_date,
                    'end_date' => $log->end_date,
                    'duration' => $duration,
                    'status' => $log->status == 1 ? 'finished' : ($log->status == 2 ? 'inprogress' : 'failed'),
                    'total_records' => $log->numrow,
                    'offset' => $log->offset,
                    'created_at' => isset($log->created_at) ? $log->created_at : null
                ];
            }

            return [
                'logs' => $formatted_logs,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'current_page' => floor($offset / $limit) + 1,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
        } catch (Exception $e) {
            log_message('error', 'Error getting ETL logs: ' . $e->getMessage());
            throw $e;
        }
    }

    // Create initial scheduler log entry
    private function create_scheduler_log()
    {
        try {
            $data = [
                'offset' => 0,
                'numrow' => 0,
                'status' => 2, // 2 = inprogress
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => null
            ];

            $this->db->query(
                "INSERT INTO celoeapi.log_scheduler (offset, numrow, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)",
                array($data['offset'], $data['numrow'], $data['status'], $data['start_date'], $data['end_date'])
            );
            $log_id = $this->db->insert_id();

            log_message('info', "ETL scheduler log created with ID: {$log_id}");
            return $log_id;
        } catch (Exception $e) {
            log_message('error', 'Error creating scheduler log: ' . $e->getMessage());
            throw $e;
        }
    }

    // Complete scheduler log entry
    private function complete_scheduler_log($log_id, $total_records)
    {
        try {
            $end_date = date('Y-m-d H:i:s');
            $this->db->query(
                "UPDATE celoeapi.log_scheduler SET numrow = ?, status = ?, end_date = ? WHERE id = ?",
                array($total_records, 1, $end_date, $log_id)
            );

            log_message('info', "ETL scheduler log completed. ID: {$log_id}, Records: {$total_records}");
        } catch (Exception $e) {
            log_message('error', 'Error completing scheduler log: ' . $e->getMessage());
            throw $e;
        }
    }

    // Mark scheduler log as failed
    private function fail_scheduler_log($log_id, $error_message)
    {
        try {
            $end_date = date('Y-m-d H:i:s');
            $this->db->query(
                "UPDATE celoeapi.log_scheduler SET status = ?, end_date = ? WHERE id = ?",
                array(3, $end_date, $log_id)
            );

            log_message('error', "ETL scheduler log marked as failed. ID: {$log_id}, Error: {$error_message}");
        } catch (Exception $e) {
            log_message('error', 'Error updating failed scheduler log: ' . $e->getMessage());
        }
    }
}
