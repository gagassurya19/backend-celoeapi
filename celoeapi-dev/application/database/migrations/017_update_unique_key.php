<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Update_unique_key extends CI_Migration {

    public function up()
    {
        // Drop the old unique key
        $this->db->query("ALTER TABLE `sp_etl_detail` DROP INDEX `uk_user_course_module_object_date`");
        
        // Add new unique key that includes timecreated
        $this->db->query("
            ALTER TABLE `sp_etl_detail` 
            ADD UNIQUE KEY `uk_user_course_module_object_timecreated_date` 
            (`user_id`, `course_id`, `module_type`, `object_id`, `timecreated`, `extraction_date`)
        ");
        
        echo "✅ Updated unique key constraint to include timecreated\n";
    }

    public function down()
    {
        // Drop the new unique key
        $this->db->query("ALTER TABLE `sp_etl_detail` DROP INDEX `uk_user_course_module_object_timecreated_date`");
        
        // Add back the old unique key
        $this->db->query("
            ALTER TABLE `sp_etl_detail` 
            ADD UNIQUE KEY `uk_user_course_module_object_date` 
            (`user_id`, `course_id`, `module_type`, `object_id`, `extraction_date`)
        ");
        
        echo "✅ Restored old unique key constraint\n";
    }
}
