# 🔄 ETL Migration Setup Guide

This guide shows how to set up the ETL database schema using CodeIgniter CLI migrations for proper version control and database management.

## 📋 Table of Contents

1. [Migration Overview](#migration-overview)
2. [CodeIgniter Migration Files](#codeigniter-migration-files)
3. [CLI Migration Controller](#cli-migration-controller)
4. [Setup Procedures](#setup-procedures)
5. [CLI Migration Commands](#cli-migration-commands)
6. [Rollback Procedures](#rollback-procedures)
7. [Version Control](#version-control)

---

## 🔄 Migration Overview

### What are Migrations?

CodeIgniter migrations are version-controlled database schema changes that allow you to:
- Track database schema evolution using PHP classes
- Deploy consistent database structures across environments
- Rollback changes using built-in CI migration library
- Collaborate with team members safely
- Use environment variables for dynamic configuration
- **CLI-only access for security** - migrations are for initial server setup

### ETL Database Architecture

```
celoeapi (ETL Database)
├── application/database/migrations/
│   ├── 001_create_log_scheduler.php
│   ├── 002_create_activity_counts_etl.php
│   ├── 003_create_user_counts_etl.php
│   ├── 004_create_user_activity_etl.php
│   ├── 005_create_raw_log.php
│   ├── 006_create_course_activity_summary.php
│   ├── 007_create_student_profile.php
│   ├── 008_create_student_quiz_detail.php
│   ├── 009_create_student_assignment_detail.php
│   ├── 010_create_student_resource_access.php
│   ├── 011_create_course_summary.php
│   └── 012_create_etl_status.php
├── application/controllers/Migrate.php (CLI-only)
├── migrate.sh (CLI wrapper script)
└── migration_tracker (auto-created by CI)
```

---

## 📁 CodeIgniter Migration Files

### Migration 001: Create Log Scheduler Table

File: `celoeapi-dev/application/database/migrations/001_create_log_scheduler.php`

```php
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
```

### Migration 002: Create Activity Counts ETL Table

File: `celoeapi-dev/application/database/migrations/002_create_activity_counts_etl.php`

```php
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
```

### Migration 003: Create User Counts ETL Table

File: `celoeapi-dev/application/database/migrations/003_create_user_counts_etl.php`

```php
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
        $this->dbforge->add_key(['courseid', 'extraction_date'], FALSE, TRUE); // Unique key
        
        $this->dbforge->create_table('user_counts_etl');
    }

    public function down()
    {
        $this->dbforge->drop_table('user_counts_etl');
    }
}
```

### Migration 004: Create User Activity ETL Table

File: `celoeapi-dev/application/database/migrations/004_create_user_activity_etl.php`

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_user_activity_etl extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ),
            'course_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'null' => FALSE
            ),
            'id_number' => array(
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => TRUE
            ),
            'course_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ),
            'course_shortname' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => TRUE
            ),
            'num_teachers' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'num_students' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'file_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'video_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'forum_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'quiz_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'assignment_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'url_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'total_views' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'avg_activity_per_student_per_day' => array(
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => TRUE,
                'default' => NULL
            ),
            'active_days' => array(
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ),
            'extraction_date' => array(
                'type' => 'DATE',
                'null' => FALSE
            ),
            'created_at' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            ),
            'updated_at' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            )
        ));

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('user_activity_etl');
    }

    public function down()
    {
        $this->dbforge->drop_table('user_activity_etl');
    }
}
```

### Additional Schema Tables

The complete ETL schema includes 8 additional tables for comprehensive data management:

- **005_create_raw_log.php** - Raw Moodle log data storage
- **006_create_course_activity_summary.php** - Course activity summaries
- **007_create_student_profile.php** - Student profile information
- **008_create_student_quiz_detail.php** - Detailed quiz attempt data
- **009_create_student_assignment_detail.php** - Assignment submission details
- **010_create_student_resource_access.php** - Resource access tracking
- **011_create_course_summary.php** - Course overview data
- **012_create_etl_status.php** - ETL process status tracking

---

## 🎛️ CLI Migration Controller

### CLI-Only Migration Controller

File: `celoeapi-dev/application/controllers/Migrate.php`

```php
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
    
    public function create($migration_name = '')
    {
        if (empty($migration_name)) {
            echo "Error: Migration name is required\n";
            echo "Usage: ./migrate.sh create migration_name\n";
            exit(1);
        }
        
        $migrations_path = $this->config->item('migration_path');
        
        // Get next migration number
        $migration_files = glob($migrations_path . '*.php');
        $highest_number = 0;
        
        foreach ($migration_files as $file) {
            $filename = basename($file);
            if (preg_match('/^(\d{3})_/', $filename, $matches)) {
                $highest_number = max($highest_number, (int)$matches[1]);
            }
        }
        
        $next_number = str_pad($highest_number + 1, 3, '0', STR_PAD_LEFT);
        $class_name = 'Migration_' . ucfirst($migration_name);
        $filename = $next_number . '_' . $migration_name . '.php';
        $filepath = $migrations_path . $filename;
        
        // Create migration template
        $template = $this->get_migration_template($class_name, $migration_name);
        
        if (file_put_contents($filepath, $template)) {
            echo "Migration created successfully:\n";
            echo "  File: {$filename}\n";
            echo "  Path: {$filepath}\n";
            echo "  Class: {$class_name}\n";
        } else {
            echo "Error: Could not create migration file\n";
            exit(1);
        }
    }
    
    public function config($key = '', $value = '')
    {
        $config_file = APPPATH . 'config/migration.php';
        
        if (empty($key)) {
            echo "Available config options:\n";
            echo "  - version: Current migration version\n";
            echo "  - enabled: Migration system enabled/disabled\n";
            echo "  - type: Migration type (sequential/timestamp)\n";
            echo "\nUsage:\n";
            echo "  ./migrate.sh config key [value]\n";
            echo "  ./migrate.sh config version 12\n";
            return;
        }
        
        $config_key = 'migration_' . $key;
        
        if (empty($value)) {
            // Show current value
            $config = $this->config->item($config_key);
            echo "Current {$key}: {$config}\n";
        } else {
            // Update value
            echo "Setting configuration {$key} = {$value}\n";
            if ($this->update_config_value($config_file, $config_key, $value)) {
                echo "Migration {$key} updated to: {$value}\n";
            } else {
                echo "Error: Could not update configuration\n";
                exit(1);
            }
        }
    }
    
    private function update_config_value($config_file, $key, $value)
    {
        $content = file_get_contents($config_file);
        
        if ($content === false) {
            return false;
        }
        
        // Handle different value types
        if (is_numeric($value)) {
            $formatted_value = $value;
        } elseif ($value === 'true' || $value === 'false') {
            $formatted_value = strtoupper($value);
        } else {
            $formatted_value = "'{$value}'";
        }
        
        // Update the configuration value
        $pattern = '/(\$config\[\'' . preg_quote($key) . '\'\]\s*=\s*)[^;]+(;)/';
        $replacement = '${1}' . $formatted_value . '${2}';
        
        $updated_content = preg_replace($pattern, $replacement, $content);
        
        if ($updated_content && $updated_content !== $content) {
            return file_put_contents($config_file, $updated_content) !== false;
        }
        
        return false;
    }
    
    public function reset()
    {
        if ($this->migration->version(0) === FALSE) {
            echo "Reset failed: " . $this->migration->error_string() . "\n";
            exit(1);
        } else {
            echo "All migrations have been rolled back successfully.\n";
            echo "Database is now at version: 000\n";
        }
    }
    
    public function test()
    {
        echo "=== Migration System Test ===\n\n";
        
        // Test database connection
        $db_config = $this->config->item('migration_db_host');
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
    
    public function help()
    {
        $this->show_usage();
    }
    
    private function show_usage()
    {
        echo "ETL Migration CLI Commands:\n\n";
        echo "  ./migrate.sh run              - Run all pending migrations\n";
        echo "  ./migrate.sh status           - Show current migration status\n";
        echo "  ./migrate.sh reset            - Reset all migrations (rollback to 000)\n";
        echo "  ./migrate.sh test             - Test migration system configuration\n";
        echo "  ./migrate.sh create <name>    - Create new migration file\n";
        echo "  ./migrate.sh config <key>     - Show config value\n";
        echo "  ./migrate.sh config <key> <value> - Update config value\n";
        echo "  ./migrate.sh help             - Show this help message\n\n";
        echo "Examples:\n";
        echo "  ./migrate.sh create add_new_table\n";
        echo "  ./migrate.sh config version\n";
        echo "  ./migrate.sh config version 13\n";
        echo "  ./migrate.sh config enabled true\n\n";
    }
    
    private function get_migration_template($class_name, $migration_name)
    {
        return "<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class {$class_name} extends CI_Migration {

    public function up()
    {
        // Add your migration logic here
        // Example:
        /*
        \$this->dbforge->add_field([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => FALSE
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => TRUE
            ]
        ]);
        
        \$this->dbforge->add_key('id', TRUE);
        \$this->dbforge->create_table('your_table_name');
        */
    }

    public function down()
    {
        // Add your rollback logic here
        // Example:
        // \$this->dbforge->drop_table('your_table_name');
    }
}
";
    }
}
```

### CLI Wrapper Script

File: `celoeapi-dev/migrate.sh`

```bash
#!/bin/bash

# CLI Migration Wrapper Script
# Provides unified interface for migration commands

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running inside Docker container
if [ -f /.dockerenv ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
    # Running inside container
    info "Running migration inside Docker container..."
    php index.php migrate "$@"
else
    # Running outside container
    info "Running migration in Docker container..."
    docker exec -it celoe-api php index.php migrate "$@"
fi
```

### Migration Configuration

File: `celoeapi-dev/application/config/migration.php`

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Database connection settings using environment variables
$config['migration_db_host'] = getenv('DB_HOST') ?: 'db';
$config['migration_db_username'] = getenv('DB_USERNAME') ?: 'moodleuser';
$config['migration_db_password'] = getenv('DB_PASSWORD') ?: 'moodlepass';
$config['migration_db_name'] = getenv('ETL_DATABASE') ?: 'celoeapi';

// Migration system settings
$config['migration_enabled'] = TRUE;
$config['migration_type'] = 'sequential';
$config['migration_table'] = 'migration_tracker';
$config['migration_auto_latest'] = FALSE;
$config['migration_version'] = 12; // Set to latest migration number
$config['migration_path'] = APPPATH . 'database/migrations/';

// Environment-specific settings
$environment = getenv('CI_ENV') ?: ENVIRONMENT;

switch ($environment) {
    case 'development':
        $config['migration_debug'] = TRUE;
        $config['migration_verbose'] = TRUE;
        break;
    case 'production':
        $config['migration_debug'] = FALSE;
        $config['migration_verbose'] = FALSE;
        break;
    default:
        $config['migration_debug'] = FALSE;
        $config['migration_verbose'] = TRUE;
}

// Helper functions
if (!function_exists('get_etl_database_name')) {
    function get_etl_database_name() {
        return getenv('ETL_DATABASE') ?: 'celoeapi';
    }
}
```

---

## 🚀 Setup Procedures

### Step 1: Environment Setup

Ensure your environment variables are configured:

```bash
# Add to your .env file or docker-compose.yml
DB_HOST=db
DB_USERNAME=moodleuser
DB_PASSWORD=moodlepass
ETL_DATABASE=celoeapi
MOODLE_DATABASE=moodle
CI_ENV=development
```

### Step 2: Create Database (Automated)

Use the provided script to create the database automatically:

```bash
cd celoeapi-dev/

# Create database if it doesn't exist
chmod +x create_database.sh
./create_database.sh

# Or force recreate database
./create_database.sh --force

# Or use custom database name
ETL_DATABASE=myetl ./create_database.sh
```

The script will:
- Check if database exists
- Create database with proper charset (utf8mb4) and collation
- Prompt for confirmation if database already exists
- Test database connection
- Show database configuration

**Manual Database Creation (Alternative)**

If you prefer manual setup:

```bash
# Drop and recreate database for clean migration
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "DROP DATABASE IF EXISTS celoeapi; CREATE DATABASE celoeapi;"
```

### Step 3: Run All Migrations (CLI)

```bash
# Navigate to the project directory
cd celoeapi-dev/

# Make the script executable
chmod +x migrate.sh

# Run all migrations
./migrate.sh run
```

### Step 4: Verify Migration Status

```bash
# Check migration status
./migrate.sh status

# Test migration system
./migrate.sh test
```

### Expected Output:
```
Current migration version: 012

Available migrations:
  - 001_create_log_scheduler.php
  - 002_create_activity_counts_etl.php
  - 003_create_user_counts_etl.php
  - 004_create_user_activity_etl.php
  - 005_create_raw_log.php
  - 006_create_course_activity_summary.php
  - 007_create_student_profile.php
  - 008_create_student_quiz_detail.php
  - 009_create_student_assignment_detail.php
  - 010_create_student_resource_access.php
  - 011_create_course_summary.php
  - 012_create_etl_status.php
```

---

## 💻 CLI Migration Commands

### Core Migration Commands

```bash
# Run all pending migrations
./migrate.sh run

# Check current migration status
./migrate.sh status

# Reset all migrations (rollback to version 000)
./migrate.sh reset

# Test migration system configuration
./migrate.sh test

# Show help and available commands
./migrate.sh help
```

### Development Commands

```bash
# Create new migration file
./migrate.sh create add_new_feature_table

# View current configuration
./migrate.sh config version
./migrate.sh config enabled
./migrate.sh config type

# Update configuration
./migrate.sh config version 13
./migrate.sh config enabled true
./migrate.sh config type sequential
```

### Direct Database Verification

```bash
# Check created tables
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "USE celoeapi; SHOW TABLES;"

# Check specific table structure
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "USE celoeapi; DESCRIBE log_scheduler;"

# Check migration tracker
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "USE celoeapi; SELECT * FROM migration_tracker;"
```

### API Testing After Migration

```bash
# Test ETL system with migrated database
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/status

# Run ETL pipeline
curl -X POST \
     -H "Authorization: Bearer default-webhook-token-change-this" \
     -H "Content-Type: application/json" \
     -d '{"date": "2025-01-01"}' \
     http://localhost:8081/api/user_activity_etl/run_pipeline
```

---

## ↩️ Rollback Procedures

### Rolling Back Migrations (CLI)

```bash
# Reset all migrations to version 000
./migrate.sh reset

# Check status after rollback
./migrate.sh status
```

### Manual Rollback Process

If you need to manually rollback:

```sql
-- Check current migration version
SELECT * FROM migration_tracker ORDER BY version DESC LIMIT 1;

-- Manually drop tables (if needed)
DROP TABLE IF EXISTS etl_status;
DROP TABLE IF EXISTS course_summary;
-- ... continue for other tables

-- Update migration tracker
UPDATE migration_tracker SET version = 10;
```

---

## 🔖 Version Control

### Git Integration

```bash
# Add migration files to git
git add celoeapi-dev/application/database/migrations/
git add celoeapi-dev/application/controllers/Migrate.php
git add celoeapi-dev/application/config/migration.php
git add celoeapi-dev/migrate.sh

# Commit migrations
git commit -m "Add complete ETL CLI migration system

- Implement CLI-only migration controller for security
- Create 12 migration files for complete ETL schema
- Add CLI wrapper script for Docker support
- Include create and config commands
- Support for rollback operations
- Environment-aware configuration"
```

### Creating New Migrations

```bash
# Create new migration with descriptive name
./migrate.sh create add_user_preferences_table

# This creates: 013_add_user_preferences_table.php
# Edit the generated file to add your schema changes

# Update migration version after adding new migrations
./migrate.sh config version 13

# Run the new migration
./migrate.sh run
```

### Migration File Structure

All migration files follow CodeIgniter conventions:

```
celoeapi-dev/application/database/migrations/
├── 001_create_log_scheduler.php
├── 002_create_activity_counts_etl.php
├── 003_create_user_counts_etl.php
├── 004_create_user_activity_etl.php
├── 005_create_raw_log.php
├── 006_create_course_activity_summary.php
├── 007_create_student_profile.php
├── 008_create_student_quiz_detail.php
├── 009_create_student_assignment_detail.php
├── 010_create_student_resource_access.php
├── 011_create_course_summary.php
├── 012_create_etl_status.php
└── 013_add_user_preferences_table.php (example new migration)
```

### Best Practices

1. **Sequential numbering**: Always use 3-digit sequential numbers (001, 002, 003...)
2. **Never modify existing migrations**: Create new ones for changes
3. **Test in development first**: Always test migrations before production
4. **Environment variables**: Use dynamic configuration for different environments
5. **Backup before migrations**: Always backup production databases
6. **Document changes**: Include clear descriptions in migration class names
7. **CLI-only access**: Web access removed for security - migrations are for server setup
8. **Use create command**: Generate new migrations with `./migrate.sh create migration_name`

---

## 🧪 Testing Migration Setup

### Complete Test Sequence

```bash
# 1. Navigate to project directory
cd celoeapi-dev/

# 2. Clean environment
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "DROP DATABASE IF EXISTS celoeapi; CREATE DATABASE celoeapi;"

# 3. Test migration system
./migrate.sh test

# 4. Run all migrations
./migrate.sh run

# 5. Verify migration status
./migrate.sh status

# 6. Verify table structure
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "USE celoeapi; SHOW TABLES;"

# 7. Test ETL system
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/status

# 8. Run full ETL pipeline
curl -X POST \
     -H "Authorization: Bearer default-webhook-token-change-this" \
     -H "Content-Type: application/json" \
     -d '{"date": "2025-01-01"}' \
     http://localhost:8081/api/user_activity_etl/run_pipeline
```

### Expected Results

- ✅ 12 migrations execute successfully
- ✅ All ETL tables created with correct schema
- ✅ Migration tracker shows version 012
- ✅ ETL API responds correctly
- ✅ Database constraints and indexes in place
- ✅ ETL pipeline runs without errors
- ✅ CLI commands work correctly
- ✅ Create and config commands functional

### Database Schema Verification

Expected tables after migration:

```
+---------------------------+
| Tables_in_celoeapi        |
+---------------------------+
| activity_counts_etl       |
| course_activity_summary   |
| course_summary            |
| etl_status                |
| log_scheduler             |
| migration_tracker         |
| raw_log                   |
| student_assignment_detail |
| student_profile           |
| student_quiz_detail       |
| student_resource_access   |
| user_activity_etl         |
| user_counts_etl           |
+---------------------------+
```

---

## 🛠️ Database Creation Script

### Script Features

The `create_database.sh` script provides automated database setup with the following features:

- **Automatic Detection**: Checks if database already exists
- **Safe Creation**: Uses `CREATE DATABASE IF NOT EXISTS` with proper charset
- **Environment Support**: Works both inside and outside Docker containers
- **Interactive Prompts**: Asks for confirmation before dropping existing data
- **Force Mode**: Non-interactive mode for automation
- **Connection Testing**: Validates database connectivity before operations
- **Proper Charset**: Creates database with `utf8mb4` charset and `utf8mb4_unicode_ci` collation

### Script Usage

```bash
cd celoeapi-dev/

# Basic usage - create database if not exists
./create_database.sh

# Force recreate without confirmation
./create_database.sh --force

# Show help
./create_database.sh --help

# Use custom database name
ETL_DATABASE=my_custom_etl ./create_database.sh

# Use custom credentials
DB_USERNAME=root DB_PASSWORD=secret ./create_database.sh
```

### Expected Output

```
============================================
    ETL Database Creation Script
============================================

[INFO] Testing database connection...
[SUCCESS] Database connection successful!
[INFO] Checking if database 'celoeapi' exists...
[INFO] Database 'celoeapi' does not exist.
[INFO] Creating database 'celoeapi'...
[SUCCESS] Database 'celoeapi' created successfully!
[INFO] Database Information:
  Host: db
  Username: moodleuser
  Database: celoeapi
  Character Set: utf8mb4
  Collation: utf8mb4_unicode_ci

[SUCCESS] Database setup completed!
[INFO] You can now run migrations with: ./migrate.sh run
```

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `db` | Database host |
| `DB_USERNAME` | `moodleuser` | Database username |
| `DB_PASSWORD` | `moodlepass` | Database password |
| `ETL_DATABASE` | `celoeapi` | ETL database name |

### Error Handling

The script includes comprehensive error handling:

- **Connection Failures**: Tests database connectivity before operations
- **Permission Issues**: Provides clear error messages for access problems
- **Existing Data**: Prompts for confirmation before dropping existing databases
- **Creation Failures**: Reports specific errors during database creation

---

## 🔧 Advanced Features

### CLI-Only Security

The migration system is restricted to CLI access only for security:

- Web access completely removed from routes
- `is_cli_request()` check in controller
- Migrations are intended for initial server setup
- Prevents accidental web-based schema changes

### Development Workflow

```bash
# Create new feature migration
./migrate.sh create add_analytics_tracking

# Edit the generated migration file
# vim application/database/migrations/013_add_analytics_tracking.php

# Update configuration to include new migration
./migrate.sh config version 13

# Test the migration
./migrate.sh run

# Verify it worked
./migrate.sh status
```

### Environment-Specific Configuration

The migration system automatically adapts to different environments:

- **Development**: Debug enabled, verbose output
- **Production**: Silent execution, minimal logging
- **Testing**: Debug enabled, no verbose output

### Dynamic Database Configuration

All database connections use environment variables:

```php
// Configuration automatically uses environment variables
$config['migration_db_host'] = getenv('DB_HOST') ?: 'db';
$config['migration_db_username'] = getenv('DB_USERNAME') ?: 'moodleuser';
$config['migration_db_password'] = getenv('DB_PASSWORD') ?: 'moodlepass';
$config['migration_db_name'] = getenv('ETL_DATABASE') ?: 'celoeapi';
```

### Cross-Database Support

The ETL models can now dynamically reference the correct database:

```php
// Dynamic database name resolution
$etl_db = get_etl_database_name();
$query = "SELECT * FROM {$etl_db}.activity_counts_etl";
```

---

*This CLI-only migration system provides complete version control for your ETL database schema and ensures secure, consistent deployments across all environments.*

---

*Last Updated: January 2025*
*Version: 3.0 - CLI-Only Migration System with Create & Config Commands* 