#!/bin/bash

# ETL Background Runner Script for Student Activity Summary with Date Range
# This script runs the Student Activity Summary ETL process in background for a specific date range

# Get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Path to PHP (adjust if needed)
PHP_PATH="php"

# Path to the application index.php
INDEX_PATH="$DIR/index.php"

# Log file path
LOG_FILE="$DIR/application/logs/etl_background_range.log"

# Get date range parameters
START_DATE=${1:-$(date -d "7 days ago" +%Y-%m-%d)}
END_DATE=${2:-$(date -d "yesterday" +%Y-%m-%d)}

# Function to run ETL
run_etl() {
    echo "Starting Student Activity Summary ETL process for date range: $START_DATE to $END_DATE at $(date)" >> "$LOG_FILE"
    
    # Run the Student Activity Summary ETL process with date range parameters and log output
    $PHP_PATH "$INDEX_PATH" cli run_student_activity_etl_range "$START_DATE" "$END_DATE" >> "$LOG_FILE" 2>&1
    
    echo "Student Activity Summary ETL process completed for date range: $START_DATE to $END_DATE at $(date)" >> "$LOG_FILE"
    echo "----------------------------------------" >> "$LOG_FILE"
}

# Run ETL in background
run_etl &

# Get the process ID
PID=$!

echo "Student Activity Summary ETL process started with PID: $PID for date range: $START_DATE to $END_DATE"
echo "Check progress in: $LOG_FILE"

# Save PID for potential future use
echo $PID > "$DIR/application/logs/etl_range_process.pid"

exit 0
