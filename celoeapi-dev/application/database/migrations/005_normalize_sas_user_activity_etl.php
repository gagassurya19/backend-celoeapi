<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Normalize_sas_user_activity_etl extends CI_Migration {

	public function up()
	{
		// Ensure table exists before altering
		$exists = $this->db->query("SHOW TABLES LIKE 'sas_user_activity_etl'")->num_rows() > 0;
		if (!$exists) { return; }

		// Drop existing unique key if present
		$this->db->query("ALTER TABLE `sas_user_activity_etl` DROP INDEX `idx_unique_course_subject_date`");

		// Drop redundant columns now provided by sas_courses
		$dropCols = [
			'faculty_id', 'program_id', 'subject_id', 'course_name', 'course_shortname'
		];
		foreach ($dropCols as $col) {
			// Drop column if it exists
			$this->db->query("SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sas_user_activity_etl' AND COLUMN_NAME = '{$col}')");
			$this->db->query("SET @sql := IF(@col > 0, CONCAT('ALTER TABLE sas_user_activity_etl DROP COLUMN `', '{$col}', '`'), 'SELECT 1')");
			$this->db->query("PREPARE stmt FROM @sql");
			$this->db->query("EXECUTE stmt");
			$this->db->query("DEALLOCATE PREPARE stmt");
		}

		// Add new unique key on (course_id, extraction_date)
		$this->db->query("ALTER TABLE `sas_user_activity_etl` ADD UNIQUE KEY `idx_unique_course_date` (`course_id`,`extraction_date`)");

		// Optional: add FK to sas_courses if exists
		$hasCourses = $this->db->query("SHOW TABLES LIKE 'sas_courses'")->num_rows() > 0;
		if ($hasCourses) {
			// Add FK only if not exists (safe attempt)
			$this->db->query("SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='sas_user_activity_etl' AND CONSTRAINT_NAME='fk_sas_user_activity_course')");
			$this->db->query("SET @sql := IF(@fk = 0, 'ALTER TABLE `sas_user_activity_etl` ADD CONSTRAINT `fk_sas_user_activity_course` FOREIGN KEY (`course_id`) REFERENCES `sas_courses`(`course_id`) ON UPDATE CASCADE ON DELETE RESTRICT', 'SELECT 1')");
			$this->db->query("PREPARE stmt FROM @sql");
			$this->db->query("EXECUTE stmt");
			$this->db->query("DEALLOCATE PREPARE stmt");
		}
	}

	public function down()
	{
		// Revert unique key
		$this->db->query("ALTER TABLE `sas_user_activity_etl` DROP INDEX `idx_unique_course_date`");
		$this->db->query("ALTER TABLE `sas_user_activity_etl` ADD UNIQUE KEY `idx_unique_course_subject_date` (`course_id`,`subject_id`,`extraction_date`)");

		// Re-add dropped columns (without restoring data)
		$this->db->query("ALTER TABLE `sas_user_activity_etl` 
			ADD COLUMN `faculty_id` int DEFAULT NULL AFTER `course_id`,
			ADD COLUMN `program_id` int DEFAULT NULL AFTER `faculty_id`,
			ADD COLUMN `subject_id` varchar(100) DEFAULT NULL AFTER `program_id`,
			ADD COLUMN `course_name` varchar(255) DEFAULT NULL AFTER `subject_id`,
			ADD COLUMN `course_shortname` varchar(255) DEFAULT NULL AFTER `course_name`");

		// Drop FK if exists
		$this->db->query("ALTER TABLE `sas_user_activity_etl` DROP FOREIGN KEY `fk_sas_user_activity_course`");
	}
}


