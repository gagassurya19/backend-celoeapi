<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Fix_Etl_Status_Columns extends CI_Migration {

    public function up()
    {
        // TODO: Add your migration code here
        // Example:
        // $this->dbforge->add_field(array(
        //     'id' => array(
        //         'type' => 'INT',
        //         'constraint' => 11,
        //         'unsigned' => TRUE,
        //         'auto_increment' => TRUE
        //     )
        // ));
        // $this->dbforge->add_key('id', TRUE);
        // $this->dbforge->create_table('your_table_name');
    }

    public function down()
    {
        // TODO: Add your rollback code here
        // Example:
        // $this->dbforge->drop_table('your_table_name');
    }
}