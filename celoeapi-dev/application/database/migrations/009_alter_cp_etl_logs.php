<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Alter_cp_etl_logs extends CI_Migration {

    public function up()
    {
        // Add columns if not exist
        $columns = $this->db->list_fields('cp_etl_logs');

        if (!in_array('type', $columns)) {
            $this->db->query("ALTER TABLE `cp_etl_logs` ADD COLUMN `type` varchar(32) NOT NULL DEFAULT 'run_etl' AFTER `numrow`");
        }
        if (!in_array('message', $columns)) {
            $this->db->query("ALTER TABLE `cp_etl_logs` ADD COLUMN `message` text NULL AFTER `type`");
        }
        if (!in_array('requested_start_date', $columns)) {
            $this->db->query("ALTER TABLE `cp_etl_logs` ADD COLUMN `requested_start_date` date NULL AFTER `message`");
        }
        if (!in_array('extracted_start_date', $columns)) {
            $this->db->query("ALTER TABLE `cp_etl_logs` ADD COLUMN `extracted_start_date` date NULL AFTER `requested_start_date`");
        }
        if (!in_array('extracted_end_date', $columns)) {
            $this->db->query("ALTER TABLE `cp_etl_logs` ADD COLUMN `extracted_end_date` date NULL AFTER `extracted_start_date`");
        }
        if (!in_array('duration_seconds', $columns)) {
            $this->db->query("ALTER TABLE `cp_etl_logs` ADD COLUMN `duration_seconds` int NULL AFTER `end_date`");
        }
    }

    public function down()
    {
        // No down migration to avoid dropping data; keeping columns
    }
}


