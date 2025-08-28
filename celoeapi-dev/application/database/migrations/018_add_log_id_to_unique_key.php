<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_log_id_to_unique_key extends CI_Migration {

    public function up()
    {
        // Drop the current unique key
        $this->db->query("ALTER TABLE `sp_etl_detail` DROP INDEX `uk_user_course_module_object_timecreated_date`");
        
        // Add log_id column
        $this->db->query("
            ALTER TABLE `sp_etl_detail` 
            ADD COLUMN `log_id` bigint NULL COMMENT 'Moodle log entry ID' 
            AFTER `timecreated`
        ");
        
        // Add new unique key that includes log_id
        $this->db->query("
            ALTER TABLE `sp_etl_detail` 
            ADD UNIQUE KEY `uk_user_course_module_object_timecreated_log_date` 
            (`user_id`, `course_id`, `module_type`, `object_id`, `timecreated`, `log_id`, `extraction_date`)
        ");
        
        echo "✅ Added log_id column and updated unique key constraint\n";
    }

    public function down()
    {
        // Drop the new unique key
        $this->db->query("ALTER TABLE `sp_etl_detail` DROP INDEX `uk_user_course_module_object_timecreated_log_date`");
        
        // Remove log_id column
        $this->db->query("ALTER TABLE `sp_etl_detail` DROP COLUMN `log_id`");
        
        // Add back the old unique key
        $this->db->query("
            ALTER TABLE `sp_etl_detail` 
            ADD UNIQUE KEY `uk_user_course_module_object_timecreated_date` 
            (`user_id`, `course_id`, `module_type`, `object_id`, `timecreated`, `extraction_date`)
        ");
        
        echo "✅ Restored old structure\n";
    }
}
