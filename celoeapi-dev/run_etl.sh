#!/bin/bash

# ETL Background Runner Script
# This script runs the ETL process in background

# Get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Path to PHP (adjust if needed)
PHP_PATH="php"

# Path to the application index.php
INDEX_PATH="$DIR/index.php"

# Log file path
LOG_FILE="$DIR/application/logs/etl_background.log"

# Function to run ETL
run_etl() {
    echo "Starting ETL process at $(date)" >> "$LOG_FILE"
    
    # Run the ETL process and log output
    $PHP_PATH "$INDEX_PATH" cli run_etl >> "$LOG_FILE" 2>&1
    
    echo "ETL process completed at $(date)" >> "$LOG_FILE"
    echo "----------------------------------------" >> "$LOG_FILE"
}

# Run ETL in background
run_etl &

# Get the process ID
PID=$!

echo "ETL process started with PID: $PID"
echo "Check progress in: $LOG_FILE"

# Save PID for potential future use
echo $PID > "$DIR/application/logs/etl_process.pid"

exit 0 