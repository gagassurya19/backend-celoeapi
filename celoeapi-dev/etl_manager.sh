#!/bin/bash

# ETL Manager - Unified ETL Management Script
# This script handles all ETL operations: run, monitor, and setup

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
info() { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PHP_PATH="php"
INDEX_PATH="$DIR/index.php"

# Default values (macOS compatible)
REQUEST_DATE=${1:-$(date -v-1d +%Y-%m-%d 2>/dev/null || date -d "yesterday" +%Y-%m-%d 2>/dev/null || date +%Y-%m-%d)}
START_DATE=${2:-$(date -v-7d +%Y-%m-%d 2>/dev/null || date -d "7 days ago" +%Y-%m-%d 2>/dev/null || date +%Y-%m-%d)}
END_DATE=${3:-$(date -v-1d +%Y-%m-%d 2>/dev/null || date -d "yesterday" +%Y-%m-%d 2>/dev/null || date +%Y-%m-%d)}
MONITOR_INTERVAL=5

# Show usage
show_usage() {
    echo "ETL Manager - Unified ETL Management Script"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  run [DATE]           Run ETL for specific date (default: yesterday)"
    echo "  range [START] [END]  Run ETL for date range (default: 7 days ago to yesterday)"
    echo "  monitor [INTERVAL]   Monitor ETL status (default: 5 seconds)"
    echo "  setup-cron           Setup automatic ETL cron jobs"
    echo "  status               Show current ETL status"
    echo "  help                 Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 run                    # Run ETL for yesterday"
    echo "  $0 run 2024-01-15        # Run ETL for specific date"
    echo "  $0 range 2024-01-01 2024-01-31  # Run ETL for date range"
    echo "  $0 monitor 10            # Monitor every 10 seconds"
    echo "  $0 setup-cron            # Setup automatic cron jobs"
    echo ""
}

# Run ETL for specific date
run_etl() {
    local date=$1
    local log_file="$DIR/application/logs/etl_$date.log"
    
    info "Starting ETL process for date: $date"
    echo "Starting ETL process for date: $date at $(date)" > "$log_file"
    
    # Run ETL process
    $PHP_PATH "$INDEX_PATH" cli run_student_activity_etl "$date" >> "$log_file" 2>&1
    
    echo "ETL process completed for date: $date at $(date)" >> "$log_file"
    success "ETL completed for date: $date"
    info "Check logs: $log_file"
}

# Run ETL for date range
run_etl_range() {
    local start_date=$1
    local end_date=$2
    local log_file="$DIR/application/logs/etl_range_${start_date}_${end_date}.log"
    
    info "Starting ETL process for date range: $start_date to $end_date"
    echo "Starting ETL process for date range: $start_date to $end_date at $(date)" > "$log_file"
    
    # Run ETL process with range
    $PHP_PATH "$INDEX_PATH" cli run_student_activity_etl_range "$start_date" "$end_date" >> "$log_file" 2>&1
    
    echo "ETL process completed for date range: $start_date to $end_date at $(date)" >> "$log_file"
    success "ETL completed for date range: $start_date to $end_date"
    info "Check logs: $log_file"
}

# Monitor ETL status
monitor_etl() {
    local interval=${1:-5}
    local api_url="http://localhost:8081/index.php/api/ETL/status"
    local auth_token="default-webhook-token-change-this"
    
    info "Starting ETL monitoring (interval: ${interval}s)"
    info "Monitoring: $api_url"
    echo "=================================="
    
    while true; do
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        local response=$(curl -s -X GET "$api_url" -H "Authorization: Bearer $auth_token" 2>/dev/null || echo "API_ERROR")
        
        if [ "$response" != "API_ERROR" ]; then
            if command -v jq &> /dev/null; then
                local is_running=$(echo "$response" | jq -r '.data.isRunning // "unknown"')
                local last_run_id=$(echo "$response" | jq -r '.data.lastRun.id // "unknown"')
                local last_run_status=$(echo "$response" | jq -r '.data.lastRun.status // "unknown"')
                local last_run_records=$(echo "$response" | jq -r '.data.lastRun.total_records // "unknown"')
                
                if [ "$is_running" = "true" ]; then
                    echo "[$timestamp] 🟡 ETL RUNNING - ID: $last_run_id"
                elif [ "$last_run_status" = "finished" ]; then
                    echo "[$timestamp] ✅ ETL FINISHED - ID: $last_run_id, Records: $last_run_records"
                elif [ "$last_run_status" = "failed" ]; then
                    echo "[$timestamp] ❌ ETL FAILED - ID: $last_run_id"
                else
                    echo "[$timestamp] ⚪ ETL IDLE - Last: $last_run_status"
                fi
            else
                echo "[$timestamp] Status: $response"
            fi
        else
            echo "[$timestamp] ❌ API Error - Could not fetch status"
        fi
        
        sleep $interval
    done
}

# Setup cron jobs
setup_cron() {
    info "Setting up automatic ETL cron jobs..."
    
    local cron_hourly="0 * * * * cd $DIR && php index.php cli run_student_activity_etl_auto >> $DIR/application/logs/cron_etl_auto.log 2>&1"
    local cron_daily="0 2 * * * cd $DIR && php index.php cli run_student_activity_etl_auto >> $DIR/application/logs/cron_etl_auto.log 2>&1"
    local cron_weekly="0 3 * * 0 cd $DIR && php index.php cli run_student_activity_etl_range >> $DIR/application/logs/cron_range.log 2>&1"
    local cron_monthly="0 4 1 * * cd $DIR && php index.php cli run_student_activity_etl_range >> $DIR/application/logs/cron_range.log 2>&1"
    
    # Add cron jobs
    (crontab -l 2>/dev/null; echo "$cron_hourly") | crontab -
    (crontab -l 2>/dev/null; echo "$cron_daily") | crontab -
    (crontab -l 2>/dev/null; echo "$cron_weekly") | crontab -
    (crontab -l 2>/dev/null; echo "$cron_monthly") | crontab -
    
    success "Cron jobs have been set up successfully!"
    info "View cron jobs: crontab -l"
    info "Remove cron jobs: crontab -r"
}

# Show ETL status
show_status() {
    local api_url="http://localhost:8081/index.php/api/ETL/status"
    local auth_token="default-webhook-token-change-this"
    
    info "Fetching current ETL status..."
    
    local response=$(curl -s -X GET "$api_url" -H "Authorization: Bearer $auth_token" 2>/dev/null || echo "API_ERROR")
    
    if [ "$response" != "API_ERROR" ]; then
        if command -v jq &> /dev/null; then
            echo "Current ETL Status:"
            echo "$response" | jq '.'
        else
            echo "Current ETL Status:"
            echo "$response"
        fi
    else
        error "Could not fetch ETL status from API"
        exit 1
    fi
}

# Main function
main() {
    local command=${1:-help}
    
    case $command in
        "run")
            run_etl "$REQUEST_DATE"
            ;;
        "range")
            run_etl_range "$START_DATE" "$END_DATE"
            ;;
        "monitor")
            monitor_etl "$MONITOR_INTERVAL"
            ;;
        "setup-cron")
            setup_cron
            ;;
        "status")
            show_status
            ;;
        "help"|*)
            show_usage
            ;;
    esac
}

# Run main function
main "$@"
