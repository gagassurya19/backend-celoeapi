<?php
/**
 * Simple Database Connection Test
 * Test connection to MySQL database running on localhost:3302
 * 
 * Usage: php test_db_connection.php
 */

echo "=== Database Connection Test ===\n\n";

// Test connection to celoeapi database
echo "Testing connection to 'celoeapi' database...\n";
try {
    $mysqli = new mysqli('localhost:3302', 'moodleuser', 'moodlepass', 'celoeapi');
    
    if ($mysqli->connect_error) {
        echo "✗ Failed to connect to 'celoeapi' database: " . $mysqli->connect_error . "\n";
    } else {
        echo "✓ Successfully connected to 'celoeapi' database\n";
        
        // Test if we can query the database
        $result = $mysqli->query("SHOW TABLES");
        if ($result) {
            $table_count = $result->num_rows;
            echo "   Found {$table_count} existing tables\n";
            
            if ($table_count > 0) {
                echo "   Tables:\n";
                while ($row = $result->fetch_array()) {
                    echo "     - " . $row[0] . "\n";
                }
            }
        }
        
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test connection to moodle database
echo "Testing connection to 'moodle' database...\n";
try {
    $mysqli = new mysqli('localhost:3302', 'moodleuser', 'moodlepass', 'moodle');
    
    if ($mysqli->connect_error) {
        echo "✗ Failed to connect to 'moodle' database: " . $mysqli->connect_error . "\n";
    } else {
        echo "✓ Successfully connected to 'moodle' database\n";
        
        // Test if we can query the database
        $result = $mysqli->query("SHOW TABLES");
        if ($result) {
            $table_count = $result->num_rows;
            echo "   Found {$table_count} existing tables\n";
        }
        
        $mysqli->close();
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";

echo "\nNext steps:\n";
echo "1. If both connections are successful, you can run migrations\n";
echo "2. If connections fail, check:\n";
echo "   - Docker containers are running\n";
echo "   - MySQL port 3302 is accessible\n";
echo "   - Database credentials are correct\n";
echo "   - Firewall settings\n";
