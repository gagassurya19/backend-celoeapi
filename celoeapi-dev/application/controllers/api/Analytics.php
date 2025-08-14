<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

/**
 * Analytics API Controller
 * 
 * @property Course_Analytics_Model $m_analytics
 */
class Analytics extends REST_Controller {

    function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Course_Analytics_Model', 'm_analytics');
    }

    /**
     * GET /api/analytics/courses
     * Mengambil daftar semua mata kuliah dengan filtering dan pagination
     */
    public function courses_get() 
    {      
        try {
            // Get query parameters
            $page = (int)($this->get('page') ?: 1);
            $limit = (int)($this->get('limit') ?: 10);
            $search = $this->get('search');
            $dosen_pengampu = $this->get('dosen_pengampu');
            $activity_type = $this->get('activity_type');
            $sort_by = $this->get('sort_by') ?: 'course_name';
            $sort_order = $this->get('sort_order') ?: 'asc';

            // Validate parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            $valid_sort_fields = ['course_name', 'jumlah_mahasiswa', 'jumlah_aktivitas', 'keaktifan'];
            if (!in_array($sort_by, $valid_sort_fields)) {
                $sort_by = 'course_name';
            }
            
            if (!in_array($sort_order, ['asc', 'desc'])) {
                $sort_order = 'asc';
            }

            // Prepare filters
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($dosen_pengampu) $filters['dosen_pengampu'] = $dosen_pengampu;
            if ($activity_type) $filters['activity_type'] = $activity_type;
            $filters['sort_by'] = $sort_by;
            $filters['sort_order'] = $sort_order;

            // Prepare pagination
            $offset = ($page - 1) * $limit;
            $pagination = [
                'limit' => $limit,
                'offset' => $offset
            ];

            // Get data from model
            $result = $this->m_analytics->get_courses_with_filters($filters, $pagination);
            
            if ($result === null) {
                $this->response([
                    'status' => false,
                    'message' => 'No courses found'
                ], REST_Controller::HTTP_NOT_FOUND);
                return;
            }

            // Calculate pagination info
            $total_pages = ceil($result['total_count'] / $limit);
            
            // Prepare filters_applied for response
            $filters_applied = [];
            if ($search) $filters_applied['search'] = $search;
            if ($dosen_pengampu) $filters_applied['dosen_pengampu'] = $dosen_pengampu;
            if ($activity_type) $filters_applied['activity_type'] = $activity_type;

            $this->response([
                'status' => true,
                'data' => $result['data'],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_items' => $result['total_count'],
                    'items_per_page' => $limit
                ],
                'filters_applied' => $filters_applied
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Get courses failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to get courses',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/analytics/courses/{course_id}/activities
     * Mengambil semua aktivitas dalam mata kuliah tertentu
     */
    public function course_activities_get($course_id = null) 
    {      
        try {
            if (!$course_id) {
                $this->response([
                    'status' => false,
                    'message' => 'Course ID is required'
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }

            // Get query parameters
            $activity_type = $this->get('activity_type');
            $activity_id = $this->get('activity_id');
            $section = $this->get('section');
            $page = (int)($this->get('page') ?: 1);
            $limit = (int)($this->get('limit') ?: 20);

            // Validate parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 20;

            // Prepare filters
            $filters = [];
            if ($activity_type) {
                $valid_activity_types = ['resource', 'assign', 'quiz'];
                if (in_array($activity_type, $valid_activity_types)) {
                    $filters['activity_type'] = $activity_type;
                }
            }
            if ($activity_id) {
                $filters['activity_id'] = $activity_id;
            }
            if ($section && is_numeric($section)) {
                $filters['section'] = (int)$section;
            }

            // Prepare pagination
            $offset = ($page - 1) * $limit;
            $pagination = [
                'limit' => $limit,
                'offset' => $offset
            ];

            // Get data from model
            $result = $this->m_analytics->get_course_activities($course_id, $filters, $pagination);
            
            if ($result === null) {
                $this->response([
                    'status' => false,
                    'message' => 'Course not found'
                ], REST_Controller::HTTP_NOT_FOUND);
                return;
            }

            // Calculate pagination info
            $total_pages = ceil($result['total_count'] / $limit);

            $this->response([
                'status' => true,
                'data' => $result['data'],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_items' => $result['total_count'],
                    'items_per_page' => $limit
                ],
                'course_info' => [
                    'course_id' => (int)$result['course_info']->course_id,
                    'course_name' => $result['course_info']->course_name,
                    'kelas' => $result['course_info']->kelas
                ]
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Get course activities failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to get course activities',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/analytics/activities/{activity_id}/{activity_type}/students
     * Mengambil data mahasiswa yang berpartisipasi dalam aktivitas tertentu
     * Parameter activity_type (optional): quiz, assign, resource - informational only
     */
    public function activity_students_get($activity_id = null, $activity_type = null) 
    {      
        try {
            if (!$activity_id) {
                $this->response([
                    'status' => false,
                    'message' => 'Activity ID is required'
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }

            if (!$activity_type) {
                $this->response([
                    'status' => false,
                    'message' => 'Activity type is required'
                ], REST_Controller::HTTP_BAD_REQUEST);
                return;
            }

            // Get query parameters
            $page = (int)($this->get('page') ?: 1);
            $limit = (int)($this->get('limit') ?: 10);
            $search = $this->get('search');
            $program_studi = $this->get('program_studi');
            $sort_by = $this->get('sort_by') ?: 'full_name';
            $sort_order = $this->get('sort_order') ?: 'asc';

            // Validate parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            // Different activity types have different valid sort fields
            if ($activity_type === 'resource') {
                $valid_sort_fields = ['full_name', 'nim', 'waktu_aktivitas'];
            } else {
                $valid_sort_fields = ['full_name', 'nim', 'nilai', 'waktu_aktivitas'];
            }
            
            if (!in_array($sort_by, $valid_sort_fields)) {
                $sort_by = 'full_name';
            }
            
            if (!in_array($sort_order, ['asc', 'desc'])) {
                $sort_order = 'asc';
            }

            // Prepare filters
            $filters = [];
            if ($search) $filters['search'] = $search;
            if ($program_studi) $filters['program_studi'] = $program_studi;
            $filters['sort_by'] = $sort_by;
            $filters['sort_order'] = $sort_order;

            // Prepare pagination
            $offset = ($page - 1) * $limit;
            $pagination = [
                'limit' => $limit,
                'offset' => $offset
            ];

            // Get data from model
            $result = $this->m_analytics->get_activity_students($activity_id, $activity_type, $filters, $pagination);
            
            if ($result === null) {
                $this->response([
                    'status' => false,
                    'message' => 'Activity not found'
                ], REST_Controller::HTTP_NOT_FOUND);
                return;
            }

            // Calculate pagination info
            $total_pages = ceil($result['total_count'] / $limit);

            $this->response([
                'status' => true,
                'data' => $result['data'],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_items' => $result['total_count'],
                    'items_per_page' => $limit
                ],
                'activity_info' => [
                    'activity_id' => (int)$activity_id,
                    'activity_type' => $activity_type
                ],
                'statistics' => $result['statistics']
            ], REST_Controller::HTTP_OK);
            
        } catch (Exception $e) {
            log_message('error', 'Get activity students failed: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Failed to get activity students',
                'error' => $e->getMessage()
            ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * GET /api/analytics/health
     * Health check endpoint
     */
    public function health_get() 
    {      
        $this->response([
            'status' => true,
            'message' => 'Analytics API is running',
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoints' => [
                'GET /api/analytics/courses' => 'Get courses with filtering and pagination',
                'GET /api/analytics/courses/{course_id}/activities' => 'Get activities for a specific course',
                'GET /api/analytics/activities/{activity_id}/students' => 'Get students for a specific activity'
            ]
        ], REST_Controller::HTTP_OK);
    }
}