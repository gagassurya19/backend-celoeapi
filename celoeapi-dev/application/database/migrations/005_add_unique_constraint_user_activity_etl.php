<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_Unique_Constraint_User_Activity_Etl extends CI_Migration {

	public function up()
	{
		// Check if the index already exists
		$exists = $this->db->query("SHOW INDEX FROM `sas_user_activity_etl` WHERE Key_name = 'idx_unique_course_subject_date'");
		if (!$exists || $exists->num_rows() === 0) {
			// Add unique constraint to prevent duplicates based on course_id, subject_id, and extraction_date
			$this->db->query("ALTER TABLE `sas_user_activity_etl` ADD UNIQUE INDEX `idx_unique_course_subject_date` (`course_id`, `subject_id`, `extraction_date`)");
		}
	}

	public function down()
	{
		// Remove the unique constraint if it exists
		$exists = $this->db->query("SHOW INDEX FROM `sas_user_activity_etl` WHERE Key_name = 'idx_unique_course_subject_date'");
		if ($exists && $exists->num_rows() > 0) {
			$this->db->query("ALTER TABLE `sas_user_activity_etl` DROP INDEX `idx_unique_course_subject_date`");
		}
	}
}