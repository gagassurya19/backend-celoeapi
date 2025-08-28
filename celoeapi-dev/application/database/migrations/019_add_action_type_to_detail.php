<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_action_type_to_detail extends CI_Migration {

    public function up()
    {
        // Add action_type column to sp_etl_detail table
        $this->db->query("
            ALTER TABLE `sp_etl_detail` 
            ADD COLUMN `action_type` varchar(50) NULL COMMENT 'Action type from Moodle log (viewed, created, updated, etc.)' 
            AFTER `log_id`
        ");
        
        echo "✅ Added action_type column to sp_etl_detail table\n";
    }

    public function down()
    {
        // Remove action_type column from sp_etl_detail table
        $this->db->query("ALTER TABLE `sp_etl_detail` DROP COLUMN `action_type`");
        
        echo "✅ Removed action_type column from sp_etl_detail table\n";
    }
}
