<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Rename_Sas_User_Activity_Etl_To_User_Activity_Etl extends CI_Migration {

	public function up()
	{
		// Check if the source table exists
		if ($this->db->table_exists('sas_user_activity_etl')) {
			// Rename the table from sas_user_activity_etl to user_activity_etl
			$this->db->query("RENAME TABLE `sas_user_activity_etl` TO `user_activity_etl`");
			echo "Table renamed from sas_user_activity_etl to user_activity_etl successfully.\n";
		} else {
			echo "Warning: Table sas_user_activity_etl does not exist.\n";
		}
	}

	public function down()
	{
		// Check if the target table exists
		if ($this->db->table_exists('user_activity_etl')) {
			// Rename the table back from user_activity_etl to sas_user_activity_etl
			$this->db->query("RENAME TABLE `user_activity_etl` TO `sas_user_activity_etl`");
			echo "Table renamed back from user_activity_etl to sas_user_activity_etl successfully.\n";
		} else {
			echo "Warning: Table user_activity_etl does not exist.\n";
		}
	}
}
