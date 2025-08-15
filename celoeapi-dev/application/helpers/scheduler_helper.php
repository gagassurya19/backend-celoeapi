<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Scheduler Helper Functions
 * Provides standardized logging functions for different ETL processes
 */

if (!function_exists('create_scheduler_log')) {
    /**
     * Create a new scheduler log entry with category
     * 
     * @param string $category Category of the log (etl_user_activity, etl_course_performance, api_call, system_task, etc.)
     * @param int $offset Starting offset for batch processing
     * @param int $numrow Number of rows to process
     * @param int $status Status (0=not_running, 1=finished, 2=inprogress, 3=failed)
     * @param string $start_date Start date in Y-m-d H:i:s format
     * @param string $end_date End date in Y-m-d H:i:s format (can be null)
     * @param array $additional_data Additional data to store
     * @return int|false Log ID on success, false on failure
     */
    function create_scheduler_log($category, $offset = 0, $numrow = 0, $status = 2, $start_date = null, $end_date = null, $additional_data = [])
    {
        $CI =& get_instance();
        
        $start_date = $start_date ?: date('Y-m-d H:i:s');
        
        $data = array_merge([
            'offset' => $offset,
            'numrow' => $numrow,
            'status' => $status,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'category' => $category,
            'created_at' => date('Y-m-d H:i:s')
        ], $additional_data);
        
        $result = $CI->db->insert('shared_log_scheduler', $data);
        
        if ($result) {
            $log_id = $CI->db->insert_id();
            log_message('info', "Scheduler log created for category '{$category}' with ID: {$log_id}");
            return $log_id;
        } else {
            log_message('error', "Failed to create scheduler log for category '{$category}'");
            return false;
        }
    }
}

if (!function_exists('update_scheduler_log')) {
    /**
     * Update an existing scheduler log entry
     * 
     * @param int $log_id Log ID to update
     * @param array $data Data to update
     * @return bool Success status
     */
    function update_scheduler_log($log_id, $data)
    {
        $CI =& get_instance();
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $CI->db->where('id', $log_id);
        $result = $CI->db->update('shared_log_scheduler', $data);
        
        if ($result) {
            log_message('info', "Scheduler log updated for ID: {$log_id}");
            return true;
        } else {
            log_message('error', "Failed to update scheduler log for ID: {$log_id}");
            return false;
        }
    }
}

if (!function_exists('complete_scheduler_log')) {
    /**
     * Mark a scheduler log as completed
     * 
     * @param int $log_id Log ID to complete
     * @param int $total_records Total records processed
     * @return bool Success status
     */
    function complete_scheduler_log($log_id, $total_records)
    {
        $data = [
            'status' => 1, // Finished
            'numrow' => $total_records,
            'end_date' => date('Y-m-d H:i:s')
        ];
        
        return update_scheduler_log($log_id, $data);
    }
}

if (!function_exists('fail_scheduler_log')) {
    /**
     * Mark a scheduler log as failed
     * 
     * @param int $log_id Log ID to mark as failed
     * @param string $error_message Error message
     * @return bool Success status
     */
    function fail_scheduler_log($log_id, $error_message)
    {
        $data = [
            'status' => 3, // Failed
            'end_date' => date('Y-m-d H:i:s'),
            'info' => $error_message
        ];
        
        return update_scheduler_log($log_id, $data);
    }
}

if (!function_exists('get_scheduler_logs_by_category')) {
    /**
     * Get scheduler logs filtered by category
     * 
     * @param string $category Category to filter by
     * @param int $limit Number of records to return
     * @param int $offset Starting offset
     * @return array Array of log records
     */
    function get_scheduler_logs_by_category($category, $limit = 100, $offset = 0)
    {
        $CI =& get_instance();
        
        $CI->db->select('*');
        $CI->db->from('shared_log_scheduler');
        $CI->db->where('category', $category);
        $CI->db->order_by('id', 'DESC');
        $CI->db->limit($limit, $offset);
        
        $query = $CI->db->get();
        return $query->result_array();
    }
}

if (!function_exists('get_scheduler_status_by_category')) {
    /**
     * Get current status of scheduler by category
     * 
     * @param string $category Category to check
     * @return array Status information
     */
    function get_scheduler_status_by_category($category)
    {
        $CI =& get_instance();
        
        // Get latest log for this category
        $CI->db->select('*');
        $CI->db->from('shared_log_scheduler');
        $CI->db->where('category', $category);
        $CI->db->order_by('id', 'DESC');
        $CI->db->limit(1);
        
        $query = $CI->db->get();
        $latest_log = $query->row_array();
        
        // Get count of running processes
        $CI->db->select('COUNT(*) as running_count');
        $CI->db->from('shared_log_scheduler');
        $CI->db->where('category', $category);
        $CI->db->where('status', 2); // In progress
        
        $running_query = $CI->db->get();
        $running_count = $running_query->row()->running_count;
        
        return [
            'latest_log' => $latest_log,
            'running_count' => $running_count,
            'is_running' => $running_count > 0,
            'last_status' => $latest_log ? $latest_log['status'] : null,
            'last_run' => $latest_log ? $latest_log['start_date'] : null
        ];
    }
}
