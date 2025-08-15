#!/bin/bash

# Setup Cron Jobs for Automatic ETL Execution
# This script sets up cron jobs to run ETL automatically

echo "Setting up automatic ETL cron jobs..."

# Get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Create cron job entries
CRON_JOB_HOURLY="0 * * * * cd $DIR && php index.php cli run_student_activity_etl_auto >> $DIR/application/logs/cron_etl_auto.log 2>&1"
CRON_JOB_DAILY="0 2 * * * cd $DIR && php index.php cli run_student_activity_etl_auto >> $DIR/application/logs/cron_etl_auto.log 2>&1"
CRON_JOB_WEEKLY="0 3 * * 0 cd $DIR && php index.php cli run_student_activity_etl_range >> $DIR/application/logs/cron_etl_range.log 2>&1"
CRON_JOB_MONTHLY="0 4 1 * * cd $DIR && php index.php cli run_student_activity_etl_range >> $DIR/application/logs/cron_etl_range.log 2>&1"

echo "Adding hourly ETL cron job (automatic detection)..."
(crontab -l 2>/dev/null; echo "$CRON_JOB_HOURLY") | crontab -

echo "Adding daily ETL cron job (automatic detection)..."
(crontab -l 2>/dev/null; echo "$CRON_JOB_DAILY") | crontab -

echo "Adding weekly ETL cron job (full week processing)..."
(crontab -l 2>/dev/null; echo "$CRON_JOB_WEEKLY") | crontab -

echo "Adding monthly ETL cron job (full month processing)..."
(crontab -l 2>/dev/null; echo "$CRON_JOB_MONTHLY") | crontab -

echo "✅ Cron jobs have been set up successfully!"
echo ""
echo "📋 Cron jobs added:"
echo "   - Hourly (Auto): $CRON_JOB_HOURLY"
echo "   - Daily (Auto): $CRON_JOB_DAILY"
echo "   - Weekly (Range): $CRON_JOB_WEEKLY"
echo "   - Monthly (Range): $CRON_JOB_MONTHLY"
echo ""
echo "📁 Logs will be written to:"
echo "   - Automatic ETL: $DIR/application/logs/cron_etl_auto.log"
echo "   - Range ETL: $DIR/application/logs/cron_etl_range.log"
echo ""
echo "🔍 To view current cron jobs: crontab -l"
echo "🗑️  To remove cron jobs: crontab -r"
echo ""
echo "🚀 ETL will now run automatically:"
echo "   - Every hour: Automatic detection and processing"
echo "   - Daily at 2 AM: Automatic detection and processing"
echo "   - Weekly on Sunday at 3 AM: Full week processing"
echo "   - Monthly on 1st at 4 AM: Full month processing"
