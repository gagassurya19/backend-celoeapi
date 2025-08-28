<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Modify_sp_etl_detail extends CI_Migration {

    public function up()
    {
        // Remove total_activities column from sp_etl_detail table
        $this->db->query("ALTER TABLE `sp_etl_detail` DROP COLUMN `total_activities`");
        
        // Make object_id column nullable
        $this->db->query("ALTER TABLE `sp_etl_detail` MODIFY COLUMN `object_id` bigint NULL COMMENT 'Object ID (forum_id, assign_id, quiz_id)'");
        
        echo "✅ Removed total_activities column and made object_id nullable in sp_etl_detail table\n";
    }

    public function down()
    {
        // Add back total_activities column to sp_etl_detail table
        $this->db->query("
            ALTER TABLE `sp_etl_detail` 
            ADD COLUMN `total_activities` int NOT NULL DEFAULT 0 COMMENT 'Total activities for this module type' 
            AFTER `object_id`
        ");
        
        // Make object_id column NOT NULL again
        $this->db->query("ALTER TABLE `sp_etl_detail` MODIFY COLUMN `object_id` bigint NOT NULL COMMENT 'Object ID (forum_id, assign_id, quiz_id)'");
        
        echo "✅ Added back total_activities column and made object_id NOT NULL in sp_etl_detail table\n";
    }
}
