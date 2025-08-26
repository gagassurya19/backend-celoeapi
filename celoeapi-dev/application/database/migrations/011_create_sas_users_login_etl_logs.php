<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sas_users_login_etl_logs extends CI_Migration {

    public function up()
    {
        // Create sas_users_login_etl_logs table for SAS ETL processes
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `sas_users_login_etl_logs` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `process_name` varchar(64) NOT NULL COMMENT 'Name of the ETL process (users_etl, user_login_hourly_etl, etc.)',
              `status` enum('running', 'completed', 'failed') NOT NULL DEFAULT 'running',
              `message` text NULL COMMENT 'Status message or error details',
              `extraction_date` date NULL COMMENT 'Date for which data was extracted',
              `parameters` json NULL COMMENT 'Additional parameters as JSON',
              `start_time` datetime NOT NULL COMMENT 'When the ETL process started',
              `end_time` datetime NULL COMMENT 'When the ETL process ended',
              `duration_seconds` int NULL COMMENT 'Duration in seconds',
              `extracted_count` int NULL DEFAULT 0 COMMENT 'Number of records extracted',
              `inserted_count` int NULL DEFAULT 0 COMMENT 'Number of records inserted/updated',
              `error_count` int NULL DEFAULT 0 COMMENT 'Number of errors encountered',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_process_name` (`process_name`),
              KEY `idx_status` (`status`),
              KEY `idx_extraction_date` (`extraction_date`),
              KEY `idx_start_time` (`start_time`),
              KEY `idx_process_status_date` (`process_name`, `status`, `extraction_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✅ Created sas_users_login_etl_logs table\n";
    }

    public function down()
    {
        // Drop sas_users_login_etl_logs table
        $this->db->query("DROP TABLE IF EXISTS `sas_users_login_etl_logs`");
        echo "✅ Dropped sas_users_login_etl_logs table\n";
    }
}
