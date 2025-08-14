<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_student_assignment_detail extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ],
            'course_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ],
            'assignment_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ],
            'assignment_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'submission_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ],
            'timecreated' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'timemodified' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => TRUE
            ],
            'attemptnumber' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'latest' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => FALSE
            ],
            'grade' => [
                'type' => 'DECIMAL',
                'constraint' => '10,5',
                'null' => TRUE
            ],
            'grade_overridden' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => FALSE
            ],
            'grade_overridden_date' => [
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
        $this->dbforge->add_key('user_id');
        $this->dbforge->add_key('course_id');
        $this->dbforge->add_key('assignment_id');
        $this->dbforge->add_key('submission_id');
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key(['user_id', 'assignment_id', 'attemptnumber'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('student_assignment_detail');
    }

    public function down()
    {
        $this->dbforge->drop_table('student_assignment_detail');
    }
}