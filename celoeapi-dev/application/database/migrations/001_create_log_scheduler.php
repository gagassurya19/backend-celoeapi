<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_log_scheduler extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'auto_increment' => TRUE
            ],
            'batch_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => FALSE
            ],
            'offset' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE,
                'default' => 0
            ],
            'numrow' => [
                'type' => 'INT',
                'constraint' => 10,
                'null' => FALSE,
                'default' => 0
            ],
            'status' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => FALSE,
                'comment' => '1=finished, 2=inprogress, 3=failed'
            ],
            'limit_size' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 1000
            ],
            'start_date' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ],
            'end_date' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ],
            'error_details' => [
                'type' => 'TEXT',
                'null' => TRUE
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ]
        ]);

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_key('status');
        $this->dbforge->add_key('start_date');
        $this->dbforge->add_key('end_date');
        
        $this->dbforge->create_table('log_scheduler');
    }

    public function down()
    {
        $this->dbforge->drop_table('log_scheduler');
    }
}