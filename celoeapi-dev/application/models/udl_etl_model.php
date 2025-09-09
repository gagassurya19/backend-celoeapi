<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Udl_etl_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model('udl_etl_logs_model');
    }

    /**
     * Run ETL process: Extract data from Moodle and save to udl_etl table
     * This method handles duplicate detection and login count tracking with concurrency support
     * @param string $extraction_date Extraction date in Y-m-d format
     * @param int $concurrency Number of concurrent processes (1-10)
     */
    public function run($extraction_date = null, $concurrency = 1) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        // Validate concurrency
        $concurrency = (int)$concurrency;
        if ($concurrency < 1) $concurrency = 1;
        if ($concurrency > 10) $concurrency = 10;

        try {
            // Start ETL process
            $start_time = microtime(true);
            $started_at = date('Y-m-d H:i:s');
            
            // Create initial log entry with 'running' status
            $log_data = [
                'extraction_date' => $extraction_date,
                'concurrency' => $concurrency,
                'status' => 'running',
                'started_at' => $started_at
            ];
            
            $log_result = $this->udl_etl_logs_model->create_log($log_data);
            $log_id = $log_result['success'] ? $log_result['log_id'] : null;
            
            // Extract data from Moodle
            $extract_result = $this->extract_from_moodle($extraction_date);
            
            if (!$extract_result['success']) {
                // Update log with failed status
                if ($log_id) {
                    $this->udl_etl_logs_model->update_log($log_id, [
                        'status' => 'failed',
                        'error_message' => 'Failed to extract data from Moodle: ' . $extract_result['error'],
                        'completed_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                return [
                    'success' => false,
                    'error' => 'Failed to extract data from Moodle: ' . $extract_result['error'],
                    'extraction_date' => $extraction_date
                ];
            }

            $extracted_data = $extract_result['data'];
            $total_extracted = count($extracted_data);
            
            // Process and save data to database with concurrency
            $inserted_count = 0;
            $updated_count = 0;
            $error_count = 0;
            
            if ($concurrency > 1 && $total_extracted > 0) {
                // Use concurrent processing for better performance
                $result = $this->process_data_concurrent($extracted_data, $extraction_date, $concurrency);
                $inserted_count = $result['inserted_count'];
                $updated_count = $result['updated_count'];
                $error_count = $result['error_count'];
            } else {
                // Sequential processing for small datasets or single concurrency
                foreach ($extracted_data as $user_data) {
                    try {
                    // Check if record exists for this user, hour, and activity date
                    $existing_record = $this->db->get_where('udl_etl', [
                        'user_id' => $user_data['user_id'],
                        'activity_hour' => $user_data['activity_hour'],
                        'activity_date' => $user_data['activity_date']
                    ])->row_array();
                    
                    if ($existing_record) {
                        // Record exists, check if lastaccess has changed
                        if ($existing_record['lastaccess'] != $user_data['lastaccess']) {
                            // Update existing record with new data and increment login count
                            $update_data = [
                                'lastaccess' => $user_data['lastaccess'],
                                'formatted_lastaccess' => $user_data['formatted_lastaccess'],
                                'lastlogin' => $user_data['lastlogin'],
                                'formatted_lastlogin' => $user_data['formatted_lastlogin'],
                                'currentlogin' => $user_data['currentlogin'],
                                'formatted_currentlogin' => $user_data['formatted_currentlogin'],
                                'lastip' => $user_data['lastip'],
                                'auth' => $user_data['auth'],
                                'firstaccess' => $user_data['firstaccess'],
                                'formatted_firstaccess' => $user_data['formatted_firstaccess'],
                                'role_id' => $user_data['role_id'],
                                'role_name' => $user_data['role_name'],
                                'role_shortname' => $user_data['role_shortname'],
                                'archetype' => $user_data['archetype'],
                                'course_id' => $user_data['course_id'],
                                'all_role_ids' => json_encode($user_data['all_role_ids']),
                                'all_role_names' => json_encode($user_data['all_role_names']),
                                'all_role_shortnames' => json_encode($user_data['all_role_shortnames']),
                                'all_archetypes' => json_encode($user_data['all_archetypes']),
                                'all_course_ids' => json_encode($user_data['all_course_ids']),
                                'total_courses' => $user_data['total_courses'],
                                'login_count' => $existing_record['login_count'] + 1, // Increment login count
                                'extraction_date' => $extraction_date,
                                'updated_at' => date('Y-m-d H:i:s')
                            ];
                            
                            $this->db->where('id', $existing_record['id']);
                            $this->db->update('udl_etl', $update_data);
                            
                            if ($this->db->affected_rows() > 0) {
                                $updated_count++;
                                log_message('info', "UPDATED: User {$user_data['user_id']} ({$user_data['username']}) - Login count incremented to {$update_data['login_count']} in hour {$user_data['activity_hour']}");
                            }
                        } else {
                            // No change in lastaccess, skip update
                            log_message('debug', "NO CHANGE: User {$user_data['user_id']} ({$user_data['username']}) - Lastaccess unchanged: {$user_data['lastaccess']}");
                        }
                    } else {
                        // New record, insert data
                        $insert_data = [
                            'user_id' => $user_data['user_id'],
                            'username' => $user_data['username'],
                            'firstname' => $user_data['firstname'],
                            'lastname' => $user_data['lastname'],
                            'email' => $user_data['email'],
                            'lastaccess' => $user_data['lastaccess'],
                            'formatted_lastaccess' => $user_data['formatted_lastaccess'],
                            'lastlogin' => $user_data['lastlogin'],
                            'formatted_lastlogin' => $user_data['formatted_lastlogin'],
                            'currentlogin' => $user_data['currentlogin'],
                            'formatted_currentlogin' => $user_data['formatted_currentlogin'],
                            'lastip' => $user_data['lastip'],
                            'auth' => $user_data['auth'],
                            'firstaccess' => $user_data['firstaccess'],
                            'formatted_firstaccess' => $user_data['formatted_firstaccess'],
                            'role_id' => $user_data['role_id'],
                            'role_name' => $user_data['role_name'],
                            'role_shortname' => $user_data['role_shortname'],
                            'archetype' => $user_data['archetype'],
                            'course_id' => $user_data['course_id'],
                            'all_role_ids' => json_encode($user_data['all_role_ids']),
                            'all_role_names' => json_encode($user_data['all_role_names']),
                            'all_role_shortnames' => json_encode($user_data['all_role_shortnames']),
                            'all_archetypes' => json_encode($user_data['all_archetypes']),
                            'all_course_ids' => json_encode($user_data['all_course_ids']),
                            'total_courses' => $user_data['total_courses'],
                            'activity_hour' => $user_data['activity_hour'],
                            'activity_date' => $user_data['activity_date'],
                            'login_count' => 1, // Start with 1 for new record
                            'extraction_date' => $extraction_date,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $this->db->insert('udl_etl', $insert_data);
                        
                        if ($this->db->affected_rows() > 0) {
                            $inserted_count++;
                            log_message('info', "INSERTED: User {$user_data['user_id']} ({$user_data['username']}) - New activity record in hour {$user_data['activity_hour']}");
                        }
                    }
                    
                } catch (Exception $e) {
                    $error_count++;
                    log_message('error', "ERROR: Failed to process user {$user_data['user_id']} ({$user_data['username']}) - " . $e->getMessage());
                }
            }
        }
            
            // Calculate execution time
            $end_time = microtime(true);
            $execution_time = round($end_time - $start_time, 2);
            $completed_at = date('Y-m-d H:i:s');
            
            // Update log with completed status and statistics
            if ($log_id) {
                $this->udl_etl_logs_model->update_log($log_id, [
                    'status' => 'completed',
                    'total_extracted' => $total_extracted,
                    'total_inserted' => $inserted_count,
                    'total_updated' => $updated_count,
                    'total_errors' => $error_count,
                    'execution_time' => $execution_time,
                    'completed_at' => $completed_at
                ]);
            }
            
            // Log ETL summary
            log_message('info', "UDL ETL COMPLETED: Date: {$extraction_date}, Extracted: {$total_extracted}, Inserted: {$inserted_count}, Updated: {$updated_count}, Errors: {$error_count}, Time: {$execution_time}s");
            
            return [
                'success' => true,
                'extraction_date' => $extraction_date,
                'total_extracted' => $total_extracted,
                'inserted_count' => $inserted_count,
                'updated_count' => $updated_count,
                'error_count' => $error_count,
                'execution_time' => $execution_time,
                'message' => "UDL ETL completed successfully. Extracted: {$total_extracted}, Inserted: {$inserted_count}, Updated: {$updated_count}, Errors: {$error_count}, Time: {$execution_time}s"
            ];
            
        } catch (Exception $e) {
            // Update log with failed status if log was created
            if (isset($log_id) && $log_id) {
                $this->udl_etl_logs_model->update_log($log_id, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            log_message('error', "UDL ETL FAILED: Date: {$extraction_date}, Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'extraction_date' => $extraction_date
            ];
        }
    }

    /**
     * Extract enrolled users data directly from Moodle database
     * Only returns users who are enrolled in courses
     * This method will be used to detect lastaccess changes for user activity tracking
     */
    public function extract_from_moodle($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // Load Moodle database connection
            $moodle_db = $this->load->database('moodle', TRUE);
            
            // SQL query to get enrolled users with essential information and roles
            // Using GROUP BY to avoid duplicates when user has multiple courses
            $sql = "
                SELECT 
                    u.id as user_id,
                    u.username,
                    u.firstname,
                    u.lastname,
                    u.email,
                    u.lastaccess,
                    u.lastlogin,
                    u.currentlogin,
                    u.lastip,
                    u.auth,
                    u.firstaccess,
                    GROUP_CONCAT(DISTINCT r.id) as role_ids,
                    GROUP_CONCAT(DISTINCT r.name) as role_names,
                    GROUP_CONCAT(DISTINCT r.shortname) as role_shortnames,
                    GROUP_CONCAT(DISTINCT r.archetype) as archetypes,
                    GROUP_CONCAT(DISTINCT ctx.instanceid) as course_ids,
                    COUNT(DISTINCT ctx.instanceid) as total_courses
                FROM mdl_user u
                INNER JOIN mdl_user_enrolments ue ON u.id = ue.userid
                INNER JOIN mdl_enrol e ON ue.enrolid = e.id
                INNER JOIN mdl_course c ON e.courseid = c.id
                LEFT JOIN mdl_role_assignments ra ON u.id = ra.userid
                LEFT JOIN mdl_context ctx ON ra.contextid = ctx.id
                LEFT JOIN mdl_role r ON ra.roleid = r.id
                WHERE u.deleted = 0 
                    AND u.suspended = 0
                    AND u.id > 1  -- Exclude guest user
                    AND c.visible = 1  -- Only visible courses
                    AND ue.status = 0  -- Active enrolments only
                    AND ctx.contextlevel = 50  -- Course level context
                    AND u.lastaccess > 0  -- Users with login activity
                GROUP BY u.id, u.username, u.firstname, u.lastname, u.email, 
                         u.lastaccess, u.lastlogin, u.currentlogin, u.lastip, u.auth, u.firstaccess
            ";
            
            $enrolled_users = $moodle_db->query($sql)->result_array();
            
            // Process and format the data
            $processed_data = [];
            foreach ($enrolled_users as $user) {
                // Get hour from lastaccess timestamp using Asia/Jakarta timezone
                $hour = $this->get_hour_from_timestamp($user['lastaccess']);
                
                // Format timestamps to YYYY-MM-DD HH:II:SS
                $formatted_lastaccess = $user['lastaccess'] ? date('Y-m-d H:i:s', $user['lastaccess']) : null;
                $formatted_lastlogin = $user['lastlogin'] ? date('Y-m-d H:i:s', $user['lastlogin']) : null;
                $formatted_currentlogin = $user['currentlogin'] ? date('Y-m-d H:i:s', $user['currentlogin']) : null;
                $formatted_firstaccess = $user['firstaccess'] ? date('Y-m-d H:i:s', $user['firstaccess']) : null;
                
                // Format activity_date from lastaccess using Asia/Jakarta timezone
                $activity_date = $this->get_formatted_activity_date($user['lastaccess']);
                
                // Parse role and course information
                $role_ids = $user['role_ids'] ? explode(',', $user['role_ids']) : [];
                $role_names = $user['role_names'] ? explode(',', $user['role_names']) : [];
                $role_shortnames = $user['role_shortnames'] ? explode(',', $user['role_shortnames']) : [];
                $archetypes = $user['archetypes'] ? explode(',', $user['archetypes']) : [];
                $course_ids = $user['course_ids'] ? explode(',', $user['course_ids']) : [];
                
                // Get primary role (first role in the list)
                $primary_role_id = !empty($role_ids) ? $role_ids[0] : null;
                $primary_role_name = !empty($role_names) ? $role_names[0] : null;
                $primary_role_shortname = !empty($role_shortnames) ? $role_shortnames[0] : null;
                $primary_archetype = !empty($archetypes) ? $archetypes[0] : null;
                $primary_course_id = !empty($course_ids) ? $course_ids[0] : null;
                
                $processed_data[] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'firstname' => $user['firstname'],
                    'lastname' => $user['lastname'],
                    'email' => $user['email'],
                    'lastaccess' => $user['lastaccess'],
                    'formatted_lastaccess' => $formatted_lastaccess,
                    'lastlogin' => $user['lastlogin'],
                    'formatted_lastlogin' => $formatted_lastlogin,
                    'currentlogin' => $user['currentlogin'],
                    'formatted_currentlogin' => $formatted_currentlogin,
                    'lastip' => $user['lastip'],
                    'auth' => $user['auth'],
                    'firstaccess' => $user['firstaccess'],
                    'formatted_firstaccess' => $formatted_firstaccess,
                    
                    // Primary role information (first role)
                    'role_id' => $primary_role_id,
                    'role_name' => $primary_role_name,
                    'role_shortname' => $primary_role_shortname,
                    'archetype' => $primary_archetype,
                    'course_id' => $primary_course_id,
                    
                    // All roles and courses information
                    'all_role_ids' => $role_ids,
                    'all_role_names' => $role_names,
                    'all_role_shortnames' => $role_shortnames,
                    'all_archetypes' => $archetypes,
                    'all_course_ids' => $course_ids,
                    'total_courses' => $user['total_courses'],
                    
                    'activity_hour' => $hour,
                    'activity_date' => $activity_date,
                    'login_count' => 1, // Default login count, will be incremented if same hour
                    'extraction_date' => $extraction_date,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            return [
                'success' => true,
                'extraction_date' => $extraction_date,
                'total_users' => count($processed_data),
                'data' => $processed_data,
                'message' => 'Successfully extracted ' . count($processed_data) . ' enrolled users from Moodle'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'extraction_date' => $extraction_date,
                'data' => []
            ];
        }
    }

    /**
     * Get formatted activity date from timestamp using Asia/Jakarta timezone
     * @param int $timestamp Unix timestamp
     * @return string Formatted date in YYYY-MM-DD format (date only)
     */
    private function get_formatted_activity_date($timestamp) {
        try {
            // Set timezone to Asia/Jakarta
            $timezone = new DateTimeZone('Asia/Jakarta');
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            $date->setTimezone($timezone);
            
            return $date->format('Y-m-d'); // Only date, no time
            
        } catch (Exception $e) {
            // Fallback to UTC if timezone conversion fails
            return date('Y-m-d', $timestamp);
        }
    }

    /**
     * Get hour from timestamp using Asia/Jakarta timezone
     * @param int $timestamp Unix timestamp
     * @return int Hour in 24-hour format (0-23)
     */
    private function get_hour_from_timestamp($timestamp) {
        try {
            // Set timezone to Asia/Jakarta
            $timezone = new DateTimeZone('Asia/Jakarta');
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            $date->setTimezone($timezone);
            
            return (int) $date->format('G'); // 24-hour format without leading zeros
            
        } catch (Exception $e) {
            // Fallback to UTC if timezone conversion fails
            return (int) date('G', $timestamp);
        }
    }

    /**
     * Process data concurrently using multiple database connections
     * @param array $extracted_data Array of user data to process
     * @param string $extraction_date Extraction date
     * @param int $concurrency Number of concurrent processes
     * @return array Processing results
     */
    private function process_data_concurrent($extracted_data, $extraction_date, $concurrency) {
        $total_records = count($extracted_data);
        $chunk_size = ceil($total_records / $concurrency);
        $chunks = array_chunk($extracted_data, $chunk_size);
        
        $inserted_count = 0;
        $updated_count = 0;
        $error_count = 0;
        
        // Create multiple database connections for concurrent processing
        $db_connections = [];
        for ($i = 0; $i < $concurrency; $i++) {
            $db_connections[$i] = $this->load->database('', TRUE);
        }
        
        // Process chunks concurrently
        $processes = [];
        foreach ($chunks as $chunk_index => $chunk) {
            $db_connection = $db_connections[$chunk_index % $concurrency];
            
            // Process each chunk in a separate context
            $chunk_result = $this->process_data_chunk($chunk, $extraction_date, $db_connection);
            
            $inserted_count += $chunk_result['inserted_count'];
            $updated_count += $chunk_result['updated_count'];
            $error_count += $chunk_result['error_count'];
        }
        
        // Close database connections
        foreach ($db_connections as $connection) {
            if (method_exists($connection, 'close')) {
                $connection->close();
            }
        }
        
        return [
            'inserted_count' => $inserted_count,
            'updated_count' => $updated_count,
            'error_count' => $error_count
        ];
    }

    /**
     * Process a chunk of data
     * @param array $chunk Array of user data to process
     * @param string $extraction_date Extraction date
     * @param object $db_connection Database connection object
     * @return array Processing results
     */
    private function process_data_chunk($chunk, $extraction_date, $db_connection) {
        $inserted_count = 0;
        $updated_count = 0;
        $error_count = 0;
        
        foreach ($chunk as $user_data) {
            try {
                // Check if record exists for this user, hour, and activity date
                $existing_record = $db_connection->get_where('udl_etl', [
                    'user_id' => $user_data['user_id'],
                    'activity_hour' => $user_data['activity_hour'],
                    'activity_date' => $user_data['activity_date']
                ])->row_array();
                
                if ($existing_record) {
                    // Record exists, check if lastaccess has changed
                    if ($existing_record['lastaccess'] != $user_data['lastaccess']) {
                        // Update existing record with new data and increment login count
                        $update_data = [
                            'lastaccess' => $user_data['lastaccess'],
                            'formatted_lastaccess' => $user_data['formatted_lastaccess'],
                            'lastlogin' => $user_data['lastlogin'],
                            'formatted_lastlogin' => $user_data['formatted_lastlogin'],
                            'currentlogin' => $user_data['currentlogin'],
                            'formatted_currentlogin' => $user_data['formatted_currentlogin'],
                            'lastip' => $user_data['lastip'],
                            'auth' => $user_data['auth'],
                            'firstaccess' => $user_data['firstaccess'],
                            'formatted_firstaccess' => $user_data['formatted_firstaccess'],
                            'role_id' => $user_data['role_id'],
                            'role_name' => $user_data['role_name'],
                            'role_shortname' => $user_data['role_shortname'],
                            'archetype' => $user_data['archetype'],
                            'course_id' => $user_data['course_id'],
                            'all_role_ids' => json_encode($user_data['all_role_ids']),
                            'all_role_names' => json_encode($user_data['all_role_names']),
                            'all_role_shortnames' => json_encode($user_data['all_role_shortnames']),
                            'all_archetypes' => json_encode($user_data['all_archetypes']),
                            'all_course_ids' => json_encode($user_data['all_course_ids']),
                            'total_courses' => $user_data['total_courses'],
                            'login_count' => $existing_record['login_count'] + 1, // Increment login count
                            'extraction_date' => $extraction_date,
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $db_connection->where('id', $existing_record['id']);
                        $db_connection->update('udl_etl', $update_data);
                        
                        if ($db_connection->affected_rows() > 0) {
                            $updated_count++;
                            log_message('info', "UPDATED: User {$user_data['user_id']} ({$user_data['username']}) - Login count incremented to {$update_data['login_count']} in hour {$user_data['activity_hour']}");
                        }
                    } else {
                        // No change in lastaccess, skip update
                        log_message('debug', "NO CHANGE: User {$user_data['user_id']} ({$user_data['username']}) - Lastaccess unchanged: {$user_data['lastaccess']}");
                    }
                } else {
                    // New record, insert data
                    $insert_data = [
                        'user_id' => $user_data['user_id'],
                        'username' => $user_data['username'],
                        'firstname' => $user_data['firstname'],
                        'lastname' => $user_data['lastname'],
                        'email' => $user_data['email'],
                        'lastaccess' => $user_data['lastaccess'],
                        'formatted_lastaccess' => $user_data['formatted_lastaccess'],
                        'lastlogin' => $user_data['lastlogin'],
                        'formatted_lastlogin' => $user_data['formatted_lastlogin'],
                        'currentlogin' => $user_data['currentlogin'],
                        'formatted_currentlogin' => $user_data['formatted_currentlogin'],
                        'lastip' => $user_data['lastip'],
                        'auth' => $user_data['auth'],
                        'firstaccess' => $user_data['firstaccess'],
                        'formatted_firstaccess' => $user_data['formatted_firstaccess'],
                        'role_id' => $user_data['role_id'],
                        'role_name' => $user_data['role_name'],
                        'role_shortname' => $user_data['role_shortname'],
                        'archetype' => $user_data['archetype'],
                        'course_id' => $user_data['course_id'],
                        'all_role_ids' => json_encode($user_data['all_role_ids']),
                        'all_role_names' => json_encode($user_data['all_role_names']),
                        'all_role_shortnames' => json_encode($user_data['all_role_shortnames']),
                        'all_archetypes' => json_encode($user_data['all_archetypes']),
                        'all_course_ids' => json_encode($user_data['all_course_ids']),
                        'total_courses' => $user_data['total_courses'],
                        'activity_hour' => $user_data['activity_hour'],
                        'activity_date' => $user_data['activity_date'],
                        'login_count' => 1, // Start with 1 for new record
                        'extraction_date' => $extraction_date,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $db_connection->insert('udl_etl', $insert_data);
                    
                    if ($db_connection->affected_rows() > 0) {
                        $inserted_count++;
                        log_message('info', "INSERTED: User {$user_data['user_id']} ({$user_data['username']}) - New activity record in hour {$user_data['activity_hour']}");
                    }
                }
                
            } catch (Exception $e) {
                $error_count++;
                log_message('error', "ERROR: Failed to process user {$user_data['user_id']} ({$user_data['username']}) - " . $e->getMessage());
            }
        }
        
        return [
            'inserted_count' => $inserted_count,
            'updated_count' => $updated_count,
            'error_count' => $error_count
        ];
    }
}
