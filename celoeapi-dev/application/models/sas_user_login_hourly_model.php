<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sas_user_login_hourly_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->table_name = 'sas_user_login_hourly';
    }

    /**
     * Get ETL status
     */
    public function get_etl_status() {
        if (!$this->db->table_exists('sas_users_login_etl_logs')) {
            return null;
        }
        
        $this->db->select('*');
        $this->db->from('sas_users_login_etl_logs');
        $this->db->where('process_name', 'user_login_hourly_etl');
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit(1);
        
        $query = $this->db->get();
        return $query->row_array();
    }

    /**
     * Extract user login data from Moodle per hour (ONLY when lastaccess changes)
     * This method tracks ONLY users whose lastaccess has actually changed
     * Prevents false login data when user hasn't logged in
     * COMPARES with previous extraction to detect real activity changes
     */
    public function extract_user_login_hourly_from_moodle($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        // Get users with recent activity (lastaccess in last 24 hours)
        $sql = "
            SELECT 
                u.id as user_id,
                u.username,
                CONCAT(u.firstname, ' ', u.lastname) as full_name,
                u.lastaccess,
                r.archetype,
                CASE 
                    WHEN r.archetype IN ('student', 'guest') THEN 'student'
                    WHEN r.archetype IN ('teacher', 'editingteacher', 'manager') THEN 'teacher'
                    ELSE 'student'
                END as role_type
            FROM mdl_user u
            LEFT JOIN mdl_role_assignments ra ON u.id = ra.userid
            LEFT JOIN mdl_context ctx ON ra.contextid = ctx.id
            LEFT JOIN mdl_role r ON ra.roleid = r.id
            WHERE u.deleted = 0 
                AND u.suspended = 0
                AND ctx.contextlevel = 50  -- Course level
                AND u.lastaccess > (UNIX_TIMESTAMP() - 86400)  -- Activity in last 24 hours
            GROUP BY u.id, u.username, u.firstname, u.lastname, u.lastaccess, r.archetype
        ";

        // Use Moodle database connection
        $moodle_db = $this->load->database('moodle', TRUE);
        $query = $moodle_db->query($sql);
        $users = $query->result_array();

        $hourly_data = [];
        
        foreach ($users as $user) {
            if ($user['lastaccess'] > 0) {
                // Get hour from lastaccess timestamp using Asia/Jakarta timezone
                $hour = $this->get_hour_from_timestamp($user['lastaccess']);
                
                // Check if user already has a record for this hour and date
                $existing_record = $this->db->get_where($this->table_name, [
                    'user_id' => $user['user_id'],
                    'extraction_date' => $extraction_date,
                    'hour' => $hour
                ])->row_array();
                
                if ($existing_record) {
                    // Check if lastaccess has actually changed (real activity)
                    if ($existing_record['last_login_time'] != $user['lastaccess']) {
                        // User has new activity in this hour, increment count
                        $new_count = $existing_record['login_count'] + 1;
                        $hourly_data[] = [
                            'extraction_date' => $extraction_date,
                            'hour' => $hour,
                            'user_id' => $user['user_id'],
                            'username' => $user['username'],
                            'full_name' => $user['full_name'],
                            'role_type' => $user['role_type'],
                            'login_count' => $new_count, // Increment count for new activity
                            'first_login_time' => $existing_record['first_login_time'], // Keep first time
                            'last_login_time' => $user['lastaccess'], // Update to latest
                            'is_active' => 1,
                            'created_at' => $existing_record['created_at'], // Keep original creation
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // Log the increment for debugging
                        log_message('info', "User {$user['user_id']} ({$user['username']}) - Login count incremented from {$existing_record['login_count']} to {$new_count} in hour {$hour}");
                    } else {
                        // Log when no change detected
                        log_message('debug', "User {$user['user_id']} ({$user['username']}) - No new activity detected in hour {$hour}, lastaccess unchanged: {$user['lastaccess']}");
                    }
                    // If lastaccess hasn't changed, don't add to hourly_data (no new activity)
                } else {
                    // Check if this is truly NEW activity by comparing with previous extractions
                    $previous_activity = $this->check_previous_user_activity($user['user_id'], $user['lastaccess']);
                    
                    if ($previous_activity['is_new_activity']) {
                        // This is genuinely new activity, create record
                        $hourly_data[] = [
                            'extraction_date' => $extraction_date,
                            'hour' => $hour,
                            'user_id' => $user['user_id'],
                            'username' => $user['username'],
                            'full_name' => $user['full_name'],
                            'role_type' => $user['role_type'],
                            'login_count' => 1, // Start with 1 for new hour
                            'first_login_time' => $user['lastaccess'],
                            'last_login_time' => $user['lastaccess'],
                            'is_active' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // Log the new record creation
                        log_message('info', "User {$user['user_id']} ({$user['username']}) - New activity record created in hour {$hour} with lastaccess: {$user['lastaccess']}");
                    } else {
                        // Log when activity is not new
                        log_message('debug', "User {$user['user_id']} ({$user['username']}) - Activity not new in hour {$hour}, reason: {$previous_activity['reason']}");
                    }
                    // If not new activity, don't add to hourly_data (prevents false data)
                }
            }
        }

        return $hourly_data;
    }

    /**
     * Check if user activity is truly new by comparing with previous extractions
     * This prevents storing data for users who haven't had new activity
     */
    private function check_previous_user_activity($user_id, $current_lastaccess) {
        // Get the most recent record for this user from any previous extraction
        $this->db->select('last_login_time, extraction_date, hour')
                 ->from($this->table_name)
                 ->where('user_id', $user_id)
                 ->order_by('extraction_date', 'DESC')
                 ->order_by('hour', 'DESC')
                 ->limit(1);
        
        $previous_record = $this->db->get()->row_array();
        
        if (!$previous_record) {
            // First time seeing this user - this is new activity
            return [
                'is_new_activity' => true,
                'reason' => 'First time user detected'
            ];
        }
        
        // Check if lastaccess has actually changed since last extraction
        if ($previous_record['last_login_time'] != $current_lastaccess) {
            // User has new activity
            return [
                'is_new_activity' => true,
                'reason' => 'Lastaccess changed from ' . $previous_record['last_login_time'] . ' to ' . $current_lastaccess
            ];
        }
        
        // No new activity detected
        return [
            'is_new_activity' => false,
            'reason' => 'Lastaccess unchanged: ' . $current_lastaccess
        ];
    }

    /**
     * Insert extracted data to ETL table
     */
    public function insert_user_login_hourly_data($data) {
        if (empty($data)) {
            return 0;
        }

        // Use batch insert for better performance
        $this->db->insert_batch($this->table_name, $data);
        return $this->db->affected_rows();
    }

    /**
     * Clear ETL data for specific date
     */
    public function clear_etl_data($date = null) {
        if ($date) {
            $this->db->where('extraction_date', $date);
        }
        return $this->db->delete($this->table_name);
    }

    /**
     * Run complete user login hourly ETL with real-time detection (optimized for cronjob per menit)
     * This method combines ETL extraction and real-time updates in one efficient process
     * ONLY updates users who actually have new activity (lastaccess changed)
     */
    public function run_complete_user_login_hourly_etl($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // NO LOGGING - Let main CLI method handle logging
            
            // Step 1: Extract data from Moodle (ONLY users with NEW activity - lastaccess changed)
            $extracted_data = $this->extract_user_login_hourly_from_moodle($extraction_date);
            $extracted_count = count($extracted_data);

            // Step 2: Process with smart upsert logic (only when there's real activity)
            $inserted_count = 0;
            $updated_count = 0;
            
            foreach ($extracted_data as $hourly_record) {
                // Check if record exists for this user, hour, and date
                $existing = $this->db->get_where($this->table_name, [
                    'user_id' => $hourly_record['user_id'],
                    'extraction_date' => $extraction_date,
                    'hour' => $hourly_record['hour']
                ])->row_array();
                
                if ($existing) {
                    // Only update if there's actually new activity (lastaccess changed)
                    if ($existing['last_login_time'] != $hourly_record['last_login_time']) {
                        $update_data = [
                            'login_count' => $hourly_record['login_count'],
                            'last_login_time' => $hourly_record['last_login_time'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // Log before update for debugging
                        log_message('info', "UPDATE: User {$hourly_record['user_id']} - Login count from {$existing['login_count']} to {$hourly_record['login_count']} in hour {$hourly_record['hour']}");
                        
                        // Reset query builder to ensure clean state
                        $this->db->reset_query();
                        
                        $this->db->where('id', $existing['id']);
                        $this->db->update($this->table_name, $update_data);
                        
                        // Verify update was successful
                        if ($this->db->affected_rows() > 0) {
                            $updated_count++;
                            log_message('info', "SUCCESS: User {$hourly_record['user_id']} - Update successful, new login_count: {$hourly_record['login_count']}");
                            
                            // Verify the update actually happened
                            $verify_record = $this->db->get_where($this->table_name, ['id' => $existing['id']])->row_array();
                            if ($verify_record) {
                                log_message('info', "VERIFY: User {$hourly_record['user_id']} - Database shows login_count: {$verify_record['login_count']}");
                            }
                        } else {
                            log_message('error', "FAILED: User {$hourly_record['user_id']} - Update failed, affected_rows: " . $this->db->affected_rows());
                            
                            // Try to get more error information
                            $error_info = $this->db->error();
                            log_message('error', "DB ERROR: " . json_encode($error_info));
                        }
                    } else {
                        // Log when no change detected
                        log_message('debug', "NO CHANGE: User {$hourly_record['user_id']} - Lastaccess unchanged: {$existing['last_login_time']} vs {$hourly_record['last_login_time']}");
                    }
                    // If no new activity, don't update anything (prevents false data)
                } else {
                    // Insert new record for user with new activity
                    log_message('info', "INSERT: User {$hourly_record['user_id']} - New record with login_count: {$hourly_record['login_count']} in hour {$hourly_record['hour']}");
                    
                    // Reset query builder to ensure clean state
                    $this->db->reset_query();
                    
                    $this->db->insert($this->table_name, $hourly_record);
                    
                    if ($this->db->affected_rows() > 0) {
                        $inserted_count++;
                        log_message('info', "SUCCESS: User {$hourly_record['user_id']} - Insert successful");
                    } else {
                        log_message('error', "FAILED: User {$hourly_record['user_id']} - Insert failed");
                        
                        // Try to get more error information
                        $error_info = $this->db->error();
                        log_message('error', "DB ERROR: " . json_encode($error_info));
                    }
                }
            }

            // Step 3: Get additional real-time data for current hour (if any new activity)
            $current_hour = $this->get_hour_from_timestamp(time());
            $realtime_updates = $this->detect_realtime_activity($extraction_date, $current_hour);
            
            // NO LOGGING - Return results for main CLI method to log
            return [
                'success' => true,
                'extracted' => $extracted_count,
                'inserted' => $inserted_count,
                'updated' => $updated_count,
                'realtime_processed' => $realtime_updates['processed'],
                'realtime_updated' => $realtime_updates['updated'],
                'date' => $extraction_date,
                'current_hour' => $current_hour,
                'message' => "Processed $extracted_count records: $inserted_count new, $updated_count updated, $realtime_updates[processed] real-time updates (ONLY real activity changes)"
            ];

        } catch (Exception $e) {
            // NO LOGGING - Return error for main CLI method to handle
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Detect real-time activity for current hour (helper method)
     * This method tracks user activity frequency within the current hour
     * ONLY updates users who actually have new activity (lastaccess changed)
     */
    private function detect_realtime_activity($date, $current_hour) {
        try {
            // Get users with recent activity in current hour (last 5 minutes for real-time updates)
            $moodle_db = $this->load->database('moodle', TRUE);
            $recent_users = $moodle_db->select('u.id, u.lastaccess, u.username')
                                     ->from('mdl_user u')
                                     ->where('u.deleted', 0)
                                     ->where('u.suspended', 0)
                                     ->where('u.lastaccess >', time() - 300) // Activity in last 5 minutes
                                     ->get()
                                     ->result_array();
            
            $processed = 0;
            $updated = 0;
            
            foreach ($recent_users as $user) {
                $user_hour = $this->get_hour_from_timestamp($user['lastaccess']);
                
                // Only process if activity is in current hour
                if ($user_hour === $current_hour) {
                    // Check if record exists for this hour
                    $existing = $this->db->get_where($this->table_name, [
                        'user_id' => $user['id'],
                        'extraction_date' => $date,
                        'hour' => $current_hour
                    ])->row_array();
                    
                    if ($existing) {
                        // ONLY update if there's actually new activity (lastaccess changed)
                        if ($existing['last_login_time'] != $user['lastaccess']) {
                            $new_count = $existing['login_count'] + 1;
                            $this->db->where('id', $existing['id']);
                            $this->db->update($this->table_name, [
                                'login_count' => $new_count, // Increment count for new activity
                                'last_login_time' => $user['lastaccess'], // Update to latest
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                            $updated++;
                            
                            // Log the real-time increment for debugging
                            log_message('info', "Real-time: User {$user['id']} ({$user['username']}) - Login count incremented from {$existing['login_count']} to {$new_count} in hour {$current_hour}");
                        } else {
                            // Log when no change detected in real-time
                            log_message('debug', "Real-time: User {$user['id']} ({$user['username']}) - No new activity detected in hour {$current_hour}, lastaccess unchanged: {$user['lastaccess']}");
                        }
                        // If no new activity, don't update anything (prevents false data)
                    } else {
                        // Check if this is truly NEW activity before creating record
                        $previous_activity = $this->check_previous_user_activity($user['id'], $user['lastaccess']);
                        
                        if ($previous_activity['is_new_activity']) {
                            // Create new record for user who just became active in this hour
                            $this->db->insert($this->table_name, [
                                'extraction_date' => $date,
                                'hour' => $current_hour,
                                'user_id' => $user['id'],
                                'username' => $user['username'],
                                'full_name' => $user['username'], // Will be updated in next full extraction
                                'role_type' => 'student', // Default, will be updated in next full extraction
                                'login_count' => 1, // Start with 1 for new hour
                                'first_login_time' => $user['lastaccess'],
                                'last_login_time' => $user['lastaccess'],
                                'is_active' => 1,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                            $processed++;
                            
                            // Log the real-time new record creation
                            log_message('info', "Real-time: User {$user['id']} ({$user['username']}) - New activity record created in hour {$current_hour} with lastaccess: {$user['lastaccess']}");
                        } else {
                            // Log when real-time activity is not new
                            log_message('debug', "Real-time: User {$user['id']} ({$user['username']}) - Activity not new in hour {$current_hour}, reason: {$previous_activity['reason']}");
                        }
                        // If not new activity, don't create record (prevents false data)
                    }
                }
            }
            
            return [
                'processed' => $processed,
                'updated' => $updated
            ];
            
        } catch (Exception $e) {
            return [
                'processed' => 0,
                'updated' => 0
            ];
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
            
            $hour = (int) $date->format('G'); // 24-hour format without leading zeros
            
            // Log timezone conversion for debugging
            $utc_date = new DateTime();
            $utc_date->setTimestamp($timestamp);
            $utc_date->setTimezone(new DateTimeZone('UTC'));
            
            log_message('debug', "Timezone conversion: UTC {$utc_date->format('Y-m-d H:i:s')} -> Asia/Jakarta {$date->format('Y-m-d H:i:s')} -> Hour: {$hour}");
            
            return $hour;
            
        } catch (Exception $e) {
            // Fallback to UTC if timezone conversion fails
            log_message('error', "Timezone conversion failed: " . $e->getMessage() . ". Falling back to UTC for timestamp: {$timestamp}");
            return (int) date('G', $timestamp);
        }
    }


    /**
     * Get login hourly data with pagination and relations to users, roles, enrolments
     * This method gets data from sas_user_login_hourly table with relations
     */
    public function get_login_hourly_with_relations($page = 1, $limit = 10, $search = '', $filters = [])
    {
        $offset = ($page - 1) * $limit;
        
        // Base query for login hourly data
        $this->db->select('sulh.*, u.username, u.firstname, u.lastname, u.email, u.lastaccess');
        $this->db->from('sas_user_login_hourly sulh');
        $this->db->join('sas_users_etl u', 'u.user_id = sulh.user_id', 'left');
        
        // Apply search filter
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('u.username', $search);
            $this->db->or_like('u.firstname', $search);
            $this->db->or_like('u.lastname', $search);
            $this->db->or_like('sulh.username', $search);
            $this->db->or_like('sulh.full_name', $search);
            $this->db->group_end();
        }
        
        // Apply filters
        if (!empty($filters['extraction_date'])) {
            $this->db->where('sulh.extraction_date', $filters['extraction_date']);
        }
        
        if (!empty($filters['hour'])) {
            $this->db->where('sulh.hour', $filters['hour']);
        }
        
        if (!empty($filters['role_type'])) {
            $this->db->where('sulh.role_type', $filters['role_type']);
        }
        
        if (isset($filters['is_active'])) {
            $this->db->where('sulh.is_active', $filters['is_active']);
        }
        
        // Get total count for pagination
        $total_query = $this->db->get_compiled_select();
        $total_count = $this->db->query($total_query)->num_rows();
        
        // Reset query builder for main query
        $this->db->reset_query();
        
        // Build main query for data
        $this->db->select('sulh.*, u.username, u.firstname, u.lastname, u.email, u.lastaccess');
        $this->db->from('sas_user_login_hourly sulh');
        $this->db->join('sas_users_etl u', 'u.user_id = sulh.user_id', 'left');
        
        // Apply search filter for main query
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('u.username', $search);
            $this->db->or_like('u.firstname', $search);
            $this->db->or_like('u.lastname', $search);
            $this->db->or_like('sulh.username', $search);
            $this->db->or_like('sulh.full_name', $search);
            $this->db->group_end();
        }
        
        // Apply filters for main query
        if (!empty($filters['extraction_date'])) {
            $this->db->where('sulh.extraction_date', $filters['extraction_date']);
        }
        
        if (!empty($filters['hour'])) {
            $this->db->where('sulh.hour', $filters['hour']);
        }
        
        if (!empty($filters['role_type'])) {
            $this->db->where('sulh.role_type', $filters['role_type']);
        }
        
        if (isset($filters['is_active'])) {
            $this->db->where('sulh.is_active', $filters['is_active']);
        }
        
        // Apply pagination and ordering
        $this->db->limit($limit, $offset);
        $this->db->order_by('sulh.extraction_date', 'DESC');
        $this->db->order_by('sulh.hour', 'ASC');
        $this->db->order_by('sulh.user_id', 'ASC');
        
        $login_hourly_data = $this->db->get()->result_array();
        
        // Get related data for each record
        foreach ($login_hourly_data as &$record) {
            // Get user data from sas_users_etl
            $record['user'] = $this->get_user_data_for_login_hourly($record['user_id']);
            
            // Get user roles from sas_user_roles_etl
            $record['roles'] = $this->get_user_roles_for_login_hourly($record['user_id']);
            
            // Get user enrolments from sas_user_enrolments_etl
            $record['enrolments'] = $this->get_user_enrolments_for_login_hourly($record['user_id']);
        }
        
        return [
            'data' => $login_hourly_data,
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
     * Get user data for login hourly data
     */
    private function get_user_data_for_login_hourly($user_id)
    {
        $this->db->select('sue.*');
        $this->db->from('sas_users_etl sue');
        $this->db->where('sue.user_id', $user_id);
        $this->db->limit(1);
        
        $user_data = $this->db->get()->row_array();
        
        if ($user_data) {
            return [
                'id' => $user_data['user_id'],
                'username' => $user_data['username'],
                'idnumber' => $user_data['idnumber'] ?? null,
                'firstname' => $user_data['firstname'],
                'lastname' => $user_data['lastname'],
                'full_name' => $user_data['full_name'] ?? ($user_data['firstname'] . ' ' . $user_data['lastname']),
                'email' => $user_data['email'],
                'suspended' => $user_data['suspended'] ?? 0,
                'deleted' => $user_data['deleted'] ?? 0,
                'confirmed' => $user_data['confirmed'] ?? 1,
                'firstaccess' => $user_data['firstaccess'] ?? 0,
                'lastaccess' => $user_data['lastaccess'] ?? 0,
                'lastlogin' => $user_data['lastlogin'] ?? 0,
                'currentlogin' => $user_data['currentlogin'] ?? 0,
                'lastip' => $user_data['lastip'] ?? null,
                'auth' => $user_data['auth'] ?? 'manual',
                'extraction_date' => $user_data['extraction_date'],
                'created_at' => $user_data['created_at'] ?? null,
            ];
        }
        
        return null;
    }

    /**
     * Get user roles for login hourly data
     */
    private function get_user_roles_for_login_hourly($user_id)
    {
        $this->db->select('sure.*');
        $this->db->from('sas_user_roles_etl sure');
        $this->db->where('sure.user_id', $user_id);
        $this->db->limit(1);
        
        $role = $this->db->get()->row_array();
        
        if ($role) {
            return [
            'id' => $role['id'],
            'user_id' => $role['user_id'],
            'course_id' => $role['course_id'],
            'role_id' => $role['role_id'],
            'role_name' => $role['role_name'],
            'role_shortname' => $role['role_shortname'],
            'context_id' => $role['context_id'],
            'context_level' => $role['context_level'],
            'timemodified' => $role['timemodified'],
            'extraction_date' => $role['extraction_date'],
            ];
        }
        
        return null;
    }

    /**
     * Get user enrolments for login hourly data
     */
    private function get_user_enrolments_for_login_hourly($user_id)
    {
        $this->db->select('suee.*');
        $this->db->from('sas_user_enrolments_etl suee');
        $this->db->where('suee.userid', $user_id);
        
        $enrolments = $this->db->get()->result_array();
        
        // Format enrolments data
        $formatted_enrolments = [];
        foreach ($enrolments as $enrolment) {
            $formatted_enrolments[] = [
                'id' => $enrolment['id'],
                'userid' => $enrolment['userid'],
                'course_id' => $enrolment['course_id'],
                'enrolid' => $enrolment['enrolid'],
                'status' => $enrolment['status'],
                'timestart' => $enrolment['timestart'],
                'timeend' => $enrolment['timeend'],
                'timemodified' => $enrolment['timemodified'],
                'extraction_date' => $enrolment['extraction_date'],
            ];
        }
        
        return $formatted_enrolments;
    }

    /**
     * Test method to verify login count increment logic
     * This helps debug the increment functionality
     */
    public function test_login_count_increment($user_id, $extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        // Get current record for this user and date
        $current_record = $this->db->get_where($this->table_name, [
            'user_id' => $user_id,
            'extraction_date' => $extraction_date
        ])->row_array();

        if (!$current_record) {
            return [
                'status' => 'no_record',
                'message' => 'No record found for this user and date'
            ];
        }

        // Get Moodle user data
        $moodle_db = $this->load->database('moodle', TRUE);
        $moodle_user = $moodle_db->select('id, username, lastaccess')
                                 ->from('mdl_user')
                                 ->where('id', $user_id)
                                 ->get()
                                 ->row_array();

        if (!$moodle_user) {
            return [
                'status' => 'error',
                'message' => 'User not found in Moodle'
            ];
        }

        $current_hour = $this->get_hour_from_timestamp($moodle_user['lastaccess']);
        
        return [
            'status' => 'success',
            'current_record' => $current_record,
            'moodle_user' => $moodle_user,
            'current_hour' => $current_hour,
            'lastaccess_changed' => $current_record['last_login_time'] != $moodle_user['lastaccess'],
            'should_increment' => $current_record['last_login_time'] != $moodle_user['lastaccess'],
            'current_login_count' => $current_record['login_count'],
            'expected_new_count' => $current_record['login_count'] + 1
        ];
    }

    /**
     * Debug method to test the complete ETL process for a specific user
     * This helps identify where the increment process might be failing
     */
    public function debug_user_etl_process($user_id, $extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        $debug_info = [];
        
        // Step 1: Check current ETL record
        $current_record = $this->db->get_where($this->table_name, [
            'user_id' => $user_id,
            'extraction_date' => $extraction_date
        ])->row_array();
        
        $debug_info['current_record'] = $current_record;
        
        // Step 2: Get Moodle user data
        $moodle_db = $this->load->database('moodle', TRUE);
        $moodle_user = $moodle_db->select('id, username, lastaccess, firstname, lastname')
                                 ->from('mdl_user')
                                 ->where('id', $user_id)
                                 ->get()
                                 ->row_array();
        
        $debug_info['moodle_user'] = $moodle_user;
        
        if ($moodle_user) {
            $current_hour = $this->get_hour_from_timestamp($moodle_user['lastaccess']);
            $debug_info['current_hour'] = $current_hour;
            
            // Step 3: Simulate extraction process
            $extracted_data = $this->extract_user_login_hourly_from_moodle($extraction_date);
            $user_extracted_data = array_filter($extracted_data, function($item) use ($user_id) {
                return $item['user_id'] == $user_id;
            });
            
            $debug_info['extracted_data'] = $user_extracted_data;
            $debug_info['extraction_count'] = count($user_extracted_data);
            
            // Step 4: Check what would happen in the update process
            if ($current_record && !empty($user_extracted_data)) {
                $extracted_record = reset($user_extracted_data);
                $debug_info['would_update'] = $current_record['last_login_time'] != $extracted_record['last_login_time'];
                $debug_info['current_login_count'] = $current_record['login_count'];
                $debug_info['extracted_login_count'] = $extracted_record['login_count'];
                $debug_info['login_count_difference'] = $extracted_record['login_count'] - $current_record['login_count'];
            }
        }
        
        return $debug_info;
    }

    /**
     * Force update login count for testing purposes
     * WARNING: This is for debugging only, not for production use
     */
    public function force_update_login_count($user_id, $extraction_date, $new_login_count) {
        $existing = $this->db->get_where($this->table_name, [
            'user_id' => $user_id,
            'extraction_date' => $extraction_date
        ])->row_array();
        
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Record not found'
            ];
        }
        
        $update_data = [
            'login_count' => $new_login_count,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->where('id', $existing['id']);
        $this->db->update($this->table_name, $update_data);
        
        if ($this->db->affected_rows() > 0) {
            return [
                'success' => true,
                'message' => "Login count updated from {$existing['login_count']} to {$new_login_count}",
                'old_count' => $existing['login_count'],
                'new_count' => $new_login_count
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Update failed',
                'affected_rows' => $this->db->affected_rows()
            ];
        }
    }

    /**
     * Test increment process for a specific user and hour
     * This simulates the exact process that happens during ETL
     */
    public function test_increment_process($user_id, $extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        $test_results = [];
        
        // Step 1: Get current state
        $current_record = $this->db->get_where($this->table_name, [
            'user_id' => $user_id,
            'extraction_date' => $extraction_date
        ])->row_array();
        
        $test_results['before'] = $current_record;
        
        // Step 2: Get Moodle data
        $moodle_db = $this->load->database('moodle', TRUE);
        $moodle_user = $moodle_db->select('id, username, lastaccess')
                                 ->from('mdl_user')
                                 ->where('id', $user_id)
                                 ->get()
                                 ->row_array();
        
        $test_results['moodle_data'] = $moodle_user;
        
        if ($moodle_user && $current_record) {
            $current_hour = $this->get_hour_from_timestamp($moodle_user['lastaccess']);
            
            // Step 3: Check if this should trigger an increment
            $should_increment = $current_record['last_login_time'] != $moodle_user['lastaccess'];
            $test_results['should_increment'] = $should_increment;
            
            if ($should_increment) {
                // Step 4: Simulate the increment
                $new_login_count = $current_record['login_count'] + 1;
                
                $update_data = [
                    'login_count' => $new_login_count,
                    'last_login_time' => $moodle_user['lastaccess'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Perform the update
                $this->db->where('id', $current_record['id']);
                $this->db->update($this->table_name, $update_data);
                
                $test_results['update_result'] = [
                    'affected_rows' => $this->db->affected_rows(),
                    'new_login_count' => $new_login_count,
                    'update_data' => $update_data
                ];
                
                // Step 5: Verify the update
                $verify_record = $this->db->get_where($this->table_name, ['id' => $current_record['id']])->row_array();
                $test_results['after'] = $verify_record;
                $test_results['verification'] = [
                    'update_successful' => $verify_record['login_count'] == $new_login_count,
                    'actual_new_count' => $verify_record['login_count'],
                    'expected_count' => $new_login_count
                ];
            }
        }
        
        return $test_results;
    }
}
