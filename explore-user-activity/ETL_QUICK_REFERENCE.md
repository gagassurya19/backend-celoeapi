# 🚀 ETL Quick Reference Guide

Quick reference for Moodle ETL (Extract, Transform, Load) pipeline operations with CLI migration system.

## 📋 Quick Start

### 1. Start Services
```bash
cd /path/to/moodle-docker
docker-compose up -d
```

### 2. Setup Database (First Time)
```bash
cd celoeapi-dev/
chmod +x migrate.sh
chmod +x create_database.sh

# Create database automatically
./create_database.sh

# Run migrations
./migrate.sh run
./migrate.sh status
```

### 3. Run ETL Pipeline
```bash
# API method
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_pipeline

# CLI method
docker exec celoe-api php index.php cli user_activity_etl pipeline 2025-01-01
```

---

## 🎯 Migration Commands

```bash
cd celoeapi-dev/

# Database Setup Commands
./create_database.sh         # Create database if not exists
./create_database.sh --force # Force recreate database
./create_database.sh -h      # Show help

# Migration Commands
./migrate.sh run              # Run all pending migrations
./migrate.sh status           # Show current status
./migrate.sh reset            # Reset all migrations
./migrate.sh test             # Test migration system

# Development Commands
./migrate.sh create <name>    # Create new migration
./migrate.sh config version   # Show version
./migrate.sh config version 13 # Update version
./migrate.sh help             # Show help
```

---

## 🔧 ETL API Commands

### Authentication Token
```bash
Authorization: Bearer default-webhook-token-change-this
```

### Core Endpoints
```bash
# Status Check
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/status

# Run Complete Pipeline
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_pipeline

# Individual Components
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_activity_counts

curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_user_counts

curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/run_main_etl

# Get Results
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/results

curl -H "Authorization: Bearer default-webhook-token-change-this" \
     "http://localhost:8081/api/user_activity_etl/results/2025-01-01"

# Export Data (Paginated)
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     "http://localhost:8081/api/user_activity_etl/export?limit=1000&offset=0&date=2025-01-01"

# Export with custom pagination
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     "http://localhost:8081/api/user_activity_etl/export?limit=500&offset=1000&date=2025-01-01"

# Export all data (no date filter)
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     "http://localhost:8081/api/user_activity_etl/export?limit=1000&offset=0"

# Export all data with custom pagination
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     "http://localhost:8081/api/user_activity_etl/export?limit=500&offset=1000"

# Clean Data (Manual)
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/clean_data
```

---

## 💻 CLI Commands

```bash
# Status and Info
docker exec celoe-api php index.php cli user_activity_etl status
docker exec celoe-api php index.php cli user_activity_etl scheduler_data

# Run Pipeline
docker exec celoe-api php index.php cli user_activity_etl pipeline [date]

# Individual Components
docker exec celoe-api php index.php cli user_activity_etl activity_counts [date]
docker exec celoe-api php index.php cli user_activity_etl user_counts [date]
docker exec celoe-api php index.php cli user_activity_etl main_etl [date]
```

---

## 🗄️ Database Commands

### Migration Database (celoeapi)
```bash
# Check tables
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "SHOW TABLES;"

# Check ETL data
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT 'user_activity_etl' as table_name, COUNT(*) as records FROM user_activity_etl
UNION ALL
SELECT 'activity_counts_etl', COUNT(*) FROM activity_counts_etl
UNION ALL
SELECT 'user_counts_etl', COUNT(*) FROM user_counts_etl;"

# Check scheduler status
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT id, batch_name, status, start_date, end_date FROM log_scheduler ORDER BY id DESC LIMIT 5;"

# Check migration status
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
SELECT * FROM migration_tracker ORDER BY version DESC LIMIT 3;"
```

### Source Database (moodle)
```bash
# Check log data availability
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass moodle -e "
SELECT DATE(FROM_UNIXTIME(timecreated)) as log_date, COUNT(*) as log_count
FROM mdl_logstore_standard_log 
WHERE FROM_UNIXTIME(timecreated) >= '2025-01-01'
GROUP BY DATE(FROM_UNIXTIME(timecreated))
ORDER BY log_date DESC LIMIT 5;"
```

---

## 🔍 Troubleshooting

### Clear Stuck ETL Processes
```bash
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
UPDATE log_scheduler SET status = 3, end_date = NOW(), error_details = 'Cleared manually' WHERE status = 2;"
```

### Reset Database
```bash
cd celoeapi-dev/

# Using create_database script (recommended)
./create_database.sh --force
./migrate.sh run

# Or manual method
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass -e "DROP DATABASE IF EXISTS celoeapi; CREATE DATABASE celoeapi;"
./migrate.sh run
```

### Clear ETL Data
```bash
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "
TRUNCATE TABLE user_activity_etl;
TRUNCATE TABLE activity_counts_etl;
TRUNCATE TABLE user_counts_etl;"
```

### Check Services
```bash
docker-compose ps
docker logs celoe-api
docker logs moodle-docker-db-1
```

---

## 📊 Expected Output Examples

### Migration Status
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

### ETL Status Response
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

### ETL Results Response
```json
{
  "status": true,
  "data": [
    {
      "id": "1",
      "course_id": "320",
      "course_name": "Test Course 1",
      "num_teachers": "3",
      "num_students": "3",
      "file_views": "5",
      "total_views": "62",
      "avg_activity_per_student_per_day": "5.17",
      "extraction_date": "2025-01-01"
    }
  ],
  "count": 1
}
```

### Export API Response (With Date Filter)
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "course_id": "COURSE001",
      "id_number": "STUDENT001",
      "course_name": "Introduction to Programming",
      "course_shortname": "INTRO_PROG",
      "num_teachers": 2,
      "num_students": 25,
      "file_views": 150,
      "video_views": 300,
      "forum_views": 75,
      "quiz_views": 200,
      "assignment_views": 100,
      "url_views": 50,
      "total_views": 875,
      "avg_activity_per_student_per_day": 35.00,
      "active_days": 5,
      "extraction_date": "2025-01-01",
      "created_at": "2025-01-01 10:30:00",
      "updated_at": "2025-01-01 10:30:00"
    }
  ],
  "limit": 1000,
  "offset": 0,
  "has_next": true,
  "extraction_date": "2025-01-01",
  "timestamp": "2025-01-01 10:30:00"
}
```

### Export API Response (All Data - No Date Filter)
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "course_id": "COURSE001",
      "id_number": "STUDENT001",
      "course_name": "Introduction to Programming",
      "course_shortname": "INTRO_PROG",
      "num_teachers": 2,
      "num_students": 25,
      "file_views": 150,
      "video_views": 300,
      "forum_views": 75,
      "quiz_views": 200,
      "assignment_views": 100,
      "url_views": 50,
      "total_views": 875,
      "avg_activity_per_student_per_day": 35.00,
      "active_days": 5,
      "extraction_date": "2025-01-01",
      "created_at": "2025-01-01 10:30:00",
      "updated_at": "2025-01-01 10:30:00"
    }
  ],
  "limit": 1000,
  "offset": 0,
  "has_next": true,
  "extraction_date": null,
  "note": "All data returned (no date filter applied)",
  "timestamp": "2025-01-01 10:30:00"
}
```

---

## 🏗️ Database Tables

### ETL Tables (celoeapi database)
- `activity_counts_etl` - Activity view counts per course
- `user_counts_etl` - User role counts per course
- `user_activity_etl` - Main ETL results (merged data)
- `log_scheduler` - ETL process tracking
- `migration_tracker` - Migration version control

### Additional Tables
- `course_activity_summary` - Course activity summaries
- `course_summary` - Course overview data
- `etl_status` - ETL process status
- `raw_log` - Raw log data storage
- `student_assignment_detail` - Assignment details
- `student_profile` - Student profile data
- `student_quiz_detail` - Quiz attempt details
- `student_resource_access` - Resource access tracking

---

## ⚙️ Configuration Files

### Scripts
- `celoeapi-dev/create_database.sh` - Automated database creation script
- `celoeapi-dev/migrate.sh` - CLI migration management script

### Migration Config
- `celoeapi-dev/application/config/migration.php`
- Environment variables: `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `ETL_DATABASE`

### ETL Config
- `celoeapi-dev/application/config/etl.php`
- Default token: `default-webhook-token-change-this`

### Routes
- `celoeapi-dev/application/config/routes.php`
- API routes: `/api/user_activity_etl/*`

---

## 📝 Key Notes

- **Date Range**: `date` to current date (not single day)
- **Databases**: `moodle` (source) + `celoeapi` (ETL)
- **Migration**: CLI-only for security
- **Authentication**: Bearer token required
- **Batch Size**: Default 1000 records per batch
- **Auto Cleanup**: Automatically removes existing data before new ETL runs
- **Export API**: Paginated data export with `limit`, `offset`, and `has_next` parameters

---

## 🧹 Data Cleanup Features

### Automatic Cleanup
When starting a new ETL task, the system automatically:
1. **Removes existing data** for the specified extraction date from all ETL tables
2. **Resets previous pipeline statuses** to 0 (not started)
3. **Ensures clean slate** for new ETL processing

### Manual Cleanup
Use the cleanup endpoint to manually remove data:
```bash
# Clean data for specific date
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-01"}' \
  http://localhost:8081/api/user_activity_etl/clean_data
```

**Response:**
```json
{
  "status": true,
  "message": "Data cleanup completed successfully",
  "date": "2025-01-01",
  "timestamp": "2025-01-01 10:00:00"
}
```

### What Gets Cleaned
- `activity_counts_etl` table data for the date
- `user_counts_etl` table data for the date  
- `user_activity_etl` table data for the date
- All `log_scheduler` statuses reset to 0

---

## 🔄 Common Workflows

### Daily ETL Run
```bash
# Check status first
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/status

# Run pipeline for yesterday
curl -X POST \
  -H "Authorization: Bearer default-webhook-token-change-this" \
  -H "Content-Type: application/json" \
  -d '{"date":"2025-01-12"}' \
  http://localhost:8081/api/user_activity_etl/run_pipeline

# Check results
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/results
```

### New Environment Setup
```bash
# 1. Start services
docker-compose up -d

# 2. Setup database
cd celoeapi-dev/
chmod +x create_database.sh
chmod +x migrate.sh
./create_database.sh
./migrate.sh run

# 3. Verify setup
./migrate.sh status
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "SHOW TABLES;"

# 4. Test API
curl -H "Authorization: Bearer default-webhook-token-change-this" \
     http://localhost:8081/api/user_activity_etl/status
```

### Adding New Migration
```bash
cd celoeapi-dev/

# Create new migration
./migrate.sh create add_new_table

# Edit the generated file
# vim application/database/migrations/013_add_new_table.php

# Update version and run
./migrate.sh config version 13
./migrate.sh run
```

---

*Last Updated: January 2025*
*Version: 3.0 - CLI Migration System*