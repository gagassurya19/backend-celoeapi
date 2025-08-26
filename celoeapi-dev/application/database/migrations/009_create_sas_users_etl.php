<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sas_users_etl extends CI_Migration {

    public function up()
    {
        // Create simplified sas_users_etl table for storing essential user data from Moodle
        $this->db->query("CREATE TABLE IF NOT EXISTS `sas_users_etl` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` bigint NOT NULL,                    -- ID user dari Moodle
            `username` varchar(100) NOT NULL,             -- Username Moodle
            `idnumber` varchar(255) DEFAULT NULL,         -- NIM/ID Number mahasiswa
            `firstname` varchar(100) NOT NULL,            -- Nama depan
            `lastname` varchar(100) NOT NULL,             -- Nama belakang
            `full_name` varchar(255) NOT NULL,            -- Nama lengkap
            `email` varchar(255) DEFAULT NULL,            -- Email user
            `suspended` tinyint(1) DEFAULT '0',            -- Status suspended (0=active, 1=suspended)
            `deleted` tinyint(1) DEFAULT '0',             -- Status deleted (0=active, 1=deleted)
            `confirmed` tinyint(1) DEFAULT '1',            -- Status konfirmasi (0=unconfirmed, 1=confirmed)
            `firstaccess` bigint DEFAULT '0',             -- Timestamp akses pertama
            `lastaccess` bigint DEFAULT '0',               -- Timestamp akses terakhir
            `lastlogin` bigint DEFAULT '0',                -- Timestamp login terakhir
            `currentlogin` bigint DEFAULT '0',             -- Timestamp login saat ini
            `lastip` varchar(45) DEFAULT NULL,             -- IP address terakhir
            `auth` varchar(20) DEFAULT 'manual',           -- Authentication method
            `extraction_date` date NOT NULL,               -- Tanggal ekstraksi data
            `created_at` datetime DEFAULT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_unique_user_extraction` (`user_id`,`extraction_date`),
            KEY `idx_username` (`username`),
            KEY `idx_idnumber` (`idnumber`),
            KEY `idx_email` (`extraction_date`),
            KEY `idx_extraction_date` (`extraction_date`),
            KEY `idx_lastaccess` (`lastaccess`),
            KEY `idx_suspended_deleted` (`suspended`,`deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create sas_user_roles_etl table for storing user role assignments (student/teacher)
        $this->db->query("CREATE TABLE IF NOT EXISTS `sas_user_roles_etl` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` bigint NOT NULL,                    -- ID user
            `course_id` bigint NOT NULL,                   -- ID course
            `role_id` bigint NOT NULL,                     -- ID role
            `role_name` varchar(255) NOT NULL,             -- Nama role (student, teacher, dll)
            `role_shortname` varchar(255) NOT NULL,        -- Shortname role
            `context_id` bigint NOT NULL,                  -- ID context
            `context_level` int DEFAULT '70',              -- Level context (70=course)
            `timemodified` bigint DEFAULT '0',             -- Timestamp modifikasi terakhir
            `extraction_date` date NOT NULL,               -- Tanggal ekstraksi data
            `created_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_unique_user_course_role` (`user_id`,`course_id`,`role_id`,`extraction_date`),
            KEY `idx_course_id` (`course_id`),
            KEY `idx_role_id` (`role_id`),
            KEY `idx_role_name` (`role_name`),
            KEY `idx_extraction_date` (`extraction_date`),
            KEY `idx_context_level` (`context_level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create sas_user_enrolments_etl table for storing user enrolment data
        $this->db->query("CREATE TABLE IF NOT EXISTS `sas_user_enrolments_etl` (
            `id` int NOT NULL AUTO_INCREMENT,
            `enrolid` bigint NOT NULL,                     -- ID enrolment
            `userid` bigint NOT NULL,                      -- ID user
            `course_id` bigint NOT NULL,                   -- ID course
            `enrolment_method` varchar(255) NOT NULL,      -- Metode enrolment (manual, self, dll)
            `status` int DEFAULT '0',                      -- Status enrolment (0=active, 1=suspended)
            `timestart` bigint DEFAULT '0',                -- Timestamp mulai enrolment
            `timeend` bigint DEFAULT '0',                  -- Timestamp berakhir enrolment
            `timecreated` bigint DEFAULT '0',              -- Timestamp pembuatan
            `timemodified` bigint DEFAULT '0',             -- Timestamp modifikasi terakhir
            `extraction_date` date NOT NULL,               -- Tanggal ekstraksi data
            `created_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_unique_enrolment_user` (`enrolid`,`userid`,`extraction_date`),
            KEY `idx_userid` (`userid`),
            KEY `idx_course_id` (`course_id`),
            KEY `idx_enrolment_method` (`enrolment_method`),
            KEY `idx_status` (`status`),
            KEY `idx_extraction_date` (`extraction_date`),
            KEY `idx_timestart_timeend` (`timestart`,`timeend`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        echo "Tables created successfully:\n";
        echo "- sas_users_etl (simplified)\n";
        echo "- sas_user_roles_etl\n";
        echo "- sas_user_enrolments_etl\n";
    }

    public function down()
    {
        $tables = [
            'sas_user_enrolments_etl',
            'sas_user_roles_etl',
            'sas_users_etl'
        ];
        
        foreach ($tables as $table) {
            $this->db->query("DROP TABLE IF EXISTS `$table`");
            echo "Dropped table: $table\n";
        }
    }
}
