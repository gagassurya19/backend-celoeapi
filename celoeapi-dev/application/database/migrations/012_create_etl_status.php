<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_etl_status extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ],
            'process_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => FALSE
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['running', 'completed', 'failed', 'stopped'],
                'null' => FALSE,
                'default' => 'running'
            ],
            'start_time' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ],
            'end_time' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ],
            'duration_seconds' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => TRUE
            ],
            'records_processed' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'records_total' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'progress_percentage' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => 0.00
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => TRUE
            ],
            'error_details' => [
                'type' => 'TEXT',
                'null' => TRUE
            ],
            'extraction_date' => [
                'type' => 'DATE',
                'null' => TRUE
            ],
            'parameters' => [
                'type' => 'TEXT',
                'null' => TRUE,
                'comment' => 'JSON parameters used for this ETL run'
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
        $this->dbforge->add_key('process_name');
        $this->dbforge->add_key('status');
        $this->dbforge->add_key('start_time');
        $this->dbforge->add_key('extraction_date');
        $this->dbforge->add_key(['process_name', 'extraction_date'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('etl_status');
    }

    public function down()
    {
        $this->dbforge->drop_table('etl_status');
    }
} 