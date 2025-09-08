<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tp_etl_logs_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->table_name = 'tp_etl_logs';
    }

    /**
     * Create a new ETL log entry
     * @param string $process_type Type of ETL process (e.g., 'teacher_summary', 'student_summary')
     * @param string $status Initial status ('running', 'completed', 'failed')
     * @param string $message Optional message for the log entry
     * @param int $concurrency Concurrency level used for the process
     * @return int|bool Log ID on success, false on failure
     */
    public function create_log_entry($process_type, $status = 'running', $message = '', $concurrency = 1) {
        try {
            $log_data = [
                'process_type' => $process_type,
                'status' => $status,
                'message' => $message,
                'concurrency' => $concurrency,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => null,
                'duration_seconds' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert($this->table_name, $log_data);
            
            if ($this->db->affected_rows() > 0) {
                $log_id = $this->db->insert_id();
                log_message('info', "Created ETL log entry ID: {$log_id} for process: {$process_type}");
                return $log_id;
            }
            
            return false;
            
        } catch (Exception $e) {
            log_message('error', "Error creating ETL log entry: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update ETL log status and end time
     * @param int $log_id Log entry ID
     * @param string $status New status ('completed', 'failed')
     * @param string $message Optional message (especially for failed status)
     * @return bool Success status
     */
    public function update_log_status($log_id, $status, $message = '') {
        try {
            $end_date = date('Y-m-d H:i:s');
            
            // Calculate duration if we have start_date
            $log_entry = $this->db->where('id', $log_id)->get($this->table_name)->row_array();
            $duration_seconds = null;
            
            if ($log_entry && $log_entry['start_date']) {
                $start_timestamp = strtotime($log_entry['start_date']);
                $end_timestamp = strtotime($end_date);
                $duration_seconds = $end_timestamp - $start_timestamp;
            }

            $update_data = [
                'status' => $status,
                'message' => $message,
                'end_date' => $end_date,
                'duration_seconds' => $duration_seconds,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->db->where('id', $log_id);
            $result = $this->db->update($this->table_name, $update_data);
            
            if ($result) {
                log_message('info', "Updated ETL log ID: {$log_id} to status: {$status}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            log_message('error', "Error updating ETL log status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get ETL logs with pagination and filters
     * @param int $page Page number
     * @param int $limit Records per page
     * @param string $search Search term
     * @param array $filters Additional filters
     * @return array Data with pagination
     */
    public function get_etl_logs_with_pagination($page = 1, $limit = 10, $search = '', $filters = []) {
        $offset = ($page - 1) * $limit;
        
        // Base query
        $this->db->select('*');
        $this->db->from($this->table_name);
        
        // Apply search filter
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('process_type', $search);
            $this->db->or_like('message', $search);
            $this->db->group_end();
        }
        
        // Apply filters
        if (!empty($filters['id'])) {
            $this->db->where('id', $filters['id']);
        }
        
        if (!empty($filters['process_type'])) {
            $this->db->where('process_type', $filters['process_type']);
        }
        
        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        
        if (!empty($filters['concurrency'])) {
            $this->db->where('concurrency', $filters['concurrency']);
        }
        
        if (!empty($filters['start_date'])) {
            $this->db->where('DATE(start_date)', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $this->db->where('DATE(end_date)', $filters['end_date']);
        }
        
        if (!empty($filters['date_range'])) {
            $this->db->where('start_date >=', $filters['date_range']['start']);
            $this->db->where('start_date <=', $filters['date_range']['end']);
        }
        
        // Get total count for pagination
        $total_query = $this->db->get_compiled_select();
        $total_count = $this->db->query($total_query)->num_rows();
        
        // Reset query builder for main query
        $this->db->reset_query();
        
        // Build main query for data
        $this->db->select('*');
        $this->db->from($this->table_name);
        
        // Apply search filter for main query
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('process_type', $search);
            $this->db->or_like('message', $search);
            $this->db->group_end();
        }
        
        // Apply filters for main query
        if (!empty($filters['id'])) {
            $this->db->where('id', $filters['id']);
        }
        
        if (!empty($filters['process_type'])) {
            $this->db->where('process_type', $filters['process_type']);
        }
        
        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        
        if (!empty($filters['concurrency'])) {
            $this->db->where('concurrency', $filters['concurrency']);
        }
        
        if (!empty($filters['start_date'])) {
            $this->db->where('DATE(start_date)', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $this->db->where('DATE(end_date)', $filters['end_date']);
        }
        
        if (!empty($filters['date_range'])) {
            $this->db->where('start_date >=', $filters['date_range']['start']);
            $this->db->where('start_date <=', $filters['date_range']['end']);
        }
        
        // Apply pagination and ordering
        $this->db->limit($limit, $offset);
        $this->db->order_by('start_date', 'DESC');
        $this->db->order_by('id', 'DESC');
        
        $logs_data = $this->db->get()->result_array();
        
        return [
            'success' => true,
            'data' => $logs_data,
            'total' => $total_count,
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
}
