-- ETL Database Schema
-- Create tables for ETL operations

-- 1. Raw Log Table
CREATE TABLE IF NOT EXISTS `raw_log` (
  `id` bigint(20) NOT NULL,
  `eventname` varchar(255) DEFAULT NULL,
  `component` varchar(100) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `target` varchar(100) DEFAULT NULL,
  `objecttable` varchar(50) DEFAULT NULL,
  `objectid` bigint(20) DEFAULT NULL,
  `crud` char(1) DEFAULT NULL,
  `edulevel` tinyint(4) DEFAULT NULL,
  `contextid` bigint(20) DEFAULT NULL,
  `contextlevel` bigint(20) DEFAULT NULL,
  `contextinstanceid` bigint(20) DEFAULT NULL,
  `userid` bigint(20) DEFAULT NULL,
  `courseid` bigint(20) DEFAULT NULL,
  `relateduserid` bigint(20) DEFAULT NULL,
  `anonymous` tinyint(4) DEFAULT NULL,
  `other` longtext,
  `timecreated` bigint(20) DEFAULT NULL,
  `origin` varchar(10) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `realuserid` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_courseid` (`courseid`),
  KEY `idx_userid` (`userid`),
  KEY `idx_timecreated` (`timecreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Course Activity Summary Table
CREATE TABLE IF NOT EXISTS `course_activity_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) NOT NULL,
  `section` int(11) DEFAULT NULL,
  `activity_id` bigint(20) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_name` varchar(255) NOT NULL,
  `accessed_count` int(11) DEFAULT 0,
  `submission_count` int(11) DEFAULT NULL,
  `graded_count` int(11) DEFAULT NULL,
  `attempted_count` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_activity_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Student Profile Table
CREATE TABLE IF NOT EXISTS `student_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `idnumber` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `program_studi` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_idnumber` (`idnumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Student Quiz Detail Table
CREATE TABLE IF NOT EXISTS `student_quiz_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `nim` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi_waktu` time DEFAULT NULL,
  `jumlah_soal` int(11) DEFAULT NULL,
  `jumlah_dikerjakan` int(11) DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_nim` (`nim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Student Assignment Detail Table
CREATE TABLE IF NOT EXISTS `student_assignment_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `nim` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `waktu_submit` datetime DEFAULT NULL,
  `waktu_pengerjaan` time DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assignment_id` (`assignment_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_nim` (`nim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Student Resource Access Table
CREATE TABLE IF NOT EXISTS `student_resource_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `nim` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `waktu_akses` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resource_id` (`resource_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_nim` (`nim`),
  KEY `idx_waktu_akses` (`waktu_akses`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Course Summary Table
CREATE TABLE IF NOT EXISTS `course_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `kelas` varchar(100) DEFAULT NULL,
  `jumlah_aktivitas` int(11) DEFAULT 0,
  `jumlah_mahasiswa` int(11) DEFAULT 0,
  `dosen_pengampu` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Log Scheduler Table (for tracking ETL runs)
CREATE TABLE IF NOT EXISTS `log_scheduler` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `offset` int(10) NOT NULL DEFAULT 0,
  `numrow` int(10) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL COMMENT '1=finished, 2=inprogress, 3=failed',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. ETL Status Table (optional - for additional tracking)
CREATE TABLE IF NOT EXISTS `etl_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `etl_name` varchar(100) NOT NULL,
  `status` enum('running','completed','failed') NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `records_processed` int(11) DEFAULT 0,
  `error_message` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_etl_name` (`etl_name`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 