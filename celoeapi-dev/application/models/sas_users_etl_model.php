<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sas_users_etl_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Extract users data from Moodle database (simplified)
     * Uses Moodle user.id as unique constraint to prevent duplicates
     */
    public function extract_users_from_moodle($extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d');
        
        try {
            // Load Moodle database connection
            $moodle_db = $this->load->database('moodle', TRUE);
            
            // Build simplified SQL query for users
            $sql = "
                SELECT 
                    u.id as user_id,
                    u.username,
                    u.idnumber,
                    u.firstname,
                    u.lastname,
                    CONCAT(u.firstname, ' ', u.lastname) as full_name,
                    u.email,
                    u.suspended,
                    u.deleted,
                    u.confirmed,
                    u.firstaccess,
                    u.lastaccess,
                    u.lastlogin,
                    u.currentlogin,
                    u.lastip,
                    u.auth
                FROM mdl_user u
                WHERE u.deleted = 0 
                  AND u.suspended = 0
                  AND u.id > 1  -- Exclude guest user
            ";
            
            $users = $moodle_db->query($sql)->result_array();
            
            // Process and insert users
            $inserted_count = 0;
            $updated_count = 0;
            foreach ($users as $user) {
                $user['extraction_date'] = $extraction_date;
                $user['created_at'] = date('Y-m-d H:i:s');
                
                // Check if record exists (unique by user_id from Moodle)
                $this->db->where('user_id', $user['user_id']);
                $existing = $this->db->get('sas_users_etl')->row_array();
                
                if ($existing) {
                    // Update existing record with new extraction_date
                    $this->db->where('id', $existing['id']);
                    $this->db->update('sas_users_etl', $user);
                    $updated_count++;
                } else {
                    // Insert new record
                    $this->db->insert('sas_users_etl', $user);
                    $inserted_count++;
                }
            }
            
            return [
                'success' => true,
                'extracted_count' => count($users),
                'inserted_count' => $inserted_count,
                'updated_count' => $updated_count,
                'extraction_date' => $extraction_date
            ];
            
        } catch (Exception $e) {
            log_message('error', 'Error extracting users from Moodle: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract user roles from Moodle database (ONLY for active users)
     * This method ensures only roles for existing, active users are extracted
     * Prevents duplicate roles and roles for deleted/suspended users
     * Uses Moodle (userid + course_id + roleid) as unique constraint
     */
    public function extract_user_roles_from_moodle($extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d');
        
        try {
            $moodle_db = $this->load->database('moodle', TRUE);
            
            // Improved SQL: Only get roles for active, existing users
            $sql = "
                SELECT DISTINCT
                    ra.userid as user_id,
                    ctx.instanceid as course_id,
                    ra.roleid as role_id,
                    r.name as role_name,
                    r.shortname as role_shortname,
                    ra.contextid as context_id,
                    ctx.contextlevel as context_level,
                    ra.timemodified
                FROM mdl_role_assignments ra
                JOIN mdl_context ctx ON ra.contextid = ctx.id
                JOIN mdl_role r ON ra.roleid = r.id
                JOIN mdl_user u ON ra.userid = u.id  -- Ensure user exists
                JOIN mdl_course c ON ctx.instanceid = c.id  -- Ensure course exists
                WHERE ctx.contextlevel = 50  -- Course level
                    AND u.deleted = 0  -- User not deleted
                    AND u.suspended = 0  -- User not suspended
                    AND u.id > 1  -- Exclude guest user
                    AND c.visible = 1  -- Course is visible
                    AND ra.timemodified > 0  -- Role assignment is valid
            ";
            
            $roles = $moodle_db->query($sql)->result_array();
            
            // Debug: Log the count of roles found
            log_message('info', 'Found ' . count($roles) . ' valid user roles from Moodle (active users only)');
            
            // Process and insert roles
            $inserted_count = 0;
            $updated_count = 0;
            $error_count = 0;
            
            foreach ($roles as $role) {
                try {
                    $role['extraction_date'] = $extraction_date;
                    $role['created_at'] = date('Y-m-d H:i:s');
                    
                    // Check if record exists (unique by user_id + course_id + role_id from Moodle)
                    $this->db->where('user_id', $role['user_id']);
                    $this->db->where('course_id', $role['course_id']);
                    $this->db->where('role_id', $role['role_id']);
                    $existing = $this->db->get('sas_user_roles_etl')->row_array();
                    
                    if ($existing) {
                        // Update existing record with new extraction_date
                        $this->db->where('id', $existing['id']);
                        $this->db->update('sas_user_roles_etl', $role);
                        $updated_count++;
                    } else {
                        // Insert new record
                        $this->db->insert('sas_user_roles_etl', $role);
                        $inserted_count++;
                    }
                } catch (Exception $e) {
                    log_message('error', 'Error processing role for user_id: ' . $role['user_id'] . ', course_id: ' . $role['course_id'] . ', role_id: ' . $role['role_id'] . ' - ' . $e->getMessage());
                    $error_count++;
                }
            }
            
            log_message('info', 'Roles ETL completed - Inserted: ' . $inserted_count . ', Updated: ' . $updated_count . ', Errors: ' . $error_count);
            
            return [
                'success' => true,
                'extracted_count' => count($roles),
                'inserted_count' => $inserted_count,
                'updated_count' => $updated_count,
                'error_count' => $error_count,
                'extraction_date' => $extraction_date
            ];
            
        } catch (Exception $e) {
            log_message('error', 'Error extracting user roles from Moodle: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract user enrolments from Moodle database (ONLY for active users)
     * This method ensures only enrolments for existing, active users are extracted
     * Prevents duplicate enrolments and enrolments for deleted/suspended users
     * Uses Moodle (userid + course_id + enrolid) as unique constraint
     */
    public function extract_user_enrolments_from_moodle($extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d');
        
        try {
            $moodle_db = $this->load->database('moodle', TRUE);
            
            $sql = "
                SELECT DISTINCT
                    ue.id as enrolid,
                    ue.userid,
                    c.id as course_id,
                    e.enrol as enrolment_method,
                    ue.status,
                    ue.timestart,
                    ue.timeend,
                    ue.timecreated,
                    ue.timemodified
                FROM mdl_user_enrolments ue
                JOIN mdl_enrol e ON ue.enrolid = e.id
                JOIN mdl_course c ON e.courseid = c.id
                JOIN mdl_user u ON ue.userid = u.id  -- Ensure user exists
                WHERE c.visible = 1  -- Visible courses only
                    AND u.deleted = 0  -- User not deleted
                    AND u.suspended = 0  -- User not suspended
                    AND u.id > 1  -- Exclude guest user
                    AND ue.status = 0  -- Active enrolment only
            ";
            
            $enrolments = $moodle_db->query($sql)->result_array();
            
            // Debug: Log the count of enrolments found
            log_message('info', 'Found ' . count($enrolments) . ' valid user enrolments from Moodle (active users only)');
            
            // Process and insert enrolments
            $inserted_count = 0;
            $updated_count = 0;
            $error_count = 0;
            
            foreach ($enrolments as $enrolment) {
                try {
                    $enrolment['extraction_date'] = $extraction_date;
                    $enrolment['created_at'] = date('Y-m-d H:i:s');
                    
                    // Check if record exists (unique by userid + course_id + enrolid from Moodle)
                    $this->db->where('userid', $enrolment['userid']);
                    $this->db->where('course_id', $enrolment['course_id']);
                    $this->db->where('enrolid', $enrolment['enrolid']);
                    $existing = $this->db->get('sas_user_enrolments_etl')->row_array();
                    
                    if ($existing) {
                        // Update existing record with new extraction_date
                        $this->db->where('id', $existing['id']);
                        $this->db->update('sas_user_enrolments_etl', $enrolment);
                        $updated_count++;
                    } else {
                        // Insert new record
                        $this->db->insert('sas_user_enrolments_etl', $enrolment);
                        $inserted_count++;
                    }
                } catch (Exception $e) {
                    log_message('error', 'Error processing enrolment for enrolid: ' . $enrolment['enrolid'] . ', userid: ' . $enrolment['userid'] . ' - ' . $e->getMessage());
                    $error_count++;
                }
            }
            
            log_message('info', 'Enrolments ETL completed - Inserted: ' . $inserted_count . ', Updated: ' . $updated_count . ', Errors: ' . $error_count . ' (active users only)');
            
            return [
                'success' => true,
                'extracted_count' => count($enrolments),
                'inserted_count' => $inserted_count,
                'updated_count' => $updated_count,
                'error_count' => $error_count,
                'extraction_date' => $extraction_date
            ];
            
        } catch (Exception $e) {
            log_message('error', 'Error extracting user enrolments from Moodle: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run complete users ETL process
     */
    public function run_complete_users_etl($extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d');
        $start_time = microtime(true);
        
        try {
            // NO LOGGING - Let main CLI method handle logging
            $results = [];
            
            // Step 1: Extract users
            $results['users'] = $this->extract_users_from_moodle($extraction_date);
            
            // Step 2: Extract user roles
            $results['roles'] = $this->extract_user_roles_from_moodle($extraction_date);
            
            // Step 3: Extract user enrolments
            $results['enrolments'] = $this->extract_user_enrolments_from_moodle($extraction_date);
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            
            // NO LOGGING - Return results for main CLI method to log
            return [
                'success' => true,
                'extraction_date' => $extraction_date,
                'duration' => $duration,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            
            // NO LOGGING - Return error for main CLI method to handle
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration' => $duration
            ];
        }
    }

    /**
     * Clear ETL data for a specific date
     */
    public function clear_etl_data($date)
    {
        $tables = [
            'sas_users_etl',
            'sas_user_roles_etl',
            'sas_user_enrolments_etl'
        ];
        
        $total_affected = 0;
        
        foreach ($tables as $table) {
            if ($this->db->table_exists($table)) {
                $this->db->where('extraction_date', $date);
                $this->db->delete($table);
                $total_affected += $this->db->affected_rows();
            }
        }
        
        return $total_affected;
    }

    /**
     * Test database connections and table existence
     */
    public function test_connections()
    {
        $results = [];
        
        try {
            // Test main database connection
            $main_db_test = $this->db->query('SELECT 1 as test')->row_array();
            $results['main_db'] = $main_db_test ? 'Connected' : 'Failed';
        } catch (Exception $e) {
            $results['main_db'] = 'Error: ' . $e->getMessage();
        }
        
        try {
            // Test Moodle database connection
            $moodle_db = $this->load->database('moodle', TRUE);
            $moodle_db_test = $moodle_db->query('SELECT 1 as test')->row_array();
            $results['moodle_db'] = $moodle_db_test ? 'Connected' : 'Failed';
        } catch (Exception $e) {
            $results['moodle_db'] = 'Error: ' . $e->getMessage();
        }
        
        // Test table existence
        $tables = [
            'sas_users_etl',
            'sas_user_roles_etl',
            'sas_user_enrolments_etl'
        ];
        
        foreach ($tables as $table) {
            $results['table_' . $table] = $this->db->table_exists($table) ? 'Exists' : 'Missing';
        }
        
        return $results;
    }

    /**
     * Get sample data from Moodle for debugging
     */
    public function get_moodle_sample_data()
    {
        try {
            $moodle_db = $this->load->database('moodle', TRUE);
            
            $results = [];
            
            // Sample users
            $users_count = $moodle_db->query('SELECT COUNT(*) as count FROM mdl_user WHERE deleted = 0 AND suspended = 0 AND id > 1')->row_array();
            $results['users_count'] = $users_count['count'];
            
            // Sample roles
            $roles_count = $moodle_db->query('
                SELECT COUNT(*) as count 
                FROM mdl_role_assignments ra
                JOIN mdl_context ctx ON ra.contextid = ctx.id
                WHERE ctx.contextlevel = 50
            ')->row_array();
            $results['roles_count'] = $roles_count['count'];
            
            // Sample enrolments
            $enrolments_count = $moodle_db->query('
                SELECT COUNT(*) as count 
                FROM mdl_user_enrolments ue
                JOIN mdl_enrol e ON ue.enrolid = e.id
                JOIN mdl_course c ON e.courseid = c.id
                WHERE c.visible = 1
            ')->row_array();
            $results['enrolments_count'] = $enrolments_count['count'];
            
            return $results;
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get table structure for debugging
     */
    public function get_table_structure()
    {
        $results = [];
        
        $tables = [
            'sas_users_etl',
            'sas_user_roles_etl',
            'sas_user_enrolments_etl'
        ];
        
        foreach ($tables as $table) {
            if ($this->db->table_exists($table)) {
                $fields = $this->db->list_fields($table);
                $results[$table] = $fields;
            } else {
                $results[$table] = 'Table does not exist';
            }
        }
        
        return $results;
    }

    /**
     * Get users ETL data with pagination and relations
     */
    public function get_users_etl_with_relations($page = 1, $limit = 10, $search = '', $filters = [])
    {
        $offset = ($page - 1) * $limit;
        
        // Base query for users ETL
        $this->db->select('sue.*, u.username, u.firstname, u.lastname, u.email, u.lastaccess');
        $this->db->from('sas_users_etl sue');
        $this->db->join('users u', 'u.id = sue.user_id', 'left');
        
        // Apply search filter
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('u.username', $search);
            $this->db->or_like('u.firstname', $search);
            $this->db->or_like('u.lastname', $search);
            $this->db->or_like('u.email', $search);
            $this->db->or_like('sue.idnumber', $search);
            $this->db->group_end();
        }
        
        // Apply filters
        if (!empty($filters['extraction_date'])) {
            $this->db->where('sue.extraction_date', $filters['extraction_date']);
        }
        
        if (!empty($filters['suspended'])) {
            $this->db->where('sue.suspended', $filters['suspended']);
        }
        
        if (!empty($filters['deleted'])) {
            $this->db->where('sue.deleted', $filters['deleted']);
        }
        
        // Get total count for pagination
        $total_query = $this->db->get_compiled_select();
        $total_count = $this->db->query($total_query)->num_rows();
        
        // Apply pagination
        $this->db->limit($limit, $offset);
        $this->db->order_by('sue.user_id', 'ASC');
        
        $users = $this->db->get()->result_array();
        
        // Get related data for each user
        foreach ($users as &$user) {
            // Get user roles
            $user['roles'] = $this->get_user_roles($user['user_id'], $user['extraction_date']);
            
            // Get user enrolments
            $user['enrolments'] = $this->get_user_enrolments($user['user_id'], $user['extraction_date']);
        }
        
        return [
            'data' => $users,
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
     * Get user roles for a specific user and extraction date
     */
    private function get_user_roles($user_id, $extraction_date)
    {
        $this->db->select('sure.*, c.fullname as course_name, c.shortname as course_shortname');
        $this->db->from('sas_user_roles_etl sure');
        $this->db->join('courses c', 'c.id = sure.course_id', 'left');
        $this->db->where('sure.user_id', $user_id);
        $this->db->where('sure.extraction_date', $extraction_date);
        
        return $this->db->get()->result_array();
    }

    /**
     * Get user enrolments for a specific user and extraction date
     */
    private function get_user_enrolments($user_id, $extraction_date)
    {
        $this->db->select('suee.*, c.fullname as course_name, c.shortname as course_shortname');
        $this->db->from('sas_user_enrolments_etl suee');
        $this->db->join('courses c', 'c.id = suee.course_id', 'left');
        $this->db->where('suee.userid', $user_id);
        $this->db->where('suee.extraction_date', $extraction_date);
        
        return $this->db->get()->result_array();
    }

    /**
     * Get summary statistics for users ETL
     */
    public function get_users_etl_summary($extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d');
        
        $summary = [];
        
        // Total users
        $this->db->where('extraction_date', $extraction_date);
        $summary['total_users'] = $this->db->count_all_results('sas_users_etl');
        
        // Active users (not suspended, not deleted)
        $this->db->where('extraction_date', $extraction_date);
        $this->db->where('suspended', 0);
        $this->db->where('deleted', 0);
        $summary['active_users'] = $this->db->count_all_results('sas_users_etl');
        
        // Suspended users
        $this->db->where('extraction_date', $extraction_date);
        $this->db->where('suspended', 1);
        $summary['suspended_users'] = $this->db->count_all_results('sas_users_etl');
        
        // Deleted users
        $this->db->where('extraction_date', $extraction_date);
        $this->db->where('deleted', 1);
        $summary['deleted_users'] = $this->db->count_all_results('sas_users_etl');
        
        // Total roles
        $this->db->where('extraction_date', $extraction_date);
        $summary['total_roles'] = $this->db->count_all_results('sas_user_roles_etl');
        
        // Total enrolments
        $this->db->where('extraction_date', $extraction_date);
        $summary['total_enrolments'] = $this->db->count_all_results('sas_user_enrolments_etl');
        
        return $summary;
    }
}
