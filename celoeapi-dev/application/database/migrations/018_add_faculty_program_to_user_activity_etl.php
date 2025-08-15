<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_Faculty_Program_To_User_Activity_Etl extends CI_Migration {

	public function up()
	{
		// Check if the table exists first
		if (!$this->db->table_exists('user_activity_etl') && !$this->db->table_exists('sas_user_activity_etl')) {
			echo "Warning: Neither user_activity_etl nor sas_user_activity_etl table exists. Skipping column addition.\n";
			return;
		}
		
		// Determine which table name to use
		$table_name = $this->db->table_exists('user_activity_etl') ? 'user_activity_etl' : 'sas_user_activity_etl';
		
		$fields = array();
		if (!$this->db->field_exists('faculty_id', $table_name)) {
			$fields['faculty_id'] = array(
				'type' => 'INT',
				'constraint' => 11,
				'null' => TRUE
			);
		}
		if (!$this->db->field_exists('program_id', $table_name)) {
			$fields['program_id'] = array(
				'type' => 'INT',
				'constraint' => 11,
				'null' => TRUE
			);
		}

		if (!empty($fields)) {
			$this->dbforge->add_column($table_name, $fields);
		}
	}

	public function down()
	{
		// Check if the table exists first
		if (!$this->db->table_exists('user_activity_etl') && !$this->db->table_exists('sas_user_activity_etl')) {
			echo "Warning: Neither user_activity_etl nor sas_user_activity_etl table exists. Skipping column removal.\n";
			return;
		}
		
		// Determine which table name to use
		$table_name = $this->db->table_exists('user_activity_etl') ? 'user_activity_etl' : 'sas_user_activity_etl';
		
		if ($this->db->field_exists('faculty_id', $table_name)) {
			$this->dbforge->drop_column($table_name, 'faculty_id');
		}
		if ($this->db->field_exists('program_id', $table_name)) {
			$this->dbforge->drop_column($table_name, 'program_id');
		}
	}
} 