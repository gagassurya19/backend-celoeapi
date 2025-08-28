<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sp_etl_detail extends CI_Migration {

    public function up()
    {
        // Create sp_etl_detail table for detailed module activity data
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `sp_etl_detail` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `user_id` bigint NOT NULL COMMENT 'Moodle user ID',
              `course_id` bigint NOT NULL COMMENT 'Moodle course ID',
              `course_name` varchar(254) NOT NULL COMMENT 'Course full name',
              `module_type` varchar(50) NOT NULL COMMENT 'Module type (mod_quiz, mod_assign, mod_forum)',
              `module_name` varchar(255) NOT NULL COMMENT 'Name of the module (quiz name, forum name, assignment name)',
              `object_id` bigint NULL COMMENT 'Object ID (forum_id, assign_id, quiz_id)',
              `grade` decimal(10,2) NULL DEFAULT NULL COMMENT 'Grade for this module (nullable)',
              `timecreated` bigint NULL COMMENT 'Unix timestamp from Moodle log',
              `log_id` bigint NULL COMMENT 'Moodle log entry ID',
              `action_type` varchar(50) NULL COMMENT 'Action type from Moodle log (viewed, created, updated, etc.)',
              `extraction_date` date NOT NULL COMMENT 'Date for which data was extracted',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_user_course_module_object_timecreated_log_date` (`user_id`, `course_id`, `module_type`, `object_id`, `timecreated`, `log_id`, `extraction_date`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_course_id` (`course_id`),
              KEY `idx_module_type` (`module_type`),
              KEY `idx_module_name` (`module_name`),
              KEY `idx_object_id` (`object_id`),
              KEY `idx_extraction_date` (`extraction_date`),
              KEY `idx_user_course_module` (`user_id`, `course_id`, `module_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✅ Created sp_etl_detail table with new structure\n";
    }

    public function down()
    {
        // Drop sp_etl_detail table
        $this->db->query("DROP TABLE IF EXISTS `sp_etl_detail`");
        echo "✅ Dropped sp_etl_detail table\n";
    }
}
