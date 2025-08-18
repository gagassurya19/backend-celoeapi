<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_faculty_program_to_user_activity_etl extends CI_Migration {

	public function up()
	{
		$fields = array();
		if (!$this->db->field_exists('faculty_id', 'sas_user_activity_etl')) {
			$fields['faculty_id'] = array(
				'type' => 'INT',
				'constraint' => 11,
				'null' => TRUE
			);
		}
		if (!$this->db->field_exists('program_id', 'sas_user_activity_etl')) {
			$fields['program_id'] = array(
				'type' => 'INT',
				'constraint' => 11,
				'null' => TRUE
			);
		}

		if (!empty($fields)) {
			$this->load->dbforge();
			$this->dbforge->add_column('sas_user_activity_etl', $fields);
		}
	}

	public function down()
	{
		$this->load->dbforge();
		if ($this->db->field_exists('faculty_id', 'sas_user_activity_etl')) {
			$this->dbforge->drop_column('sas_user_activity_etl', 'faculty_id');
		}
		if ($this->db->field_exists('program_id', 'sas_user_activity_etl')) {
			$this->dbforge->drop_column('sas_user_activity_etl', 'program_id');
		}
	}
}
