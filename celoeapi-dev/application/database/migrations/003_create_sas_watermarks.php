<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sas_watermarks extends CI_Migration {

    public function up()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `sas_etl_watermarks` (
            `process_name` varchar(100) NOT NULL,
            `last_date` date DEFAULT NULL,
            `last_timecreated` bigint DEFAULT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`process_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->db->query($sql);
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS `sas_etl_watermarks`");
    }
}


