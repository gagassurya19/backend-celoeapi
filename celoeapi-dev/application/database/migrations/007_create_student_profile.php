<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_student_profile extends CI_Migration {

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
            'id_number' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ],
            'firstname' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ],
            'lastname' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ],
            'department' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'institution' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ],
            'city' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => TRUE
            ],
            'country' => [
                'type' => 'VARCHAR',
                'constraint' => 2,
                'null' => TRUE
            ],
            'timezone' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ],
            'lastaccess' => [
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
        $this->dbforge->add_key('id_number');
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key(['user_id', 'extraction_date'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('student_profile');
    }

    public function down()
    {
        $this->dbforge->drop_table('student_profile');
    }
}