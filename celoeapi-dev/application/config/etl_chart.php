<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| ETL Chart Configuration
|--------------------------------------------------------------------------
|
| Configuration settings for ETL Chart operations
|
*/

// Webhook authentication tokens
$config['etl_chart_webhook_tokens'] = [
    'default-webhook-token-change-this',
    // Add more webhook tokens as needed
    // You can set these from environment variables in production
];

// External API configuration
$config['celoe_api_base_url'] = 'https://celoe.telkomuniversity.ac.id/api/v1';
$config['celoe_api_key'] = '4bc0d5f0-6482-11ea-9377-64c753dc5ace';

// API endpoints
$config['celoe_api_endpoints'] = [
    'categories' => '/course/category',
    'subjects' => '/course/subject'
];

// ETL Chart processing settings
$config['etl_chart_batch_size'] = 1000;              // Records to process in batches
$config['etl_chart_timeout'] = 600;                  // ETL operation timeout in seconds
$config['etl_chart_log_level'] = 'info';             // Log level for ETL operations

// Database settings specific to ETL Chart
$config['etl_chart_target_database'] = 'celoeapi';  // Target database name
$config['etl_chart_target_tables'] = [
    'etl_chart_categories',
    'etl_chart_subjects'
];

// ETL Chart schedule settings (for cron jobs)
$config['etl_chart_schedule'] = [
    'enabled' => true,
    'frequency' => 'daily',                    // hourly, daily, weekly
    'time' => '02:00',                        // time for daily run
];

// ETL Chart status tracking
$config['etl_chart_status_table'] = 'etl_chart_logs';   // Table to track ETL runs
$config['etl_chart_max_runtime'] = 1800;                // Maximum runtime in seconds (30 minutes) 