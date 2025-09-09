<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sp_etl_logs_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();  // Add this line to load database
        $this->table_name = 'sp_etl_logs';
        
        // Check if database connection is available
        if (!$this->db) {
            log_message('error', 'Database connection not available in Sp_etl_logs_model');
        }
    }

    /**
     * Create new ETL log entry
     * @param array $data Log data
     * @return int|bool Insert ID or false on failure
     */
    public function create_log($data) {
        try {
            // Check if database connection is available
            if (!$this->db) {
                log_message('error', 'Database connection not available in create_log');
                return false;
            }
            
            $this->db->insert($this->table_name, $data);
            return $this->db->insert_id();
        } catch (Exception $e) {
            log_message('error', "Error creating ETL log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update ETL log entry
     * @param int $log_id Log ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function update_log($log_id, $data) {
        try {
            // Check if database connection is available
            if (!$this->db) {
                log_message('error', 'Database connection not available in update_log');
                return false;
            }
            
            $this->db->where('id', $log_id);
            $this->db->update($this->table_name, $data);
            return $this->db->affected_rows() > 0;
        } catch (Exception $e) {
            log_message('error', "Error updating ETL log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get ETL log by ID
     * @param int $log_id Log ID
     * @return array|bool Log data or false on failure
     */
    public function get_log_by_id($log_id) {
        try {
            // Check if database connection is available
            if (!$this->db) {
                log_message('error', 'Database connection not available in get_log_by_id');
                return false;
            }
            
            $this->db->where('id', $log_id);
            $result = $this->db->get($this->table_name);
            return $result->num_rows() > 0 ? $result->row_array() : false;
        } catch (Exception $e) {
            log_message('error', "Error getting ETL log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent ETL logs with pagination
     * @param int $page Page number
     * @param int $limit Records per page
     * @param array $filters Additional filters
     * @return array Data with pagination
     */
    public function get_logs_with_pagination($page = 1, $limit = 10, $filters = []) {
        try {
            // Check if database connection is available
            if (!$this->db) {
                log_message('error', 'Database connection not available in get_logs_with_pagination');
                return [
                    'data' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total_records' => 0,
                        'total_pages' => 0,
                        'has_next_page' => false,
                        'has_prev_page' => false
                    ]
                ];
            }
            
            $offset = ($page - 1) * $limit;
            
            // Base query
            $this->db->select('*');
            $this->db->from($this->table_name);
            
            // Apply filters
            if (!empty($filters['status'])) {
                $this->db->where('status', $filters['status']);
            }
            
            if (!empty($filters['process_type'])) {
                $this->db->where('process_type', $filters['process_type']);
            }
            
            if (!empty($filters['concurrency'])) {
                $this->db->where('concurrency', $filters['concurrency']);
            }
            
            if (!empty($filters['date_from'])) {
                $this->db->where('start_time >=', $filters['date_from']);
            }
            
            if (!empty($filters['date_to'])) {
                $this->db->where('start_time <=', $filters['date_to']);
            }
            
            // Get total count for pagination
            $total_query = $this->db->get_compiled_select();
            $total_count = $this->db->query($total_query)->num_rows();
            
            // Reset query builder for main query
            $this->db->reset_query();
            
            // Build main query for data
            $this->db->select('*');
            $this->db->from($this->table_name);
            
            // Apply filters for main query
            if (!empty($filters['status'])) {
                $this->db->where('status', $filters['status']);
            }
            
            if (!empty($filters['process_type'])) {
                $this->db->where('process_type', $filters['process_type']);
            }
            
            if (!empty($filters['concurrency'])) {
                $this->db->where('concurrency', $filters['concurrency']);
            }
            
            if (!empty($filters['date_from'])) {
                $this->db->where('start_time >=', $filters['date_to']);
            }
            
            if (!empty($filters['date_to'])) {
                $this->db->where('start_time <=', $filters['date_to']);
            }
            
            // Apply pagination and ordering
            $this->db->limit($limit, $offset);
            $this->db->order_by('start_time', 'DESC');
            
            $logs_data = $this->db->get()->result_array();
            
            return [
                'data' => $logs_data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_records' => $total_count,
                    'total_pages' => ceil($total_count / $limit),
                    'has_next_page' => $page < ceil($total_count / $limit),
                    'has_prev_page' => $page > 1
                ]
            ];
            
        } catch (Exception $e) {
            log_message('error', "Error in get_logs_with_pagination: " . $e->getMessage());
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_next_page' => false,
                    'has_prev_page' => false
                ]
            ];
        }
    }

    /**
     * Get ETL logs statistics
     * @param string $date_from Start date (optional)
     * @param string $date_to End date (optional)
     * @return array Statistics data
     */
    public function get_logs_statistics($date_from = null, $date_to = null) {
        try {
            // Check if database connection is available
            if (!$this->db) {
                log_message('error', 'Database connection not available in get_logs_statistics');
                return [
                    'total_logs' => 0,
                    'completed_count' => 0,
                    'failed_count' => 0,
                    'running_count' => 0,
                    'avg_duration' => 0,
                    'max_duration' => 0,
                    'min_duration' => 0
                ];
            }
            
            $this->db->select('
                COUNT(*) as total_logs,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_count,
                COUNT(CASE WHEN status = "running" THEN 1 END) as running_count,
                AVG(duration) as avg_duration,
                MAX(duration) as max_duration,
                MIN(duration) as min_duration
            ');
            $this->db->from($this->table_name);
            
            if ($date_from) {
                $this->db->where('start_time >=', $date_from);
            }
            
            if ($date_to) {
                $this->db->where('start_time <=', $date_to);
            }
            
            $stats = $this->db->get()->row_array();
            return $stats;
            
        } catch (Exception $e) {
            log_message('error', "Error getting ETL logs statistics: " . $e->getMessage());
            return [
                'total_logs' => 0,
                'completed_count' => 0,
                'failed_count' => 0,
                'running_count' => 0,
                'avg_duration' => 0,
                'max_duration' => 0,
                'min_duration' => 0
            ];
        }
    }

    /**
     * Clean old logs (older than specified days)
     * @param int $days_old Days old to clean
     * @return int Number of deleted records
     */
    public function clean_old_logs($days_old = 30) {
        try {
            // Check if database connection is available
            if (!$this->db) {
                log_message('error', 'Database connection not available in clean_old_logs');
                return 0;
            }
            
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
            $this->db->where('start_time <', $cutoff_date);
            $this->db->delete($this->table_name);
            
            $deleted_count = $this->db->affected_rows();
            log_message('info', "Cleaned {$deleted_count} old ETL logs older than {$days_old} days");
            
            return $deleted_count;
            
        } catch (Exception $e) {
            log_message('error', "Error cleaning old ETL logs: " . $e->getMessage());
            return 0;
        }
    }
}
