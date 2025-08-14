<?php
class DataExportCoursePerformance_Model extends CI_Model
{
    private $batch_size = 1000;
    private $max_memory_usage = 256; // MB
    private $query_timeout = 300; // 5 minutes
    private $logs_db; // Second database connection for moodle_logs

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        
        // Load second database connection for moodle_logs
        $this->logs_db = $this->load->database('moodle_logs', TRUE);
        
        // Set PHP limits for large data operations
        ini_set('memory_limit', $this->max_memory_usage . 'M');
        set_time_limit($this->query_timeout);
        
        // Optimize database connection for read operations
        $this->optimize_for_reads();
    }

    /**
     * Optimize database connection for read operations
     */
    private function optimize_for_reads()
    {
        $read_optimizations = [
            "SET SESSION read_buffer_size = 16777216",        // 16MB
            "SET SESSION read_rnd_buffer_size = 33554432",    // 32MB
            "SET SESSION sort_buffer_size = 16777216",        // 16MB
            "SET SESSION join_buffer_size = 16777216",        // 16MB
            "SET SESSION tmp_table_size = 134217728",         // 128MB
            "SET SESSION max_heap_table_size = 134217728"     // 128MB
        ];
        
        foreach ($read_optimizations as $optimization) {
            try {
                $this->logs_db->query($optimization);
            } catch (Exception $e) {
                log_message('warning', "Could not apply read optimization: " . $optimization);
            }
        }
    }

    /**
     * Get course activity summary data with pagination
     */
    public function get_course_activity_summary($page = 1, $limit = 1000, $course_id = null, $activity_type = null)
    {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where_conditions = [];
        $where_params = [];
        
        if ($course_id) {
            $where_conditions[] = "course_id = ?";
            $where_params[] = $course_id;
        }
        
        if ($activity_type) {
            $where_conditions[] = "activity_type = ?";
            $where_params[] = $activity_type;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM course_activity_summary $where_clause";
        $count_query = $this->logs_db->query($count_sql, $where_params);
        $total = $count_query->row()->total;
        
        // Get data with pagination
        $data_sql = "SELECT * FROM course_activity_summary $where_clause ORDER BY id ASC LIMIT ? OFFSET ?";
        $data_params = array_merge($where_params, [$limit, $offset]);
        $data_query = $this->logs_db->query($data_sql, $data_params);
        
        return [
            'data' => $data_query->result_array(),
            'total' => $total
        ];
    }

    /**
     * Get student profile data with pagination
     */
    public function get_student_profile($page = 1, $limit = 1000, $program_studi = null, $idnumber = null)
    {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where_conditions = [];
        $where_params = [];
        
        if ($program_studi) {
            $where_conditions[] = "program_studi = ?";
            $where_params[] = $program_studi;
        }
        
        if ($idnumber) {
            $where_conditions[] = "idnumber = ?";
            $where_params[] = $idnumber;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM student_profile $where_clause";
        $count_query = $this->logs_db->query($count_sql, $where_params);
        $total = $count_query->row()->total;
        
        // Get data with pagination
        $data_sql = "SELECT * FROM student_profile $where_clause ORDER BY id ASC LIMIT ? OFFSET ?";
        $data_params = array_merge($where_params, [$limit, $offset]);
        $data_query = $this->logs_db->query($data_sql, $data_params);
        
        return [
            'data' => $data_query->result_array(),
            'total' => $total
        ];
    }

    /**
     * Get student quiz detail data with pagination
     */
    public function get_student_quiz_detail($page = 1, $limit = 1000, $quiz_id = null, $user_id = null, $nim = null)
    {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where_conditions = [];
        $where_params = [];
        
        if ($quiz_id) {
            $where_conditions[] = "quiz_id = ?";
            $where_params[] = $quiz_id;
        }
        
        if ($user_id) {
            $where_conditions[] = "user_id = ?";
            $where_params[] = $user_id;
        }
        
        if ($nim) {
            $where_conditions[] = "nim = ?";
            $where_params[] = $nim;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM student_quiz_detail $where_clause";
        $count_query = $this->logs_db->query($count_sql, $where_params);
        $total = $count_query->row()->total;
        
        // Get data with pagination
        $data_sql = "SELECT * FROM student_quiz_detail $where_clause ORDER BY id ASC LIMIT ? OFFSET ?";
        $data_params = array_merge($where_params, [$limit, $offset]);
        $data_query = $this->logs_db->query($data_sql, $data_params);
        
        return [
            'data' => $data_query->result_array(),
            'total' => $total
        ];
    }

    /**
     * Get student assignment detail data with pagination
     */
    public function get_student_assignment_detail($page = 1, $limit = 1000, $assignment_id = null, $user_id = null, $nim = null)
    {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where_conditions = [];
        $where_params = [];
        
        if ($assignment_id) {
            $where_conditions[] = "assignment_id = ?";
            $where_params[] = $assignment_id;
        }
        
        if ($user_id) {
            $where_conditions[] = "user_id = ?";
            $where_params[] = $user_id;
        }
        
        if ($nim) {
            $where_conditions[] = "nim = ?";
            $where_params[] = $nim;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM student_assignment_detail $where_clause";
        $count_query = $this->logs_db->query($count_sql, $where_params);
        $total = $count_query->row()->total;
        
        // Get data with pagination
        $data_sql = "SELECT * FROM student_assignment_detail $where_clause ORDER BY id ASC LIMIT ? OFFSET ?";
        $data_params = array_merge($where_params, [$limit, $offset]);
        $data_query = $this->logs_db->query($data_sql, $data_params);
        
        return [
            'data' => $data_query->result_array(),
            'total' => $total
        ];
    }

    /**
     * Get student resource access data with pagination
     */
    public function get_student_resource_access($page = 1, $limit = 1000, $resource_id = null, $user_id = null, $nim = null, $date_from = null, $date_to = null)
    {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where_conditions = [];
        $where_params = [];
        
        if ($resource_id) {
            $where_conditions[] = "resource_id = ?";
            $where_params[] = $resource_id;
        }
        
        if ($user_id) {
            $where_conditions[] = "user_id = ?";
            $where_params[] = $user_id;
        }
        
        if ($nim) {
            $where_conditions[] = "nim = ?";
            $where_params[] = $nim;
        }
        
        if ($date_from) {
            $where_conditions[] = "waktu_akses >= ?";
            $where_params[] = $date_from;
        }
        
        if ($date_to) {
            $where_conditions[] = "waktu_akses <= ?";
            $where_params[] = $date_to;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM student_resource_access $where_clause";
        $count_query = $this->logs_db->query($count_sql, $where_params);
        $total = $count_query->row()->total;
        
        // Get data with pagination
        $data_sql = "SELECT * FROM student_resource_access $where_clause ORDER BY id ASC LIMIT ? OFFSET ?";
        $data_params = array_merge($where_params, [$limit, $offset]);
        $data_query = $this->logs_db->query($data_sql, $data_params);
        
        return [
            'data' => $data_query->result_array(),
            'total' => $total
        ];
    }

    /**
     * Get course summary data with pagination
     */
    public function get_course_summary($page = 1, $limit = 1000, $course_id = null, $kelas = null)
    {
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where_conditions = [];
        $where_params = [];
        
        if ($course_id) {
            $where_conditions[] = "course_id = ?";
            $where_params[] = $course_id;
        }
        
        if ($kelas) {
            $where_conditions[] = "kelas = ?";
            $where_params[] = $kelas;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM course_summary $where_clause";
        $count_query = $this->logs_db->query($count_sql, $where_params);
        $total = $count_query->row()->total;
        
        // Get data with pagination
        $data_sql = "SELECT * FROM course_summary $where_clause ORDER BY id ASC LIMIT ? OFFSET ?";
        $data_params = array_merge($where_params, [$limit, $offset]);
        $data_query = $this->logs_db->query($data_sql, $data_params);
        
        return [
            'data' => $data_query->result_array(),
            'total' => $total
        ];
    }

    /**
     * Get export status and statistics
     */
    public function get_export_status()
    {
        $status = [];
        
        // Get table statistics
        $tables = [
            'course_activity_summary',
            'student_profile',
            'student_quiz_detail',
            'student_assignment_detail',
            'student_resource_access',
            'course_summary'
        ];
        
        foreach ($tables as $table) {
            try {
                $count_query = $this->logs_db->query("SELECT COUNT(*) as total FROM $table");
                $count = $count_query->row()->total;
                
                // Get last updated timestamp (if column exists)
                $last_update = null;
                try {
                    // First check if updated_at column exists
                    $column_check = $this->logs_db->query("SHOW COLUMNS FROM $table LIKE 'updated_at'");
                    if ($column_check->num_rows() > 0) {
                        $last_update_query = $this->logs_db->query("SELECT MAX(updated_at) as last_update FROM $table");
                        $last_update = $last_update_query->row()->last_update;
                    } else {
                        // If no updated_at column, use created_at if available
                        $column_check = $this->logs_db->query("SHOW COLUMNS FROM $table LIKE 'created_at'");
                        if ($column_check->num_rows() > 0) {
                            $last_update_query = $this->logs_db->query("SELECT MAX(created_at) as last_update FROM $table");
                            $last_update = $last_update_query->row()->last_update;
                        }
                    }
                } catch (Exception $e) {
                    $last_update = null;
                }
                
                $status[$table] = [
                    'total_records' => $count,
                    'last_updated' => $last_update,
                    'estimated_pages' => ceil($count / $this->batch_size)
                ];
            } catch (Exception $e) {
                $status[$table] = [
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Get system information
        $status['system'] = [
            'memory_usage' => memory_get_usage(true) / 1024 / 1024, // MB
            'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024, // MB
            'max_memory_limit' => $this->max_memory_usage,
            'batch_size' => $this->batch_size,
            'query_timeout' => $this->query_timeout,
            'database' => 'moodle_logs'
        ];
        
        return $status;
    }

    /**
     * Bulk export multiple tables with concurrent processing
     */
    public function bulk_export($tables, $page = 1, $limit = 1000)
    {
        $result = [
            'data' => [],
            'pagination' => []
        ];
        
        $total_records = 0;
        $total_pages = 0;
        
        foreach ($tables as $table) {
            try {
                // Get table data based on table name
                switch ($table) {
                    case 'course_activity_summary':
                        $table_data = $this->get_course_activity_summary($page, $limit);
                        break;
                    case 'student_profile':
                        $table_data = $this->get_student_profile($page, $limit);
                        break;
                    case 'student_quiz_detail':
                        $table_data = $this->get_student_quiz_detail($page, $limit);
                        break;
                    case 'student_assignment_detail':
                        $table_data = $this->get_student_assignment_detail($page, $limit);
                        break;
                    case 'student_resource_access':
                        $table_data = $this->get_student_resource_access($page, $limit);
                        break;
                    case 'course_summary':
                        $table_data = $this->get_course_summary($page, $limit);
                        break;
                    default:
                        continue 2; // Skip unknown table
                }
                
                $result['data'][$table] = $table_data['data'];
                $total_records += $table_data['total'];
                $total_pages = max($total_pages, ceil($table_data['total'] / $limit));
                
            } catch (Exception $e) {
                $result['data'][$table] = [
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Set pagination info
        $result['pagination'] = [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'has_next' => ($page * $limit) < $total_records,
            'has_prev' => $page > 1
        ];
        
        return $result;
    }

    /**
     * Get optimized query for large datasets
     */
    private function get_optimized_query($table, $where_clause = '', $params = [], $limit = 1000, $offset = 0)
    {
        // Use SQL_CALC_FOUND_ROWS for efficient counting
        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM $table $where_clause ORDER BY id ASC LIMIT ? OFFSET ?";
        $query_params = array_merge($params, [$limit, $offset]);
        
        $query = $this->logs_db->query($sql, $query_params);
        $data = $query->result_array();
        
        // Get total count
        $count_query = $this->logs_db->query("SELECT FOUND_ROWS() as total");
        $total = $count_query->row()->total;
        
        return [
            'data' => $data,
            'total' => $total
        ];
    }

    /**
     * Check memory usage and optimize if needed
     */
    private function check_memory_usage()
    {
        $current_memory = memory_get_usage(true) / 1024 / 1024; // MB
        $peak_memory = memory_get_peak_usage(true) / 1024 / 1024; // MB
        
        if ($current_memory > ($this->max_memory_usage * 0.8)) {
            // Force garbage collection
            gc_collect_cycles();
            
            log_message('warning', "High memory usage detected: {$current_memory}MB (peak: {$peak_memory}MB)");
        }
        
        return $current_memory;
    }
} 