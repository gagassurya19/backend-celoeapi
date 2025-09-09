<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_udl_etl_table extends CI_Migration {

    public function up()
    {
        // Create udl_etl table for storing enrolled users data with login activity
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `udl_etl` (
                `id` bigint NOT NULL AUTO_INCREMENT,
                `user_id` bigint NOT NULL COMMENT 'ID user dari Moodle',
                `username` varchar(100) NOT NULL COMMENT 'Username Moodle',
                `firstname` varchar(100) NOT NULL COMMENT 'Nama depan',
                `lastname` varchar(100) NOT NULL COMMENT 'Nama belakang',
                `email` varchar(255) DEFAULT NULL COMMENT 'Email user',
                
                -- Login Information
                `lastaccess` bigint DEFAULT '0' COMMENT 'Timestamp akses terakhir (Unix)',
                `formatted_lastaccess` datetime DEFAULT NULL COMMENT 'Format YYYY-MM-DD HH:II:SS dari lastaccess',
                `lastlogin` bigint DEFAULT '0' COMMENT 'Timestamp login terakhir (Unix)',
                `formatted_lastlogin` datetime DEFAULT NULL COMMENT 'Format YYYY-MM-DD HH:II:SS dari lastlogin',
                `currentlogin` bigint DEFAULT '0' COMMENT 'Timestamp login saat ini (Unix)',
                `formatted_currentlogin` datetime DEFAULT NULL COMMENT 'Format YYYY-MM-DD HH:II:SS dari currentlogin',
                `lastip` varchar(45) DEFAULT NULL COMMENT 'IP address terakhir',
                `auth` varchar(20) DEFAULT 'manual' COMMENT 'Authentication method',
                `firstaccess` bigint DEFAULT '0' COMMENT 'Timestamp akses pertama (Unix)',
                `formatted_firstaccess` datetime DEFAULT NULL COMMENT 'Format YYYY-MM-DD HH:II:SS dari firstaccess',
                
                -- Primary Role Information
                `role_id` bigint DEFAULT NULL COMMENT 'ID role utama',
                `role_name` varchar(255) DEFAULT NULL COMMENT 'Nama role utama',
                `role_shortname` varchar(255) DEFAULT NULL COMMENT 'Shortname role utama',
                `archetype` varchar(255) DEFAULT NULL COMMENT 'Archetype role utama',
                `course_id` bigint DEFAULT NULL COMMENT 'ID course utama',
                
                -- All Roles and Courses Information
                `all_role_ids` text DEFAULT NULL COMMENT 'Array semua role IDs (JSON)',
                `all_role_names` text DEFAULT NULL COMMENT 'Array semua role names (JSON)',
                `all_role_shortnames` text DEFAULT NULL COMMENT 'Array semua role shortnames (JSON)',
                `all_archetypes` text DEFAULT NULL COMMENT 'Array semua archetypes (JSON)',
                `all_course_ids` text DEFAULT NULL COMMENT 'Array semua course IDs (JSON)',
                `total_courses` int DEFAULT '0' COMMENT 'Jumlah total course user',
                
                -- Activity Tracking
                `activity_hour` int DEFAULT NULL COMMENT 'Jam aktivitas (0-23) dalam timezone Asia/Jakarta',
                `activity_date` date DEFAULT NULL COMMENT 'Tanggal aktivitas dalam format YYYY-MM-DD (Asia/Jakarta)',
                `login_count` int DEFAULT '1' COMMENT 'Jumlah login dalam jam yang sama',
                
                -- Metadata
                `extraction_date` date NOT NULL COMMENT 'Tanggal ekstraksi data',
                `created_at` datetime DEFAULT NULL COMMENT 'Waktu pembuatan record',
                `updated_at` datetime DEFAULT NULL COMMENT 'Waktu update record',
                
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_unique_user_activity` (`user_id`, `activity_hour`, `activity_date`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_username` (`username`),
                KEY `idx_email` (`email`),
                KEY `idx_extraction_date` (`extraction_date`),
                KEY `idx_activity_hour` (`activity_hour`),
                KEY `idx_activity_date` (`activity_date`),
                KEY `idx_role_id` (`role_id`),
                KEY `idx_role_name` (`role_name`),
                KEY `idx_course_id` (`course_id`),
                KEY `idx_lastaccess` (`lastaccess`),
                KEY `idx_login_count` (`login_count`),
                KEY `idx_user_activity_hour` (`user_id`, `activity_hour`),
                KEY `idx_user_activity_date` (`user_id`, `activity_date`),
                KEY `idx_hour_date_activity` (`activity_hour`, `activity_date`, `login_count`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "✅ Created udl_etl table successfully\n";
        echo "📊 Table structure:\n";
        echo "   - User information (id, username, names, email)\n";
        echo "   - Login information (lastaccess, lastlogin, currentlogin, lastip, auth)\n";
        echo "   - Role information (primary role + all roles)\n";
        echo "   - Course information (primary course + all courses)\n";
        echo "   - Activity tracking (hour, date, login count)\n";
        echo "   - Metadata (extraction date, timestamps)\n";
        echo "🔍 Indexes created for optimal query performance\n";
        echo "🎯 Unique key on (user_id, activity_hour, activity_date) for tracking busiest time per user per day\n";
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS `udl_etl`");
        echo "🗑️ Dropped udl_etl table\n";
    }
}
