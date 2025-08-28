<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_timecreated_to_detail extends CI_Migration {

    public function up()
    {
        // Add timecreated column to sp_etl_detail table
        $this->db->query("
            ALTER TABLE `sp_etl_detail` 
            ADD COLUMN `timecreated` bigint NULL COMMENT 'Unix timestamp from Moodle log' 
            AFTER `grade`
        ");
        
        echo "✅ Added timecreated column to sp_etl_detail table\n";
    }

    public function down()
    {
        // Remove timecreated column from sp_etl_detail table
        $this->db->query("ALTER TABLE `sp_etl_detail` DROP COLUMN `timecreated`");
        
        echo "✅ Removed timecreated column from sp_etl_detail table\n";
    }
}
