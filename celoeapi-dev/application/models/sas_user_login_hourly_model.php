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
     * Extract user login data from Moodle per hour (ACTIVITY FREQUENCY BASED)
     * This method tracks how many times lastaccess changes per hour for each user
     * ONLY for users who actually have activity
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
                // Get hour from lastaccess timestamp
                $hour = (int) date('G', $user['lastaccess']);
                
                // Check if user already has a record for this hour
                $existing_record = $this->db->get_where($this->table_name, [
                    'user_id' => $user['user_id'],
                    'extraction_date' => $extraction_date,
                    'hour' => $hour
                ])->row_array();
                
                if ($existing_record) {
                    // User already has activity in this hour, increment count
                    $new_count = $existing_record['login_count'] + 1;
                    $hourly_data[] = [
                        'extraction_date' => $extraction_date,
                        'hour' => $hour,
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'full_name' => $user['full_name'],
                        'role_type' => $user['role_type'],
                        'login_count' => $new_count, // Increment count for same hour
                        'first_login_time' => $existing_record['first_login_time'], // Keep first time
                        'last_login_time' => $user['lastaccess'], // Update to latest
                        'is_active' => 1,
                        'created_at' => $existing_record['created_at'], // Keep original creation
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                } else {
                    // New activity in this hour, start with count = 1
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
                }
            }
        }

        return $hourly_data;
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
     * ONLY updates users who actually have new activity
     */
    public function run_complete_user_login_hourly_etl($extraction_date = null) {
        if (!$extraction_date) {
            $extraction_date = date('Y-m-d');
        }

        try {
            // NO LOGGING - Let main CLI method handle logging
            
            // Step 1: Extract data from Moodle (ONLY users with recent activity)
            $extracted_data = $this->extract_user_login_hourly_from_moodle($extraction_date);
            $extracted_count = count($extracted_data);

            // Step 2: Process with upsert logic (insert or update existing records)
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
                        
                        $this->db->where('id', $existing['id']);
                        $this->db->update($this->table_name, $update_data);
                        $updated_count++;
                    }
                    // If no new activity, don't update anything
                } else {
                    // Insert new record for user with new activity
                    $this->db->insert($this->table_name, $hourly_record);
                    $inserted_count++;
                }
            }

            // Step 3: Get additional real-time data for current hour (if any new activity)
            $current_hour = (int) date('G');
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
                'message' => "Processed $extracted_count records: $inserted_count new, $updated_count updated, $realtime_updates[processed] real-time updates"
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
     * ONLY updates users who actually have new activity
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
                $user_hour = (int) date('G', $user['lastaccess']);
                
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
                                'login_count' => $new_count, // Increment count for same hour
                                'last_login_time' => $user['lastaccess'], // Update to latest
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                            $updated++;
                        }
                        // If no new activity, don't update anything
                    } else {
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
     * Get detailed hourly data for charts (24-hour format)
     */
    public function get_hourly_chart_data($date = null) {
        $date = $date ?: date('Y-m-d');
        
        // Get data for all 24 hours (0-23)
        $hourly_data = [];
        
        for ($hour = 0; $hour < 24; $hour++) {
            $hour_stats = $this->db->select('
                    COUNT(DISTINCT user_id) as unique_users,
                    SUM(login_count) as total_activities,
                    COUNT(CASE WHEN role_type = "teacher" THEN 1 END) as teacher_count,
                    COUNT(CASE WHEN role_type = "student" THEN 1 END) as student_count,
                    AVG(login_count) as avg_activities_per_user
                ')
                ->from($this->table_name)
                ->where('extraction_date', $date)
                ->where('hour', $hour)
                ->get()
                ->row_array();
            
            $hourly_data[$hour] = [
                'hour' => $hour,
                'formatted_hour' => sprintf('%02d:00', $hour),
                'unique_users' => (int) $hour_stats['unique_users'],
                'total_activities' => (int) $hour_stats['total_activities'],
                'teacher_count' => (int) $hour_stats['teacher_count'],
                'student_count' => (int) $hour_stats['student_count'],
                'avg_activities_per_user' => round($hour_stats['avg_activities_per_user'], 2),
                'is_peak_hour' => false // Will be set below
            ];
        }
        
        // Identify peak hours (top 3 busiest)
        $peak_hours = $this->db->select('hour, SUM(login_count) as total_activities')
                               ->from($this->table_name)
                               ->where('extraction_date', $date)
                               ->group_by('hour')
                               ->order_by('total_activities', 'DESC')
                               ->limit(3)
                               ->get()
                               ->result_array();
        
        $peak_hour_numbers = array_column($peak_hours, 'hour');
        foreach ($hourly_data as $hour => &$data) {
            $data['is_peak_hour'] = in_array($hour, $peak_hour_numbers);
        }
        
        return $hourly_data;
    }
    
    /**
     * Get busiest hours analysis for teachers and students
     */
    public function get_busiest_hours_analysis($date = null, $limit = 5) {
        $date = $date ?: date('Y-m-d');
        
        // Get busiest hours for teachers
        $teacher_hours = $this->db->select('
                hour,
                COUNT(DISTINCT user_id) as unique_teachers,
                SUM(login_count) as total_activities,
                AVG(login_count) as avg_activities_per_teacher
            ')
            ->from($this->table_name)
            ->where('extraction_date', $date)
            ->where('role_type', 'teacher')
            ->group_by('hour')
            ->order_by('total_activities', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
        
        // Get busiest hours for students
        $student_hours = $this->db->select('
                hour,
                COUNT(DISTINCT user_id) as unique_students,
                SUM(login_count) as total_activities,
                AVG(login_count) as avg_activities_per_student
            ')
            ->from($this->table_name)
            ->where('extraction_date', $date)
            ->where('role_type', 'student')
            ->group_by('hour')
            ->order_by('total_activities', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
        
        // Get overall busiest hours
        $overall_hours = $this->db->select('
                hour,
                COUNT(DISTINCT user_id) as unique_users,
                SUM(login_count) as total_activities,
                COUNT(CASE WHEN role_type = "teacher" THEN 1 END) as teacher_count,
                COUNT(CASE WHEN role_type = "student" THEN 1 END) as student_count
            ')
            ->from($this->table_name)
            ->where('extraction_date', $date)
            ->group_by('hour')
            ->order_by('total_activities', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
        
        return [
            'date' => $date,
            'teacher_hours' => $teacher_hours,
            'student_hours' => $student_hours,
            'overall_hours' => $overall_hours,
            'summary' => [
                'total_teachers' => $this->db->where('extraction_date', $date)->where('role_type', 'teacher')->count_all_results($this->table_name),
                'total_students' => $this->db->where('extraction_date', $date)->where('role_type', 'student')->count_all_results($this->table_name),
                'total_activities' => $this->db->select('SUM(login_count) as total')->where('extraction_date', $date)->get($this->table_name)->row()->total
            ]
        ];
    }
    
    /**
     * Get real-time activity summary for current hour with role breakdown
     */
    public function get_realtime_activity_summary($date = null, $hour = null) {
        $date = $date ?: date('Y-m-d');
        $hour = $hour !== null ? $hour : (int) date('G');
        
        $summary = $this->db->select('
                COUNT(DISTINCT user_id) as unique_users,
                SUM(login_count) as total_activities,
                role_type,
                COUNT(*) as user_count,
                AVG(login_count) as avg_activities_per_user
            ')
            ->from($this->table_name)
            ->where('extraction_date', $date)
            ->where('hour', $hour)
            ->group_by('role_type')
            ->get()
            ->result_array();
        
        $total_users = 0;
        $total_activities = 0;
        $role_breakdown = [];
        
        foreach ($summary as $row) {
            $total_users += $row['user_count'];
            $total_activities += $row['total_activities'];
            $role_breakdown[$row['role_type']] = [
                'users' => $row['user_count'],
                'activities' => $row['total_activities'],
                'avg_activities' => round($row['avg_activities_per_user'], 2)
            ];
        }
        
        // Get current hour statistics
        $current_hour_stats = $this->db->select('
                COUNT(DISTINCT user_id) as total_users,
                SUM(login_count) as total_activities,
                MIN(first_login_time) as earliest_activity,
                MAX(last_login_time) as latest_activity
            ')
            ->from($this->table_name)
            ->where('extraction_date', $date)
            ->where('hour', $hour)
            ->get()
            ->row_array();
        
        return [
            'date' => $date,
            'hour' => $hour,
            'total_unique_users' => $total_users,
            'total_activities' => $total_activities,
            'role_breakdown' => $role_breakdown,
            'current_hour_stats' => $current_hour_stats,
            'timestamp' => time(),
            'formatted_hour' => sprintf('%02d:00', $hour)
        ];
    }

    /**
     * Get busiest hours based on number of active users per hour
     * This method focuses on detecting which hours have the most active users
     */
    public function get_busiest_hours_by_user_count($date = null, $limit = 5) {
        $date = $date ?: date('Y-m-d');
        
        // Get hours with most active users (based on unique user count)
        $busiest_hours = $this->db->select('
                hour,
                COUNT(DISTINCT user_id) as active_users_count,
                COUNT(CASE WHEN role_type = "teacher" THEN 1 END) as teacher_count,
                COUNT(CASE WHEN role_type = "student" THEN 1 END) as student_count,
                SUM(login_count) as total_activities
            ')
            ->from($this->table_name)
            ->where('extraction_date', $date)
            ->group_by('hour')
            ->order_by('active_users_count', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
        
        // Get detailed breakdown for each busy hour
        $detailed_hours = [];
        foreach ($busiest_hours as $hour_data) {
            $hour = $hour_data['hour'];
            
            // Get user details for this hour
            $users_in_hour = $this->db->select('
                    username,
                    full_name,
                    role_type,
                    login_count,
                    first_login_time,
                    last_login_time
                ')
                ->from($this->table_name)
                ->where('extraction_date', $date)
                ->where('hour', $hour)
                ->order_by('last_login_time', 'DESC')
                ->get()
                ->result_array();
            
            $detailed_hours[] = [
                'hour' => $hour,
                'formatted_hour' => sprintf('%02d:00', $hour),
                'active_users_count' => $hour_data['active_users_count'],
                'teacher_count' => $hour_data['teacher_count'],
                'student_count' => $hour_data['student_count'],
                'total_activities' => $hour_data['total_activities'],
                'users' => $users_in_hour
            ];
        }
        
        return [
            'date' => $date,
            'busiest_hours' => $detailed_hours,
            'summary' => [
                'total_hours_with_activity' => $this->db->where('extraction_date', $date)->count_all_results($this->table_name),
                'total_active_users' => $this->db->select('COUNT(DISTINCT user_id) as total')->where('extraction_date', $date)->get($this->table_name)->row()->total,
                'peak_hour' => $busiest_hours[0]['hour'] ?? null,
                'peak_users' => $busiest_hours[0]['active_users_count'] ?? 0
            ]
        ];
    }
}
