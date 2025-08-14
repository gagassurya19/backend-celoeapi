<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_student_quiz_detail extends CI_Migration {

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
            'quiz_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ],
            'quiz_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'attempt' => [
                'type' => 'INT',
                'constraint' => 6,
                'null' => FALSE
            ],
            'uniqueid' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'layout' => [
                'type' => 'TEXT',
                'null' => TRUE
            ],
            'currentpage' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'preview' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => FALSE
            ],
            'state' => [
                'type' => 'VARCHAR',
                'constraint' => 16,
                'null' => FALSE
            ],
            'timestart' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'timefinish' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'timemodified' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'timecheckstate' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => TRUE
            ],
            'sumgrades' => [
                'type' => 'DECIMAL',
                'constraint' => '10,5',
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
        $this->dbforge->add_key('quiz_id');
        $this->dbforge->add_key('uniqueid');
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key(['user_id', 'quiz_id', 'attempt'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('student_quiz_detail');
    }

    public function down()
    {
        $this->dbforge->drop_table('student_quiz_detail');
    }
} 