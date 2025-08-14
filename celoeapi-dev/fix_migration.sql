-- Fix migration script to add faculty_id and program_id columns
-- This script directly modifies the database schema

USE celoeapi;

-- Add faculty_id column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'celoeapi' 
     AND TABLE_NAME = 'user_activity_etl' 
     AND COLUMN_NAME = 'faculty_id') = 0,
    'ALTER TABLE user_activity_etl ADD COLUMN faculty_id INT(11) NULL AFTER course_id',
    'SELECT "faculty_id column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add program_id column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'celoeapi' 
     AND TABLE_NAME = 'user_activity_etl' 
     AND COLUMN_NAME = 'program_id') = 0,
    'ALTER TABLE user_activity_etl ADD COLUMN program_id INT(11) NULL AFTER faculty_id',
    'SELECT "program_id column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show the updated table structure
DESCRIBE user_activity_etl;

-- Update migration version in the migration_tracker table
UPDATE migration_tracker SET version = 18 WHERE id = 1;
INSERT INTO migration_tracker (version) SELECT 18 WHERE NOT EXISTS (SELECT 1 FROM migration_tracker WHERE version = 18);

-- Show current migration status
SELECT * FROM migration_tracker ORDER BY version DESC;