<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| ETL Configuration
|--------------------------------------------------------------------------
|
| Configuration settings for ETL (Extract, Transform, Load) operations
|
*/

// Webhook authentication tokens
$config['etl_webhook_tokens'] = [
    'default-webhook-token-change-this',
    // Add more webhook tokens as needed
    // You can set these from environment variables in production
];

// ETL processing settings
$config['etl_batch_size'] = 1000;              // Records to process in batches
$config['etl_timeout'] = 300;                  // ETL operation timeout in seconds
$config['etl_log_level'] = 'info';             // Log level for ETL operations

// Database settings specific to ETL
$config['etl_source_database'] = 'celoeapi';  // Source database name
$config['etl_target_tables'] = [
    'raw_log',
    'course_activity_summary', 
    'student_profile',
    'student_quiz_detail',
    'student_assignment_detail',
    'student_resource_access',
    'course_summary'
];

// ETL schedule settings (for cron jobs)
$config['etl_schedule'] = [
    'enabled' => true,
    'frequency' => 'hourly',                    // hourly, daily, weekly
    'time' => '00',                            // minute for hourly, hour for daily
];

// ETL status tracking
$config['etl_status_table'] = 'etl_status';   // Table to track ETL runs
$config['etl_max_runtime'] = 1800;            // Maximum runtime in seconds (30 minutes)