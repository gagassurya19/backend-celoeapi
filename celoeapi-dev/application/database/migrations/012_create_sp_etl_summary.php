<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sp_etl_summary extends CI_Migration {

    public function up()
    {
        // Create sp_etl_summary table for student summary data
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `sp_etl_summary` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `user_id` bigint NOT NULL COMMENT 'Moodle user ID',
              `username` varchar(100) NOT NULL COMMENT 'User username',
              `firstname` varchar(100) NOT NULL COMMENT 'User first name',
              `lastname` varchar(100) NOT NULL COMMENT 'User last name',
              `total_course` int NOT NULL DEFAULT 0 COMMENT 'Total number of courses enrolled',
              `total_login` int NOT NULL DEFAULT 0 COMMENT 'Total number of login days',
              `total_activities` int NOT NULL DEFAULT 0 COMMENT 'Total number of activities performed',
              `extraction_date` date NOT NULL COMMENT 'Date for which data was extracted',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_user_date` (`user_id`, `extraction_date`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_username` (`username`),
              KEY `idx_extraction_date` (`extraction_date`),
              KEY `idx_total_course` (`total_course`),
              KEY `idx_total_activities` (`total_activities`),
              KEY `idx_user_date_activities` (`user_id`, `extraction_date`, `total_activities`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✅ Created sp_etl_summary table\n";
    }

    public function down()
    {
        // Drop sp_etl_summary table
        $this->db->query("DROP TABLE IF EXISTS `sp_etl_summary`");
        echo "✅ Dropped sp_etl_summary table\n";
    }
}
