<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Fix_accessed_count_etl extends CI_Migration {

    public function up()
    {
        // Fix accessed_count for assignments based on submission_count
        $this->db->query("
            UPDATE cp_activity_summary 
            SET accessed_count = submission_count 
            WHERE activity_type = 'assign' 
            AND accessed_count IS NULL 
            AND submission_count > 0
        ");

        // Fix accessed_count for quizzes based on attempted_count
        $this->db->query("
            UPDATE cp_activity_summary 
            SET accessed_count = attempted_count 
            WHERE activity_type = 'quiz' 
            AND accessed_count IS NULL 
            AND attempted_count > 0
        ");

        // Fix accessed_count for resources (keep existing logic)
        $this->db->query("
            UPDATE cp_activity_summary 
            SET accessed_count = COALESCE(accessed_count, 0) 
            WHERE activity_type = 'resource' 
            AND accessed_count IS NULL
        ");

        // Set accessed_count = 1 for activities that have any interaction but no access count
        $this->db->query("
            UPDATE cp_activity_summary 
            SET accessed_count = 1 
            WHERE accessed_count IS NULL 
            AND (
                submission_count > 0 
                OR attempted_count > 0 
                OR graded_count > 0
            )
        ");

        // Set accessed_count = 0 for remaining activities with no interaction
        $this->db->query("
            UPDATE cp_activity_summary 
            SET accessed_count = 0 
            WHERE accessed_count IS NULL
        ");
    }

    public function down()
    {
        // Reset accessed_count to NULL for all records
        $this->db->query("
            UPDATE cp_activity_summary 
            SET accessed_count = NULL
        ");
    }
}
