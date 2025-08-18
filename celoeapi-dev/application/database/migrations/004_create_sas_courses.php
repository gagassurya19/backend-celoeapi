<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sas_courses extends CI_Migration {

	public function up()
	{
		$sql = "
		CREATE TABLE IF NOT EXISTS `sas_courses` (
		  `course_id` int NOT NULL,
		  `subject_id` varchar(100) DEFAULT NULL,
		  `course_name` varchar(255) DEFAULT NULL,
		  `course_shortname` varchar(255) DEFAULT NULL,
		  `faculty_id` int DEFAULT NULL,
		  `program_id` int DEFAULT NULL,
		  `visible` tinyint(1) NOT NULL DEFAULT '1',
		  `created_at` datetime DEFAULT NULL,
		  `updated_at` datetime DEFAULT NULL,
		  PRIMARY KEY (`course_id`),
		  UNIQUE KEY `uk_subject_id` (`subject_id`),
		  KEY `idx_program_id` (`program_id`),
		  KEY `idx_faculty_id` (`faculty_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

		$this->db->query($sql);
	}

	public function down()
	{
		$this->db->query("DROP TABLE IF EXISTS `sas_courses`");
	}
}


