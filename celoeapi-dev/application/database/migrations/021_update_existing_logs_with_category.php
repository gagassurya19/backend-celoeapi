<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Update_Existing_Logs_With_Category extends CI_Migration {

	public function up()
	{
		// Check if the table exists first
		if ($this->db->table_exists('shared_log_scheduler')) {
			// Update existing logs with category 'etl_general' if they don't have a category
			$this->db->where('category IS NULL OR category = "general"');
			$this->db->update('shared_log_scheduler', ['category' => 'etl_general']);
			
			$affected_rows = $this->db->affected_rows();
			echo "Updated {$affected_rows} existing logs with category 'etl_general'.\n";
			
			// Update specific logs based on their data patterns
			// You can add more specific category assignments here if needed
			
		} else {
			echo "Warning: Table shared_log_scheduler does not exist.\n";
		}
	}

	public function down()
	{
		// Check if the table exists first
		if ($this->db->table_exists('shared_log_scheduler')) {
			// Revert category back to 'general'
			$this->db->where('category', 'etl_general');
			$this->db->update('shared_log_scheduler', ['category' => 'general']);
			
			$affected_rows = $this->db->affected_rows();
			echo "Reverted {$affected_rows} logs back to category 'general'.\n";
		} else {
			echo "Warning: Table shared_log_scheduler does not exist.\n";
		}
	}
}
