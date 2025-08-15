<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_Unique_Constraint_User_Activity_Etl extends CI_Migration {

	public function up()
	{
		// Check if the table exists first
		if (!$this->db->table_exists('user_activity_etl') && !$this->db->table_exists('sas_user_activity_etl')) {
			echo "Warning: Neither user_activity_etl nor sas_user_activity_etl table exists. Skipping constraint addition.\n";
			return;
		}
		
		// Determine which table name to use
		$table_name = $this->db->table_exists('user_activity_etl') ? 'user_activity_etl' : 'sas_user_activity_etl';
		
		// Check if the index already exists
		$exists = $this->db->query("SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_unique_course_subject_date'");
		if (!$exists || $exists->num_rows() === 0) {
			// Check which column name exists (id_number or subject_id)
			$columns = $this->db->list_fields($table_name);
			$id_column = in_array('subject_id', $columns) ? 'subject_id' : 'id_number';
			
			// Add unique constraint to prevent duplicates based on course_id, id_column, and extraction_date
			$this->db->query("ALTER TABLE `{$table_name}` ADD UNIQUE INDEX `idx_unique_course_subject_date` (`course_id`, `{$id_column}`, `extraction_date`)");
		}
	}

	public function down()
	{
		// Check if the table exists first
		if (!$this->db->table_exists('user_activity_etl') && !$this->db->table_exists('sas_user_activity_etl')) {
			echo "Warning: Neither user_activity_etl nor sas_user_activity_etl table exists. Skipping constraint removal.\n";
			return;
		}
		
		// Determine which table name to use
		$table_name = $this->db->table_exists('user_activity_etl') ? 'user_activity_etl' : 'sas_user_activity_etl';
		
		// Remove the unique constraint if it exists
		$exists = $this->db->query("SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_unique_course_subject_date'");
		if ($exists && $exists->num_rows() > 0) {
			$this->db->query("ALTER TABLE `{$table_name}` DROP INDEX `idx_unique_course_subject_date`");
		}
	}
}