<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sp_etl_logs extends CI_Migration {

    public function up()
    {
        // Create sp_etl_logs table for ETL execution logging
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `sp_etl_logs` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `process_type` varchar(50) NOT NULL COMMENT 'ETL process type (summary, detail, both)',
              `status` enum('running', 'completed', 'failed') NOT NULL DEFAULT 'running',
              `concurrency` int NOT NULL DEFAULT 1 COMMENT 'Concurrency level used',
              `parameters` text NULL COMMENT 'JSON parameters sent by client',
              `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `end_time` timestamp NULL DEFAULT NULL,
              `duration` decimal(10,2) NULL COMMENT 'Execution duration in seconds',
              `summary_result` text NULL COMMENT 'Summary ETL result (JSON)',
              `detail_result` text NULL COMMENT 'Detail ETL result (JSON)',
              `error_message` text NULL COMMENT 'Error message if failed',
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_process_type` (`process_type`),
              KEY `idx_status` (`status`),
              KEY `idx_start_time` (`start_time`),
              KEY `idx_concurrency` (`concurrency`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✅ Created sp_etl_logs table for ETL execution logging\n";
    }

    public function down()
    {
        // Drop sp_etl_logs table
        $this->db->query("DROP TABLE IF EXISTS `sp_etl_logs`");
        echo "✅ Dropped sp_etl_logs table\n";
    }
}
