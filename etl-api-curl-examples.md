# ETL Chart API - CURL Examples

Base URL: `http://localhost/celoeapi-dev/index.php/api/etl`

## 1. Get ETL Logs (Historical)

### Basic Request
```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/logs" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

### With Pagination
```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/logs?limit=10&offset=0" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

### With Filters
```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/logs?status=finished&limit=5" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

**Expected Response:**
```json
{
  "status": true,
  "data": {
    "logs": [
      {
        "id": 1,
        "start_date": "2024-01-15 10:30:00",
        "end_date": "2024-01-15 10:35:00",
        "duration": "00:05:00",
        "status": "finished",
        "total_records": 150,
        "offset": 0,
        "created_at": "2024-01-15 10:30:00"
      }
    ],
    "pagination": {
      "total": 25,
      "limit": 5,
      "offset": 0,
      "current_page": 1,
      "total_pages": 5
    }
  }
}
```

## 2. Check ETL Status

```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/status" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

**Expected Response:**
```json
{
  "status": true,
  "data": {
    "is_running": false,
    "current_log_id": null,
    "last_run": {
      "id": 25,
      "start_date": "2024-01-15 09:30:00",
      "end_date": "2024-01-15 09:35:00",
      "status": "finished",
      "total_records": 150
    }
  }
}
```

## 3. Trigger ETL Process

```bash
curl -X POST "http://localhost/celoeapi-dev/index.php/api/etl/trigger" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY" \
  -d '{
    "force": false
  }'
```

**Expected Response:**
```json
{
  "status": true,
  "message": "ETL process started successfully",
  "data": {
    "log_id": 26,
    "estimated_duration": "5-10 minutes",
    "started_at": "2024-01-15 11:30:00"
  }
}
```

## 4. Real-time Logs (Server-Sent Events)

```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/logs/realtime" \
  -H "Accept: text/event-stream" \
  -H "Cache-Control: no-cache" \
  -H "celoe-api-key: YOUR_API_KEY" \
  --no-buffer
```

**Expected Response (SSE Stream):**
```
data: {"event":"log_created","log_id":26,"status":"running","message":"ETL process started"}

data: {"event":"log_updated","log_id":26,"status":"running","progress":25,"message":"Fetching categories..."}

data: {"event":"log_updated","log_id":26,"status":"running","progress":50,"message":"Saving categories..."}

data: {"event":"log_updated","log_id":26,"status":"running","progress":75,"message":"Fetching subjects..."}

data: {"event":"log_completed","log_id":26,"status":"finished","total_records":150,"message":"ETL completed successfully"}
```

## 5. Get ETL Progress

```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/progress/26" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

**Expected Response:**
```json
{
  "status": true,
  "data": {
    "log_id": 26,
    "status": "running",
    "progress_percentage": 45,
    "current_step": "Saving categories",
    "elapsed_time": "00:02:30",
    "estimated_remaining": "00:03:00",
    "categories_processed": 75,
    "subjects_processed": 0,
    "total_records": 0
  }
}
```

## 6. Get ETL Statistics

```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/stats" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

**Expected Response:**
```json
{
  "status": true,
  "data": {
    "total_runs": 25,
    "successful_runs": 23,
    "failed_runs": 2,
    "success_rate": 92.0,
    "average_duration": "00:05:30",
    "average_records": 145,
    "last_24h_runs": 3,
    "performance_metrics": {
      "fastest_run": "00:03:45",
      "slowest_run": "00:08:20",
      "most_records": 200,
      "least_records": 95
    }
  }
}
```

## 7. Get Categories

```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/categories" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

**Expected Response:**
```json
{
  "status": true,
  "data": [
    {
      "category_id": 1,
      "category_name": "Science",
      "category_site": "main",
      "category_type": "course",
      "category_parent_id": 0
    }
  ]
}
```

## 8. Get Subjects

```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/subjects" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

**Expected Response:**
```json
{
  "status": true,
  "data": [
    {
      "subject_id": 1,
      "subject_code": "MATH101",
      "subject_name": "Basic Mathematics",
      "curriculum_year": "2024",
      "category_id": 1
    }
  ]
}
```

## 9. Update ETL Log

```bash
curl -X PUT "http://localhost/celoeapi-dev/index.php/api/etl/logs/26" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY" \
  -d '{
    "status": "failed",
    "total_records": 0,
    "error_message": "API connection timeout"
  }'
```

**Expected Response:**
```json
{
  "status": true,
  "message": "ETL log updated successfully",
  "data": {
    "log_id": 26,
    "status": "failed",
    "total_records": 0,
    "end_date": "2024-01-15 11:35:00",
    "duration": "00:05:00"
  }
}
```

## 10. Delete ETL Log

```bash
curl -X DELETE "http://localhost/celoeapi-dev/index.php/api/etl/logs/26" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

**Expected Response:**
```json
{
  "status": true,
  "message": "ETL log deleted successfully",
  "data": {
    "deleted_log_id": 26
  }
}
```

## Error Responses

### 401 Unauthorized
```bash
curl -X GET "http://localhost/celoeapi-dev/index.php/api/etl/logs" \
  -H "Content-Type: application/json"
  # Missing API key
```

**Response:**
```json
{
  "status": false,
  "error": "Unauthorized",
  "message": "API key is required"
}
```

### 409 Conflict (ETL Already Running)
```bash
curl -X POST "http://localhost/celoeapi-dev/index.php/api/etl/trigger" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: YOUR_API_KEY"
```

**Response:**
```json
{
  "status": false,
  "error": "Conflict",
  "message": "ETL process is already running",
  "data": {
    "current_log_id": 25,
    "started_at": "2024-01-15 11:25:00"
  }
}
```

### 500 Internal Server Error
```json
{
  "status": false,
  "error": "Internal Server Error",
  "message": "Database connection failed"
}
```

## Testing with Different Environments

### Development
```bash
export API_BASE="http://localhost/celoeapi-dev/index.php/api/etl"
export API_KEY="your-dev-api-key"
```

### Staging
```bash
export API_BASE="https://staging.celoe.com/api/etl"
export API_KEY="your-staging-api-key"
```

### Production
```bash
export API_BASE="https://api.celoe.com/etl"
export API_KEY="your-production-api-key"
```

### Using Environment Variables
```bash
curl -X GET "${API_BASE}/logs" \
  -H "Content-Type: application/json" \
  -H "celoe-api-key: ${API_KEY}"
```

## Batch Testing Script

```bash
#!/bin/bash

API_BASE="http://localhost/celoeapi-dev/index.php/api/etl"
API_KEY="your-api-key"

echo "Testing ETL API endpoints..."

# Test status
echo "1. Checking ETL status..."
curl -s -X GET "${API_BASE}/status" -H "celoe-api-key: ${API_KEY}" | jq .

# Test logs
echo "2. Getting ETL logs..."
curl -s -X GET "${API_BASE}/logs?limit=5" -H "celoe-api-key: ${API_KEY}" | jq .

# Test trigger (only if not running)
echo "3. Triggering ETL process..."
curl -s -X POST "${API_BASE}/trigger" -H "celoe-api-key: ${API_KEY}" | jq .

echo "All tests completed!"
``` 