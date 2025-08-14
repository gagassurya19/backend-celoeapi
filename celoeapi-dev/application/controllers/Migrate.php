<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migrate extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        
        // Restrict to CLI only - migrations are for server setup
        if (!$this->is_cli_request()) {
            show_error('This controller can only be accessed via command line for security reasons.');
            return;
        }
        
        $this->load->library('migration');
        $this->load->config('migration');
    }
    
    private function is_cli_request()
    {
        return (php_sapi_name() === 'cli' OR defined('STDIN'));
    }

    // CLI Commands
    public function run()
    {
        if ($this->migration->latest() === FALSE) {
            echo "Migration failed: " . $this->migration->error_string() . "\n";
            exit(1);
        } else {
            $current = $this->migration->current();
            echo "Migration completed successfully!\n";
            echo "Updated to version: " . str_pad($current, 3, '0', STR_PAD_LEFT) . "\n";
        }
    }
    
    public function status()
    {
        $current = $this->migration->current();
        echo "Current migration version: " . str_pad($current, 3, '0', STR_PAD_LEFT) . "\n\n";
        
        $migrations_path = $this->config->item('migration_path');
        $migration_files = glob($migrations_path . '*.php');
        
        echo "Available migrations:\n";
        foreach ($migration_files as $file) {
            $filename = basename($file);
            echo "  - {$filename}\n";
        }
        echo "\n";
    }
    
    public function test()
    {
        echo "=== Migration System Test ===\n\n";
        
        // Test database connection
        echo "Database Configuration:\n";
        echo "  Host: " . $this->config->item('migration_db_host') . "\n";
        echo "  Username: " . $this->config->item('migration_db_username') . "\n";
        echo "  Database: " . $this->config->item('migration_db_name') . "\n\n";
        
        // Test migration path
        $migrations_path = $this->config->item('migration_path');
        echo "Migration Path: {$migrations_path}\n";
        echo "Path exists: " . (is_dir($migrations_path) ? 'Yes' : 'No') . "\n";
        
        // Count migration files
        $migration_files = glob($migrations_path . '*.php');
        echo "Migration files found: " . count($migration_files) . "\n\n";
        
        // Show current status
        $current = $this->migration->current();
        echo "Current migration version: " . str_pad($current, 3, '0', STR_PAD_LEFT) . "\n";
        
        echo "\n=== Test Complete ===\n";
    }
    
    public function create($name = null)
    {
        if (!$name) {
            echo "Error: Migration name is required.\n";
            echo "Usage: ./migrate.sh create <migration_name>\n";
            exit(1);
        }
        
        $migrations_path = $this->config->item('migration_path');
        $current_version = $this->migration->current();
        $next_version = str_pad($current_version + 1, 3, '0', STR_PAD_LEFT);
        
        $filename = $next_version . '_' . $name . '.php';
        $filepath = $migrations_path . $filename;
        
        if (file_exists($filepath)) {
            echo "Error: Migration file already exists: {$filename}\n";
            exit(1);
        }
        
        $template = $this->get_migration_template($name);
        
        if (file_put_contents($filepath, $template)) {
            echo "Migration file created: {$filename}\n";
            echo "Path: {$filepath}\n";
        } else {
            echo "Error: Failed to create migration file.\n";
            exit(1);
        }
    }
    
    private function get_migration_template($name)
    {
        $class_name = 'Migration_' . str_replace(' ', '_', ucwords(str_replace('_', ' ', $name)));
        
        return "<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class {$class_name} extends CI_Migration {

    public function up()
    {
        // TODO: Add your migration code here
        // Example:
        // \$this->dbforge->add_field(array(
        //     'id' => array(
        //         'type' => 'INT',
        //         'constraint' => 11,
        //         'unsigned' => TRUE,
        //         'auto_increment' => TRUE
        //     )
        // ));
        // \$this->dbforge->add_key('id', TRUE);
        // \$this->dbforge->create_table('your_table_name');
    }

    public function down()
    {
        // TODO: Add your rollback code here
        // Example:
        // \$this->dbforge->drop_table('your_table_name');
    }
}";
    }
    
    public function help()
    {
        $this->show_usage();
    }
    
    private function show_usage()
    {
        echo "ETL Migration CLI Commands:\n\n";
        echo "  ./migrate.sh run              - Run all pending migrations\n";
        echo "  ./migrate.sh status           - Show current migration status\n";
        echo "  ./migrate.sh create <name>    - Create new migration file\n";
        echo "  ./migrate.sh test             - Test migration system configuration\n";
        echo "  ./migrate.sh help             - Show this help message\n\n";
    }
}