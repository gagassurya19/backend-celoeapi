<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Init_sas extends CI_Migration {

    public function up()
    {
        $queries = [
            // sas_etl_logs (single SAS logging table)
            "CREATE TABLE IF NOT EXISTS `sas_etl_logs` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `process_name` varchar(100) NOT NULL,
              `status` varchar(32) NOT NULL,
              `message` text NULL,
              `start_time` datetime DEFAULT NULL,
              `end_time` datetime DEFAULT NULL,
              `duration_seconds` int DEFAULT NULL,
              `extraction_date` date DEFAULT NULL,
              `parameters` text,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_process` (`process_name`),
              KEY `idx_status` (`status`),
              KEY `idx_extract_date` (`extraction_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            // sas_activity_counts_etl
            "CREATE TABLE IF NOT EXISTS `sas_activity_counts_etl` (
              `id` int NOT NULL AUTO_INCREMENT,
              `courseid` int NOT NULL,
              `file_views` int DEFAULT '0',
              `video_views` int DEFAULT '0',
              `forum_views` int DEFAULT '0',
              `quiz_views` int DEFAULT '0',
              `assignment_views` int DEFAULT '0',
              `url_views` int DEFAULT '0',
              `active_days` int DEFAULT '0',
              `extraction_date` date NOT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_courseid` (`courseid`),
              KEY `idx_extraction_date` (`extraction_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            // sas_user_counts_etl
            "CREATE TABLE IF NOT EXISTS `sas_user_counts_etl` (
              `id` int NOT NULL AUTO_INCREMENT,
              `courseid` int NOT NULL,
              `num_students` int DEFAULT '0',
              `num_teachers` int DEFAULT '0',
              `extraction_date` date NOT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_courseid` (`courseid`),
              KEY `idx_extraction_date` (`extraction_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            // sas_user_activity_etl
            "CREATE TABLE IF NOT EXISTS `sas_user_activity_etl` (
              `id` int NOT NULL AUTO_INCREMENT,
              `course_id` int NOT NULL,
              `faculty_id` int DEFAULT NULL,
              `program_id` int DEFAULT NULL,
              `subject_id` varchar(100) DEFAULT NULL,
              `course_name` varchar(255) DEFAULT NULL,
              `course_shortname` varchar(255) DEFAULT NULL,
              `num_teachers` int DEFAULT '0',
              `num_students` int DEFAULT '0',
              `file_views` int DEFAULT '0',
              `video_views` int DEFAULT '0',
              `forum_views` int DEFAULT '0',
              `quiz_views` int DEFAULT '0',
              `assignment_views` int DEFAULT '0',
              `url_views` int DEFAULT '0',
              `total_views` int DEFAULT '0',
              `avg_activity_per_student_per_day` decimal(10,2) DEFAULT NULL,
              `active_days` int DEFAULT '0',
              `extraction_date` date NOT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_course_id` (`course_id`),
              KEY `idx_extraction_date` (`extraction_date`),
              UNIQUE KEY `idx_unique_course_subject_date` (`course_id`,`subject_id`,`extraction_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            // Only ETL tables needed by SAS service
        ];

        foreach ($queries as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        $tables = [
            'sas_user_activity_etl',
            'sas_activity_counts_etl',
            'sas_user_counts_etl',
            'sas_etl_logs',
        ];
        foreach ($tables as $tbl) {
            $this->db->query("DROP TABLE IF EXISTS `$tbl`");
        }
    }
}


