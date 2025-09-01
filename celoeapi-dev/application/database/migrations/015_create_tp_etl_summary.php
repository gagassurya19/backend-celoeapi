<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_tp_etl_summary extends CI_Migration {

    public function up() {
        // Create tp_etl_summary table using raw SQL for better MySQL compatibility
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `tp_etl_summary` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `user_id` bigint NOT NULL COMMENT 'Moodle user ID',
              `username` varchar(100) NOT NULL COMMENT 'User username',
              `firstname` varchar(100) NOT NULL COMMENT 'User first name',
              `lastname` varchar(100) NOT NULL COMMENT 'User last name',
              `email` varchar(100) NOT NULL COMMENT 'User email address',
              `total_courses_taught` int NOT NULL DEFAULT 0 COMMENT 'Total number of courses taught',
              `total_activities` int NOT NULL DEFAULT 0 COMMENT 'Total number of activities performed',
              `forum_replies` int NOT NULL DEFAULT 0 COMMENT 'Number of forum replies to students',
              `assignment_feedback_count` int NOT NULL DEFAULT 0 COMMENT 'Number of assignment feedback given',
              `quiz_feedback_count` int NOT NULL DEFAULT 0 COMMENT 'Number of quiz feedback given',
              `grading_count` int NOT NULL DEFAULT 0 COMMENT 'Number of grading activities performed',
              `mod_assign_logs` int NOT NULL DEFAULT 0 COMMENT 'Number of assignment module logs',
              `mod_forum_logs` int NOT NULL DEFAULT 0 COMMENT 'Number of forum module logs',
              `mod_quiz_logs` int NOT NULL DEFAULT 0 COMMENT 'Number of quiz module logs',
              `total_login` int NOT NULL DEFAULT 0 COMMENT 'Total number of login days',
              `total_student_interactions` int NOT NULL DEFAULT 0 COMMENT 'Total student interactions (forum + feedback + grading)',
              `extraction_date` date NOT NULL COMMENT 'Date for which data was extracted',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_user_date` (`user_id`, `extraction_date`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_username` (`username`),
              KEY `idx_email` (`email`),
              KEY `idx_extraction_date` (`extraction_date`),
              KEY `idx_total_courses_taught` (`total_courses_taught`),
              KEY `idx_total_activities` (`total_activities`),
              KEY `idx_total_student_interactions` (`total_student_interactions`),
              KEY `idx_forum_replies` (`forum_replies`),
              KEY `idx_assignment_feedback` (`assignment_feedback_count`),
              KEY `idx_quiz_feedback` (`quiz_feedback_count`),
              KEY `idx_grading_count` (`grading_count`),
              KEY `idx_user_date_interactions` (`user_id`, `extraction_date`, `total_student_interactions`),
              KEY `idx_user_date_courses` (`user_id`, `extraction_date`, `total_courses_taught`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✅ Created tp_etl_summary table\n";
    }

    public function down() {
        // Drop tp_etl_summary table
        $this->db->query("DROP TABLE IF EXISTS `tp_etl_summary`");
        echo "✅ Dropped tp_etl_summary table\n";
    }
}
