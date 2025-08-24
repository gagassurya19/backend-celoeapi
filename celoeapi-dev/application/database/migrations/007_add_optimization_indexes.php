<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_optimization_indexes extends CI_Migration {

    public function up() {
        // Add optimization indexes to existing tables
        
        // CP table optimizations
        $this->_create_index_if_not_exists('cp_activity_summary', 'idx_cp_activity_course_type', '(`course_id`, `activity_type`)');
        $this->_create_index_if_not_exists('cp_activity_summary', 'idx_cp_activity_section', '(`course_id`, `section`)');
        $this->_create_index_if_not_exists('cp_activity_summary', 'idx_cp_activity_created', '(`created_at` DESC)');
        
        $this->_create_index_if_not_exists('cp_student_quiz_detail', 'idx_cp_student_quiz_course_user', '(`course_id`, `user_id`)');
        $this->_create_index_if_not_exists('cp_student_quiz_detail', 'idx_cp_student_quiz_waktu_mulai', '(`waktu_mulai` DESC)');
        $this->_create_index_if_not_exists('cp_student_quiz_detail', 'idx_cp_student_quiz_nilai', '(`nilai` DESC)');
        
        $this->_create_index_if_not_exists('cp_student_assignment_detail', 'idx_cp_student_assignment_waktu_submit', '(`waktu_submit` DESC)');
        $this->_create_index_if_not_exists('cp_student_resource_access', 'idx_cp_student_resource_waktu_akses', '(`waktu_akses` DESC)');
        
        // SAS table optimizations
        $this->_create_index_if_not_exists('sas_activity_counts_etl', 'idx_sas_activity_courseid', '(`courseid`, `extraction_date`)');
        $this->_create_index_if_not_exists('sas_user_counts_etl', 'idx_sas_user_counts_courseid', '(`courseid`, `extraction_date`)');
        
        // ETL logs optimizations
        $this->_create_index_if_not_exists('cp_etl_logs', 'idx_cp_etl_logs_status_dates', '(`status`, `start_date` DESC, `end_date` DESC)');
        $this->_create_index_if_not_exists('sas_etl_logs', 'idx_sas_etl_logs_process_status', '(`process_name`, `status`, `extraction_date`)');
        
        // Additional composite indexes for better query performance
        $this->_create_index_if_not_exists('cp_course_summary', 'idx_cp_course_jumlah_mahasiswa', '(`jumlah_mahasiswa` DESC)');
        $this->_create_index_if_not_exists('cp_student_profile', 'idx_cp_student_program', '(`program_studi`)');
        $this->_create_index_if_not_exists('sas_courses', 'idx_sas_courses_visible_program', '(`visible`, `program_id`)');
    }

    public function down() {
        // Remove all optimization indexes
        $indexes_to_drop = [
            'cp_activity_summary' => [
                'idx_cp_activity_course_type',
                'idx_cp_activity_section', 
                'idx_cp_activity_created'
            ],
            'cp_student_quiz_detail' => [
                'idx_cp_student_quiz_course_user',
                'idx_cp_student_quiz_waktu_mulai',
                'idx_cp_student_quiz_nilai'
            ],
            'cp_student_assignment_detail' => [
                'idx_cp_student_assignment_waktu_submit'
            ],
            'cp_student_resource_access' => [
                'idx_cp_student_resource_waktu_akses'
            ],
            'sas_activity_counts_etl' => [
                'idx_sas_activity_courseid'
            ],
            'sas_user_counts_etl' => [
                'idx_sas_user_counts_courseid'
            ],
            'cp_etl_logs' => [
                'idx_cp_etl_logs_status_dates'
            ],
            'sas_etl_logs' => [
                'idx_sas_etl_logs_process_status'
            ],
            'cp_course_summary' => [
                'idx_cp_course_jumlah_mahasiswa'
            ],
            'cp_student_profile' => [
                'idx_cp_student_program'
            ],
            'sas_courses' => [
                'idx_sas_courses_visible_program'
            ]
        ];
        
        foreach ($indexes_to_drop as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->_drop_index_if_exists($table, $index);
            }
        }
    }
    
    private function _create_index_if_not_exists($table, $index_name, $columns) {
        // Check if index exists
        $result = $this->db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index_name'");
        if ($result->num_rows() == 0) {
            $this->db->query("CREATE INDEX `$index_name` ON `$table` $columns");
            echo "✓ Created index `$index_name` on table `$table`\n";
        } else {
            echo "ℹ Index `$index_name` already exists on table `$table`\n";
        }
    }
    
    private function _drop_index_if_exists($table, $index_name) {
        // Check if index exists
        $result = $this->db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index_name'");
        if ($result->num_rows() > 0) {
            $this->db->query("DROP INDEX `$index_name` ON `$table`");
            echo "✓ Dropped index `$index_name` from table `$table`\n";
        }
    }
}
