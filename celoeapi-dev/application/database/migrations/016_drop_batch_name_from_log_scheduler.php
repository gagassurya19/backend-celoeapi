<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Drop_batch_name_from_log_scheduler extends CI_Migration {

	public function up()
	{
		if ($this->db->field_exists('batch_name', 'log_scheduler')) {
			$this->dbforge->drop_column('log_scheduler', 'batch_name');
		}
	}

	public function down()
	{
		if (!$this->db->field_exists('batch_name', 'log_scheduler')) {
			$fields = [
				'batch_name' => [
					'type' => 'VARCHAR',
					'constraint' => 100,
					'null' => FALSE
				]
			];
			$this->dbforge->add_column('log_scheduler', $fields);
		}
	}
} 