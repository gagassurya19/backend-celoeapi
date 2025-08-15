# ETL Automation Guide - Student Activity Summary

## Overview
Sistem ETL Student Activity Summary sekarang mendukung **concurrent otomatis** yang dapat memproses data secara berkelanjutan tanpa intervensi manual, termasuk **date range processing** dan **automatic scheduling**.

## Fitur yang Tersedia

### 1. **Manual ETL** (Sudah Ada)
```bash
# Process specific date
php index.php cli run_student_activity_etl 2025-08-14

# Process yesterday (default)
php index.php cli run_student_activity_etl
```

### 2. **Date Range ETL** (Baru)
```bash
# Process specific date range
php index.php cli run_student_activity_etl_range 2025-08-10 2025-08-15

# Process last 7 days (default)
php index.php cli run_student_activity_etl_range
```

### 3. **Automatic ETL** (Sudah Ada)
```bash
# Process all new data automatically
php index.php cli run_student_activity_etl_auto
```

### 4. **API Endpoint dengan Date Range** (Baru)
```bash
POST /api/etl_student_activity_summary/run_pipeline
{
  "start_date": "2025-08-10",  # Optional, defaults to 7 days ago
  "end_date": "2025-08-15"     # Optional, defaults to yesterday
}

# Response:
{
  "status": true,
  "message": "ETL pipeline started in background with date range",
  "date_range": {
    "start_date": "2025-08-10",
    "end_date": "2025-08-15"
  },
  "note": "Check logs for ETL progress and completion status"
}
```

## Cara Kerja

### **Date Range ETL**
- **Input**: `start_date` dan `end_date` (YYYY-MM-DD format)
- **Validation**: Format tanggal dan range validation
- **Processing**: Memproses setiap tanggal dalam range secara sequential
- **Smart Detection**: Hanya memproses tanggal yang memiliki data

### **Automatic ETL**
- **Intelligent Date Detection**: Otomatis menemukan tanggal terakhir yang sudah diproses
- **Smart Data Processing**: Hanya memproses tanggal yang memiliki data activity log
- **Error Recovery**: Continue processing jika ada error pada satu tanggal

## Setup Automatic ETL

### **Step 1: Setup Cron Jobs**
```bash
# Make script executable
chmod +x setup_cron_etl.sh

# Run setup script
./setup_cron_etl.sh
```

### **Step 2: Verify Cron Jobs**
```bash
# View current cron jobs
crontab -l

# Expected output:
# 0 * * * * cd /path/to/celoeapi-dev && php index.php cli run_student_activity_etl_auto >> /path/to/celoeapi-dev/application/logs/cron_etl_auto.log 2>&1
# 0 2 * * * cd /path/to/celoeapi-dev && php index.php cli run_student_activity_etl_auto >> /path/to/celoeapi-dev/application/logs/cron_etl_auto.log 2>&1
# 0 3 * * 0 cd /path/to/celoeapi-dev && php index.php cli run_student_activity_etl_range >> /path/to/celoeapi-dev/application/logs/cron_etl_range.log 2>&1
# 0 4 1 * * cd /path/to/celoeapi-dev && php index.php cli run_student_activity_etl_range >> /path/to/celoeapi-dev/application/logs/cron_etl_range.log 2>&1
```

### **Step 3: Test Date Range ETL**
```bash
# Test manual first
php index.php cli run_student_activity_etl_range 2025-08-10 2025-08-15

# Expected output:
# Starting Student Activity Summary ETL process for date range: 2025-08-10 to 2025-08-15...
# Processing date range: 2025-08-10 to 2025-08-15
# Processing date: 2025-08-10
# ...
```

## Cron Job Schedule

### **Hourly ETL** (Setiap Jam)
- **Time**: `0 * * * *` (setiap jam tepat)
- **Method**: `run_student_activity_etl_auto`
- **Purpose**: Process real-time data updates
- **Log**: `application/logs/cron_etl_auto.log`

### **Daily ETL** (Setiap Hari)
- **Time**: `0 2 * * *` (setiap hari jam 2 pagi)
- **Method**: `run_student_activity_etl_auto`
- **Purpose**: Full daily processing
- **Log**: `application/logs/cron_etl_auto.log`

### **Weekly ETL** (Setiap Minggu)
- **Time**: `0 3 * * 0` (setiap Minggu jam 3 pagi)
- **Method**: `run_student_activity_etl_range`
- **Purpose**: Full week processing
- **Log**: `application/logs/cron_etl_range.log`

### **Monthly ETL** (Setiap Bulan)
- **Time**: `0 4 1 * *` (setiap tanggal 1 jam 4 pagi)
- **Method**: `run_student_activity_etl_range`
- **Purpose**: Full month processing
- **Log**: `application/logs/cron_etl_range.log`

## Monitoring dan Logs

### **Log Files**
- **ETL Background**: `application/logs/etl_background.log`
- **ETL Background Range**: `application/logs/etl_background_range.log`
- **Cron Auto ETL**: `application/logs/cron_etl_auto.log`
- **Cron Range ETL**: `application/logs/cron_etl_range.log`

### **Status Monitoring**
```sql
-- Check last processed date
SELECT MAX(extraction_date) as last_processed FROM user_activity_etl;

-- Check processing status by date range
SELECT extraction_date, COUNT(*) as records FROM user_activity_etl 
WHERE extraction_date BETWEEN '2025-08-10' AND '2025-08-15'
GROUP BY extraction_date ORDER BY extraction_date DESC;

-- Check scheduler status
SELECT * FROM scheduler_status ORDER BY date DESC LIMIT 10;
```

## Testing

### **Test Date Range API**
```bash
# Run test script
php test_etl_range_api.php

# Test manual
curl -X POST http://localhost:8081/api/etl_student_activity_summary/run_pipeline \
  -H "Content-Type: application/json" \
  -d '{"start_date": "2025-08-10", "end_date": "2025-08-15"}'
```

### **Test CLI Commands**
```bash
# Test date range ETL
php index.php cli run_student_activity_etl_range 2025-08-10 2025-08-15

# Test automatic ETL
php index.php cli run_student_activity_etl_auto
```

## Troubleshooting

### **Common Issues**

#### 1. **Date Range Validation Error**
```bash
# Check date format
echo "2025-08-15" | grep -E '^\d{4}-\d{2}-\d{2}$'

# Check date logic
php -r "echo strtotime('2025-08-15') . PHP_EOL;"
```

#### 2. **Cron Job Tidak Berjalan**
```bash
# Check cron service
sudo service cron status

# Check cron logs
sudo tail -f /var/log/cron

# Check file permissions
ls -la setup_cron_etl.sh
```

#### 3. **Background Process Error**
```bash
# Check script permissions
chmod +x run_etl_range.sh

# Test script manually
./run_etl_range.sh 2025-08-10 2025-08-15
```

## Performance Optimization

### **Date Range Processing**
- **Sequential Processing**: Tanggal diproses satu per satu untuk memory efficiency
- **Smart Detection**: Skip tanggal tanpa data
- **Progress Tracking**: Real-time progress monitoring

### **Batch Processing**
- Data diproses dalam batch 1000 records
- Pagination untuk menghindari memory overflow
- Progress tracking untuk setiap batch

## Production Deployment

### **Recommended Setup**
1. **Cron Jobs**: Hourly + Daily + Weekly + Monthly
2. **Log Rotation**: Daily log rotation untuk semua log files
3. **Monitoring**: Check logs setiap hari
4. **Backup**: Backup ETL data secara regular

### **Monitoring Commands**
```bash
# Check ETL status
tail -f application/logs/cron_etl_auto.log
tail -f application/logs/cron_etl_range.log

# Check database growth
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "SELECT COUNT(*) as total_records FROM user_activity_etl;"

# Check last processed date
docker exec -it moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "SELECT MAX(extraction_date) as last_processed FROM user_activity_etl;"
```

## Summary

✅ **Sistem ETL sekarang FULLY AUTOMATED dengan Date Range Support:**
- **Date Range Processing** - specific start/end dates
- **Concurrent processing** - multiple dates simultaneously
- **Automatic scheduling** - cron jobs (hourly, daily, weekly, monthly)
- **Intelligent detection** - auto-find new data
- **Error recovery** - continue on failures
- **Progress tracking** - detailed monitoring
- **Production ready** - robust and scalable

🚀 **ETL akan berjalan otomatis dengan berbagai schedule:**
- **Hourly/Daily**: Automatic detection dan processing
- **Weekly/Monthly**: Full range processing
- **Manual**: Date range API endpoint
- **Background**: Non-blocking execution

**Data akan selalu up-to-date secara otomatis!** 🎯
