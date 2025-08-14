# 📊 ETL Testing Guide: From Database Setup to Complete Testing

This guide provides step-by-step instructions for setting up and testing the Moodle ETL (Extract, Transform, Load) pipeline from scratch using the CLI migration system.

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Database Migration Setup](#database-migration-setup)
4. [API Authentication Setup](#api-authentication-setup)
5. [ETL Pipeline Components](#etl-pipeline-components)
6. [Testing Procedures](#testing-procedures)
7. [API Endpoints Reference](#api-endpoints-reference)
8. [Troubleshooting](#troubleshooting)

---

## 🔧 Prerequisites

- Docker and Docker Compose installed
- MySQL client access
- curl command-line tool
- Basic understanding of REST APIs

### Required Services
- **Moodle**: Main LMS application (port 9003)
- **CeloeAPI**: ETL processing service (port 8081)
- **MySQL**: Database server (port 3302)

---

## 🚀 Environment Setup

### Step 1: Start Docker Services

```bash
cd /path/to/moodle-docker
docker-compose up -d
```

### Step 2: Verify Services are Running

```bash
docker-compose ps
```

Expected output:
```
NAME                  STATUS             PORTS
celoe-api             Up                 0.0.0.0:8081->80/tcp
moodle-docker-db-1    Up                 0.0.0.0:3302->3306/tcp  
moodle-docker-web-1   Up                 0.0.0.0:9003->80/tcp
```

### Step 3: Verify Database Connectivity

```bash
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "SHOW DATABASES;"
```

Expected databases:
- `moodle` - Main Moodle database
- `celoeapi` - ETL processing database (created by migrations)

---

## 🗄️ Database Migration Setup

### Step 4: Setup ETL Database Using CLI Migration

Navigate to the CeloeAPI directory and run migrations:

```bash
cd celoeapi-dev/

# Make scripts executable
chmod +x migrate.sh
chmod +x create_database.sh

# Create database automatically (recommended)
./create_database.sh

# Or force recreate database for clean setup
./create_database.sh --force

# Test migration system
./migrate.sh test

# Run all migrations
./migrate.sh run

# Verify migration status
./migrate.sh status
```

**Alternative Manual Database Setup:**

If you prefer manual database creation:

```bash
# Drop and recreate database manually
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "DROP DATABASE IF EXISTS celoeapi; CREATE DATABASE celoeapi;"
```

Expected output:
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

### Step 5: Verify ETL Table Structure

Check that all required ETL tables exist with correct schema:

```bash
# Check ETL tables in celoeapi database
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "SHOW TABLES;"
```

Expected tables:
- `activity_counts_etl`
- `course_activity_summary`
- `course_summary`
- `etl_status`
- `log_scheduler`
- `migration_tracker`
- `raw_log`
- `student_assignment_detail`
- `student_profile`
- `student_quiz_detail`
- `student_resource_access`
- `user_activity_etl`
- `user_counts_etl`

### Step 6: Verify log_scheduler Table Schema

```bash
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "DESCRIBE log_scheduler;"
```

Required columns:
- `id` (bigint, AUTO_INCREMENT, PRIMARY KEY)
- `batch_name` (varchar(100), NOT NULL)
- `offset` (int(10), DEFAULT 0)
- `numrow` (int(10), DEFAULT 0)
- `limit_size` (int(10), DEFAULT 1000)
- `status` (tinyint(1), NOT NULL)
- `start_date` (datetime)
- `end_date` (datetime)
- `error_details` (text)
- `created_at` (datetime)

### Step 7: Check Available Moodle Log Data

```bash
# Check log data by date
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass moodle -e "
SELECT 
    DATE(FROM_UNIXTIME(timecreated)) as log_date,
    COUNT(*) as log_count,
    MIN(FROM_UNIXTIME(timecreated)) as earliest_log,
    MAX(FROM_UNIXTIME(timecreated)) as latest_log
FROM mdl_logstore_standard_log 
WHERE FROM_UNIXTIME(timecreated) >= '2025-01-01'
GROUP BY DATE(FROM_UNIXTIME(timecreated))
ORDER BY log_date DESC LIMIT 10;
"
```

---

## 🔐 API Authentication Setup

### Step 8: Verify API Token Configuration

The default authentication token is configured in:
`celoeapi-dev/application/config/etl.php`

```php
$config['etl_webhook_tokens'] = [
    'default-webhook-token-change-this',
    // Add more tokens as needed
];
```

### Step 9: Test API Connectivity

```bash
# Basic connectivity test
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/status
```

Expected response (JSON format):
```json
{
  "status": true,
  "data": {
    "activity_counts": {"status": "not_started"},
    "user_counts": {"status": "not_started"},
    "main_etl": {"status": "not_started"}
  },
  "timestamp": "2025-01-13 12:00:00"
}
```

---

## ⚙️ ETL Pipeline Components

### ETL Architecture Overview

The ETL pipeline consists of three main components:

1. **Activity Counts ETL**: Processes user activity logs (file views, forum posts, etc.)
2. **User Counts ETL**: Processes user role assignments (teachers, students)
3. **Main ETL**: Merges data from the above two components

### Date Range Logic

- **Target Date**: The starting date for ETL processing
- **Date Range**: Process data from date to current_date
- **Example**: If date = "2025-01-01", process all data from January 1st to today

### Database Configuration

The ETL system now uses environment variables for dynamic configuration:

- **ETL Database**: `celoeapi` (configured via `ETL_DATABASE` env var)
- **Moodle Database**: `moodle` (configured via `MOODLE_DATABASE` env var)
- **Cross-Database Joins**: ETL queries join data between both databases

---

## 🧪 Testing Procedures

### Test Case 1: Clean Environment Setup

```bash
# Clear any existing ETL data
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
TRUNCATE TABLE user_activity_etl;
TRUNCATE TABLE activity_counts_etl; 
TRUNCATE TABLE user_counts_etl;
UPDATE log_scheduler SET status = 3, end_date = NOW(), error_details = 'Cleared for testing' WHERE status = 2;
"
```

### Test Case 2: Individual ETL Component Testing

#### Test Activity Counts ETL
```bash
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_activity_counts
```

Expected response:
```json
{
  "status": "completed",
  "message": "ETL process completed successfully",
  "records": 1,
  "total": "1"
}
```

#### Test User Counts ETL
```bash
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_user_counts
```

#### Test Main ETL
```bash
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_main_etl
```

### Test Case 3: Complete Pipeline Testing

```bash
# Run complete ETL pipeline with date range processing
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_pipeline
```

Expected response:
```json
{
  "status": "completed",
  "message": "Full ETL pipeline completed successfully",
  "results": {
    "activity_counts": {
      "status": "completed",
      "records": 1,
      "total": "1"
    },
    "user_counts": {
      "status": "completed", 
      "records": 2,
      "total": "2"
    },
    "main_etl": {
      "status": "completed",
      "records": 1,
      "total": "1"
    }
  },
  "total_records": 1
}
```

### Test Case 4: Data Cleanup Testing

```bash
# Test manual data cleanup
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/clean_data
```

Expected response:
```json
{
  "status": true,
  "message": "Data cleanup completed successfully",
  "date": "2025-01-01",
  "timestamp": "2025-01-01 10:00:00"
}
```

### Test Case 5: Results Verification

```bash
# Check ETL results for current date
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/results

# Check ETL results for specific date
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     "http://localhost:8081/api/user_activity_etl/results/2025-01-01"
```

Expected response format:
```json
{
  "status": true,
  "data": [
    {
      "id": "1",
      "course_id": "320",
      "id_number": "39618",
      "course_name": "Test Course 1",
      "course_shortname": "TC1",
      "num_teachers": "3",
      "num_students": "3",
      "file_views": "5",
      "video_views": "0",
      "forum_views": "9",
      "quiz_views": "15",
      "assignment_views": "21",
      "url_views": "12",
      "total_views": "62",
      "avg_activity_per_student_per_day": "5.17",
      "active_days": "4",
      "extraction_date": "2025-01-01"
    }
  ],
  "count": 1,
  "extraction_date": "2025-01-01",
  "timestamp": "2025-01-13 03:22:50"
}
```

### Test Case 5: CLI Interface Testing

```bash
# Test CLI status
docker exec celoe-api php index.php cli user_activity_etl status

# Test CLI pipeline execution
docker exec celoe-api php index.php cli user_activity_etl pipeline 2025-01-01
```

### Test Case 6: Migration System Testing

```bash
# Test migration commands
cd celoeapi-dev/

# Check current migration status
./migrate.sh status

# Test migration configuration
./migrate.sh config version

# Test creating new migration (optional)
./migrate.sh create test_migration

# Test migration reset (optional - use with caution)
./migrate.sh reset
```

---

## 📚 API Endpoints Reference

### Authentication
All API endpoints require Bearer token authentication:
```
Authorization: Bearer default-webhook-token-change-this
```

### ETL Control Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/user_activity_etl/run_pipeline` | Run complete ETL pipeline |
| POST | `/api/user_activity_etl/run_activity_counts` | Run Activity Counts ETL only |
| POST | `/api/user_activity_etl/run_user_counts` | Run User Counts ETL only |
| POST | `/api/user_activity_etl/run_main_etl` | Run Main ETL only |

### ETL Status Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/user_activity_etl/status` | Get ETL pipeline status |
| GET | `/api/user_activity_etl/scheduler_data` | Get scheduler information |
| GET | `/api/user_activity_etl/results/{date}` | Get ETL results for specific date |
| GET | `/api/user_activity_etl/results` | Get ETL results for current date |
| POST | `/api/user_activity_etl/clean_data` | Clean existing data for specific date |

### Request Body Format
For POST endpoints:
```json
{
  "date": "YYYY-MM-DD"
}
```

**Note**: `date` specifies the starting date for processing. ETL will process data from `date` to current date.

### CLI Commands

```bash
# Status check
docker exec celoe-api php index.php cli user_activity_etl status

# Run complete pipeline
docker exec celoe-api php index.php cli user_activity_etl pipeline [date]

# Run individual components
docker exec celoe-api php index.php cli user_activity_etl activity_counts [date]
docker exec celoe-api php index.php cli user_activity_etl user_counts [date]
docker exec celoe-api php index.php cli user_activity_etl main_etl [date]

# Get scheduler data
docker exec celoe-api php index.php cli user_activity_etl scheduler_data
```

### Migration CLI Commands

```bash
# Navigate to project directory
cd celoeapi-dev/

# Run all migrations
./migrate.sh run

# Check migration status
./migrate.sh status

# Reset all migrations
./migrate.sh reset

# Test migration system
./migrate.sh test

# Create new migration
./migrate.sh create migration_name

# View/update configuration
./migrate.sh config version
./migrate.sh config version 13

# Get help
./migrate.sh help
```

---

## 🔍 Troubleshooting

### Common Issues and Solutions

#### 1. Database Connection Issues
**Problem**: Cannot connect to database
**Solution**: 
```bash
# Check if database service is running
docker-compose ps

# Restart database service
docker-compose restart db
```

#### 2. Missing ETL Tables
**Problem**: Table 'celoeapi.activity_counts_etl' doesn't exist
**Solution**: 
```bash
# Run CLI migration setup
cd celoeapi-dev/
./migrate.sh run
```

#### 3. Migration System Issues
**Problem**: Migration fails or shows incorrect version
**Solution**:
```bash
# Test migration system
./migrate.sh test

# Check migration configuration
./migrate.sh config version

# Reset and re-run migrations if needed
./migrate.sh reset
./migrate.sh run
```

#### 4. Stuck ETL Processes
**Problem**: "ETL process is already running"
**Solution**:
```bash
# Clear stuck processes
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
UPDATE log_scheduler SET status = 3, end_date = NOW(), error_details = 'Cleared manually' WHERE status = 2;
"
```

#### 5. API Authentication Errors
**Problem**: 401 Unauthorized or Invalid token
**Solution**: 
- Verify token in `celoeapi-dev/application/config/etl.php`
- Check Authorization header format: `Bearer default-webhook-token-change-this`

#### 6. No Data in Results
**Problem**: ETL completes but returns empty results
**Solution**: 
- Check if source data exists in Moodle logs
- Verify date range has activity data
- Check database joins in User_Activity_ETL_Model.php
- Ensure cross-database configuration is correct

#### 7. Database Configuration Issues
**Problem**: Cannot connect to correct database
**Solution**: 
- Check environment variables in docker-compose.yml
- Verify database names: `celoeapi` for ETL, `moodle` for source data
- Check migration configuration in `application/config/migration.php`

#### 8. PHP Header Warnings
**Problem**: "Cannot modify header information - headers already sent"
**Solution**: Remove closing `?>` tags from PHP files

### Debug Commands

```bash
# Check ETL table data
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT 'activity_counts_etl' as table_name, COUNT(*) as records FROM activity_counts_etl
UNION ALL
SELECT 'user_counts_etl', COUNT(*) FROM user_counts_etl
UNION ALL  
SELECT 'user_activity_etl', COUNT(*) FROM user_activity_etl;
"

# Check scheduler status
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT id, batch_name, status, start_date, end_date FROM log_scheduler ORDER BY id DESC LIMIT 10;
"

# Check migration status
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT * FROM migration_tracker ORDER BY version DESC LIMIT 5;
"

# Check Moodle log data availability
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass moodle -e "
SELECT COUNT(*) as total_logs, COUNT(DISTINCT courseid) as unique_courses, COUNT(DISTINCT userid) as unique_users
FROM mdl_logstore_standard_log WHERE courseid > 1;
"

# Check database configuration
docker exec celoe-api php index.php migrate test
```

### Log Files

- **API Logs**: Check Docker container logs
```bash
docker logs celoe-api
```

- **Database Logs**: Check MySQL error logs
```bash
docker logs moodle-docker-db-1
```

---

## 📈 Success Criteria

A successful ETL test should demonstrate:

1. ✅ All Docker services running
2. ✅ CLI migration system working correctly
3. ✅ Database tables properly configured via migrations
4. ✅ API authentication working
5. ✅ Activity Counts ETL processing data from date range
6. ✅ User Counts ETL processing role assignments
7. ✅ Main ETL successfully merging data with cross-database joins
8. ✅ Final results available via API
9. ✅ CLI interface functioning correctly
10. ✅ Migration commands working for database management

### Expected Test Results

For a typical test run with data from 2025-01-01:

- **Activity Counts**: 1+ courses processed with activity data from Jan 1 to current date
- **User Counts**: 2+ records (multiple courses with users)
- **Main ETL**: 1+ final records with merged data
- **Final Results**: JSON response with complete course analytics
- **Migration System**: All 12 migrations successfully applied

---

## 📝 Notes

- **Date Range Processing**: ETL processes data from date to current_date, not just a single day
- **Database Architecture**: Uses cross-database joins between `moodle` and `celoeapi` databases
- **Authentication**: Uses simple Bearer token authentication (suitable for internal APIs)
- **Pagination**: ETL processes data in configurable batches (default: 1000 records)
- **Error Handling**: Failed processes are logged in `log_scheduler` table with error details
- **CLI-Only Migrations**: Database schema changes are managed through CLI for security
- **Environment Variables**: Database configuration uses environment variables for flexibility

---

## 🔄 Maintenance

### Regular Tasks

1. **Monitor ETL Performance**
```bash
# Check recent ETL runs
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT batch_name, status, start_date, end_date, 
       TIMESTAMPDIFF(SECOND, start_date, end_date) as duration_seconds
FROM log_scheduler 
WHERE start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY start_date DESC;
"
```

2. **Clean Old ETL Data**
```bash
# Remove ETL data older than 30 days
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
DELETE FROM user_activity_etl WHERE extraction_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY);
DELETE FROM activity_counts_etl WHERE extraction_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY);
DELETE FROM user_counts_etl WHERE extraction_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY);
"
```

3. **Backup ETL Results**
```bash
# Export ETL results
docker exec moodle-docker-db-1 mysqldump -u moodleuser -pmoodlepass celoeapi user_activity_etl > etl_backup_$(date +%Y%m%d).sql
```

4. **Migration Maintenance**
```bash
# Navigate to project directory
cd celoeapi-dev/

# Check migration status regularly
./migrate.sh status

# Create new migrations as needed
./migrate.sh create add_new_feature

# Update migration version when adding new migrations
./migrate.sh config version 13
```

### Database Migration Best Practices

1. **Always test migrations in development first**
2. **Backup database before running migrations in production**
3. **Use CLI-only access for security**
4. **Document migration changes in Git commits**
5. **Never modify existing migration files**
6. **Use descriptive names for new migrations**

---

*Last Updated: August 2025*
*Version: 1.0* 