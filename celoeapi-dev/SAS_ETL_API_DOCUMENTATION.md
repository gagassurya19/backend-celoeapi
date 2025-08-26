# SAS Users Login Hourly ETL API Documentation

## 🎯 **Overview**

API Controller untuk mengelola SAS Users Login Hourly ETL (Student Activity Summary ETL) process, monitoring logs, dan mendapatkan data untuk dashboard.

## 🚀 **Base URL**

```
http://your-domain.com/api/sas_users_login_hourly_etl/
```

## 📋 **Available Endpoints**

### **1. Run SAS ETL Process**

**POST** `/api/sas_users_login_hourly_etl/run`

Menjalankan proses SAS ETL untuk sync users, roles, enrolments, dan detect login hourly activity.

#### **Request Body:**

```json
{
  "extraction_date": "2025-08-26" // Optional, default: today
}
```

#### **Response Success (200):**

```json
{
    "success": true,
    "message": "SAS ETL process completed successfully",
    "data": {
        "extraction_date": "2025-08-26",
        "log_id": 18,
        "users_etl": {
            "success": true,
            "results": {
                "users": {"extracted_count": 4, "inserted_count": 4},
                "roles": {"extracted_count": 5, "inserted_count": 5},
                "enrolments": {"extracted_count": 4, "inserted_count": 4}
            }
        },
        "hourly_etl": {
            "success": true,
            "extracted": 6,
            "inserted": 0,
            "updated": 6,
            "realtime_processed": 0
        },
        "busiest_hours": {
            "summary": {
                "total_teachers": 0,
                "total_students": 6,
                "total_activities": 12
            },
            "overall_hours": [
                {"hour": 5, "unique_users": 3, "total_activities": 7}
            ]
        },
        "hourly_chart_data": [...],
        "final_summary": {...},
        "timestamp": "2025-08-26 11:30:00"
    }
}
```

#### **Response Error (500):**

```json
{
    "success": false,
    "error": "SAS ETL process failed",
    "details": {...}
}
```

---

### **2. Get ETL Logs**

**GET** `/api/sas_users_login_hourly_etl/logs`

Mengambil log ETL dari tabel `sas_users_login_etl_logs` dengan filtering dan pagination.

#### **Query Parameters:**

- `process_name` (optional): Filter by process name (e.g., "sas_etl_complete")
- `status` (optional): Filter by status ("running", "completed", "failed")
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)
- `limit` (optional): Number of records (default: 50, max: 100)
- `offset` (optional): Pagination offset (default: 0)

#### **Example Request:**

```
GET /api/sas_users_login_hourly_etl/logs?status=completed&limit=10&date_from=2025-08-26
```

#### **Response (200):**

```json
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": 18,
        "process_name": "sas_etl_complete",
        "status": "completed",
        "message": "SAS ETL process completed successfully",
        "extraction_date": "2025-08-26",
        "start_time": "2025-08-26 11:30:00",
        "end_time": "2025-08-26 11:30:05",
        "duration_seconds": 5,
        "extracted_count": 10,
        "inserted_count": 10,
        "created_at": "2025-08-26 11:30:00",
        "updated_at": "2025-08-26 11:30:05",
        "created_at_formatted": "2025-08-26 11:30:00",
        "updated_at_formatted": "2025-08-26 11:30:05",
        "start_time_formatted": "2025-08-26 11:30:00",
        "end_time_formatted": "2025-08-26 11:30:05",
        "calculated_duration": 5
      }
    ],
    "pagination": {
      "total": 25,
      "limit": 10,
      "offset": 0,
      "has_more": true
    },
    "filters": {
      "process_name": null,
      "status": "completed",
      "date_from": "2025-08-26",
      "date_to": null
    }
  }
}
```

---

### **3. Get Specific Log by ID**

**GET** `/api/sas_users_login_hourly_etl/logs/{id}`

Mengambil log ETL spesifik berdasarkan ID.

#### **Example Request:**

```
GET /api/sas_users_login_hourly_etl/logs/18
```

#### **Response (200):**

```json
{
  "success": true,
  "data": {
    "id": 18,
    "process_name": "sas_etl_complete",
    "status": "completed",
    "message": "SAS ETL process completed successfully",
    "extraction_date": "2025-08-26",
    "start_time": "2025-08-26 11:30:00",
    "end_time": "2025-08-26 11:30:05",
    "duration_seconds": 5,
    "extracted_count": 10,
    "inserted_count": 10,
    "created_at": "2025-08-26 11:30:00",
    "updated_at": "2025-08-26 11:30:05",
    "created_at_formatted": "2025-08-26 11:30:00",
    "updated_at_formatted": "2025-08-26 11:30:05",
    "start_time_formatted": "2025-08-26 11:30:00",
    "end_time_formatted": "2025-08-26 11:30:05"
  }
}
```

#### **Response Error (404):**

```json
{
  "success": false,
  "error": "Log not found",
  "id": "999"
}
```

---

### **4. Get ETL Status Summary**

**GET** `/api/sas_users_login_hourly_etl/status`

Mendapatkan summary status ETL untuk monitoring dashboard.

#### **Response (200):**

```json
{
  "success": true,
  "data": {
    "latest_logs": [
      {
        "process_name": "sas_etl_complete",
        "status": "completed",
        "message": "SAS ETL process completed successfully",
        "extraction_date": "2025-08-26",
        "start_time": "2025-08-26 11:30:00",
        "end_time": "2025-08-26 11:30:05",
        "duration_seconds": 5,
        "extracted_count": 10,
        "inserted_count": 10,
        "created_at": "2025-08-26 11:30:00"
      }
    ],
    "status_counts": [
      { "status": "completed", "count": 20 },
      { "status": "failed", "count": 2 },
      { "status": "running", "count": 0 }
    ],
    "today_summary": {
      "total_runs": 5,
      "completed": 4,
      "failed": 1,
      "running": 0
    },
    "current_time": "2025-08-26 11:35:00"
  }
}
```

---

### **5. Get Chart Data**

**GET** `/api/sas_users_login_hourly_etl/chart_data`

Mendapatkan data untuk chart dashboard (hourly activity, busiest hours, real-time summary).

#### **Query Parameters:**

- `date` (optional): Date for chart data (YYYY-MM-DD, default: today)

#### **Example Request:**

```
GET /api/sas_users_login_hourly_etl/chart_data?date=2025-08-26
```

#### **Response (200):**

```json
{
  "success": true,
  "data": {
    "date": "2025-08-26",
    "hourly_chart_data": {
      "5": {
        "hour": 5,
        "formatted_hour": "05:00",
        "unique_users": 3,
        "total_activities": 7,
        "teacher_count": 0,
        "student_count": 3,
        "avg_activities_per_user": 2.33,
        "is_peak_hour": true
      }
    },
    "busiest_hours": {
      "summary": {
        "total_teachers": 0,
        "total_students": 6,
        "total_activities": 12
      },
      "overall_hours": [
        { "hour": 5, "unique_users": 3, "total_activities": 7 },
        { "hour": 4, "unique_users": 1, "total_activities": 2 }
      ]
    },
    "current_hour_summary": {
      "date": "2025-08-26",
      "hour": 11,
      "total_unique_users": 0,
      "total_activities": 0,
      "role_breakdown": {},
      "formatted_hour": "11:00"
    },
    "timestamp": "2025-08-26 11:35:00"
  }
}
```

---

## 🔧 **Error Handling**

### **HTTP Status Codes:**

- **200**: Success
- **400**: Bad Request (invalid parameters)
- **404**: Not Found
- **405**: Method Not Allowed
- **500**: Internal Server Error

### **Error Response Format:**

```json
{
  "success": false,
  "error": "Error message description",
  "details": {} // Additional error details if available
}
```

---

## 📱 **Usage Examples**

### **1. Run ETL Process via cURL:**

```bash
curl -X POST http://your-domain.com/api/sas_users_login_hourly_etl/run \
  -H "Content-Type: application/json" \
  -d '{"extraction_date": "2025-08-26"}'
```

### **2. Get Logs via cURL:**

```bash
# Get all logs
curl http://your-domain.com/api/sas_users_login_hourly_etl/logs

# Get completed logs for today
curl "http://your-domain.com/api/sas_users_login_hourly_etl/logs?status=completed&date_from=2025-08-26"

# Get logs with pagination
curl "http://your-domain.com/api/sas_users_login_hourly_etl/logs?limit=10&offset=0"
```

### **3. Get Chart Data via cURL:**

```bash
# Get chart data for today
curl http://your-domain.com/api/sas_users_login_hourly_etl/chart_data

# Get chart data for specific date
curl "http://your-domain.com/api/sas_users_login_hourly_etl/chart_data?date=2025-08-26"
```

---

## 🎯 **Use Cases**

### **1. Dashboard Monitoring:**

- Real-time ETL status
- Process completion rates
- Error tracking
- Performance metrics

### **2. ETL Process Control:**

- Manual ETL execution
- Scheduled ETL monitoring
- Process validation

### **3. Data Analysis:**

- User activity patterns
- Peak usage hours
- Role-based statistics
- Historical trends

### **4. Integration:**

- Frontend applications
- Monitoring tools
- Alert systems
- Reporting dashboards

---

## ⚠️ **Important Notes**

1. **Authentication**: Currently no authentication required (add as needed)
2. **Rate Limiting**: Consider implementing rate limiting for production
3. **Logging**: All API calls are logged in CodeIgniter logs
4. **Data Validation**: Input validation implemented for all endpoints
5. **Error Handling**: Comprehensive error handling with detailed messages

---

## 🚀 **Next Steps**

1. **Add Authentication**: Implement JWT or API key authentication
2. **Rate Limiting**: Add request rate limiting
3. **Caching**: Implement Redis caching for frequently accessed data
4. **Monitoring**: Add API usage analytics and monitoring
5. **Documentation**: Generate OpenAPI/Swagger documentation
