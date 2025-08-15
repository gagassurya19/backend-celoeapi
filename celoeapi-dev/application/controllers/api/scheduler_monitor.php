<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Scheduler_monitor extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('scheduler_helper');
    }

    /**
     * Get scheduler status by category
     * GET /api/scheduler_monitor/status/{category}
     */
    public function status($category = null)
    {
        if (!$category) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'error' => 'Category parameter is required',
                    'available_categories' => ['etl_user_activity', 'etl_course_performance', 'etl_general', 'api_call', 'system_task']
                ]));
            return;
        }

        try {
            $status = get_scheduler_status_by_category($category);
            
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'category' => $category,
                    'status' => $status
                ]));
                
        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'error' => 'Failed to get scheduler status',
                    'message' => $e->getMessage()
                ]));
        }
    }

    /**
     * Get scheduler logs by category with pagination
     * GET /api/scheduler_monitor/logs/{category}?limit=10&offset=0
     */
    public function logs($category = null)
    {
        if (!$category) {
            $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'error' => 'Category parameter is required'
                ]));
            return;
        }

        $limit = $this->input->get('limit') ?: 100;
        $offset = $this->input->get('offset') ?: 0;

        try {
            $logs = get_scheduler_logs_by_category($category, $limit, $offset);
            
            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'category' => $category,
                    'logs' => $logs,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'total' => count($logs)
                    ]
                ]));
                
        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'error' => 'Failed to get scheduler logs',
                    'message' => $e->getMessage()
                ]));
        }
    }

    /**
     * Get summary of all scheduler categories
     * GET /api/scheduler_monitor/summary
     */
    public function summary()
    {
        try {
            $categories = ['etl_user_activity', 'etl_course_performance', 'etl_general', 'api_call', 'system_task'];
            $summary = [];

            foreach ($categories as $category) {
                $status = get_scheduler_status_by_category($category);
                $summary[$category] = $status;
            }

            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'summary' => $summary,
                    'total_categories' => count($categories)
                ]));

        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'error' => 'Failed to get scheduler summary',
                    'message' => $e->getMessage()
                ]));
        }
    }

    /**
     * Get running processes count by category
     * GET /api/scheduler_monitor/running
     */
    public function running()
    {
        try {
            $categories = ['etl_user_activity', 'etl_course_performance', 'etl_general', 'api_call', 'system_task'];
            $running = [];

            foreach ($categories as $category) {
                $status = get_scheduler_status_by_category($category);
                $running[$category] = [
                    'is_running' => $status['is_running'],
                    'running_count' => $status['running_count']
                ];
            }

            $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'running_processes' => $running,
                    'total_running' => array_sum(array_column($running, 'running_count'))
                ]));

        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'error' => 'Failed to get running processes',
                    'message' => $e->getMessage()
                ]));
        }
    }
}
