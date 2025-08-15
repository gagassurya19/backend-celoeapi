<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_Category_To_Shared_Log_Scheduler extends CI_Migration {

	public function up()
	{
		// Check if the table exists first
		if ($this->db->table_exists('shared_log_scheduler')) {
			// Add category field if it doesn't exist
			if (!$this->db->field_exists('category', 'shared_log_scheduler')) {
				$fields = array(
					'category' => array(
						'type' => 'VARCHAR',
						'constraint' => 100,
						'null' => TRUE,
						'default' => 'general',
						'comment' => 'Category of the log (etl, api, system, etc.)'
					)
				);
				
				$this->dbforge->add_column('shared_log_scheduler', $fields);
				echo "Field 'category' added to shared_log_scheduler table successfully.\n";
			} else {
				echo "Field 'category' already exists in shared_log_scheduler table.\n";
			}
		} else {
			echo "Warning: Table shared_log_scheduler does not exist.\n";
		}
	}

	public function down()
	{
		// Check if the table exists first
		if ($this->db->table_exists('shared_log_scheduler')) {
			// Remove the category field if it exists
			if ($this->db->field_exists('category', 'shared_log_scheduler')) {
				$this->dbforge->drop_column('shared_log_scheduler', 'category');
				echo "Field 'category' removed from shared_log_scheduler table successfully.\n";
			} else {
				echo "Field 'category' does not exist in shared_log_scheduler table.\n";
			}
		} else {
			echo "Warning: Table shared_log_scheduler does not exist.\n";
		}
	}
}
