#!/bin/bash

# ETL Status Monitor Script
# This script monitors ETL status in real-time

# Configuration
API_URL="http://localhost:8081/index.php/api/ETL/status"
AUTH_TOKEN="default-webhook-token-change-this"
INTERVAL=5  # Check every 5 seconds

echo "🔍 ETL Status Monitor Started"
echo "Monitoring: $API_URL"
echo "Check interval: ${INTERVAL}s"
echo "=================================="

while true; do
    # Get current timestamp
    TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
    
    # Make API call and extract status
    RESPONSE=$(curl -s -X GET "$API_URL" -H "Authorization: Bearer $AUTH_TOKEN")
    
    if [ $? -eq 0 ]; then
        # Parse response using jq if available
        if command -v jq &> /dev/null; then
            IS_RUNNING=$(echo "$RESPONSE" | jq -r '.data.isRunning')
            LAST_RUN_ID=$(echo "$RESPONSE" | jq -r '.data.lastRun.id')
            LAST_RUN_STATUS=$(echo "$RESPONSE" | jq -r '.data.lastRun.status')
            LAST_RUN_RECORDS=$(echo "$RESPONSE" | jq -r '.data.lastRun.total_records')
            LAST_RUN_START=$(echo "$RESPONSE" | jq -r '.data.lastRun.start_date')
            LAST_RUN_END=$(echo "$RESPONSE" | jq -r '.data.lastRun.end_date')
            
            # Status display
            if [ "$IS_RUNNING" = "true" ]; then
                echo "[$TIMESTAMP] 🟡 ETL RUNNING - ID: $LAST_RUN_ID"
            else
                if [ "$LAST_RUN_STATUS" = "finished" ]; then
                    echo "[$TIMESTAMP] ✅ ETL FINISHED - ID: $LAST_RUN_ID, Records: $LAST_RUN_RECORDS"
                elif [ "$LAST_RUN_STATUS" = "failed" ]; then
                    echo "[$TIMESTAMP] ❌ ETL FAILED - ID: $LAST_RUN_ID"
                else
                    echo "[$TIMESTAMP] ⚪ ETL IDLE - Last: $LAST_RUN_STATUS"
                fi
            fi
        else
            # Fallback without jq
            echo "[$TIMESTAMP] Status: $RESPONSE"
        fi
    else
        echo "[$TIMESTAMP] ❌ API Error - Could not fetch status"
    fi
    
    # Wait before next check
    sleep $INTERVAL
done 