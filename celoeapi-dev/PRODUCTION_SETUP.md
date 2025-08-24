# 🚀 CeloeAPI Production Setup

Setup CeloeAPI untuk production environment dimana Moodle sudah running di server yang sama.

## 📋 **Prerequisites**

- **Moodle** sudah running di server (port 8080)
- **Database Moodle** sudah ada dan accessible
- **Docker** dan **Docker Compose** terinstall
- **MySQL Client** terinstall untuk database operations

## 🎯 **Skenario Setup**

```
┌─────────────────────────────────────────────────────────────┐
│                    SERVER PRODUCTION                        │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    │
│  │   Moodle    │    │  CeloeAPI   │    │   MySQL     │    │
│  │  (Port 80)  │    │ (Port 8081) │    │ (Port 3306) │    │
│  │             │    │             │    │             │    │
│  │ Native App  │    │  Docker     │    │  Native     │    │
│  │ (Apache/    │    │ Container   │    │  MySQL      │    │
│  │  Nginx)     │    │             │    │  Service    │    │
│  └─────────────┘    └─────────────┘    └─────────────┘    │
│                                                           │
│  • Moodle: Native installation (bukan Docker)            │
│  • MySQL: Native service (bukan Docker)                  │
│  • CeloeAPI: Docker container                            │
│  • Semua berjalan di server yang sama                    │
└─────────────────────────────────────────────────────────────┘
```

## 🚀 **Quick Setup (One Command)**

### **1. Setup Otomatis**
```bash
# Dari folder celoeapi-dev
./quick-setup-production.sh
```

### **2. Setup dengan Custom Configuration**
```bash
# Override default values
MOODLE_DB_HOST=192.168.1.100 \
MOODLE_DB_USER=myuser \
MOODLE_DB_PASS=mypass \
MOODLE_DB_NAME=moodle_prod \
./quick-setup-production.sh
```

## ⚙️ **Manual Setup**

### **1. Check Dependencies**
```bash
# Check Docker
docker --version
docker-compose --version

# Check MySQL client
mysql --version

# Check Moodle database connection
mysql -h localhost -u moodleuser -p moodle -e "SELECT COUNT(*) FROM mdl_course;"
```

### **2. Create CeloeAPI Database**
```bash
# Connect ke MySQL
mysql -u root -p

# Create database
CREATE DATABASE celoeapi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Grant privileges
GRANT ALL PRIVILEGES ON celoeapi.* TO 'moodleuser'@'%';
FLUSH PRIVILEGES;
EXIT;
```

### **3. Start CeloeAPI Service**
```bash
# Load environment
source .env.production

# Start service
docker-compose -f docker-compose.celoeapi-only.yml up -d --build
```

### **4. Run Migrations**
```bash
# Run migrations
docker-compose -f docker-compose.celoeapi-only.yml exec celoeapi php run_migrations.php

# Add optimization indexes
docker-compose -f docker-compose.celoeapi-only.yml exec celoeapi php -r "
require_once 'index.php';
\$CI =& get_instance();
\$CI->load->database();
\$CI->load->library('migration');
\$CI->migration->version(7);
"
```

## 🔧 **Configuration Files**

### **1. Environment Variables**
```bash
# .env.production
CI_ENV=production
# Container akan connect ke host MySQL via host.docker.internal
CELOEAPI_DB_HOST=host.docker.internal
CELOEAPI_DB_PORT=3306
CELOEAPI_DB_USER=moodleuser
CELOEAPI_DB_PASS=moodlepass
CELOEAPI_DB_NAME=celoeapi
MOODLE_DB_HOST=host.docker.internal
MOODLE_DB_PORT=3306
MOODLE_DB_USER=moodleuser
MOODLE_DB_PASS=moodlepass
MOODLE_DB_NAME=moodle
```

### **2. Database Configuration**
- **Development**: `application/config/database.php`
- **Production**: `application/config/database_production.php`

### **3. Docker Compose**
- **Development**: `docker-compose.yml` (dengan Moodle)
- **Production**: `docker-compose.celoeapi-only.yml` (tanpa Moodle)

## 📊 **Database Architecture**

```
┌─────────────────┐    ┌─────────────────┐
│   MOODLE DB     │    │  CELOEAPI DB    │
│   (Source)      │    │   (Results)     │
├─────────────────┤    ├─────────────────┤
│ • mdl_course    │    │ • cp_* tables   │
│ • mdl_user      │    │ • sas_* tables  │
│ • mdl_logstore  │    │ • ETL logs      │
│ • mdl_*         │    │ • Watermarks    │
└─────────────────┘    └─────────────────┘
         │                       ▲
         │                       │
         └───────────────────────┘
              ETL Process
         (Read from Moodle, Write to CeloeAPI)
```

## 🧪 **Testing**

### **1. Health Check**
```bash
# Welcome page
curl http://localhost:8081/

# Swagger UI
curl http://localhost:8081/swagger

# API status
curl http://localhost:8081/api/etl_sas/status
```

### **2. Database Connection Test**
```bash
# Test Moodle connection
docker-compose -f docker-compose.celoeapi-only.yml exec celoeapi php -r "
require_once 'index.php';
\$CI =& get_instance();
\$CI->load->database('moodle');
echo 'Moodle DB: ' . (\$CI->db->simple_query('SELECT 1') ? 'OK' : 'FAILED') . PHP_EOL;
"

# Test CeloeAPI connection
docker-compose -f docker-compose.celoeapi-only.yml exec celoeapi php -r "
require_once 'index.php';
\$CI =& get_instance();
\$CI->load->database('default');
echo 'CeloeAPI DB: ' . (\$CI->db->simple_query('SELECT 1') ? 'OK' : 'FAILED') . PHP_EOL;
"
```

## 🚨 **Troubleshooting**

### **1. Database Connection Issues**
```bash
# Check if Moodle database accessible
mysql -h localhost -u moodleuser -p moodle -e "SELECT 1;"

# Check if CeloeAPI database exists
mysql -h localhost -u moodleuser -p -e "SHOW DATABASES LIKE 'celoeapi';"

# Check user privileges
mysql -u root -p -e "SHOW GRANTS FOR 'moodleuser'@'%';"
```

### **2. Service Issues**
```bash
# Check container status
docker-compose -f docker-compose.celoeapi-only.yml ps

# Check logs
docker-compose -f docker-compose.celoeapi-only.yml logs celoeapi

# Restart service
docker-compose -f docker-compose.celoeapi-only.yml restart celoeapi
```

### **3. Migration Issues**
```bash
# Check migration status
docker-compose -f docker-compose.celoeapi-only.yml exec celoeapi php -r "
require_once 'index.php';
\$CI =& get_instance();
\$CI->load->database();
\$CI->load->library('migration');
echo 'Current version: ' . \$CI->migration->current() . PHP_EOL;
"
```

## 📈 **Monitoring**

### **1. Service Status**
```bash
# Show all services
docker-compose -f docker-compose.celoeapi-only.yml ps

# Show logs
docker-compose -f docker-compose.celoeapi-only.yml logs -f celoeapi
```

### **2. Database Status**
```bash
# Check ETL tables
mysql -h localhost -u moodleuser -p celoeapi -e "
SELECT 'cp_etl_logs' as table_name, COUNT(*) as records FROM cp_etl_logs
UNION ALL
SELECT 'sas_etl_logs', COUNT(*) FROM sas_etl_logs;"
```

## 🔄 **Updates & Maintenance**

### **1. Update CeloeAPI**
```bash
# Pull latest code
git pull origin main

# Rebuild and restart
docker-compose -f docker-compose.celoeapi-only.yml up -d --build celoeapi

# Run migrations if needed
docker-compose -f docker-compose.celoeapi-only.yml exec celoeapi php run_migrations.php
```

### **2. Backup & Restore**
```bash
# Backup CeloeAPI database
mysqldump -h localhost -u moodleuser -p celoeapi > celoeapi_backup.sql

# Restore CeloeAPI database
mysql -h localhost -u moodleuser -p celoeapi < celoeapi_backup.sql
```

## 🎉 **Success Indicators**

✅ **CeloeAPI service running** di port 8081  
✅ **Database connection** ke Moodle berhasil  
✅ **Database celoeapi** terbuat dan accessible  
✅ **Migrations completed** dengan sukses  
✅ **Endpoints responding** (Welcome, Swagger, API)  
✅ **ETL processes** bisa dijalankan  

## 📞 **Support**

Jika mengalami masalah:
1. Check logs: `docker-compose -f docker-compose.celoeapi-only.yml logs celoeapi`
2. Verify database connections
3. Check environment variables
4. Ensure Moodle database accessible
5. Verify Docker service running
