<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Rename_Id_Number_To_Subject_Id extends CI_Migration {

	public function up()
	{
		// Check if the table exists first
		if (!$this->db->table_exists('user_activity_etl') && !$this->db->table_exists('sas_user_activity_etl')) {
			echo "Warning: Neither user_activity_etl nor sas_user_activity_etl table exists. Skipping column rename.\n";
			return;
		}
		
		// Determine which table name to use
		$table_name = $this->db->table_exists('user_activity_etl') ? 'user_activity_etl' : 'sas_user_activity_etl';
		
		// Check if id_number column exists
		$columns = $this->db->list_fields($table_name);
		if (in_array('id_number', $columns)) {
			// Drop old unique index if it exists
			$exists = $this->db->query("SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_unique_course_id_number_date'");
			if ($exists && $exists->num_rows() > 0) {
				$this->db->query("ALTER TABLE `{$table_name}` DROP INDEX `idx_unique_course_id_number_date`");
			}

			// Rename column id_number -> subject_id (keeping same type and nullability)
			$this->db->query("ALTER TABLE `{$table_name}` CHANGE COLUMN `id_number` `subject_id` VARCHAR(100) NULL");
		}

		// Create new unique index on (course_id, subject_id, extraction_date) if it doesn't exist
		$existsNew = $this->db->query("SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_unique_course_subject_date'");
		if (!$existsNew || $existsNew->num_rows() === 0) {
			$this->db->query("ALTER TABLE `{$table_name}` ADD UNIQUE INDEX `idx_unique_course_subject_date` (`course_id`, `subject_id`, `extraction_date`)");
		}
	}

	public function down()
	{
		// Check if the table exists first
		if (!$this->db->table_exists('user_activity_etl') && !$this->db->table_exists('sas_user_activity_etl')) {
			echo "Warning: Neither user_activity_etl nor sas_user_activity_etl table exists. Skipping column rename.\n";
			return;
		}
		
		// Determine which table name to use
		$table_name = $this->db->table_exists('user_activity_etl') ? 'user_activity_etl' : 'sas_user_activity_etl';
		
		// Check if subject_id column exists
		$columns = $this->db->list_fields($table_name);
		if (in_array('subject_id', $columns)) {
			// Drop new unique index if it exists
			$existsNew = $this->db->query("SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_unique_course_subject_date'");
			if ($existsNew && $existsNew->num_rows() > 0) {
				$this->db->query("ALTER TABLE `{$table_name}` DROP INDEX `idx_unique_course_subject_date`");
			}

			// Rename column subject_id -> id_number
			$this->db->query("ALTER TABLE `{$table_name}` CHANGE COLUMN `subject_id` `id_number` VARCHAR(100) NULL");

			// Re-create the old unique index if missing
			$existsOld = $this->db->query("SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_unique_course_id_number_date'");
			if (!$existsOld || $existsOld->num_rows() === 0) {
				$this->db->query("ALTER TABLE `{$table_name}` ADD UNIQUE INDEX `idx_unique_course_id_number_date` (`course_id`, `id_number`, `extraction_date`)");
			}
		}
	}
} 