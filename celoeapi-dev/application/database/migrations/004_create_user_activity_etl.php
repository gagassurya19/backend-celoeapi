<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_user_activity_etl extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ),
            'course_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ),
            'faculty_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'null' => TRUE
            ),
            'program_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'null' => TRUE
            ),
            'subject_id' => array(
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ),
            'course_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ),
            'course_shortname' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ),
            'num_teachers' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'num_students' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'file_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'video_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'forum_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'quiz_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'assignment_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'url_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'total_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'avg_activity_per_student_per_day' => array(
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => TRUE,
                'default' => NULL
            ),
            'active_days' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'extraction_date' => array(
                'type' => 'DATE',
                'null' => FALSE
            ),
            'created_at' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            ),
            'updated_at' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            )
        ));

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_key('course_id');
        $this->dbforge->add_key('faculty_id');
        $this->dbforge->add_key('program_id');
        $this->dbforge->add_key('subject_id');
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key(['course_id', 'subject_id', 'extraction_date'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('sas_user_activity_etl', TRUE);
    }

    public function down()
    {
        $this->dbforge->drop_table('sas_user_activity_etl');
    }
}