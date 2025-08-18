<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_user_counts_etl extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ],
            'courseid' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ],
            'num_students' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'num_teachers' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
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
        $this->dbforge->add_key('courseid');
        $this->dbforge->add_key('extraction_date');
        
        $this->dbforge->create_table('sas_user_counts_etl', TRUE);
    }

    public function down()
    {
        $this->dbforge->drop_table('sas_user_counts_etl');
    }
}