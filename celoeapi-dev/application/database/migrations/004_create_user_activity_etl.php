<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_user_activity_etl extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ],
            'course_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ],
            'id_number' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ],
            'course_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'course_shortname' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ],
            'num_teachers' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'num_students' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'file_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'video_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'forum_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'quiz_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'assignment_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'url_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'total_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'avg_activity_per_student_per_day' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => TRUE
            ],
            'active_days' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'extraction_date' => [
                'type' => 'DATE',
                'null' => FALSE
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ]
        ]);

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_key('course_id');
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key(['course_id', 'extraction_date'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('user_activity_etl');
    }

    public function down()
    {
        $this->dbforge->drop_table('user_activity_etl');
    }
}