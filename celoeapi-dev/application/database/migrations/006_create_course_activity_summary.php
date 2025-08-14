<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_course_activity_summary extends CI_Migration {

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
            'total_activities' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'total_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'total_participants' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'active_days' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'last_activity_date' => [
                'type' => 'DATE',
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
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key('course_id_extraction_date', FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('course_activity_summary', TRUE);
    }

    public function down()
    {
        $this->dbforge->drop_table('course_activity_summary');
    }
} 