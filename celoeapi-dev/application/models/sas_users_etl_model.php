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
            foreach ($users as $user) {
                $user['extraction_date'] = $extraction_date;
                $user['created_at'] = date('Y-m-d H:i:s');
                $user['updated_at'] = date('Y-m-d H:i:s');
                
                // Insert or update user
                $this->db->where('user_id', $user['user_id']);
                $this->db->where('extraction_date', $extraction_date);
                $existing = $this->db->get('sas_users_etl')->row_array();
                
                if ($existing) {
                    // Update existing record
                    $this->db->where('id', $existing['id']);
                    $this->db->update('sas_users_etl', $user);
                } else {
                    // Insert new record
                    $this->db->insert('sas_users_etl', $user);
                }
                
                $inserted_count++;
            }
            
            return [
                'success' => true,
                'extracted_count' => count($users),
                'inserted_count' => $inserted_count,
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
     * Extract user roles from Moodle database
     */
    public function extract_user_roles_from_moodle($extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d');
        
        try {
            $moodle_db = $this->load->database('moodle', TRUE);
            
            $sql = "
                SELECT 
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
                WHERE ctx.contextlevel = 50  -- Course level
                  AND ra.timemodified > 0
            ";
            
            $roles = $moodle_db->query($sql)->result_array();
            
            // Process and insert roles
            $inserted_count = 0;
            foreach ($roles as $role) {
                $role['extraction_date'] = $extraction_date;
                $role['created_at'] = date('Y-m-d H:i:s');
                
                // Insert or update role
                $this->db->where('user_id', $role['user_id']);
                $this->db->where('course_id', $role['course_id']);
                $this->db->where('role_id', $role['role_id']);
                $this->db->where('extraction_date', $extraction_date);
                $existing = $this->db->get('sas_user_roles_etl')->row_array();
                
                if ($existing) {
                    // Update existing record
                    $this->db->where('id', $existing['id']);
                    $this->db->update('sas_user_roles_etl', $role);
                } else {
                    // Insert new record
                    $this->db->insert('sas_user_roles_etl', $role);
                }
                
                $inserted_count++;
            }
            
            return [
                'success' => true,
                'extracted_count' => count($roles),
                'inserted_count' => $inserted_count,
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
     * Extract user enrolments from Moodle database
     */
    public function extract_user_enrolments_from_moodle($extraction_date = null)
    {
        $extraction_date = $extraction_date ?: date('Y-m-d');
        
        try {
            $moodle_db = $this->load->database('moodle', TRUE);
            
            $sql = "
                SELECT 
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
                WHERE ue.status = 0  -- Active enrolments only
                  AND c.visible = 1  -- Visible courses only
            ";
            
            $enrolments = $moodle_db->query($sql)->result_array();
            
            // Process and insert enrolments
            $inserted_count = 0;
            foreach ($enrolments as $enrolment) {
                $enrolment['extraction_date'] = $extraction_date;
                $enrolment['created_at'] = date('Y-m-d H:i:s');
                
                // Insert or update enrolment
                $this->db->where('enrolid', $enrolment['enrolid']);
                $this->db->where('userid', $enrolment['userid']);
                $this->db->where('extraction_date', $extraction_date);
                $existing = $this->db->get('sas_user_enrolments_etl')->row_array();
                
                if ($existing) {
                    // Update existing record
                    $this->db->where('id', $existing['id']);
                    $this->db->update('sas_user_enrolments_etl', $enrolment);
                } else {
                    // Insert new record
                    $this->db->insert('sas_user_enrolments_etl', $enrolment);
                }
                
                $inserted_count++;
            }
            
            return [
                'success' => true,
                'extracted_count' => count($enrolments),
                'inserted_count' => $inserted_count,
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
}
