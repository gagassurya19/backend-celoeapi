<?php
    use Restserver\Libraries\REST_Controller;
    defined('BASEPATH') OR exit('No direct script access allowed');

    require APPPATH . 'libraries/REST_Controller.php';
    require APPPATH . 'libraries/Format.php';

    class DataExportCoursePerformance extends REST_Controller {

        private $batch_size = 1000; // Records per page
        private $available_tables = [
            'course_activity_summary',
            'course_summary', 
            'student_assignment_detail',
            'student_profile',
            'student_quiz_detail',
            'student_resource_access'
        ];

        function __construct()
        {
            parent::__construct();
            $this->load->database();
            $this->load->model('DataExportCoursePerformance_Model', 'm_export');
            $this->load->helper('auth');
            $this->load->config('etl');
            
            // Set headers for large data responses
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        }

        /**
         * GET /api/export/bulk
         * Bulk export all tables with concurrent processing
         */
        public function bulk_get()
        {
            try {
                // Validate authentication
                if (!$this->_validate_auth()) {
                    return;
                }

                // Get parameters
                $page = (int)$this->get('page') ?: 1;
                $limit = (int)$this->get('limit') ?: $this->batch_size;
                $tables = $this->get('tables') ? explode(',', $this->get('tables')) : $this->available_tables;

                // Validate tables parameter
                $valid_tables = [];
                foreach ($tables as $table) {
                    if (in_array($table, $this->available_tables)) {
                        $valid_tables[] = $table;
                    }
                }

                if (empty($valid_tables)) {
                    $this->response([
                        'status' => false,
                        'message' => 'No valid tables specified. Available tables: ' . implode(', ', $this->available_tables)
                    ], REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }

                // Validate parameters
                if ($limit > 5000) {
                    $this->response([
                        'status' => false,
                        'message' => 'Maximum limit is 5000 records per request'
                    ], REST_Controller::HTTP_BAD_REQUEST);
                    return;
                }

                $result = $this->m_export->bulk_export($valid_tables, $page, $limit);
                
                $this->response([
                    'status' => true,
                    'data' => $result['data'],
                    'pagination' => $result['pagination'],
                    'meta' => [
                        'tables' => $valid_tables,
                        'available_tables' => $this->available_tables,
                        'exported_at' => date('Y-m-d H:i:s'),
                        'concurrent_processing' => true
                    ]
                ], REST_Controller::HTTP_OK);

            } catch (Exception $e) {
                log_message('error', 'Bulk export failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'Failed to perform bulk export',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        /**
         * GET /api/export/status
         * Get export status and statistics for all tables
         */
        public function status_get()
        {
            try {
                // Validate authentication
                if (!$this->_validate_auth()) {
                    return;
                }

                $status = $this->m_export->get_export_status();
                
                $this->response([
                    'status' => true,
                    'data' => $status,
                    'meta' => [
                        'available_tables' => $this->available_tables,
                        'exported_at' => date('Y-m-d H:i:s')
                    ]
                ], REST_Controller::HTTP_OK);

            } catch (Exception $e) {
                log_message('error', 'Export status failed: ' . $e->getMessage());
                $this->response([
                    'status' => false,
                    'message' => 'Failed to get export status',
                    'error' => $e->getMessage()
                ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        /**
         * Validate authentication
         */
        private function _validate_auth()
        {
            $auth_header = $this->input->get_request_header('Authorization', TRUE);
            if (!$this->_validate_webhook_token($auth_header)) {
                $this->response([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], REST_Controller::HTTP_UNAUTHORIZED);
                return false;
            }
            return true;
        }

        /**
         * Validate webhook token
         */
        private function _validate_webhook_token($auth_header)
        {
            if (!$auth_header) {
                return false;
            }

            // Extract token from Authorization header
            if (strpos($auth_header, 'Bearer ') === 0) {
                $token = substr($auth_header, 7);
            } else {
                $token = $auth_header;
            }

            // Validate against configured webhook tokens from etl config
            $webhook_tokens = $this->config->item('etl_webhook_tokens');
            return in_array($token, $webhook_tokens);
        }
    } 