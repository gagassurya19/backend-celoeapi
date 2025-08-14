<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_activity_counts_etl extends CI_Migration {

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
            'file_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'video_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'forum_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'quiz_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'assignment_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'url_views' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'active_days' => [
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
        $this->dbforge->add_key(['courseid', 'extraction_date'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('activity_counts_etl');
    }

    public function down()
    {
        $this->dbforge->drop_table('activity_counts_etl');
    }
} 