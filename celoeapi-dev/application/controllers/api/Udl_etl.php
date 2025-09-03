<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Udl_etl extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('udl_etl_model');
        $this->load->model('udl_etl_logs_model');
        $this->load->helper('url');
        
        // Set JSON response header
        header('Content-Type: application/json');
    }

    /**
     * Run UDL ETL process
     * POST /api/udl_etl/run
     */
    public function run() {
        // Check if request method is POST
        if ($this->input->method() !== 'post') {
            $this->output
                ->set_status_header(405)
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Method not allowed. Use POST method.',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            return;
        }

        try {
            // Get extraction date from request body or use current date
            $extraction_date = $this->input->post('extraction_date') ?: date('Y-m-d');
            
            // Get concurrency parameter (default: 1, max: 10)
            $concurrency = (int)($this->input->post('concurrency') ?: 1);
            if ($concurrency < 1) $concurrency = 1;
            if ($concurrency > 10) $concurrency = 10;
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $extraction_date)) {
                $this->output
                    ->set_status_header(400)
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => 'Invalid date format. Use YYYY-MM-DD format.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
                return;
            }

            // Run ETL process with concurrency
            $result = $this->udl_etl_model->run($extraction_date, $concurrency);
            
            if ($result['success']) {
                $this->output
                    ->set_status_header(200)
                    ->set_output(json_encode([
                        'success' => true,
                        'message' => $result['message'],
                        'data' => [
                            'extraction_date' => $result['extraction_date'],
                            'concurrency' => $concurrency,
                            'total_extracted' => $result['total_extracted'],
                            'inserted_count' => $result['inserted_count'],
                            'updated_count' => $result['updated_count'],
                            'error_count' => $result['error_count'],
                            'execution_time' => $result['execution_time']
                        ],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
            } else {
                $this->output
                    ->set_status_header(500)
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => $result['error'],
                        'extraction_date' => $result['extraction_date'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
            }
            
        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Internal server error: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
        }
    }

    /**
     * Get UDL ETL data with pagination
     * GET /api/udl_etl/data
     */
    public function data() {
        try {
            // Get query parameters
            $page = (int)($this->input->get('page') ?: 1);
            $limit = (int)($this->input->get('limit') ?: 10);
            $search = $this->input->get('search') ?: '';
            $extraction_date = $this->input->get('extraction_date') ?: '';
            $activity_hour = $this->input->get('activity_hour') ?: '';
            $role_name = $this->input->get('role_name') ?: '';
            $username = $this->input->get('username') ?: '';

            // Validate parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;

            // Build filters
            $filters = [];
            if ($extraction_date) $filters['extraction_date'] = $extraction_date;
            if ($activity_hour !== '') $filters['activity_hour'] = $activity_hour;
            if ($role_name) $filters['role_name'] = $role_name;
            if ($username) $filters['username'] = $username;

            // Get data from database
            $this->db->select('*');
            $this->db->from('udl_etl');
            
            // Apply filters
            if ($extraction_date) {
                $this->db->where('extraction_date', $extraction_date);
            }
            if ($activity_hour !== '') {
                $this->db->where('activity_hour', $activity_hour);
            }
            if ($role_name) {
                $this->db->like('role_name', $role_name);
            }
            if ($username) {
                $this->db->like('username', $username);
            }
            if ($search) {
                $this->db->group_start();
                $this->db->like('username', $search);
                $this->db->or_like('firstname', $search);
                $this->db->or_like('lastname', $search);
                $this->db->or_like('email', $search);
                $this->db->group_end();
            }

            // Get total count
            $total_count = $this->db->count_all_results('', false);
            
            // Get paginated data
            $offset = ($page - 1) * $limit;
            $this->db->limit($limit, $offset);
            $this->db->order_by('created_at', 'DESC');
            $this->db->order_by('user_id', 'ASC');
            
            $data = $this->db->get()->result_array();

            // Process data for JSON response
            foreach ($data as &$record) {
                // Decode JSON fields
                if ($record['all_role_ids']) {
                    $record['all_role_ids'] = json_decode($record['all_role_ids'], true);
                }
                if ($record['all_role_names']) {
                    $record['all_role_names'] = json_decode($record['all_role_names'], true);
                }
                if ($record['all_role_shortnames']) {
                    $record['all_role_shortnames'] = json_decode($record['all_role_shortnames'], true);
                }
                if ($record['all_archetypes']) {
                    $record['all_archetypes'] = json_decode($record['all_archetypes'], true);
                }
                if ($record['all_course_ids']) {
                    $record['all_course_ids'] = json_decode($record['all_course_ids'], true);
                }
            }

            $this->output
                ->set_status_header(200)
                ->set_output(json_encode([
                    'success' => true,
                    'data' => $data,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total_records' => $total_count,
                        'total_pages' => ceil($total_count / $limit),
                        'has_next_page' => $page < ceil($total_count / $limit),
                        'has_prev_page' => $page > 1
                    ],
                    'filters' => [
                        'extraction_date' => $extraction_date,
                        'activity_hour' => $activity_hour,
                        'role_name' => $role_name,
                        'username' => $username,
                        'search' => $search
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]));

        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Internal server error: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
        }
    }

    /**
     * Export UDL ETL data with pagination
     * GET /api/udl_etl/export
     */
    public function export() {
        try {
            // Get query parameters
            $page = (int)($this->input->get('page') ?: 1);
            $limit = (int)($this->input->get('limit') ?: 100);
            $extraction_date = $this->input->get('extraction_date') ?: '';
            $activity_hour = $this->input->get('activity_hour') ?: '';
            $role_name = $this->input->get('role_name') ?: '';
            $username = $this->input->get('username') ?: '';
            $search = $this->input->get('search') ?: '';

            // Validate parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 1000) $limit = 100; // Max 1000 records per page

            // Build filters
            $filters = [];
            if ($extraction_date) $filters['extraction_date'] = $extraction_date;
            if ($activity_hour !== '') $filters['activity_hour'] = $activity_hour;
            if ($role_name) $filters['role_name'] = $role_name;
            if ($username) $filters['username'] = $username;
            if ($search) $filters['search'] = $search;

            // Get data from database with pagination
            $this->db->select('*');
            $this->db->from('udl_etl');
            
            // Apply filters
            if ($extraction_date) {
                $this->db->where('extraction_date', $extraction_date);
            }
            if ($activity_hour !== '') {
                $this->db->where('activity_hour', $activity_hour);
            }
            if ($role_name) {
                $this->db->like('role_name', $role_name);
            }
            if ($username) {
                $this->db->like('username', $username);
            }
            if ($search) {
                $this->db->group_start();
                $this->db->like('username', $search);
                $this->db->or_like('firstname', $search);
                $this->db->or_like('lastname', $search);
                $this->db->or_like('email', $search);
                $this->db->group_end();
            }

            // Get total count
            $total_count = $this->db->count_all_results('', false);
            
            // Get paginated data
            $offset = ($page - 1) * $limit;
            $this->db->limit($limit, $offset);
            $this->db->order_by('created_at', 'DESC');
            $this->db->order_by('user_id', 'ASC');
            
            $data = $this->db->get()->result_array();

            // Process data for JSON response
            foreach ($data as &$record) {
                // Decode JSON fields
                if ($record['all_role_ids']) {
                    $record['all_role_ids'] = json_decode($record['all_role_ids'], true);
                }
                if ($record['all_role_names']) {
                    $record['all_role_names'] = json_decode($record['all_role_names'], true);
                }
                if ($record['all_role_shortnames']) {
                    $record['all_role_shortnames'] = json_decode($record['all_role_shortnames'], true);
                }
                if ($record['all_archetypes']) {
                    $record['all_archetypes'] = json_decode($record['all_archetypes'], true);
                }
                if ($record['all_course_ids']) {
                    $record['all_course_ids'] = json_decode($record['all_course_ids'], true);
                }
            }

            // Calculate pagination info
            $total_pages = ceil($total_count / $limit);
            $has_next_page = $page < $total_pages;
            $has_prev_page = $page > 1;
            $current_page_records = count($data);

            // Get export statistics
            $export_date = date('Y-m-d H:i:s');
            $export_id = uniqid('udl_export_');

            // JSON format only
            $this->output
                ->set_status_header(200)
                ->set_output(json_encode([
                    'success' => true,
                    'export_info' => [
                        'export_id' => $export_id,
                        'export_date' => $export_date,
                        'total_records' => $total_count,
                        'current_page_records' => $current_page_records,
                        'format' => 'json',
                        'filters' => $filters,
                        'is_complete' => !$has_next_page, // Complete when no more pages
                        'file_size' => strlen(json_encode($data)),
                        'download_url' => base_url('api/udl_etl/export') . '?' . http_build_query(array_merge($filters, ['page' => $page, 'limit' => $limit]))
                    ],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total_records' => $total_count,
                        'total_pages' => $total_pages,
                        'has_next_page' => $has_next_page,
                        'has_prev_page' => $has_prev_page,
                        'next_page_url' => $has_next_page ? base_url('api/udl_etl/export') . '?' . http_build_query(array_merge($filters, ['page' => $page + 1, 'limit' => $limit])) : null,
                        'prev_page_url' => $has_prev_page ? base_url('api/udl_etl/export') . '?' . http_build_query(array_merge($filters, ['page' => $page - 1, 'limit' => $limit])) : null,
                        'first_page_url' => base_url('api/udl_etl/export') . '?' . http_build_query(array_merge($filters, ['page' => 1, 'limit' => $limit])),
                        'last_page_url' => base_url('api/udl_etl/export') . '?' . http_build_query(array_merge($filters, ['page' => $total_pages, 'limit' => $limit]))
                    ],
                    'data' => $data,
                    'timestamp' => date('Y-m-d H:i:s')
                ]));

        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Internal server error: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
        }
    }

    /**
     * Get latest ETL log
     * GET /api/udl_etl/latest_log
     */
    public function latest_log() {
        try {
            // Get latest log from database
            $result = $this->udl_etl_logs_model->get_latest_log();
            
            if ($result['success']) {
                $this->output
                    ->set_status_header(200)
                    ->set_output(json_encode([
                        'success' => true,
                        'data' => $result['data'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
            } else {
                $this->output
                    ->set_status_header(404)
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => $result['error'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
            }

        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Internal server error: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
        }
    }

    /**
     * Get ETL logs with pagination and filtering
     * GET /api/udl_etl/logs
     */
    public function logs() {
        try {
            // Get query parameters
            $page = (int)($this->input->get('page') ?: 1);
            $limit = (int)($this->input->get('limit') ?: 10);
            $extraction_date = $this->input->get('extraction_date') ?: '';
            $status = $this->input->get('status') ?: '';
            $concurrency = $this->input->get('concurrency') ?: '';
            $date_from = $this->input->get('date_from') ?: '';
            $date_to = $this->input->get('date_to') ?: '';

            // Validate parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;

            // Build filters
            $filters = [];
            if ($extraction_date) $filters['extraction_date'] = $extraction_date;
            if ($status) $filters['status'] = $status;
            if ($concurrency) $filters['concurrency'] = $concurrency;
            if ($date_from) $filters['date_from'] = $date_from;
            if ($date_to) $filters['date_to'] = $date_to;

            // Get logs from database
            $result = $this->udl_etl_logs_model->get_logs($filters, $page, $limit);
            
            if ($result['success']) {
                $this->output
                    ->set_status_header(200)
                    ->set_output(json_encode([
                        'success' => true,
                        'data' => $result['data'],
                        'pagination' => $result['pagination'],
                        'filters' => $result['filters'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
            } else {
                $this->output
                    ->set_status_header(500)
                    ->set_output(json_encode([
                        'success' => false,
                        'error' => $result['error'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]));
            }

        } catch (Exception $e) {
            $this->output
                ->set_status_header(500)
                ->set_output(json_encode([
                    'success' => false,
                    'error' => 'Internal server error: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
        }
    }
}
