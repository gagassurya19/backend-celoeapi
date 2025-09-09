<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Udl_etl_logs_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Create new ETL log entry
     * @param array $log_data Log data containing execution details
     * @return array Result of log creation
     */
    public function create_log($log_data) {
        try {
            $insert_data = [
                'extraction_date' => $log_data['extraction_date'],
                'concurrency' => $log_data['concurrency'],
                'status' => $log_data['status'], // running, completed, failed
                'total_extracted' => $log_data['total_extracted'] ?? 0,
                'total_inserted' => $log_data['total_inserted'] ?? 0,
                'total_updated' => $log_data['total_updated'] ?? 0,
                'total_errors' => $log_data['total_errors'] ?? 0,
                'execution_time' => $log_data['execution_time'] ?? 0,
                'error_message' => $log_data['error_message'] ?? null,
                'started_at' => $log_data['started_at'] ?? date('Y-m-d H:i:s'),
                'completed_at' => $log_data['completed_at'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('udl_etl_logs', $insert_data);
            
            if ($this->db->affected_rows() > 0) {
                $log_id = $this->db->insert_id();
                log_message('info', "UDL ETL LOG CREATED: ID {$log_id}, Date: {$log_data['extraction_date']}, Status: {$log_data['status']}");
                
                return [
                    'success' => true,
                    'log_id' => $log_id,
                    'message' => 'ETL log created successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to create ETL log'
                ];
            }
            
        } catch (Exception $e) {
            log_message('error', "UDL ETL LOG CREATION FAILED: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update existing ETL log entry
     * @param int $log_id Log ID to update
     * @param array $update_data Data to update
     * @return array Result of log update
     */
    public function update_log($log_id, $update_data) {
        try {
            $update_data['updated_at'] = date('Y-m-d H:i:s');
            
            $this->db->where('id', $log_id);
            $this->db->update('udl_etl_logs', $update_data);
            
            if ($this->db->affected_rows() > 0) {
                log_message('info', "UDL ETL LOG UPDATED: ID {$log_id}, Status: {$update_data['status']}");
                
                return [
                    'success' => true,
                    'message' => 'ETL log updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to update ETL log or no changes made'
                ];
            }
            
        } catch (Exception $e) {
            log_message('error', "UDL ETL LOG UPDATE FAILED: ID {$log_id}, Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get ETL logs with pagination and filtering
     * @param array $filters Optional filters
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Logs data with pagination
     */
    public function get_logs($filters = [], $page = 1, $limit = 10) {
        try {
            // Build query
            $this->db->select('*');
            $this->db->from('udl_etl_logs');
            
            // Apply filters
            if (!empty($filters['extraction_date'])) {
                $this->db->where('extraction_date', $filters['extraction_date']);
            }
            if (!empty($filters['status'])) {
                $this->db->where('status', $filters['status']);
            }
            if (!empty($filters['concurrency'])) {
                $this->db->where('concurrency', $filters['concurrency']);
            }
            if (!empty($filters['date_from'])) {
                $this->db->where('extraction_date >=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $this->db->where('extraction_date <=', $filters['date_to']);
            }
            
            // Get total count
            $total_count = $this->db->count_all_results('', false);
            
            // Get paginated data
            $offset = ($page - 1) * $limit;
            $this->db->limit($limit, $offset);
            $this->db->order_by('created_at', 'DESC');
            $this->db->order_by('id', 'DESC');
            
            $logs = $this->db->get()->result_array();
            
            return [
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_records' => $total_count,
                    'total_pages' => ceil($total_count / $limit),
                    'has_next_page' => $page < ceil($total_count / $limit),
                    'has_prev_page' => $page > 1
                ],
                'filters' => $filters
            ];
            
        } catch (Exception $e) {
            log_message('error', "UDL ETL LOGS FETCH FAILED: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'pagination' => []
            ];
        }
    }

    /**
     * Get latest ETL log
     * @return array Latest log data
     */
    public function get_latest_log() {
        try {
            $this->db->select('*');
            $this->db->from('udl_etl_logs');
            $this->db->order_by('created_at', 'DESC');
            $this->db->order_by('id', 'DESC');
            $this->db->limit(1);
            
            $log = $this->db->get()->row_array();
            
            if ($log) {
                return [
                    'success' => true,
                    'data' => $log
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No logs found'
                ];
            }
            
        } catch (Exception $e) {
            log_message('error', "UDL ETL LATEST LOG FETCH FAILED: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get ETL log by ID
     * @param int $log_id Log ID
     * @return array Log data
     */
    public function get_log_by_id($log_id) {
        try {
            $log = $this->db->get_where('udl_etl_logs', ['id' => $log_id])->row_array();
            
            if ($log) {
                return [
                    'success' => true,
                    'data' => $log
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Log not found'
                ];
            }
            
        } catch (Exception $e) {
            log_message('error', "UDL ETL LOG FETCH FAILED: ID {$log_id}, Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get ETL execution statistics
     * @param array $filters Optional filters
     * @return array Statistics data
     */
    public function get_statistics($filters = []) {
        try {
            // Build base query
            $this->db->from('udl_etl_logs');
            
            // Apply filters
            if (!empty($filters['extraction_date'])) {
                $this->db->where('extraction_date', $filters['extraction_date']);
            }
            if (!empty($filters['status'])) {
                $this->db->where('status', $filters['status']);
            }
            if (!empty($filters['date_from'])) {
                $this->db->where('extraction_date >=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $this->db->where('extraction_date <=', $filters['date_to']);
            }
            
            // Get total executions
            $total_executions = $this->db->count_all_results('', false);
            
            // Get status distribution
            $this->db->select('status, COUNT(*) as count');
            $this->db->from('udl_etl_logs');
            if (!empty($filters['extraction_date'])) {
                $this->db->where('extraction_date', $filters['extraction_date']);
            }
            if (!empty($filters['date_from'])) {
                $this->db->where('extraction_date >=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $this->db->where('extraction_date <=', $filters['date_to']);
            }
            $this->db->group_by('status');
            $status_distribution = $this->db->get()->result_array();
            
            // Get concurrency distribution
            $this->db->select('concurrency, COUNT(*) as count, AVG(execution_time) as avg_execution_time');
            $this->db->from('udl_etl_logs');
            if (!empty($filters['extraction_date'])) {
                $this->db->where('extraction_date', $filters['extraction_date']);
            }
            if (!empty($filters['date_from'])) {
                $this->db->where('extraction_date >=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $this->db->where('extraction_date <=', $filters['date_to']);
            }
            $this->db->group_by('concurrency');
            $this->db->order_by('concurrency', 'ASC');
            $concurrency_distribution = $this->db->get()->result_array();
            
            // Get total statistics
            $this->db->select('
                SUM(total_extracted) as total_extracted,
                SUM(total_inserted) as total_inserted,
                SUM(total_updated) as total_updated,
                SUM(total_errors) as total_errors,
                AVG(execution_time) as avg_execution_time,
                MAX(execution_time) as max_execution_time,
                MIN(execution_time) as min_execution_time
            ');
            $this->db->from('udl_etl_logs');
            if (!empty($filters['extraction_date'])) {
                $this->db->where('extraction_date', $filters['extraction_date']);
            }
            if (!empty($filters['date_from'])) {
                $this->db->where('extraction_date >=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $this->db->where('extraction_date <=', $filters['date_to']);
            }
            $total_stats = $this->db->get()->row_array();
            
            // Get latest execution
            $this->db->select('*');
            $this->db->from('udl_etl_logs');
            if (!empty($filters['extraction_date'])) {
                $this->db->where('extraction_date', $filters['extraction_date']);
            }
            if (!empty($filters['date_from'])) {
                $this->db->where('extraction_date >=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $this->db->where('extraction_date <=', $filters['date_to']);
            }
            $this->db->order_by('created_at', 'DESC');
            $this->db->limit(1);
            $latest_execution = $this->db->get()->row_array();
            
            return [
                'success' => true,
                'statistics' => [
                    'total_executions' => $total_executions,
                    'status_distribution' => $status_distribution,
                    'concurrency_distribution' => $concurrency_distribution,
                    'total_stats' => $total_stats,
                    'latest_execution' => $latest_execution
                ],
                'filters' => $filters
            ];
            
        } catch (Exception $e) {
            log_message('error', "UDL ETL LOGS STATISTICS FAILED: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'statistics' => []
            ];
        }
    }

    /**
     * Get running ETL processes
     * @return array Running processes
     */
    public function get_running_processes() {
        try {
            $running_logs = $this->db->get_where('udl_etl_logs', ['status' => 'running'])->result_array();
            
            return [
                'success' => true,
                'data' => $running_logs,
                'count' => count($running_logs)
            ];
            
        } catch (Exception $e) {
            log_message('error', "UDL ETL RUNNING PROCESSES FETCH FAILED: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'count' => 0
            ];
        }
    }

    /**
     * Clean old logs (older than specified days)
     * @param int $days_to_keep Number of days to keep logs
     * @return array Result of cleanup
     */
    public function clean_old_logs($days_to_keep = 30) {
        try {
            $cutoff_date = date('Y-m-d', strtotime("-{$days_to_keep} days"));
            
            $this->db->where('created_at <', $cutoff_date . ' 00:00:00');
            $deleted_count = $this->db->delete('udl_etl_logs');
            
            log_message('info', "UDL ETL LOGS CLEANUP: Deleted {$deleted_count} logs older than {$days_to_keep} days");
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'cutoff_date' => $cutoff_date,
                'message' => "Cleaned up {$deleted_count} old log entries"
            ];
            
        } catch (Exception $e) {
            log_message('error', "UDL ETL LOGS CLEANUP FAILED: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
