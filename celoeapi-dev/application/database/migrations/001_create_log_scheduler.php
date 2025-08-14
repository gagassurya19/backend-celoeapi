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
            'start_date' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ],
            'end_date' => [
                'type' => 'DATETIME',
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
        
        // Add IF NOT EXISTS to avoid error when table already exists
        $this->dbforge->create_table('log_scheduler', TRUE);
    }

    public function down()
    {
        $this->dbforge->drop_table('log_scheduler');
    }
}