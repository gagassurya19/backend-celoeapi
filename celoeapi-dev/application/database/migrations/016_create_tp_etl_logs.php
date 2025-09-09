<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_tp_etl_logs extends CI_Migration {

    public function up() {
        // Create tp_etl_logs table using raw SQL for better MySQL compatibility
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `tp_etl_logs` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `process_type` varchar(100) NOT NULL COMMENT 'ETL process type',
              `status` enum('running','completed','failed') NOT NULL DEFAULT 'running' COMMENT 'Process status',
              `message` text NULL COMMENT 'Process message or error details',
              `concurrency` int NOT NULL DEFAULT 1 COMMENT 'Concurrency level used',
              `start_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Process start time',
              `end_date` timestamp NULL DEFAULT NULL COMMENT 'Process end time',
              `duration_seconds` int NULL COMMENT 'Process duration in seconds',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_process_type` (`process_type`),
              KEY `idx_status` (`status`),
              KEY `idx_start_date` (`start_date`),
              KEY `idx_concurrency` (`concurrency`),
              KEY `idx_process_type_status` (`process_type`, `status`),
              KEY `idx_start_date_process` (`start_date`, `process_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✅ Created tp_etl_logs table\n";
    }

    public function down() {
        // Drop tp_etl_logs table
        $this->db->query("DROP TABLE IF EXISTS `tp_etl_logs`");
        echo "✅ Dropped tp_etl_logs table\n";
    }
}
