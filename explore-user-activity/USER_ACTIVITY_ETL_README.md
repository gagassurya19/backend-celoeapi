# User Activity ETL System

This document describes the **User Activity ETL (Extract, Transform, Load)** system based on the `finals_query_mysql57.sql` requirements and flowchart design.

## 🏗️ **System Architecture**

The ETL system is divided into **3 separate processes** that work together:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ ActivityCounts  │    │   UserCounts    │    │  Main ETL       │
│ ETL             │    │   ETL           │    │  (Merge)        │
│                 │    │                 │    │                 │
│ • File Views    │    │ • Num Students  │    │ • Final Report  │
│ • Video Views   │    │ • Num Teachers  │    │ • Calculations  │
│ • Forum Views   │    │                 │    │ • Aggregations  │
│ • Quiz Views    │    │                 │    │                 │
│ • Assignment    │    │                 │    │                 │
│ • URL Views     │    │                 │    │                 │
│ • Active Days   │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                                 ▼
                    ┌─────────────────────────┐
                    │   user_activity_etl     │
                    │   (Final Results)       │
                    └─────────────────────────┘
```

## 📋 **ETL Process Flow**

Each ETL follows the **flowchart authentication and scheduler logic**:

### **1. Authentication Flow**
```
Start → Decode Token → Username Found? → Set Privilege → Has Admin Role? → Set User Session
  │                          │                              │
  NO                        NO                             NO
  │                          │                              │
  ▼                          ▼                              ▼
Error                   Set Filter                  Set Filter
                    Directorate & Unit          Directorate & Unit
```

### **2. Scheduler Flow** 
```
Get Data from Scheduler → Not Empty Data? → Status Not Running? → End Date Valid? → Run Extraction
      │                        │                    │                    │
     NO                       NO                   NO                   NO
      │                        │                    │                    │
      ▼                        ▼                    ▼                    ▼
     End                      End                  End                  End
```

### **3. Data Processing Flow**
```
Extract Data → Transform Data → Load Data → Update Scheduler → Delete Log Data → End
     │              │              │              │              │
     └──────────────┴──────────────┴──────────────┴──────────────┘
                          (Pagination Loop)
```

## 🗂️ **File Structure**

```
celoeapi-dev/
├── application/
│   ├── models/
│   │   ├── Activity_Counts_ETL_Model.php    # ActivityCounts ETL
│   │   ├── User_Counts_ETL_Model.php        # UserCounts ETL  
│   │   └── User_Activity_ETL_Model.php      # Main ETL (merge)
│   ├── controllers/
│   │   ├── api/
│   │   │   └── User_Activity_ETL.php        # REST API endpoints
│   │   └── Cli.php                          # CLI commands (updated)
└── USER_ACTIVITY_ETL_README.md             # This documentation
```

## 🗄️ **Database Tables**

### **ETL Tables**
1. **`activity_counts_etl`** - Stores ActivityCounts data
2. **`user_counts_etl`** - Stores UserCounts data  
3. **`user_activity_etl`** - Final merged results
4. **`log_scheduler`** - ETL process tracking

### **ETL Table Schemas**

#### ActivityCounts ETL Table
```sql
CREATE TABLE activity_counts_etl (
  id int(11) AUTO_INCREMENT PRIMARY KEY,
  courseid bigint(20) NOT NULL,
  file_views int(11) DEFAULT 0,
  video_views int(11) DEFAULT 0,
  forum_views int(11) DEFAULT 0,
  quiz_views int(11) DEFAULT 0,
  assignment_views int(11) DEFAULT 0,
  url_views int(11) DEFAULT 0,
  active_days int(11) DEFAULT 0,
  extraction_date date NOT NULL,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_course_date (courseid, extraction_date)
);
```

#### UserCounts ETL Table
```sql
CREATE TABLE user_counts_etl (
  id int(11) AUTO_INCREMENT PRIMARY KEY,
  courseid bigint(20) NOT NULL,
  num_students int(11) DEFAULT 0,
  num_teachers int(11) DEFAULT 0,
  extraction_date date NOT NULL,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_course_date_users (courseid, extraction_date)
);
```

#### Main ETL Table (Final Results)
```sql
CREATE TABLE user_activity_etl (
  id int(11) AUTO_INCREMENT PRIMARY KEY,
  course_id varchar(100),
  id_number varchar(100), 
  course_name varchar(255),
  course_shortname varchar(100),
  num_teachers int(11) DEFAULT 0,
  num_students int(11) DEFAULT 0,
  file_views int(11) DEFAULT 0,
  video_views int(11) DEFAULT 0,
  forum_views int(11) DEFAULT 0,
  quiz_views int(11) DEFAULT 0,
  assignment_views int(11) DEFAULT 0,
  url_views int(11) DEFAULT 0,
  total_views int(11) DEFAULT 0,
  avg_activity_per_student_per_day decimal(10,2) DEFAULT 0.00,
  active_days int(11) DEFAULT 0,
  extraction_date date NOT NULL,
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_course_id_date (course_id, extraction_date)
);
```

## 🚀 **API Endpoints**

### **Authentication Required**
All endpoints require `Authorization` header with valid webhook token.

### **Available Endpoints**

#### **1. Run Full ETL Pipeline**
```bash
POST /api/user_activity_etl/run_pipeline
Content-Type: application/json
Authorization: Bearer your-webhook-token

{
  "date": "2024-01-15"  // Optional, defaults to today
}
```

#### **2. Run Individual ETL Processes**
```bash
# ActivityCounts ETL only
POST /api/user_activity_etl/run_activity_counts
{
  "date": "2024-01-15"
}

# UserCounts ETL only  
POST /api/user_activity_etl/run_user_counts
{
  "date": "2024-01-15"
}

# Main ETL (merge) only
POST /api/user_activity_etl/run_main_etl
{
  "date": "2024-01-15"
}
```

#### **3. Check ETL Status**
```bash
GET /api/user_activity_etl/status
```

#### **4. Get Scheduler Data**
```bash
GET /api/user_activity_etl/scheduler_data
```

#### **5. Get ETL Results**
```bash
GET /api/user_activity_etl/results/2024-01-15
```

#### **6. Export User Activity Data**
```bash
GET /api/user_activity_etl/export?limit=1000&offset=0&date=2024-01-15
```

**Parameters:**
- `limit` (optional): Number of records to return (default: 1000, max: 10000)
- `offset` (optional): Number of records to skip (default: 0)
- `date` (optional): Extraction date in Y-m-d format (default: today)

**Response:**
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
      "extraction_date": "2024-01-15",
      "created_at": "2024-01-15 10:30:00",
      "updated_at": "2024-01-15 10:30:00"
    }
  ],
  "limit": 1000,
  "offset": 0,
  "has_next": true,
  "extraction_date": "2024-01-15",
  "timestamp": "2024-01-15 10:30:00"
}
```

**Usage Examples:**
```bash
# Get first 1000 records for today
GET /api/user_activity_etl/export

# Get 500 records starting from offset 1000 for specific date
GET /api/user_activity_etl/export?limit=500&offset=1000&date=2024-01-15

# Get all records for a specific date (paginated)
GET /api/user_activity_etl/export?limit=1000&offset=0&date=2024-01-15
```

## 🖥️ **CLI Commands**

### **Basic Usage**
```bash
php index.php cli user_activity_etl [action] [date]
```

### **Available Actions**

#### **Run Full Pipeline**
```bash
php index.php cli user_activity_etl pipeline
php index.php cli user_activity_etl pipeline 2024-01-15
```

#### **Run Individual ETL Processes**
```bash
# ActivityCounts ETL
php index.php cli user_activity_etl activity_counts 2024-01-15

# UserCounts ETL
php index.php cli user_activity_etl user_counts 2024-01-15

# Main ETL (merge)
php index.php cli user_activity_etl main_etl 2024-01-15
```

#### **Check Status**
```bash
php index.php cli user_activity_etl status
```

#### **View Scheduler Data**
```bash
php index.php cli user_activity_etl scheduler_data
```

#### **Show Usage Help**
```bash
php index.php cli user_activity_etl
```

## ⚙️ **Configuration**

### **ETL Configuration (application/config/etl.php)**
```php
// ETL processing settings
$config['etl_batch_size'] = 1000;              // Records per batch
$config['etl_timeout'] = 300;                  // Timeout in seconds
$config['etl_log_level'] = 'info';             // Log level

// Webhook authentication tokens
$config['etl_webhook_tokens'] = [
    'your-webhook-token-here',
    // Add more tokens as needed
];
```

### **Database Connections**
- **Primary**: `celoeapi` (ETL results)
- **Secondary**: `moodle` (source data)

## 📊 **Data Flow Details**

### **1. ActivityCounts ETL**
**Source**: `mdl_logstore_standard_log`
**Logic**: 
```sql
SELECT
    courseid,
    COUNT(CASE WHEN component = 'mod_resource' THEN 1 END) AS File_Views,
    COUNT(CASE WHEN component = 'mod_page' THEN 1 END) AS Video_Views,
    COUNT(CASE WHEN component = 'mod_forum' THEN 1 END) AS Forum_Views,
    COUNT(CASE WHEN component = 'mod_quiz' THEN 1 END) AS Quiz_Views,
    COUNT(CASE WHEN component = 'mod_assign' THEN 1 END) AS Assignment_Views,
    COUNT(CASE WHEN component = 'mod_url' THEN 1 END) AS URL_Views,
    DATEDIFF(FROM_UNIXTIME(MAX(timecreated)), FROM_UNIXTIME(MIN(timecreated))) + 1 AS ActiveDays
FROM mdl_logstore_standard_log
WHERE contextlevel = 70 AND action = 'viewed'
AND timecreated BETWEEN [start_date] AND [end_date]
GROUP BY courseid
```

### **2. UserCounts ETL**
**Source**: `mdl_role_assignments` + `mdl_context`
**Logic**:
```sql
SELECT
    ctx.instanceid AS courseid,
    COUNT(DISTINCT CASE WHEN ra.roleid = 5 THEN ra.userid END) AS Num_Students,
    COUNT(DISTINCT CASE WHEN ra.roleid IN (3, 4) THEN ra.userid END) AS Num_Teachers
FROM mdl_role_assignments ra
JOIN mdl_context ctx ON ra.contextid = ctx.id
WHERE ctx.contextlevel = 50
GROUP BY ctx.instanceid
```

### **3. Main ETL (Merge)**
**Source**: `activity_counts_etl` + `user_counts_etl` + `mdl_course` + `mdl_course_categories`
**Logic**: Implements the original `finals_query_mysql57.sql` query
```sql
SELECT 
    categories.idnumber AS course_id,
    subjects.idnumber AS id_number,
    subjects.fullname AS course_name,
    subjects.shortname AS course_shortname,
    COALESCE(uc.num_teachers, 0) AS num_teachers,
    COALESCE(uc.num_students, 0) AS num_students,
    COALESCE(ac.file_views, 0) AS file_views,
    -- ... other fields
    ROUND(
        (total_views) / NULLIF(uc.num_students, 0) / NULLIF(ac.active_days, 0), 2
    ) AS avg_activity_per_student_per_day
FROM mdl_course subjects
LEFT JOIN mdl_course_categories categories ON subjects.category = categories.id
LEFT JOIN activity_counts_etl ac ON subjects.id = ac.courseid AND ac.extraction_date = ?
LEFT JOIN user_counts_etl uc ON subjects.id = uc.courseid AND uc.extraction_date = ?
WHERE subjects.visible = 1 AND categories.idnumber IS NOT NULL
```

## 🔄 **Pagination Support**

All ETL processes use **pagination** to handle large datasets:

- **ActivityCounts**: Processes courses in batches of 1000
- **UserCounts**: Processes courses in batches of 1000  
- **Main ETL**: Processes merged data in batches of 500

### **Pagination Logic**
```php
$offset = 0;
$limit = $this->batch_size;

while ($offset < $total_count) {
    // Update scheduler progress
    $this->update_scheduler_progress($scheduler_id, $offset, $limit);
    
    // Process batch
    $batch_data = $this->extract_batch($offset, $limit);
    $this->transform_and_load($batch_data);
    
    $offset += $limit;
}
```

## 📈 **Monitoring & Status**

### **ETL Status Values**
- `not_started` - ETL has not been run
- `queued` - ETL is waiting to start
- `running` - ETL is currently processing
- `completed` - ETL finished successfully
- `failed` - ETL encountered an error

### **Scheduler Status Values**
- `1` - Finished
- `2` - In progress  
- `3` - Failed
- `4` - Queued

### **Status Monitoring**
```bash
# CLI status check
php index.php cli user_activity_etl status

# API status check
curl -H "Authorization: Bearer token" http://localhost:8081/api/user_activity_etl/status
```

## 🛠️ **Troubleshooting**

### **Common Issues**

1. **ETL Already Running**
   ```
   Error: ETL process is already running
   ```
   **Solution**: Wait for current process to finish or check `log_scheduler` table

2. **Time Window Expired**
   ```
   Error: ETL time window expired
   ```
   **Solution**: ETL must run within H+1 23:59 time window

3. **Missing Prerequisites**
   ```
   Error: Waiting for ActivityCounts and UserCounts ETL to complete
   ```
   **Solution**: Run individual ETL processes first, then main ETL

4. **Database Connection Error**
   ```
   Error: Failed to connect to database
   ```
   **Solution**: Check `database.php` configuration

### **Debug Commands**
```bash
# Check database tables
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "SHOW TABLES LIKE '%etl%';"

# Check scheduler status
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "SELECT * FROM log_scheduler ORDER BY id DESC LIMIT 5;"

# Check ETL results
docker exec moodle-docker-db-1 mysql -u moodleuser -pmoodlepass celoeapi -e "SELECT COUNT(*) as total FROM user_activity_etl;"
```

## 🚀 **Production Deployment**

### **Cron Job Setup**
```bash
# Run ETL pipeline daily at 2 AM
0 2 * * * cd /path/to/celoeapi-dev && php index.php cli user_activity_etl pipeline

# Check status every hour
0 * * * * cd /path/to/celoeapi-dev && php index.php cli user_activity_etl status
```

### **Performance Optimization**
- **Batch Size**: Adjust based on server memory (500-2000)
- **Indexes**: Ensure proper database indexes exist
- **Memory Limit**: Set appropriate PHP memory limit
- **Timeout**: Configure based on data volume

### **Security**
- Change default webhook tokens
- Use environment variables for sensitive config
- Implement rate limiting
- Monitor API access logs

## 📝 **Example Usage**

### **Complete ETL Workflow**
```bash
# 1. Check current status
php index.php cli user_activity_etl status

# 2. Run full pipeline for specific date  
php index.php cli user_activity_etl pipeline 2024-01-15

# 3. Check results
curl -H "Authorization: Bearer token" \
  http://localhost:8081/api/user_activity_etl/results/2024-01-15
```

### **API Integration Example**
```php
// Trigger ETL via API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8081/api/user_activity_etl/run_pipeline');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['date' => '2024-01-15']));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer your-webhook-token',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
echo "ETL Status: " . $result['status'];
```

---

This ETL system provides a robust, scalable solution for processing Moodle analytics data following the original SQL requirements while implementing proper authentication, pagination, and monitoring capabilities. 