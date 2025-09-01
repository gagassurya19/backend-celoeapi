<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_tp_etl_detail extends CI_Migration {

    public function up() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `tp_etl_detail` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `user_id` bigint NOT NULL COMMENT 'Moodle user ID',
                `username` varchar(100) NOT NULL COMMENT 'Username',
                `firstname` varchar(100) NOT NULL COMMENT 'First name',
                `lastname` varchar(100) NOT NULL COMMENT 'Last name',
                `email` varchar(100) NOT NULL COMMENT 'Email address',
                `course_id` bigint NOT NULL COMMENT 'Moodle course ID',
                `course_name` varchar(255) NOT NULL COMMENT 'Course full name',
                `course_shortname` varchar(100) NOT NULL COMMENT 'Course short name',
                `activity_date` date NOT NULL COMMENT 'Date of activity',
                `component` varchar(100) NULL COMMENT 'Component (e.g., mod_assign, mod_forum)',
                `action` varchar(100) NULL COMMENT 'Action performed (e.g., graded, loggedin)',
                `target` varchar(100) NULL COMMENT 'Target of action',
                `objectid` bigint NULL COMMENT 'Object ID',
                `log_id` bigint NOT NULL COMMENT 'Moodle log ID (for duplicate prevention)',
                `activity_timestamp` bigint NULL COMMENT 'Activity timestamp (Unix)',
                `extraction_date` date NOT NULL COMMENT 'Date when data was extracted',
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_log_id` (`log_id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_course_id` (`course_id`),
                KEY `idx_log_id` (`log_id`),
                KEY `idx_extraction_date` (`extraction_date`),
                KEY `idx_activity_date` (`activity_date`),
                KEY `idx_component` (`component`),
                KEY `idx_action` (`action`),
                KEY `idx_username` (`username`),
                KEY `idx_activity_timestamp` (`activity_timestamp`),
                KEY `idx_user_date` (`user_id`, `extraction_date`),
                KEY `idx_course_date` (`course_id`, `extraction_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Created simplified tp_etl_detail table\n";
    }

    public function down() {
        $this->db->query("DROP TABLE IF EXISTS `tp_etl_detail`");
        echo "✅ Dropped tp_etl_detail table\n";
    }
}
