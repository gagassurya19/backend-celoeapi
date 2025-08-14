<?php
// CREATE TABLE etl_chart_logs (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     start_date DATETIME,
//     end_date DATETIME,
//     duration VARCHAR(20),
//     status VARCHAR(20),
//     total_records INT,
//     offset INT,
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// );
class ETL_Chart_Model extends CI_Model
{
    private $batch_size;
    private $start_time;
    private $moodle_logs_db = 'moodle_logs';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->config('etl_chart');
        $this->batch_size = $this->config->item('etl_chart_batch_size') ?: 1000;
        $this->start_time = microtime(true);
        
        // Set PHP limits for large operations
        $this->set_php_limits();
    }

    /**
     * Set PHP limits for large operations
     */
    private function set_php_limits()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(1800); // 30 minutes
        ini_set('max_execution_time', 1800);
    }

    /**
     * Create initial log entry for ETL process
     */
    public function create_etl_log()
    {
        $data = [
            'start_date' => date('Y-m-d H:i:s'),
            'status' => 'running',
            'total_records' => 0,
            'offset' => 0
        ];
        
        $this->db->query(
            "INSERT INTO {$this->moodle_logs_db}.etl_chart_logs (start_date, status, total_records, offset) VALUES (?, ?, ?, ?)",
            [$data['start_date'], $data['status'], $data['total_records'], $data['offset']]
        );
        
        return $this->db->insert_id();
    }

    /**
     * Update ETL log with final status
     */
    public function update_etl_log($log_id, $status, $total_records)
    {
        $end_date = date('Y-m-d H:i:s');
        $start_result = $this->db->query(
            "SELECT start_date FROM {$this->moodle_logs_db}.etl_chart_logs WHERE id = ?",
            [$log_id]
        )->row();
        
        if ($start_result) {
            $start_time = new DateTime($start_result->start_date);
            $end_time = new DateTime($end_date);
            $duration = $end_time->diff($start_time)->format('%H:%I:%S');
        } else {
            $duration = '00:00:00';
        }
        
        $this->db->query(
            "UPDATE {$this->moodle_logs_db}.etl_chart_logs 
             SET end_date = ?, duration = ?, status = ?, total_records = ? 
             WHERE id = ?",
            [$end_date, $duration, $status, $total_records, $log_id]
        );
    }

    /**
     * Get ETL logs with pagination
     */
    public function get_etl_logs($limit = 5, $offset = 0)
    {
        // Get logs
        $logs_result = $this->db->query(
            "SELECT id, start_date, end_date, duration, status, total_records, offset, created_at 
             FROM {$this->moodle_logs_db}.etl_chart_logs 
             ORDER BY id DESC 
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        
        // Get total count
        $count_result = $this->db->query(
            "SELECT COUNT(*) as total FROM {$this->moodle_logs_db}.etl_chart_logs"
        );
        
        $total = $count_result->row()->total;
        $total_pages = ceil($total / $limit);
        $current_page = floor($offset / $limit) + 1;
        
        return [
            'logs' => $logs_result->result(),
            'pagination' => [
                'total' => $total,
                'limit' => (int)$limit,
                'offset' => (int)$offset,
                'current_page' => (int)$current_page,
                'total_pages' => (int)$total_pages
            ]
        ];
    }

    /**
     * Fetch data from external API
     */
    public function fetch_external_api($endpoint)
    {
        $base_url = $this->config->item('celoe_api_base_url');
        $api_key = $this->config->item('celoe_api_key');
        $url = $base_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "celoe-api-key: {$api_key}",
                "Content-Type: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }
        
        if ($http_code !== 200) {
            throw new Exception("HTTP Error: {$http_code}");
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['status']) || !$data['status']) {
            throw new Exception("Invalid API response");
        }
        
        return $data['data'] ?? [];
    }

    /**
     * Fetch data from external API with detailed logging
     * 
     * @param string $endpoint API endpoint to fetch from
     * @param int $log_id ETL log ID for logging
     * @param float $start_progress Starting progress percentage
     * @param float $end_progress Ending progress percentage
     * @return array API response data
     */
    public function fetch_external_api_with_logging($endpoint, $log_id, $start_progress, $end_progress)
    {
        $base_url = $this->config->item('celoe_api_base_url');
        $api_key = $this->config->item('celoe_api_key');
        $url = $base_url . $endpoint;
        
        $fetch_start_time = microtime(true);
        
        // Log initial connection attempt
        $this->add_realtime_log($log_id, 'info', "Establishing connection to: {$url}", $start_progress);
        $this->add_realtime_log($log_id, 'debug', "Request timeout set to: 30 seconds", $start_progress + 1);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "celoe-api-key: {$api_key}",
                "Content-Type: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $this->add_realtime_log($log_id, 'info', "Sending HTTP GET request...", $start_progress + 2);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $download_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $error = curl_error($ch);
        curl_close($ch);
        
        $fetch_duration = round((microtime(true) - $fetch_start_time) * 1000, 2);
        
        // Log response details
        $this->add_realtime_log($log_id, 'info', "Received HTTP response: {$http_code}", $start_progress + 3);
        $this->add_realtime_log($log_id, 'debug', "Response time: {$fetch_duration}ms", $start_progress + 4);
        $this->add_realtime_log($log_id, 'debug', "Content-Type: {$content_type}", $start_progress + 5);
        $this->add_realtime_log($log_id, 'debug', "Download size: " . round($download_size / 1024, 2) . " KB", $start_progress + 6);
        
        if ($error) {
            $this->add_realtime_log($log_id, 'error', "CURL Error occurred: {$error}", null);
            throw new Exception("CURL Error: {$error}");
        }
        
        if ($http_code !== 200) {
            $this->add_realtime_log($log_id, 'error', "HTTP Error: Server returned status {$http_code}", null);
            throw new Exception("HTTP Error: {$http_code}");
        }
        
        $this->add_realtime_log($log_id, 'info', "Parsing JSON response...", $end_progress - 1);
        
        $data = json_decode($response, true);
        
        if (!$data) {
            $this->add_realtime_log($log_id, 'error', "JSON parsing failed: Invalid or empty response", null);
            throw new Exception("Invalid JSON response");
        }
        
        if (!isset($data['status']) || !$data['status']) {
            $this->add_realtime_log($log_id, 'error', "API Error: Response status is false or missing", null);
            throw new Exception("Invalid API response status");
        }
        
        $result_data = $data['data'] ?? [];
        $result_count = count($result_data);
        
        $this->add_realtime_log($log_id, 'info', "JSON parsing successful: {$result_count} records found", $end_progress);
        $this->add_realtime_log($log_id, 'debug', "API response validation passed", $end_progress);
        
        // Log additional response metadata if available
        if (isset($data['message'])) {
            $this->add_realtime_log($log_id, 'debug', "API message: {$data['message']}", $end_progress);
        }
        
        return $result_data;
    }

    /**
     * Save categories to database
     */
    public function save_categories($categories)
    {
        if (empty($categories)) {
            return 0;
        }
        
        $saved_count = 0;
        
        // Begin transaction
        $this->db->trans_begin();
        
        try {
            foreach ($categories as $category) {
                // Check if category exists
                $existing = $this->db->query(
                    "SELECT category_id FROM {$this->moodle_logs_db}.etl_chart_categories WHERE category_id = ?",
                    [$category['category_id']]
                )->row();
                
                if ($existing) {
                    // Update existing
                    $this->db->query(
                        "UPDATE {$this->moodle_logs_db}.etl_chart_categories 
                         SET category_name = ?, category_site = ?, category_type = ?, category_parent_id = ? 
                         WHERE category_id = ?",
                        [
                            $category['category_name'],
                            $category['category_site'],
                            $category['category_type'],
                            $category['category_parent_id'],
                            $category['category_id']
                        ]
                    );
                } else {
                    // Insert new
                    $this->db->query(
                        "INSERT INTO {$this->moodle_logs_db}.etl_chart_categories 
                         (category_id, category_name, category_site, category_type, category_parent_id) 
                         VALUES (?, ?, ?, ?, ?)",
                        [
                            $category['category_id'],
                            $category['category_name'],
                            $category['category_site'],
                            $category['category_type'],
                            $category['category_parent_id']
                        ]
                    );
                }
                
                $saved_count++;
            }
            
            // Commit transaction
            $this->db->trans_commit();
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->trans_rollback();
            throw $e;
        }
        
        return $saved_count;
    }

    /**
     * Save subjects to database
     */
    public function save_subjects($subjects)
    {
        if (empty($subjects)) {
            return 0;
        }
        
        $saved_count = 0;
        
        // Begin transaction
        $this->db->trans_begin();
        
        try {
            foreach ($subjects as $subject) {
                // Check if subject exists
                $existing = $this->db->query(
                    "SELECT subject_id FROM {$this->moodle_logs_db}.etl_chart_subjects WHERE subject_id = ?",
                    [$subject['subject_id']]
                )->row();
                
                if ($existing) {
                    // Update existing
                    $this->db->query(
                        "UPDATE {$this->moodle_logs_db}.etl_chart_subjects 
                         SET subject_code = ?, subject_name = ?, curriculum_year = ?, category_id = ? 
                         WHERE subject_id = ?",
                        [
                            $subject['subject_code'],
                            $subject['subject_name'],
                            $subject['curriculum_year'],
                            $subject['category_id'],
                            $subject['subject_id']
                        ]
                    );
                } else {
                    // Insert new
                    $this->db->query(
                        "INSERT INTO {$this->moodle_logs_db}.etl_chart_subjects 
                         (subject_id, subject_code, subject_name, curriculum_year, category_id) 
                         VALUES (?, ?, ?, ?, ?)",
                        [
                            $subject['subject_id'],
                            $subject['subject_code'],
                            $subject['subject_name'],
                            $subject['curriculum_year'],
                            $subject['category_id']
                        ]
                    );
                }
                
                $saved_count++;
            }
            
            // Commit transaction
            $this->db->trans_commit();
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->trans_rollback();
            throw $e;
        }
        
        return $saved_count;
    }

    /**
     * Run complete ETL process with real-time logging
     */
    public function run_etl_process()
    {
        $log_id = null;
        
        try {
            log_message('info', 'Starting ETL Chart process');
            
            // Create log entry
            $log_id = $this->create_etl_log();
            
            // Add real-time log: ETL process started
            $this->add_realtime_log($log_id, 'info', 'ETL Chart process started', 0);
            
            $total_records = 0;
            $categories_saved = 0;
            $subjects_saved = 0;
            
            // === PHASE 1: Fetch and save categories ===
            $this->add_realtime_log($log_id, 'info', '=== PHASE 1: CATEGORIES PROCESSING ===', 5);
            $this->add_realtime_log($log_id, 'info', 'Initializing categories fetch from external API...', 10);
            log_message('info', 'Starting categories processing phase');
            
            try {
                // Detailed fetch logging for categories
                $category_fetch_start = microtime(true);
                $this->add_realtime_log($log_id, 'info', 'Connecting to API endpoint: https://celoe.telkomuniversity.ac.id/api/v1/course/category', 12);
                $this->add_realtime_log($log_id, 'info', 'Sending HTTP request with authentication headers...', 14);
                
                $categories = $this->fetch_external_api_with_logging('/course/category', $log_id, 15, 22);
                
                $category_fetch_duration = round((microtime(true) - $category_fetch_start) * 1000, 2);
                $categories_count = count($categories);
                
                $this->add_realtime_log($log_id, 'info', "Categories fetch completed in {$category_fetch_duration}ms", 23);
                $this->add_realtime_log($log_id, 'info', "Total categories received: {$categories_count}", 24);
                $this->add_realtime_log($log_id, 'info', "Average fetch time per category: " . round($category_fetch_duration / max($categories_count, 1), 2) . "ms", 25);
                
                if ($categories_count > 0) {
                    // Log sample category data for verification
                    $sample_category = $categories[0];
                    $this->add_realtime_log($log_id, 'debug', "Sample category data: ID={$sample_category['category_id']}, Name={$sample_category['category_name']}", 26);
                }
                
                $this->add_realtime_log($log_id, 'info', "Starting batch processing for {$categories_count} categories...", 28);
                $categories_saved = $this->save_categories_with_progress($categories, $log_id);
                $total_records += $categories_saved;
                
                $this->add_realtime_log($log_id, 'info', "Categories phase completed: {$categories_saved}/{$categories_count} records processed", 50);
                log_message('info', "Categories phase completed: Saved {$categories_saved} categories");
                
            } catch (Exception $e) {
                $this->add_realtime_log($log_id, 'error', "Categories processing failed: " . $e->getMessage(), null);
                throw $e;
            }
            
            // === PHASE 2: Fetch and save subjects ===
            $this->add_realtime_log($log_id, 'info', '=== PHASE 2: SUBJECTS PROCESSING ===', 52);
            $this->add_realtime_log($log_id, 'info', 'Initializing subjects fetch from external API...', 55);
            log_message('info', 'Starting subjects processing phase');
            
            try {
                // Detailed fetch logging for subjects
                $subject_fetch_start = microtime(true);
                $this->add_realtime_log($log_id, 'info', 'Connecting to API endpoint: https://celoe.telkomuniversity.ac.id/api/v1/course/subject', 57);
                $this->add_realtime_log($log_id, 'info', 'Sending HTTP request with authentication headers...', 59);
                $this->add_realtime_log($log_id, 'info', 'Waiting for server response... (this may take longer for large datasets)', 61);
                
                $subjects = $this->fetch_external_api_with_logging('/course/subject', $log_id, 62, 67);
                
                $subject_fetch_duration = round((microtime(true) - $subject_fetch_start) * 1000, 2);
                $subjects_count = count($subjects);
                
                $this->add_realtime_log($log_id, 'info', "Subjects fetch completed in {$subject_fetch_duration}ms", 68);
                $this->add_realtime_log($log_id, 'info', "Total subjects received: {$subjects_count}", 69);
                $this->add_realtime_log($log_id, 'info', "Average fetch time per subject: " . round($subject_fetch_duration / max($subjects_count, 1), 2) . "ms", 70);
                
                if ($subjects_count > 0) {
                    // Log sample subject data for verification
                    $sample_subject = $subjects[0];
                    $this->add_realtime_log($log_id, 'debug', "Sample subject data: ID={$sample_subject['subject_id']}, Code={$sample_subject['subject_code']}, Name={$sample_subject['subject_name']}", 71);
                    
                    // Log data size analysis
                    $this->add_realtime_log($log_id, 'info', "Data size analysis: " . round(strlen(json_encode($subjects)) / 1024, 2) . " KB of subject data received", 72);
                }
                
                $this->add_realtime_log($log_id, 'info', "Starting batch processing for {$subjects_count} subjects...", 74);
                $this->add_realtime_log($log_id, 'info', "Initiating database insertion process for subjects...", 74.5);
                $subjects_saved = $this->save_subjects_with_progress($subjects, $log_id);
                $total_records += $subjects_saved;
                
                $this->add_realtime_log($log_id, 'info', "Subjects phase completed: {$subjects_saved}/{$subjects_count} records processed", 95);
                log_message('info', "Subjects phase completed: Saved {$subjects_saved} subjects");
                
            } catch (Exception $e) {
                $this->add_realtime_log($log_id, 'error', "Subjects processing failed: " . $e->getMessage(), null);
                throw $e;
            }
            
            // === COMPLETION ===
            $this->add_realtime_log($log_id, 'info', '=== ETL PROCESS COMPLETION ===', 97);
            
            // Calculate total execution time
            $total_execution_time = round((microtime(true) - $this->start_time) * 1000, 2);
            $this->add_realtime_log($log_id, 'info', "Total execution time: {$total_execution_time}ms", 98);
            $this->add_realtime_log($log_id, 'info', "Processing rate: " . round($total_records / ($total_execution_time / 1000), 2) . " records/second", 99);
            
            // Update log with success
            $this->update_etl_log($log_id, 'finished', $total_records);
            $this->add_realtime_log($log_id, 'info', "ETL Chart process completed successfully! Total: {$total_records} records processed ({$categories_saved} categories + {$subjects_saved} subjects)", 100);
            
            log_message('info', "ETL Chart process completed successfully. Total records: {$total_records} (Categories: {$categories_saved}, Subjects: {$subjects_saved})");
            
            return [
                'status' => 'success',
                'total_records' => $total_records,
                'categories_saved' => $categories_saved,
                'subjects_saved' => $subjects_saved,
                'execution_time_ms' => $total_execution_time
            ];
            
        } catch (Exception $e) {
            log_message('error', 'ETL Chart process failed: ' . $e->getMessage());
            
            // Add real-time error log
            if ($log_id) {
                $this->add_realtime_log($log_id, 'error', "ETL Chart process failed: " . $e->getMessage(), null);
                $this->update_etl_log($log_id, 'failed', 0);
            }
            
            throw $e;
        }
    }

    /**
     * Check if ETL process is currently running
     */
    public function is_etl_running()
    {
        $result = $this->db->query(
            "SELECT COUNT(*) as count FROM {$this->moodle_logs_db}.etl_chart_logs 
             WHERE status = 'running' 
             AND start_date > DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        )->row();
        
        return $result->count > 0;
    }

    /**
     * Get real-time logs with filtering and pagination
     * 
     * @param int|null $log_id Filter by log_id
     * @param string|null $level Filter by level (info, warning, error, debug)
     * @param string|null $since Get logs since this timestamp
     * @param int $limit Number of records to fetch
     * @param int $offset Offset for pagination
     * @return array Array of real-time logs
     */
    public function get_realtime_logs($log_id = null, $level = null, $since = null, $limit = 50, $offset = 0)
    {
        // Build WHERE conditions
        $where_conditions = [];
        $params = [];
        
        if ($log_id !== null) {
            $where_conditions[] = "log_id = ?";
            $params[] = $log_id;
        }
        
        if ($level !== null) {
            $where_conditions[] = "level = ?";
            $params[] = $level;
        }
        
        if ($since !== null) {
            $where_conditions[] = "timestamp > ?";
            $params[] = $since;
        }
        
        // Build WHERE clause
        $where_clause = "";
        if (!empty($where_conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        }
        
        // Add pagination params
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $query = "SELECT id, log_id, timestamp, level, message, progress, created_at 
                  FROM {$this->moodle_logs_db}.etl_chart_realtime_logs 
                  {$where_clause}
                  ORDER BY timestamp DESC, id DESC
                  LIMIT ? OFFSET ?";
        
        $result = $this->db->query($query, $params);
        
        return $result->result_array();
    }

    /**
     * Add a new real-time log entry
     * 
     * @param int $log_id Reference to etl_chart_logs.id
     * @param string $level Log level (info, warning, error, debug)
     * @param string $message Log message
     * @param float|null $progress Optional progress percentage
     * @return int Inserted record ID
     */
    public function add_realtime_log($log_id, $level, $message, $progress = null)
    {
        try {
            // Validate level
            $valid_levels = ['info', 'warning', 'error', 'debug'];
            if (!in_array($level, $valid_levels)) {
                log_message('error', "Invalid log level: {$level}. Must be one of: " . implode(', ', $valid_levels));
                return false;
            }
            
            // Ensure table exists first
            $this->ensure_realtime_logs_table();
            
            // Validate that log_id exists
            $log_exists = $this->db->query(
                "SELECT id FROM {$this->moodle_logs_db}.etl_chart_logs WHERE id = ?",
                [$log_id]
            )->row();
            
            if (!$log_exists) {
                log_message('error', "ETL Chart log with ID {$log_id} does not exist");
                return false;
            }
            
            $data = [
                'log_id' => $log_id,
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => $level,
                'message' => $message,
                'progress' => $progress
            ];
            
            // Add detailed debugging
            log_message('debug', "Inserting realtime log: " . json_encode($data));
            
            $result = $this->db->query(
                "INSERT INTO {$this->moodle_logs_db}.etl_chart_realtime_logs 
                 (log_id, timestamp, level, message, progress) 
                 VALUES (?, ?, ?, ?, ?)",
                [$data['log_id'], $data['timestamp'], $data['level'], $data['message'], $data['progress']]
            );
            
            if (!$result) {
                $db_error = $this->db->error();
                log_message('error', "Failed to insert realtime log. DB Error: " . json_encode($db_error));
                return false;
            }
            
            $insert_id = $this->db->insert_id();
            log_message('debug', "Realtime log inserted successfully with ID: {$insert_id}");
            
            return $insert_id;
            
        } catch (Exception $e) {
            log_message('error', "Exception in add_realtime_log: " . $e->getMessage());
            // Continue execution even if logging fails
            return false;
        }
    }

    /**
     * Ensure the realtime logs table exists
     */
    private function ensure_realtime_logs_table()
    {
        try {
            // Check if table exists
            $table_exists = $this->db->query(
                "SELECT COUNT(*) as count FROM information_schema.tables 
                 WHERE table_schema = '{$this->moodle_logs_db}' 
                 AND table_name = 'etl_chart_realtime_logs'"
            )->row();
            
            if ($table_exists->count == 0) {
                // Create the table
                $create_sql = "
                    CREATE TABLE {$this->moodle_logs_db}.etl_chart_realtime_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        log_id INT NOT NULL,
                        timestamp DATETIME NOT NULL,
                        level ENUM('info', 'warning', 'error', 'debug') NOT NULL DEFAULT 'info',
                        message TEXT NOT NULL,
                        progress DECIMAL(5,2) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_log_id (log_id),
                        INDEX idx_timestamp (timestamp),
                        INDEX idx_level (level),
                        FOREIGN KEY (log_id) REFERENCES {$this->moodle_logs_db}.etl_chart_logs(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                
                $this->db->query($create_sql);
                log_message('info', "Created etl_chart_realtime_logs table");
            }
        } catch (Exception $e) {
            log_message('error', "Failed to ensure realtime logs table: " . $e->getMessage());
        }
    }

    /**
     * Flush logs to ensure they are immediately written to database
     */
    private function flush_logs()
    {
        try {
            // Force commit any pending transactions for logging
            if ($this->db->trans_status() !== FALSE) {
                // Don't interfere with main transactions, just flush the connection
                $this->db->query("SELECT 1"); // Simple query to flush buffer
            }
        } catch (Exception $e) {
            // Ignore flush errors to prevent disrupting main process
            log_message('debug', "Log flush warning: " . $e->getMessage());
        }
    }

    /**
     * Add real-time log with automatic log_id detection
     * Will use the latest running ETL process, or create a new one if none exists
     * 
     * @param string $level Log level (info, warning, error, debug)
     * @param string $message Log message
     * @param float|null $progress Optional progress percentage
     * @return int Inserted record ID
     */
    public function add_realtime_log_auto($level, $message, $progress = null)
    {
        // Try to find the latest running or most recent ETL log
        $latest_log = $this->db->query(
            "SELECT id FROM {$this->moodle_logs_db}.etl_chart_logs 
             WHERE status = 'running' 
             ORDER BY start_date DESC 
             LIMIT 1"
        )->row();
        
        // If no running ETL, get the most recent one
        if (!$latest_log) {
            $latest_log = $this->db->query(
                "SELECT id FROM {$this->moodle_logs_db}.etl_chart_logs 
                 ORDER BY id DESC 
                 LIMIT 1"
            )->row();
        }
        
        // If still no log, create a new one
        if (!$latest_log) {
            $log_id = $this->create_etl_log();
        } else {
            $log_id = $latest_log->id;
        }
        
        return $this->add_realtime_log($log_id, $level, $message, $progress);
    }

    /**
     * Get real-time logs count by level for a specific ETL process
     * 
     * @param int $log_id ETL log ID
     * @return array Count by level
     */
    public function get_realtime_logs_count_by_level($log_id)
    {
        $result = $this->db->query(
            "SELECT level, COUNT(*) as count 
             FROM {$this->moodle_logs_db}.etl_chart_realtime_logs 
             WHERE log_id = ?
             GROUP BY level",
            [$log_id]
        );
        
        $counts = ['info' => 0, 'warning' => 0, 'error' => 0, 'debug' => 0];
        
        foreach ($result->result() as $row) {
            $counts[$row->level] = (int)$row->count;
        }
        
        return $counts;
    }

    /**
     * Clear old real-time logs (older than specified days)
     * 
     * @param int $days Number of days to keep logs
     * @return int Number of deleted records
     */
    public function cleanup_old_realtime_logs($days = 30)
    {
        $this->db->query(
            "DELETE FROM {$this->moodle_logs_db}.etl_chart_realtime_logs 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        
        return $this->db->affected_rows();
    }

    /**
     * Save categories with high-performance bulk operations
     * 
     * @param array $categories Array of categories to save
     * @param int $log_id ETL log ID for progress tracking
     * @return int Number of categories saved
     */
    public function save_categories_with_progress($categories, $log_id)
    {
        if (empty($categories)) {
            $this->add_realtime_log($log_id, 'warning', "No categories to process", 50);
            $this->flush_logs();
            return 0;
        }
        
        $total_count = count($categories);
        $batch_size = 100; // Optimized batch size for categories using bulk operations
        
        $this->add_realtime_log($log_id, 'info', "=== CATEGORIES DATABASE BULK INSERTION STARTED ===", 29);
        $this->add_realtime_log($log_id, 'info', "Processing {$total_count} categories using bulk INSERT ON DUPLICATE KEY UPDATE", 30);
        $this->add_realtime_log($log_id, 'info', "Batch size optimized to {$batch_size} for bulk operations", 30.5);
        $this->flush_logs();
        
        $saved_count = 0;
        $overall_start_time = microtime(true);
        
        try {
            // Process categories using bulk upsert
            $batches = array_chunk($categories, $batch_size);
            $batch_number = 0;
            $total_batches = count($batches);
            
            foreach ($batches as $batch) {
                $batch_number++;
                $batch_start_time = microtime(true);
                
                $this->add_realtime_log($log_id, 'info', "Processing bulk batch {$batch_number}/{$total_batches} (" . count($batch) . " categories)", null);
                
                // Build bulk upsert query
                $values_array = [];
                $params = [];
                
                foreach ($batch as $category) {
                    $values_array[] = "(?, ?, ?, ?, ?)";
                    $params[] = $category['category_id'];
                    $params[] = $category['category_name'];
                    $params[] = $category['category_site'];
                    $params[] = $category['category_type'];
                    $params[] = $category['category_parent_id'];
                }
                
                $values_string = implode(', ', $values_array);
                
                // Single bulk upsert query
                $bulk_query = "
                    INSERT INTO {$this->moodle_logs_db}.etl_chart_categories 
                    (category_id, category_name, category_site, category_type, category_parent_id) 
                    VALUES {$values_string}
                    ON DUPLICATE KEY UPDATE 
                        category_name = VALUES(category_name),
                        category_site = VALUES(category_site),
                        category_type = VALUES(category_type),
                        category_parent_id = VALUES(category_parent_id)
                ";
                
                $this->add_realtime_log($log_id, 'debug', "Executing bulk upsert for category batch {$batch_number} with " . count($batch) . " records", null);
                
                // Execute bulk operation
                $this->db->trans_begin();
                $result = $this->db->query($bulk_query, $params);
                
                if (!$result) {
                    $this->db->trans_rollback();
                    throw new Exception("Bulk upsert failed for category batch {$batch_number}");
                }
                
                $this->db->trans_commit();
                $saved_count += count($batch);
                
                $batch_duration = round((microtime(true) - $batch_start_time) * 1000, 2);
                $progress_percent = round(30 + (($saved_count / $total_count) * 20), 2); // 30% to 50%
                $records_per_second = round(count($batch) / ($batch_duration / 1000), 2);
                
                $this->add_realtime_log(
                    $log_id, 
                    'info', 
                    "Category bulk batch {$batch_number} completed: {$saved_count}/{$total_count} processed ({$records_per_second} records/sec)", 
                    $progress_percent
                );
                
                $this->flush_logs();
                usleep(1000); // 1ms delay for categories
            }
            
            $total_processing_time = round((microtime(true) - $overall_start_time) * 1000, 2);
            $overall_rate = round($total_count / ($total_processing_time / 1000), 2);
            
            $this->add_realtime_log($log_id, 'info', "=== CATEGORIES DATABASE BULK INSERTION COMPLETED ===", 50);
            $this->add_realtime_log($log_id, 'info', "Successfully processed {$total_count} categories in {$total_processing_time}ms", 50);
            $this->add_realtime_log($log_id, 'info', "Categories performance: {$overall_rate} categories/sec using {$total_batches} bulk operations", 50);
            $this->add_realtime_log($log_id, 'info', "All category records processed using MySQL bulk upsert operations", 50);
            $this->flush_logs();
            
        } catch (Exception $e) {
            $this->add_realtime_log($log_id, 'error', "=== CATEGORIES DATABASE BULK INSERTION FAILED ===", null);
            $this->add_realtime_log($log_id, 'error', "Categories bulk processing failed: " . $e->getMessage(), null);
            $this->flush_logs();
            throw $e;
        }
        
        return $saved_count;
    }

    /**
     * Save subjects with high-performance bulk operations
     * 
     * @param array $subjects Array of subjects to save
     * @param int $log_id ETL log ID for progress tracking
     * @return int Number of subjects saved
     */
    public function save_subjects_with_progress($subjects, $log_id)
    {
        if (empty($subjects)) {
            $this->add_realtime_log($log_id, 'warning', "No subjects to process", 95);
            $this->flush_logs();
            return 0;
        }
        
        $total_count = count($subjects);
        
        // For large datasets (>5000), use ultra-fast bulk method
        if ($total_count > 5000) {
            $this->add_realtime_log($log_id, 'info', "=== ULTRA-FAST BULK PROCESSING MODE ACTIVATED ===", 74);
            $this->add_realtime_log($log_id, 'info', "Large dataset detected ({$total_count} subjects), using optimized bulk operations", 75);
            return $this->save_subjects_bulk_ultra_fast($subjects, $log_id);
        } else {
            // Use standard batch processing for smaller datasets
            return $this->save_subjects_bulk_standard($subjects, $log_id);
        }
    }

    /**
     * Ultra-fast bulk processing for large datasets using MySQL bulk operations
     */
    private function save_subjects_bulk_ultra_fast($subjects, $log_id)
    {
        $total_count = count($subjects);
        $batch_size = 1000; // Much larger batches for bulk operations
        
        $this->add_realtime_log($log_id, 'info', "=== SUBJECTS DATABASE BULK INSERTION STARTED ===", 75);
        $this->add_realtime_log($log_id, 'info', "Processing {$total_count} subjects using bulk INSERT ON DUPLICATE KEY UPDATE", 76);
        $this->add_realtime_log($log_id, 'info', "Batch size optimized to {$batch_size} for maximum performance", 77);
        $this->flush_logs();
        
        $saved_count = 0;
        $overall_start_time = microtime(true);
        
        try {
            // Process in large batches using bulk upsert
            $batches = array_chunk($subjects, $batch_size);
            $batch_number = 0;
            $total_batches = count($batches);
            
            foreach ($batches as $batch) {
                $batch_number++;
                $batch_start_time = microtime(true);
                
                $this->add_realtime_log($log_id, 'info', "Processing bulk batch {$batch_number}/{$total_batches} (" . count($batch) . " subjects)", null);
                
                // Build bulk upsert query
                $values_array = [];
                $params = [];
                
                foreach ($batch as $subject) {
                    $values_array[] = "(?, ?, ?, ?, ?)";
                    $params[] = $subject['subject_id'];
                    $params[] = $subject['subject_code'];
                    $params[] = $subject['subject_name'];
                    $params[] = $subject['curriculum_year'];
                    $params[] = $subject['category_id'];
                }
                
                $values_string = implode(', ', $values_array);
                
                // Single bulk upsert query - much faster than individual INSERTs/UPDATEs
                $bulk_query = "
                    INSERT INTO {$this->moodle_logs_db}.etl_chart_subjects 
                    (subject_id, subject_code, subject_name, curriculum_year, category_id) 
                    VALUES {$values_string}
                    ON DUPLICATE KEY UPDATE 
                        subject_code = VALUES(subject_code),
                        subject_name = VALUES(subject_name),
                        curriculum_year = VALUES(curriculum_year),
                        category_id = VALUES(category_id)
                ";
                
                $this->add_realtime_log($log_id, 'debug', "Executing bulk upsert for {$batch_number} batch with " . count($batch) . " records", null);
                
                // Execute bulk operation
                $this->db->trans_begin();
                $result = $this->db->query($bulk_query, $params);
                
                if (!$result) {
                    $this->db->trans_rollback();
                    throw new Exception("Bulk upsert failed for batch {$batch_number}");
                }
                
                $this->db->trans_commit();
                $saved_count += count($batch);
                
                $batch_duration = round((microtime(true) - $batch_start_time) * 1000, 2);
                $progress_percent = round(75 + (($saved_count / $total_count) * 20), 2);
                $records_per_second = round(count($batch) / ($batch_duration / 1000), 2);
                
                $this->add_realtime_log(
                    $log_id, 
                    'info', 
                    "Bulk batch {$batch_number} completed: {$saved_count}/{$total_count} processed ({$records_per_second} records/sec)", 
                    $progress_percent
                );
                
                // Progress milestones for large datasets
                if ($batch_number % 5 == 0 || $batch_number == $total_batches) {
                    $overall_elapsed = round((microtime(true) - $overall_start_time) * 1000, 2);
                    $avg_batch_time = round($overall_elapsed / $batch_number, 2);
                    $estimated_remaining = round(($total_batches - $batch_number) * $avg_batch_time / 1000, 2);
                    
                    $this->add_realtime_log(
                        $log_id, 
                        'info', 
                        "Bulk progress: {$batch_number}/{$total_batches} batches, avg {$avg_batch_time}ms/batch, ~{$estimated_remaining}s remaining", 
                        null
                    );
                }
                
                $this->flush_logs();
                
                // Minimal delay for ultra-fast processing
                usleep(1000); // 1ms delay only
            }
            
            $total_processing_time = round((microtime(true) - $overall_start_time) * 1000, 2);
            $overall_rate = round($total_count / ($total_processing_time / 1000), 2);
            
            $this->add_realtime_log($log_id, 'info', "=== ULTRA-FAST BULK INSERTION COMPLETED ===", 95);
            $this->add_realtime_log($log_id, 'info', "Successfully processed {$total_count} subjects in {$total_processing_time}ms", 95);
            $this->add_realtime_log($log_id, 'info', "Ultra-fast performance: {$overall_rate} subjects/sec, {$total_batches} bulk operations", 95);
            $this->add_realtime_log($log_id, 'info', "All subject records processed using MySQL bulk upsert operations", 95);
            $this->flush_logs();
            
        } catch (Exception $e) {
            $this->add_realtime_log($log_id, 'error', "=== BULK INSERTION FAILED ===", null);
            $this->add_realtime_log($log_id, 'error', "Bulk processing failed: " . $e->getMessage(), null);
            $this->flush_logs();
            throw $e;
        }
        
        return $saved_count;
    }

    /**
     * Standard bulk processing for smaller datasets
     */
    private function save_subjects_bulk_standard($subjects, $log_id)
    {
        $total_count = count($subjects);
        $batch_size = 250; // Optimized batch size for standard processing
        
        $this->add_realtime_log($log_id, 'info', "=== SUBJECTS DATABASE INSERTION STARTED ===", 74);
        $this->add_realtime_log($log_id, 'info', "Processing {$total_count} subjects using optimized batch operations", 75);
        $this->add_realtime_log($log_id, 'info', "Batch size: {$batch_size} (optimized for standard datasets)", 76);
        $this->flush_logs();
        
        $saved_count = 0;
        $overall_start_time = microtime(true);
        
        try {
            $batches = array_chunk($subjects, $batch_size);
            $batch_number = 0;
            $total_batches = count($batches);
            
            foreach ($batches as $batch) {
                $batch_number++;
                $batch_start_time = microtime(true);
                
                $this->add_realtime_log($log_id, 'info', "Processing batch {$batch_number}/{$total_batches} (" . count($batch) . " subjects)", null);
                
                // Build bulk upsert for this batch
                $values_array = [];
                $params = [];
                
                foreach ($batch as $subject) {
                    $values_array[] = "(?, ?, ?, ?, ?)";
                    $params[] = $subject['subject_id'];
                    $params[] = $subject['subject_code'];
                    $params[] = $subject['subject_name'];
                    $params[] = $subject['curriculum_year'];
                    $params[] = $subject['category_id'];
                }
                
                $values_string = implode(', ', $values_array);
                
                $bulk_query = "
                    INSERT INTO {$this->moodle_logs_db}.etl_chart_subjects 
                    (subject_id, subject_code, subject_name, curriculum_year, category_id) 
                    VALUES {$values_string}
                    ON DUPLICATE KEY UPDATE 
                        subject_code = VALUES(subject_code),
                        subject_name = VALUES(subject_name),
                        curriculum_year = VALUES(curriculum_year),
                        category_id = VALUES(category_id)
                ";
                
                $this->db->trans_begin();
                $result = $this->db->query($bulk_query, $params);
                
                if (!$result) {
                    $this->db->trans_rollback();
                    throw new Exception("Batch upsert failed for batch {$batch_number}");
                }
                
                $this->db->trans_commit();
                $saved_count += count($batch);
                
                $batch_duration = round((microtime(true) - $batch_start_time) * 1000, 2);
                $progress_percent = round(75 + (($saved_count / $total_count) * 20), 2);
                $records_per_second = round(count($batch) / ($batch_duration / 1000), 2);
                
                $this->add_realtime_log(
                    $log_id, 
                    'info', 
                    "Batch {$batch_number} completed: {$saved_count}/{$total_count} processed ({$records_per_second} records/sec)", 
                    $progress_percent
                );
                
                $this->flush_logs();
                usleep(2000); // 2ms delay
            }
            
            $total_processing_time = round((microtime(true) - $overall_start_time) * 1000, 2);
            $overall_rate = round($total_count / ($total_processing_time / 1000), 2);
            
            $this->add_realtime_log($log_id, 'info', "=== SUBJECTS DATABASE INSERTION COMPLETED ===", 95);
            $this->add_realtime_log($log_id, 'info', "Successfully processed {$total_count} subjects in {$total_processing_time}ms", 95);
            $this->add_realtime_log($log_id, 'info', "Performance: {$overall_rate} subjects/sec using {$total_batches} batch operations", 95);
            $this->flush_logs();
            
        } catch (Exception $e) {
            $this->add_realtime_log($log_id, 'error', "=== SUBJECTS DATABASE INSERTION FAILED ===", null);
            $this->add_realtime_log($log_id, 'error', "Batch processing failed: " . $e->getMessage(), null);
            $this->flush_logs();
            throw $e;
        }
        
        return $saved_count;
    }

    /**
     * Clear stuck ETL Chart processes
     * 
     * @return array Result information
     */
    public function clear_stuck_etl_processes()
    {
        try {
            // Find stuck ETL Chart processes (running for more than 10 minutes)
            $stuck_query = "
                SELECT id, start_date, 
                       TIMESTAMPDIFF(MINUTE, start_date, NOW()) as minutes_running 
                FROM {$this->moodle_logs_db}.etl_chart_logs 
                WHERE status = 'running' 
                AND start_date < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ";
            
            $stuck_processes = $this->db->query($stuck_query)->result();
            
            if (empty($stuck_processes)) {
                return [
                    'action' => 'no_stuck_processes',
                    'message' => 'No stuck ETL Chart processes found'
                ];
            }
            
            $cleared_count = 0;
            foreach ($stuck_processes as $process) {
                // Mark as failed and set end date
                $this->db->query(
                    "UPDATE {$this->moodle_logs_db}.etl_chart_logs 
                     SET status = 'failed', end_date = NOW(), duration = TIMEDIFF(NOW(), start_date) 
                     WHERE id = ?",
                    array($process->id)
                );
                
                // Add real-time log for the stuck process
                $this->add_realtime_log(
                    $process->id, 
                    'error', 
                    "ETL Chart process marked as failed due to timeout (was running for {$process->minutes_running} minutes)", 
                    null
                );
                
                log_message('info', "Cleared stuck ETL Chart process ID: {$process->id}, was running for {$process->minutes_running} minutes");
                $cleared_count++;
            }
            
            // Also clear any processes that have been running for more than 30 minutes without proper handling
            $hanging_query = "
                SELECT id FROM {$this->moodle_logs_db}.etl_chart_logs 
                WHERE status = 'running' 
                AND (end_date IS NULL OR end_date = '0000-00-00 00:00:00')
                AND start_date < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ";
            
            $hanging_processes = $this->db->query($hanging_query)->result();
            
            foreach ($hanging_processes as $process) {
                $this->db->query(
                    "UPDATE {$this->moodle_logs_db}.etl_chart_logs 
                     SET status = 'failed', end_date = NOW(), duration = TIMEDIFF(NOW(), start_date) 
                     WHERE id = ?",
                    array($process->id)
                );
                
                // Add real-time log for hanging process
                $this->add_realtime_log(
                    $process->id, 
                    'error', 
                    "ETL Chart process marked as failed due to hanging state", 
                    null
                );
                
                log_message('info', "Cleared hanging ETL Chart process ID: {$process->id}");
                $cleared_count++;
            }
            
            return [
                'action' => 'cleared_stuck_processes',
                'cleared_count' => $cleared_count,
                'message' => "Cleared {$cleared_count} stuck ETL Chart processes"
            ];
            
        } catch (Exception $e) {
            log_message('error', 'Failed to clear stuck ETL Chart processes: ' . $e->getMessage());
            throw $e;
        }
    }
}
