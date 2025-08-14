<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_Faculty_Program_To_User_Activity_Etl extends CI_Migration {

	public function up()
	{
		$fields = array();
		if (!$this->db->field_exists('faculty_id', 'user_activity_etl')) {
			$fields['faculty_id'] = array(
				'type' => 'INT',
				'constraint' => 11,
				'null' => TRUE
			);
		}
		if (!$this->db->field_exists('program_id', 'user_activity_etl')) {
			$fields['program_id'] = array(
				'type' => 'INT',
				'constraint' => 11,
				'null' => TRUE
			);
		}

		if (!empty($fields)) {
			$this->dbforge->add_column('user_activity_etl', $fields);
		}
	}

	public function down()
	{
		if ($this->db->field_exists('faculty_id', 'user_activity_etl')) {
			$this->dbforge->drop_column('user_activity_etl', 'faculty_id');
		}
		if ($this->db->field_exists('program_id', 'user_activity_etl')) {
			$this->dbforge->drop_column('user_activity_etl', 'program_id');
		}
	}
} 