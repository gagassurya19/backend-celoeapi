<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_student_resource_access extends CI_Migration {

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
            'resource_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ],
            'resource_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'resource_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => TRUE
            ],
            'access_time' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'access_date' => [
                'type' => 'DATE',
                'null' => FALSE
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => TRUE
            ],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => TRUE
            ],
            'session_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
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
        $this->dbforge->add_key('resource_id');
        $this->dbforge->add_key('access_time');
        $this->dbforge->add_key('access_date');
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key(['user_id', 'resource_id', 'access_time'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('student_resource_access', TRUE);
    }

    public function down()
    {
        $this->dbforge->drop_table('student_resource_access');
    }
}