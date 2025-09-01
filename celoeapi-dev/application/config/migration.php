<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Database connection settings using environment variables
$config['migration_db_host'] = getenv('DB_HOST') ?: 'db';
$config['migration_db_username'] = getenv('DB_USERNAME') ?: 'moodleuser';
$config['migration_db_password'] = getenv('DB_PASSWORD') ?: 'moodlepass';
$config['migration_db_name'] = getenv('ETL_DATABASE') ?: 'celoeapi';

// Migration system settings
$config['migration_enabled'] = TRUE;
$config['migration_type'] = 'sequential';
$config['migration_table'] = 'migration_tracker';
$config['migration_auto_latest'] = FALSE;
$config['migration_version'] = 17;
$config['migration_path'] = APPPATH . 'database/migrations/';

// Environment-specific settings
$environment = getenv('CI_ENV') ?: ENVIRONMENT;

switch ($environment) {
	case 'development':
		$config['migration_debug'] = TRUE;
		$config['migration_verbose'] = TRUE;
		break;
	case 'production':
		$config['migration_debug'] = FALSE;
		$config['migration_verbose'] = FALSE;
		break;
	default:
		$config['migration_debug'] = FALSE;
		$config['migration_verbose'] = TRUE;
}

// Helper functions
if (!function_exists('get_etl_database_name')) {
	function get_etl_database_name() {
		return getenv('ETL_DATABASE') ?: 'celoeapi';
	}
}
