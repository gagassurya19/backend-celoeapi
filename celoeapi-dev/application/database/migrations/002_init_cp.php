<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Init_cp extends CI_Migration {

    public function up()
    {
        // Create CP tables via raw SQL for exact schema
        $queries = [
            "CREATE TABLE IF NOT EXISTS `cp_activity_summary` (
              `id` int NOT NULL AUTO_INCREMENT,
              `course_id` bigint NOT NULL,
              `section` int DEFAULT NULL,
              `activity_id` bigint NOT NULL,
              `activity_type` varchar(50) NOT NULL,
              `activity_name` varchar(255) NOT NULL,
              `accessed_count` int DEFAULT '0',
              `submission_count` int DEFAULT NULL,
              `graded_count` int DEFAULT NULL,
              `attempted_count` int DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_course_id` (`course_id`),
              KEY `idx_activity_type` (`activity_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `cp_course_summary` (
              `id` int NOT NULL AUTO_INCREMENT,
              `course_id` bigint NOT NULL,
              `course_name` varchar(255) NOT NULL,
              `kelas` varchar(100) DEFAULT NULL,
              `jumlah_aktivitas` int DEFAULT '0',
              `jumlah_mahasiswa` int DEFAULT '0',
              `dosen_pengampu` text,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `course_id` (`course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `cp_etl_logs` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `offset` int NOT NULL DEFAULT '0',
              `numrow` int NOT NULL DEFAULT '0',
              `type` varchar(32) NOT NULL DEFAULT 'run_etl',
              `message` text NULL,
              `requested_start_date` date NULL,
              `extracted_start_date` date NULL,
              `extracted_end_date` date NULL,
              `status` tinyint(1) NOT NULL COMMENT '1=finished, 2=inprogress, 3=failed',
              `start_date` datetime DEFAULT NULL,
              `end_date` datetime DEFAULT NULL,
              `duration_seconds` int NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_status` (`status`),
              KEY `idx_start_date` (`start_date`),
              KEY `idx_end_date` (`end_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `cp_student_assignment_detail` (
              `id` int NOT NULL AUTO_INCREMENT,
              `assignment_id` bigint NOT NULL,
              `user_id` bigint NOT NULL,
              `nim` varchar(255) DEFAULT NULL,
              `full_name` varchar(255) NOT NULL,
              `waktu_submit` datetime DEFAULT NULL,
              `waktu_pengerjaan` time DEFAULT NULL,
              `nilai` decimal(5,2) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_assignment_id` (`assignment_id`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_nim` (`nim`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `cp_student_profile` (
              `id` int NOT NULL AUTO_INCREMENT,
              `user_id` bigint NOT NULL,
              `idnumber` varchar(255) DEFAULT NULL,
              `full_name` varchar(255) NOT NULL,
              `email` varchar(255) DEFAULT NULL,
              `program_studi` varchar(255) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `user_id` (`user_id`),
              KEY `idx_idnumber` (`idnumber`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `cp_student_quiz_detail` (
              `id` int NOT NULL AUTO_INCREMENT,
              `quiz_id` bigint NOT NULL,
              `user_id` bigint NOT NULL,
              `nim` varchar(255) DEFAULT NULL,
              `full_name` varchar(255) NOT NULL,
              `waktu_mulai` datetime DEFAULT NULL,
              `waktu_selesai` datetime DEFAULT NULL,
              `durasi_waktu` time DEFAULT NULL,
              `jumlah_soal` int DEFAULT NULL,
              `jumlah_dikerjakan` int DEFAULT NULL,
              `nilai` decimal(5,2) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_quiz_id` (`quiz_id`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_nim` (`nim`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

            "CREATE TABLE IF NOT EXISTS `cp_student_resource_access` (
              `id` int NOT NULL AUTO_INCREMENT,
              `resource_id` bigint NOT NULL,
              `user_id` bigint NOT NULL,
              `nim` varchar(255) DEFAULT NULL,
              `full_name` varchar(255) NOT NULL,
              `waktu_akses` datetime DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_resource_id` (`resource_id`),
              KEY `idx_user_id` (`user_id`),
              KEY `idx_nim` (`nim`),
              KEY `idx_waktu_akses` (`waktu_akses`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        ];

        foreach ($queries as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        $tables = [
            'cp_activity_summary',
            'cp_course_summary',
            'cp_etl_logs',
            'cp_student_assignment_detail',
            'cp_student_profile',
            'cp_student_quiz_detail',
            'cp_student_resource_access',
        ];
        foreach ($tables as $tbl) {
            $this->db->query("DROP TABLE IF EXISTS `$tbl`");
        }
    }
}


