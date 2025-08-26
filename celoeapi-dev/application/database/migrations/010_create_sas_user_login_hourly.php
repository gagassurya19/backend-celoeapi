<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sas_user_login_hourly extends CI_Migration {

    public function up() {
        // Buat tabel sas_user_login_hourly untuk tracking user login per jam
        $this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE,
            ],
            'extraction_date' => [
                'type' => 'DATE',
                'null' => FALSE,
                'comment' => 'Tanggal ekstraksi data',
            ],
            'hour' => [
                'type' => 'TINYINT',
                'constraint' => 2,
                'null' => FALSE,
                'comment' => 'Jam aktivitas (0-23)',
            ],
            'user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'null' => FALSE,
                'comment' => 'ID user dari Moodle',
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => FALSE,
                'comment' => 'Username untuk kemudahan query',
            ],
            'full_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => FALSE,
                'comment' => 'Nama lengkap user',
            ],
            'role_type' => [
                'type' => "ENUM('student', 'teacher')",
                'null' => FALSE,
                'comment' => 'Role user (student atau teacher)',
            ],
            'login_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 1,
                'comment' => 'Count login di jam tersebut',
            ],
            'first_login_time' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'null' => TRUE,
                'comment' => 'Timestamp login pertama di jam tersebut',
            ],
            'last_login_time' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'null' => TRUE,
                'comment' => 'Timestamp login terakhir di jam tersebut',
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'comment' => 'Status aktif di jam tersebut',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => FALSE,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => FALSE,
            ],
        ]);

        // Tambahkan primary key
        $this->dbforge->add_key('id', TRUE);

        // Tambahkan unique key untuk mencegah duplikasi data
        $this->dbforge->add_field("UNIQUE KEY `unique_user_hour` (`user_id`, `extraction_date`, `hour`)");

        // Tambahkan index untuk optimasi query
        $this->dbforge->add_field("KEY `idx_extraction_date` (`extraction_date`)");
        $this->dbforge->add_field("KEY `idx_hour` (`hour`)");
        $this->dbforge->add_field("KEY `idx_role_type` (`role_type`)");
        $this->dbforge->add_field("KEY `idx_user_id` (`user_id`)");
        $this->dbforge->add_field("KEY `idx_date_hour` (`extraction_date`, `hour`)");

        // Buat tabel
        $this->dbforge->create_table('sas_user_login_hourly', TRUE);

        echo "✅ Tabel sas_user_login_hourly berhasil dibuat\n";
    }

    public function down() {
        // Hapus tabel jika rollback
        $this->dbforge->drop_table('sas_user_login_hourly', TRUE);
        echo "❌ Tabel sas_user_login_hourly berhasil dihapus\n";
    }
}
