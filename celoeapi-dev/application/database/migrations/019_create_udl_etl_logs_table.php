<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_udl_etl_logs_table extends CI_Migration {

    public function up()
    {
        // Create udl_etl_logs table for storing ETL execution logs
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `udl_etl_logs` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `extraction_date` date NOT NULL COMMENT 'Tanggal ekstraksi data',
                `concurrency` int DEFAULT '1' COMMENT 'Jumlah concurrent processes yang digunakan',
                `status` enum('running','completed','failed') NOT NULL DEFAULT 'running' COMMENT 'Status eksekusi ETL',
                
                -- Execution Statistics
                `total_extracted` int DEFAULT '0' COMMENT 'Total data yang diekstrak dari Moodle',
                `total_inserted` int DEFAULT '0' COMMENT 'Total record yang diinsert',
                `total_updated` int DEFAULT '0' COMMENT 'Total record yang diupdate',
                `total_errors` int DEFAULT '0' COMMENT 'Total error yang terjadi',
                `execution_time` decimal(10,2) DEFAULT '0.00' COMMENT 'Waktu eksekusi dalam detik',
                
                -- Error Information
                `error_message` text DEFAULT NULL COMMENT 'Pesan error jika status failed',
                
                -- Timestamps
                `started_at` datetime DEFAULT NULL COMMENT 'Waktu mulai eksekusi',
                `completed_at` datetime DEFAULT NULL COMMENT 'Waktu selesai eksekusi',
                `created_at` datetime DEFAULT NULL COMMENT 'Waktu pembuatan log',
                `updated_at` datetime DEFAULT NULL COMMENT 'Waktu update log',
                
                PRIMARY KEY (`id`),
                KEY `idx_extraction_date` (`extraction_date`),
                KEY `idx_status` (`status`),
                KEY `idx_concurrency` (`concurrency`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_started_at` (`started_at`),
                KEY `idx_completed_at` (`completed_at`),
                KEY `idx_execution_time` (`execution_time`),
                KEY `idx_status_date` (`status`, `extraction_date`),
                KEY `idx_concurrency_date` (`concurrency`, `extraction_date`),
                KEY `idx_date_status` (`extraction_date`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✅ Created udl_etl_logs table successfully\n";
        echo "📊 Table structure:\n";
        echo "   - Execution tracking (extraction_date, concurrency, status)\n";
        echo "   - Statistics (total_extracted, total_inserted, total_updated, total_errors)\n";
        echo "   - Performance metrics (execution_time)\n";
        echo "   - Error handling (error_message)\n";
        echo "   - Timestamps (started_at, completed_at, created_at, updated_at)\n";
        echo "🔍 Indexes created for optimal query performance\n";
        echo "📈 Status tracking: running, completed, failed\n";
        echo "⚡ Concurrency tracking for performance analysis\n";
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS `udl_etl_logs`");
        echo "🗑️ Dropped udl_etl_logs table\n";
    }
}
