# Response Format Fix for export_incremental Method

## Problem Description
The `export_incremental` method in `Sp_etl` controller was returning a response with duplicate structure. The response was being wrapped twice:

1. First, the method created a proper response structure with `meta`, `export_info`, and `data`
2. Then, the `_send_response` method wrapped it again with additional structure

This resulted in the client receiving nested data structures instead of the expected flat structure.

## Solution Implemented

### 1. Modified `_send_response` Method
- Added a new parameter `$wrap_response` (default: true) to control response wrapping
- Added logic to detect if data already has the proper structure (meta, export_info, data)
- If data already has proper structure, merge it directly instead of wrapping again

### 2. Updated `export_incremental` Method
- Changed the call to `_send_response(200, 'OK', $response, false)` to prevent double wrapping
- This ensures the response structure is preserved as intended

## Response Structure

The `export_incremental` method now returns a clean response structure:

```json
{
    "meta": {
        "success": true,
        "message": "Data exported successfully",
        "table_name": "sp_etl_summary",
        "batch_size": 100,
        "current_offset": 0,
        "next_offset": 100
    },
    "export_info": {
        "records_exported": 100,
        "total_available": 500,
        "has_more_data": true,
        "export_completed": false,
        "progress_percentage": 20.0
    },
    "data": [
        // Actual data records from database
    ]
}
```

## Key Benefits

1. **Clean Response Structure**: No more nested data structures
2. **Consistent Format**: Response follows the intended design
3. **Backward Compatibility**: Other methods using `_send_response` remain unaffected
4. **Flexible Wrapping**: The `_send_response` method can now handle both wrapped and unwrapped responses

## Usage

- **For structured responses** (like `export_incremental`): Use `_send_response($code, $text, $data, false)`
- **For simple responses**: Use `_send_response($code, $text, $data)` (default behavior)

## Validation

The response structure includes all necessary fields for pagination:
- `total_available`: Total number of records available
- `has_more_data`: Boolean indicating if more data is available
- `current_offset` and `next_offset`: For pagination logic
- `progress_percentage`: Progress indicator for long-running exports
