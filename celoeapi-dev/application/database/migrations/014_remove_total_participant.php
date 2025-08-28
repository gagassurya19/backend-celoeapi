<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Remove_total_participant extends CI_Migration {

    public function up()
    {
        // Remove total_participant column from sp_etl_summary table
        $this->db->query("ALTER TABLE `sp_etl_summary` DROP COLUMN `total_participant`");
        
        echo "✅ Removed total_participant column from sp_etl_summary table\n";
    }

    public function down()
    {
        // Add back total_participant column to sp_etl_summary table
        $this->db->query("
            ALTER TABLE `sp_etl_summary` 
            ADD COLUMN `total_participant` int NOT NULL DEFAULT 0 COMMENT 'Total number of course participations' 
            AFTER `total_activities`
        ");
        
        echo "✅ Added back total_participant column to sp_etl_summary table\n";
    }
}
