<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_Unique_Constraint_User_Activity_Etl extends CI_Migration {

    public function up()
    {
        // Add unique constraint to prevent duplicates based on course_id, id_number, and extraction_date
        $this->db->query("ALTER TABLE `user_activity_etl` ADD UNIQUE INDEX `idx_unique_course_id_number_date` (`course_id`, `id_number`, `extraction_date`)");
    }

    public function down()
    {
        // Remove the unique constraint
        $this->db->query("ALTER TABLE `user_activity_etl` DROP INDEX `idx_unique_course_id_number_date`");
    }
}