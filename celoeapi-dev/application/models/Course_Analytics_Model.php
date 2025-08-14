<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Course_Analytics_Model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get courses with filtering and pagination for GET /api/courses
     */
    public function get_courses_with_filters($filters = [], $pagination = [])
    {
        // Build WHERE conditions
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(cs.course_name LIKE ? OR cs.kelas LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['dosen_pengampu'])) {
            $where_conditions[] = "cs.dosen_pengampu LIKE ?";
            $params[] = '%' . $filters['dosen_pengampu'] . '%';
        }

        // Base query - mengakses celoeapi database langsung
        $base_query = "SELECT DISTINCT cs.course_id, cs.course_name, cs.kelas, 
                       cs.jumlah_aktivitas, cs.jumlah_mahasiswa, cs.dosen_pengampu 
                       FROM celoeapi.course_summary cs";

        if (!empty($filters['activity_type'])) {
            $base_query .= " JOIN celoeapi.course_activity_summary cas ON cas.course_id = cs.course_id";
            $where_conditions[] = "cas.activity_type = ?";
            $params[] = $filters['activity_type'];
        }

        // Add WHERE clause
        if (!empty($where_conditions)) {
            $base_query .= " WHERE " . implode(" AND ", $where_conditions);
        }

        // Count total records
        $count_query = "SELECT COUNT(DISTINCT cs.course_id) as total FROM celoeapi.course_summary cs";
        if (!empty($filters['activity_type'])) {
            $count_query .= " JOIN celoeapi.course_activity_summary cas ON cas.course_id = cs.course_id";
        }
        if (!empty($where_conditions)) {
            $count_query .= " WHERE " . implode(" AND ", $where_conditions);
        }

        $count_result = $this->db->query($count_query, $params);
        $total_count = $count_result->row()->total;

        // Apply sorting
        $sort_by = isset($filters['sort_by']) ? $filters['sort_by'] : 'course_name';
        $sort_order = isset($filters['sort_order']) ? $filters['sort_order'] : 'asc';
        
        $valid_sort_fields = ['course_name', 'jumlah_mahasiswa', 'jumlah_aktivitas', 'keaktifan'];
        if (in_array($sort_by, $valid_sort_fields)) {
            if ($sort_by === 'keaktifan') {
                // Sort by activity level: (jumlah_aktivitas / jumlah_mahasiswa) * 100 for percentage
                // Higher percentage means more active students per capita
                $base_query .= " ORDER BY (cs.jumlah_aktivitas / NULLIF(cs.jumlah_mahasiswa, 0)) * 100 " . strtoupper($sort_order);
            } else {
                $base_query .= " ORDER BY cs." . $sort_by . " " . strtoupper($sort_order);
            }
        }

        // Apply pagination
        $limit = isset($pagination['limit']) ? $pagination['limit'] : 10;
        $offset = isset($pagination['offset']) ? $pagination['offset'] : 0;
        $base_query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $result = $this->db->query($base_query, $params);
        $courses = $result->result();

        return [
            'data' => $courses,
            'total_count' => (int)$total_count
        ];
    }

    /**
     * Get activities for a specific course with pagination
     */
    public function get_course_activities($course_id, $filters = [], $pagination = [])
    {
        // Get course info first
        $course_query = "SELECT course_id, course_name, kelas FROM celoeapi.course_summary WHERE course_id = ?";
        $course_result = $this->db->query($course_query, [$course_id]);
        $course_info = $course_result->row();

        if (!$course_info) {
            return null;
        }

        // Build WHERE conditions for activities
        $where_conditions = ["cas.course_id = ?"];
        $params = [$course_id];

        if (!empty($filters['activity_type'])) {
            $where_conditions[] = "cas.activity_type = ?";
            $params[] = $filters['activity_type'];
        }

        if (!empty($filters['activity_id'])) {
            $where_conditions[] = "cas.activity_id = ?";
            $params[] = $filters['activity_id'];
        }

        if (!empty($filters['section'])) {
            $where_conditions[] = "cas.section = ?";
            $params[] = $filters['section'];
        }

        // Count total records
        $count_query = "SELECT COUNT(*) as total FROM celoeapi.course_activity_summary cas WHERE " . implode(" AND ", $where_conditions);
        $count_result = $this->db->query($count_query, $params);
        $total_count = $count_result->row()->total;

        // Get activities with pagination
        $activities_query = "SELECT cas.id, cas.course_id, cas.section, cas.activity_id, 
                            cas.activity_type, cas.activity_name, cas.accessed_count, 
                            cas.submission_count, cas.graded_count, cas.attempted_count, 
                            cas.created_at 
                            FROM celoeapi.course_activity_summary cas 
                            WHERE " . implode(" AND ", $where_conditions) . "
                            ORDER BY cas.section ASC, cas.activity_name ASC 
                            LIMIT ? OFFSET ?";

        $limit = isset($pagination['limit']) ? $pagination['limit'] : 20;
        $offset = isset($pagination['offset']) ? $pagination['offset'] : 0;
        $params[] = $limit;
        $params[] = $offset;

        $activities_result = $this->db->query($activities_query, $params);
        $activities = $activities_result->result();

        return [
            'data' => $activities,
            'total_count' => (int)$total_count,
            'course_info' => $course_info
        ];
    }

    /**
     * Get students participating in a specific activity
     */
    public function get_activity_students($activity_id, $activity_type, $filters = [], $pagination = [])
    {
        $statistics = [
            'total_participants' => 0,
            'completion_rate' => 0
        ];

        switch ($activity_type) {
            case 'quiz':
                $result = $this->_get_quiz_students($activity_id, $filters, $pagination);
                break;
            case 'assign':
                $result = $this->_get_assignment_students($activity_id, $filters, $pagination);
                break;
            case 'resource':
                $result = $this->_get_resource_students($activity_id, $filters, $pagination);
                break;
            default:
                $result = ['data' => [], 'total_count' => 0, 'statistics' => $statistics];
        }

        return [
            'data' => $result['data'],
            'total_count' => $result['total_count'],
            'statistics' => $result['statistics']
        ];
    }

    /**
     * Get quiz students data
     */
    private function _get_quiz_students($quiz_id, $filters, $pagination)
    {
        // Build WHERE conditions
        $where_conditions = ["sqd.quiz_id = ?"];
        $params = [$quiz_id];

        if (!empty($filters['search'])) {
            $where_conditions[] = "(sqd.full_name LIKE ? OR sqd.nim LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['program_studi'])) {
            $where_conditions[] = "sp.program_studi LIKE ?";
            $params[] = '%' . $filters['program_studi'] . '%';
        }

        // Count total records
        $count_query = "SELECT COUNT(*) as total FROM celoeapi.student_quiz_detail sqd 
                       LEFT JOIN celoeapi.student_profile sp ON sp.user_id = sqd.user_id 
                       WHERE " . implode(" AND ", $where_conditions);
        $count_result = $this->db->query($count_query, $params);
        $total_count = $count_result->row()->total;

        // Build main query
        $sort_by = isset($filters['sort_by']) ? $filters['sort_by'] : 'full_name';
        $sort_order = isset($filters['sort_order']) ? $filters['sort_order'] : 'asc';
        $valid_sort_fields = ['full_name', 'nim', 'nilai', 'waktu_mulai'];
        
        if (!in_array($sort_by, $valid_sort_fields)) {
            $sort_by = 'full_name';
        }
        
        if ($sort_by === 'waktu_aktivitas') {
            $sort_by = 'waktu_mulai';
        }

        $main_query = "SELECT sqd.id, sqd.user_id, sqd.nim, sqd.full_name, sp.program_studi,
                      sqd.waktu_mulai, sqd.waktu_selesai, sqd.durasi_waktu as durasi_pengerjaan, sqd.jumlah_soal,
                      sqd.jumlah_dikerjakan, sqd.nilai, sqd.waktu_mulai as waktu_aktivitas
                      FROM celoeapi.student_quiz_detail sqd 
                      LEFT JOIN celoeapi.student_profile sp ON sp.user_id = sqd.user_id 
                      WHERE " . implode(" AND ", $where_conditions) . "
                      ORDER BY sqd." . $sort_by . " " . strtoupper($sort_order) . "
                      LIMIT ? OFFSET ?";

        $limit = isset($pagination['limit']) ? $pagination['limit'] : 10;
        $offset = isset($pagination['offset']) ? $pagination['offset'] : 0;
        $params[] = $limit;
        $params[] = $offset;

        $students_result = $this->db->query($main_query, $params);
        $students = $students_result->result();

        // Calculate statistics
        $statistics = $this->_calculate_quiz_statistics($quiz_id);

        return [
            'data' => $students,
            'total_count' => (int)$total_count,
            'statistics' => $statistics
        ];
    }

    /**
     * Get assignment students data
     */
    private function _get_assignment_students($assignment_id, $filters, $pagination)
    {
        // Build WHERE conditions
        $where_conditions = ["sad.assignment_id = ?"];
        $params = [$assignment_id];

        if (!empty($filters['search'])) {
            $where_conditions[] = "(sad.full_name LIKE ? OR sad.nim LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['program_studi'])) {
            $where_conditions[] = "sp.program_studi LIKE ?";
            $params[] = '%' . $filters['program_studi'] . '%';
        }

        // Count total records
        $count_query = "SELECT COUNT(*) as total FROM celoeapi.student_assignment_detail sad 
                       LEFT JOIN celoeapi.student_profile sp ON sp.user_id = sad.user_id 
                       WHERE " . implode(" AND ", $where_conditions);
        $count_result = $this->db->query($count_query, $params);
        $total_count = $count_result->row()->total;

        // Build main query
        $sort_by = isset($filters['sort_by']) ? $filters['sort_by'] : 'full_name';
        if ($sort_by === 'waktu_aktivitas') {
            $sort_by = 'waktu_submit';
        }
        $sort_order = isset($filters['sort_order']) ? $filters['sort_order'] : 'asc';

        $main_query = "SELECT sad.id, sad.user_id, sad.nim, sad.full_name, sp.program_studi,
                      sad.waktu_submit, sad.waktu_pengerjaan as durasi_pengerjaan, sad.nilai, 
                      sad.waktu_submit as waktu_aktivitas
                      FROM celoeapi.student_assignment_detail sad 
                      LEFT JOIN celoeapi.student_profile sp ON sp.user_id = sad.user_id 
                      WHERE " . implode(" AND ", $where_conditions) . "
                      ORDER BY sad." . $sort_by . " " . strtoupper($sort_order) . "
                      LIMIT ? OFFSET ?";

        $limit = isset($pagination['limit']) ? $pagination['limit'] : 10;
        $offset = isset($pagination['offset']) ? $pagination['offset'] : 0;
        $params[] = $limit;
        $params[] = $offset;

        $students_result = $this->db->query($main_query, $params);
        $students = $students_result->result();

        // Calculate statistics
        $statistics = $this->_calculate_assignment_statistics($assignment_id);

        return [
            'data' => $students,
            'total_count' => (int)$total_count,
            'statistics' => $statistics
        ];
    }

    /**
     * Get resource access students data
     */
    private function _get_resource_students($resource_id, $filters, $pagination)
    {
        // Build WHERE conditions
        $where_conditions = ["sra.resource_id = ?"];
        $params = [$resource_id];

        if (!empty($filters['search'])) {
            $where_conditions[] = "(sra.full_name LIKE ? OR sra.nim LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['program_studi'])) {
            $where_conditions[] = "sp.program_studi LIKE ?";
            $params[] = '%' . $filters['program_studi'] . '%';
        }

        // Count total records
        $count_query = "SELECT COUNT(*) as total FROM celoeapi.student_resource_access sra 
                       LEFT JOIN celoeapi.student_profile sp ON sp.user_id = sra.user_id 
                       WHERE " . implode(" AND ", $where_conditions);
        $count_result = $this->db->query($count_query, $params);
        $total_count = $count_result->row()->total;

        // Build main query
        $sort_by = isset($filters['sort_by']) ? $filters['sort_by'] : 'full_name';
        if ($sort_by === 'waktu_aktivitas') {
            $sort_by = 'waktu_akses';
        } elseif ($sort_by === 'nilai') {
            // Resources don't have scores, fallback to full_name
            $sort_by = 'full_name';
        }
        $sort_order = isset($filters['sort_order']) ? $filters['sort_order'] : 'asc';

        $main_query = "SELECT sra.id, sra.user_id, sra.nim, sra.full_name, sp.program_studi,
                      sra.waktu_akses, sra.waktu_akses as waktu_aktivitas
                      FROM celoeapi.student_resource_access sra 
                      LEFT JOIN celoeapi.student_profile sp ON sp.user_id = sra.user_id 
                      WHERE " . implode(" AND ", $where_conditions) . "
                      ORDER BY sra." . $sort_by . " " . strtoupper($sort_order) . "
                      LIMIT ? OFFSET ?";

        $limit = isset($pagination['limit']) ? $pagination['limit'] : 10;
        $offset = isset($pagination['offset']) ? $pagination['offset'] : 0;
        $params[] = $limit;
        $params[] = $offset;

        $students_result = $this->db->query($main_query, $params);
        $students = $students_result->result();

        // Calculate statistics
        $statistics = $this->_calculate_resource_statistics($resource_id);

        return [
            'data' => $students,
            'total_count' => (int)$total_count,
            'statistics' => $statistics
        ];
    }



    /**
     * Calculate quiz statistics
     */
    private function _calculate_quiz_statistics($quiz_id)
    {
        $stats_query = "SELECT COUNT(*) as total_participants,
                              AVG(nilai) as average_score,
                              COUNT(CASE WHEN nilai IS NOT NULL THEN 1 END) as completed_count
                       FROM celoeapi.student_quiz_detail 
                       WHERE quiz_id = ?";
        
        $stats_result = $this->db->query($stats_query, [$quiz_id]);
        $stats = $stats_result->row();

        $completion_rate = $stats->total_participants > 0 
            ? ($stats->completed_count / $stats->total_participants) * 100 
            : 0;

        return [
            'total_participants' => (int)$stats->total_participants,
            'average_score' => $stats->average_score ? round($stats->average_score, 2) : null,
            'completion_rate' => round($completion_rate, 2)
        ];
    }

    /**
     * Calculate assignment statistics
     */
    private function _calculate_assignment_statistics($assignment_id)
    {
        $stats_query = "SELECT COUNT(*) as total_participants,
                              AVG(nilai) as average_score,
                              COUNT(CASE WHEN nilai IS NOT NULL THEN 1 END) as completed_count
                       FROM celoeapi.student_assignment_detail 
                       WHERE assignment_id = ?";
        
        $stats_result = $this->db->query($stats_query, [$assignment_id]);
        $stats = $stats_result->row();

        $completion_rate = $stats->total_participants > 0 
            ? ($stats->completed_count / $stats->total_participants) * 100 
            : 0;

        return [
            'total_participants' => (int)$stats->total_participants,
            'average_score' => $stats->average_score ? round($stats->average_score, 2) : null,
            'completion_rate' => round($completion_rate, 2)
        ];
    }

    /**
     * Calculate resource statistics
     */
    private function _calculate_resource_statistics($resource_id)
    {
        $stats_query = "SELECT COUNT(DISTINCT user_id) as total_participants 
                       FROM celoeapi.student_resource_access 
                       WHERE resource_id = ?";
        
        $stats_result = $this->db->query($stats_query, [$resource_id]);
        $stats = $stats_result->row();

        return [
            'total_participants' => (int)$stats->total_participants,
            'completion_rate' => 100 // Resources are considered "completed" when accessed
        ];
    }
}