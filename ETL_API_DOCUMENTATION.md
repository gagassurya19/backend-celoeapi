# ETL API Documentation

## Overview

This documentation covers the ETL (Extract, Transform, Load) API endpoints for two main systems:
- **SAS (Student Activity Summary)**: User activity analytics and course engagement data
- **CP (Course Performance)**: Course performance metrics and student assessment data

## Base URL
```
http://localhost:8081
```

## Authentication
Currently, no authentication is required for these endpoints.

---

## SAS (Student Activity Summary) ETL Endpoints

### 1. Run SAS ETL Pipeline

**Endpoint:** `POST /api/etl_sas/run`

**Description:** Starts the SAS ETL pipeline in the background to process user activity data from a specified date range.

#### Request Body
```json
{
  "start_date": "2024-01-01",
  "end_date": "2024-01-31",
  "concurrency": 1
}
```

#### Parameters
- `start_date` (string, optional): Start date in YYYY-MM-DD format. Defaults to 7 days ago
- `end_date` (string, optional): End date in YYYY-MM-DD format. Defaults to yesterday
- `concurrency` (integer, optional): Number of concurrent processes. Defaults to 1

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "message": "SAS ETL started in background",
  "date_range": {
    "start_date": "2024-01-01",
    "end_date": "2024-01-31"
  },
  "concurrency": 1,
  "note": "Check sas_etl_logs for progress",
  "log_id": 123
}
```

#### Negative Case Response (400 Bad Request)
```json
{
  "status": false,
  "message": "SAS ETL failed to start",
  "error": "Invalid date format. Use YYYY-MM-DD format."
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "message": "SAS ETL failed to start",
  "error": "Database connection failed"
}
```

#### cURL Examples

**Basic Request:**
```bash
curl -X POST http://localhost:8081/api/etl_sas/run \
  -H "Content-Type: application/json" \
  -d '{
    "start_date": "2024-01-01",
    "end_date": "2024-01-31",
    "concurrency": 1
  }'
```

**Minimal Request (uses defaults):**
```bash
curl -X POST http://localhost:8081/api/etl_sas/run \
  -H "Content-Type: application/json" \
  -d '{}'
```

**Form Data Alternative:**
```bash
curl -X POST http://localhost:8081/api/etl_sas/run \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "start_date=2024-01-01&end_date=2024-01-31&concurrency=2"
```

---

### 2. Clean SAS ETL Data

**Endpoint:** `POST /api/etl_sas/clean`

**Description:** Cleans all SAS ETL data from the database.

#### Request Body
```json
{}
```

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "message": "All SAS ETL tables cleaned successfully",
  "summary": {
    "sas_user_activity_etl": 1785,
    "sas_activity_counts_etl": 5,
    "sas_user_counts_etl": 7,
    "sas_courses": 3,
    "sas_etl_watermarks": 1,
    "total_affected": 1801
  },
  "timestamp": "2025-08-18 17:02:40"
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "message": "Failed to clean all ETL data",
  "error": "Database transaction failed"
}
```

#### cURL Example
```bash
curl -X POST http://localhost:8081/api/etl_sas/clean \
  -H "Content-Type: application/json" \
  -d '{}'
```

---

### 3. Get SAS ETL Logs

**Endpoint:** `GET /api/etl_sas/logs`

**Description:** Retrieves SAS ETL execution logs with pagination.

#### Query Parameters
- `limit` (integer, optional): Number of records to return. Defaults to 50
- `offset` (integer, optional): Number of records to skip. Defaults to 0
- `status` (string, optional): Filter by status. Values: "running", "completed", "failed"

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "data": [
    {
      "id": "7789",
      "process_name": "user_activity_etl",
      "status": "completed",
      "message": "clean_all completed",
      "start_time": "2025-08-18 17:02:40",
      "end_time": "2025-08-18 17:02:40",
      "duration_seconds": "0",
      "extraction_date": "2025-08-17",
      "parameters": "{\"trigger\":\"api_clean_all\",\"message\":\"clean_all completed\",\"summary\":{\"tables\":{\"sas_user_activity_etl\":1785,\"sas_activity_counts_etl\":5,\"sas_user_counts_etl\":7,\"sas_courses\":3,\"sas_etl_watermarks\":1},\"total_affected\":1801}}",
      "created_at": "2025-08-18 17:02:40",
      "updated_at": "2025-08-18 17:02:40"
    }
  ],
  "pagination": {
    "limit": 3,
    "offset": 0
  }
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "error": "Database connection failed"
}
```

#### cURL Examples

**Basic Request:**
```bash
curl -X GET "http://localhost:8081/api/etl_sas/logs"
```

**With Pagination:**
```bash
curl -X GET "http://localhost:8081/api/etl_sas/logs?limit=10&offset=20"
```

**With Status Filter:**
```bash
curl -X GET "http://localhost:8081/api/etl_sas/logs?status=completed&limit=5"
```

---

### 4. Get SAS ETL Status

**Endpoint:** `GET /api/etl_sas/status`

**Description:** Retrieves the current status of SAS ETL processes.

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "data": {
    "last_run": {
      "id": 7785,
      "start_time": "2025-08-18 16:45:39",
      "end_time": "2025-08-18 16:45:39",
      "status": "completed",
      "message": "clean_all completed",
      "parameters": {
        "trigger": "api_clean_all",
        "message": "clean_all completed",
        "summary": {
          "tables": {
            "sas_user_activity_etl": 1785,
            "sas_activity_counts_etl": 5,
            "sas_user_counts_etl": 7,
            "sas_courses": 3,
            "sas_etl_watermarks": 1
          },
          "total_affected": 1801
        }
      },
      "duration_seconds": "0"
    },
    "currently_running": 8,
    "recent_activity": 15,
    "watermark": null,
    "service": "SAS"
  }
}
```

#### Response When No Data (200 OK)
```json
{
  "status": true,
  "data": {
    "last_run": null,
    "status": "no_data",
    "message": "No ETL runs found"
  }
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "error": "Database connection failed"
}
```

#### cURL Example
```bash
curl -X GET http://localhost:8081/api/etl_sas/status
```

---

### 5. Export SAS ETL Data

**Endpoint:** `GET /api/etl_sas/export`

**Description:** Exports SAS ETL data with pagination and optional filtering.

#### Query Parameters
- `limit` (integer, optional): Number of records to return. Defaults to 100
- `offset` (integer, optional): Number of records to skip. Defaults to 0
- `date` (string, optional): Filter by specific date (YYYY-MM-DD)
- `course_id` (string, optional): Filter by course ID

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "data": [],
  "has_next": false,
  "filters": {
    "date": null,
    "course_id": null
  },
  "pagination": {
    "limit": 2,
    "offset": 0,
    "count": 0,
    "total_count": 0,
    "has_more": false
  }
}
```

#### Negative Case Response (400 Bad Request)
```json
{
  "status": false,
  "message": "Offset must be 0 or greater"
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "message": "Failed to export SAS data",
  "error": "Database query failed"
}
```

#### cURL Examples

**Basic Export:**
```bash
curl -X GET "http://localhost:8081/api/etl_sas/export"
```

**With Pagination:**
```bash
curl -X GET "http://localhost:8081/api/etl_sas/export?limit=50&offset=100"
```

**With Date Filter:**
```bash
curl -X GET "http://localhost:8081/api/etl_sas/export?date=2024-01-15"
```

**With Course Filter:**
```bash
curl -X GET "http://localhost:8081/api/etl_sas/export?course_id=COURSE001&limit=10"
```

---

## CP (Course Performance) ETL Endpoints

### 1. Run CP ETL Pipeline

**Endpoint:** `POST /api/etl_cp/run`

**Description:** Starts the CP ETL pipeline in the background to process course performance data.

#### Request Body
```json
{
  "start_date": "2024-01-01",
  "end_date": "2024-01-31",
  "concurrency": 1
}
```

#### Parameters
- `start_date` (string, optional): Start date in YYYY-MM-DD format. Defaults to 7 days ago
- `end_date` (string, optional): End date in YYYY-MM-DD format. Defaults to yesterday
- `concurrency` (integer, optional): Number of concurrent processes. Defaults to 1

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "message": "CP ETL started in background",
  "date_range": {
    "start_date": "2024-01-01",
    "end_date": "2024-01-31"
  },
  "concurrency": 1,
  "log_id": 456
}
```

#### Negative Case Response (400 Bad Request)
```json
{
  "status": false,
  "message": "CP ETL failed to start",
  "error": "Invalid date format. Use YYYY-MM-DD"
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "message": "CP ETL failed to start",
  "error": "Database connection failed"
}
```

#### cURL Examples

**Basic Request:**
```bash
curl -X POST http://localhost:8081/api/etl_cp/run \
  -H "Content-Type: application/json" \
  -d '{
    "start_date": "2024-01-01",
    "end_date": "2024-01-31",
    "concurrency": 1
  }'
```

**Minimal Request:**
```bash
curl -X POST http://localhost:8081/api/etl_cp/run \
  -H "Content-Type: application/json" \
  -d '{}'
```

---

### 2. Clean CP ETL Data

**Endpoint:** `POST /api/etl_cp/clean`

**Description:** Cleans all CP ETL data from the database.

#### Request Body
```json
{}
```

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "message": "CP data cleaned successfully",
  "log_id": 18,
  "summary": {
    "tables": {
      "cp_activity_summary": 11,
      "cp_course_summary": 4,
      "cp_student_assignment_detail": 6,
      "cp_student_profile": 7,
      "cp_student_quiz_detail": 5,
      "cp_student_resource_access": 8,
      "cp_etl_watermarks": 1
    },
    "total_affected": 42
  }
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "message": "Failed to clean CP data",
  "error": "Database transaction failed",
  "log_id": 456
}
```

#### cURL Example
```bash
curl -X POST http://localhost:8081/api/etl_cp/clean \
  -H "Content-Type: application/json" \
  -d '{}'
```

---

### 3. Get CP ETL Logs

**Endpoint:** `GET /api/etl_cp/logs`

**Description:** Retrieves CP ETL execution logs with pagination.

#### Query Parameters
- `limit` (integer, optional): Number of records to return. Defaults to 50
- `offset` (integer, optional): Number of records to skip. Defaults to 0
- `status` (integer, optional): Filter by status. Values: 1 (finished), 2 (inprogress), 3 (failed)

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "data": [
    {
      "id": "18",
      "offset": "0",
      "numrow": "42",
      "type": "clear",
      "message": "CP clean completed",
      "requested_start_date": null,
      "extracted_start_date": null,
      "extracted_end_date": null,
      "status": "1",
      "start_date": "2025-08-18 17:02:44",
      "end_date": "2025-08-18 17:02:44",
      "duration_seconds": null,
      "created_at": "2025-08-18 17:02:44"
    }
  ],
  "pagination": {
    "limit": 3,
    "offset": 0
  }
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "error": "Database connection failed"
}
```

#### cURL Examples

**Basic Request:**
```bash
curl -X GET "http://localhost:8081/api/etl_cp/logs"
```

**With Pagination:**
```bash
curl -X GET "http://localhost:8081/api/etl_cp/logs?limit=10&offset=20"
```

**With Status Filter:**
```bash
curl -X GET "http://localhost:8081/api/etl_cp/logs?status=1&limit=5"
```

---

### 4. Get CP ETL Status

**Endpoint:** `GET /api/etl_cp/status`

**Description:** Retrieves the current status of CP ETL processes.

#### Positive Case Response (200 OK)
```json
{
  "status": true,
  "data": {
    "last_run": {
      "id": 16,
      "start_date": "2025-08-18 16:45:02",
      "end_date": null,
      "status": "inprogress",
      "status_code": 2,
      "message": "{\"concurrency\":4}",
      "type": "run_cp_backfill",
      "numrow": 0,
      "duration_seconds": null
    },
    "currently_running": 3,
    "recent_activity": 7,
    "watermark": {
      "last_extracted_date": "2025-08-18",
      "last_extracted_timecreated": "1755561599",
      "next_extract_date": "2025-08-19",
      "updated_at": "2025-08-18 14:39:16"
    },
    "service": "CP"
  }
}
```

#### Response When No Data (200 OK)
```json
{
  "status": true,
  "data": {
    "last_run": null,
    "status": "no_data",
    "message": "No ETL runs found"
  }
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "status": false,
  "error": "Database connection failed"
}
```

#### cURL Example
```bash
curl -X GET http://localhost:8081/api/etl_cp/status
```

---

### 5. Export CP ETL Data

**Endpoint:** `GET /api/etl_cp/export`

**Description:** Exports CP ETL data with pagination and optional table filtering.

#### Query Parameters
- `limit` (integer, optional): Number of records to return. Defaults to 100
- `offset` (integer, optional): Number of records to skip. Defaults to 0
- `table` (string, optional): Export specific table only
- `tables` (string, optional): Comma-separated list of tables to export
- `debug` (boolean, optional): Include debug information

#### Available Tables
- `cp_student_profile`
- `cp_course_summary`
- `cp_activity_summary`
- `cp_student_quiz_detail`
- `cp_student_assignment_detail`
- `cp_student_resource_access`

#### Positive Case Response (200 OK)
```json
{
  "success": true,
  "limit": 2,
  "offset": 0,
  "hasNext": true,
  "tables": {
    "cp_student_profile": {
      "count": 2,
      "hasNext": true,
      "nextOffset": 2,
      "rows": [
        {
          "id": "1",
          "user_id": "1",
          "idnumber": null,
          "full_name": "Guest user",
          "email": "root@localhost",
          "program_studi": null,
          "created_at": "2025-08-18 14:39:15",
          "updated_at": "2025-08-18 14:39:15"
        },
        {
          "id": "2",
          "user_id": "2",
          "idnumber": null,
          "full_name": "Admin User",
          "email": "admin@admin.com",
          "program_studi": null,
          "created_at": "2025-08-18 14:39:15",
          "updated_at": "2025-08-18 14:39:15"
        }
      ]
    },
    "cp_course_summary": {
      "count": 2,
      "hasNext": true,
      "nextOffset": 2,
      "rows": [
        {
          "id": "1",
          "course_id": "2",
          "course_name": "PEMROGRAMAN UNTUK PERANGKAT BERGERAK 2",
          "kelas": "GBK3BAB4",
          "jumlah_aktivitas": "16",
          "jumlah_mahasiswa": "4",
          "dosen_pengampu": "Admin User, budadosen budadosen",
          "created_at": "2025-08-18 14:39:16",
          "updated_at": "2025-08-18 14:39:16"
        }
      ]
    }
  }
}
```

#### Response with Debug Information (200 OK)
```json
{
  "success": true,
  "limit": 1,
  "offset": 0,
  "hasNext": false,
  "tables": {
    "cp_student_profile": {
      "count": 0,
      "hasNext": false,
      "nextOffset": null,
      "rows": [],
      "debug": {
        "totalCount": 0,
        "filteredCount": null
      }
    }
  },
  "debug": {
    "database": "celoeapi"
  }
}
```

#### Negative Case Response (500 Internal Server Error)
```json
{
  "success": false,
  "error": "Database connection failed"
}
```

#### cURL Examples

**Export All Tables:**
```bash
curl -X GET "http://localhost:8081/api/etl_cp/export"
```

**Export Specific Table:**
```bash
curl -X GET "http://localhost:8081/api/etl_cp/export?table=cp_student_profile"
```

**Export Multiple Tables:**
```bash
curl -X GET "http://localhost:8081/api/etl_cp/export?tables=cp_student_profile,cp_course_summary"
```

**With Pagination:**
```bash
curl -X GET "http://localhost:8081/api/etl_cp/export?limit=50&offset=100"
```

**With Debug Information:**
```bash
curl -X GET "http://localhost:8081/api/etl_cp/export?debug=true"
```

---

## Error Handling

### Common HTTP Status Codes

- **200 OK**: Request successful
- **400 Bad Request**: Invalid request parameters
- **500 Internal Server Error**: Server error or database connection issue

### Error Response Format
```json
{
  "status": false,
  "message": "Human-readable error message",
  "error": "Technical error details"
}
```

### Validation Errors

**Date Format Error:**
```json
{
  "status": false,
  "message": "Invalid date format. Use YYYY-MM-DD format.",
  "error": "Date validation failed"
}
```

**Date Range Error:**
```json
{
  "status": false,
  "message": "Start date cannot be after end date.",
  "error": "Date range validation failed"
}
```

**Parameter Error:**
```json
{
  "status": false,
  "message": "Offset must be 0 or greater",
  "error": "Parameter validation failed"
}
```

---

## Frontend Integration Examples

### React/Next.js Hook Example

```javascript
// hooks/useETL.js
import { useState, useEffect } from 'react';

export const useETL = (endpoint, options = {}) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const execute = async (params = {}) => {
    setLoading(true);
    setError(null);
    
    try {
      const response = await fetch(`/api/${endpoint}`, {
        method: options.method || 'GET',
        headers: {
          'Content-Type': 'application/json',
          ...options.headers,
        },
        body: options.method === 'POST' ? JSON.stringify(params) : undefined,
      });
      
      const result = await response.json();
      
      if (!response.ok) {
        throw new Error(result.error || result.message || 'Request failed');
      }
      
      setData(result);
      return result;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  return { data, loading, error, execute };
};
```

### Usage Examples

```javascript
// Run SAS ETL
const { execute: runSAS, loading: sasLoading } = useETL('etl_sas/run', { method: 'POST' });

const handleRunSAS = async () => {
  try {
    await runSAS({
      start_date: '2024-01-01',
      end_date: '2024-01-31',
      concurrency: 2
    });
    console.log('SAS ETL started successfully');
  } catch (error) {
    console.error('Failed to start SAS ETL:', error);
  }
};

// Get ETL Status
const { execute: getStatus, data: statusData } = useETL('etl_sas/status');

const handleGetStatus = async () => {
  try {
    const status = await getStatus();
    console.log('Current status:', status);
  } catch (error) {
    console.error('Failed to get status:', error);
  }
};

// Export Data
const { execute: exportData, data: exportResult } = useETL('etl_cp/export');

const handleExport = async () => {
  try {
    const result = await exportData({
      limit: 50,
      offset: 0,
      table: 'cp_student_profile'
    });
    console.log('Export result:', result);
  } catch (error) {
    console.error('Failed to export data:', error);
  }
};
```

---

## Testing Results Summary

### ✅ **Verified Working Endpoints**

All endpoints have been tested and verified to work correctly:

1. **SAS ETL Endpoints**:
   - ✅ `POST /api/etl_sas/run` - Start ETL pipeline
   - ✅ `POST /api/etl_sas/clean` - Clean ETL data
   - ✅ `GET /api/etl_sas/logs` - Get execution logs
   - ✅ `GET /api/etl_sas/status` - Get current status
   - ✅ `GET /api/etl_sas/export` - Export data

2. **CP ETL Endpoints**:
   - ✅ `POST /api/etl_cp/run` - Start ETL pipeline
   - ✅ `POST /api/etl_cp/clean` - Clean ETL data
   - ✅ `GET /api/etl_cp/logs` - Get execution logs
   - ✅ `GET /api/etl_cp/status` - Get current status
   - ✅ `GET /api/etl_cp/export` - Export data

### ✅ **Verified Features**

- ✅ **Pagination** - Limit and offset parameters work correctly
- ✅ **Filtering** - Status, date, course_id, and table filters work
- ✅ **Error Handling** - Invalid date formats return proper error messages
- ✅ **Content Types** - Both JSON and form-data are supported
- ✅ **Default Parameters** - Minimal requests work with defaults
- ✅ **Debug Mode** - Debug information is included when requested

### 📊 **Actual Response Examples**

The documentation now includes real response examples from the testing, ensuring accuracy for frontend development.

---

## Testing Checklist

### SAS ETL Testing
- [x] Run ETL pipeline with valid date range
- [x] Run ETL pipeline with default parameters
- [x] Run ETL pipeline with invalid date format
- [x] Run ETL pipeline with invalid date range
- [x] Clean ETL data
- [x] Get ETL logs with pagination
- [x] Get ETL logs with status filter
- [x] Get ETL status
- [x] Export data with various filters
- [x] Handle network errors gracefully

### CP ETL Testing
- [x] Run ETL pipeline with valid parameters
- [x] Run ETL pipeline with invalid parameters
- [x] Clean ETL data
- [x] Get ETL logs with different filters
- [x] Get ETL status
- [x] Export specific table
- [x] Export multiple tables
- [x] Export with debug information
- [x] Handle pagination correctly
- [x] Validate response formats

### General Testing
- [x] Test all endpoints with valid requests
- [x] Test all endpoints with invalid requests
- [x] Verify error handling and messages
- [x] Test pagination limits and offsets
- [x] Verify response structure consistency
- [x] Test concurrent requests
- [x] Validate date format handling
- [x] Test with different content types
