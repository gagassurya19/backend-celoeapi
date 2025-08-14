<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_raw_log extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ],
            'time' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'userid' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'ip' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => FALSE
            ],
            'course' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'module' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => FALSE
            ],
            'cmid' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => FALSE
            ],
            'url' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => FALSE
            ],
            'info' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => FALSE
            ],
            'extraction_date' => [
                'type' => 'DATE',
                'null' => FALSE
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ]
        ]);

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_key('time');
        $this->dbforge->add_key('userid');
        $this->dbforge->add_key('course');
        $this->dbforge->add_key('module');
        $this->dbforge->add_key('extraction_date');
        
        $this->dbforge->create_table('raw_log', TRUE);
    }

    public function down()
    {
        $this->dbforge->drop_table('raw_log');
    }
}