#!/bin/bash

# ETL Chart Runner Script
# This script runs the ETL Chart process for fetching categories and subjects

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/application/logs/etl_chart_$(date +%Y%m%d_%H%M%S).log"

# Change to the script directory
cd "$SCRIPT_DIR"

echo "[$(date)] Starting ETL Chart process..." >> "$LOG_FILE"

# Run the PHP CLI command
php index.php cli run_etl_chart >> "$LOG_FILE" 2>&1

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo "[$(date)] ETL Chart process completed successfully" >> "$LOG_FILE"
else
    echo "[$(date)] ETL Chart process failed with exit code: $EXIT_CODE" >> "$LOG_FILE"
fi

echo "[$(date)] ETL Chart process finished" >> "$LOG_FILE"

exit $EXIT_CODE 