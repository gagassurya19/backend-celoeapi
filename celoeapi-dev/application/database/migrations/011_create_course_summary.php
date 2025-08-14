<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_course_summary extends CI_Migration {

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
            'course_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'course_shortname' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'category_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => TRUE
            ],
            'category_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'start_date' => [
                'type' => 'DATE',
                'null' => TRUE
            ],
            'end_date' => [
                'type' => 'DATE',
                'null' => TRUE
            ],
            'enrollment_start' => [
                'type' => 'DATE',
                'null' => TRUE
            ],
            'enrollment_end' => [
                'type' => 'DATE',
                'null' => TRUE
            ],
            'total_students' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'total_teachers' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'total_activities' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'total_resources' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'course_format' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => TRUE
            ],
            'course_visible' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => FALSE
            ],
            'time_created' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => TRUE
            ],
            'time_modified' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => TRUE
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
        $this->dbforge->add_key('category_id');
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key(['course_id', 'extraction_date'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('course_summary', TRUE);
    }

    public function down()
    {
        $this->dbforge->drop_table('course_summary');
    }
}