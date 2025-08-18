<?php
/**
 * Local Migration Runner Script
 * Run this script from host machine to create all required ETL tables
 * 
 * Usage: php run_migrations_local.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== ETL Database Migration Runner (Local) ===\n\n";

// Check if we're in the right directory
if (!file_exists('application/config/database.php')) {
    echo "Error: Please run this script from the celoeapi-dev directory\n";
    exit(1);
}

// Test database connection first
echo "Testing database connection...\n";

try {
    // Create direct database connection to test
    $mysqli = new mysqli('localhost:3302', 'moodleuser', 'moodlepass', 'celoeapi');
    
    if ($mysqli->connect_error) {
        echo "Error: Failed to connect to database: " . $mysqli->connect_error . "\n";
        echo "Please make sure:\n";
        echo "1. Docker containers are running\n";
        echo "2. MySQL is accessible on localhost:3302\n";
        echo "3. Database 'celoeapi' exists\n";
        echo "4. User 'moodleuser' has access\n";
        exit(1);
    }
    
    echo "✓ Database connection successful!\n";
    $mysqli->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nStarting migrations...\n";

// Load CodeIgniter
require_once 'index.php';

try {
    echo "Loading CodeIgniter...\n";
    
    // Get CI instance
    $CI =& get_instance();
    
    // Load database
    $CI->load->database();
    
    echo "Database connection established.\n";
    
    // Load migration library
    $CI->load->library('migration');
    
    echo "Migration library loaded.\n";
    
    // Check current migration version
    $current_version = $CI->migration->current();
    echo "Current migration version: " . str_pad($current_version, 3, '0', STR_PAD_LEFT) . "\n";
    
    // Run migrations to latest version
    echo "Running migrations to latest version...\n";
    
    if ($CI->migration->version(18) === FALSE) {
        echo "Migration failed: " . $CI->migration->error_string() . "\n";
        exit(1);
    } else {
        $new_version = $CI->migration->current();
        echo "Migration completed successfully!\n";
        echo "Updated to version: " . str_pad($new_version, 3, '0', STR_PAD_LEFT) . "\n";
    }
    
    // Verify tables were created
    echo "\nVerifying tables were created...\n";
    
    $required_tables = [
        'sas_user_activity_etl',
        'sas_activity_counts_etl', 
        'sas_user_counts_etl',
        'cp_activity_summary',
        'cp_course_summary',
        'cp_student_profile',
        'cp_student_quiz_detail',
        'cp_student_assignment_detail',
        'cp_student_resource_access',
        'cp_etl_logs'
    ];
    
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        if ($CI->db->table_exists($table)) {
            echo "✓ Table '{$table}' exists\n";
        } else {
            echo "✗ Table '{$table}' is missing\n";
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "\n🎉 All required tables were created successfully!\n";
        echo "You can now run the ETL processes.\n";
    } else {
        echo "\n⚠️  Some tables are missing: " . implode(', ', $missing_tables) . "\n";
        echo "Please check the migration logs for errors.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Migration Runner Complete ===\n";
